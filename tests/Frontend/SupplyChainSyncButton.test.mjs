import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import { compile, baseParse } from '@vue/compiler-dom';
import { parse } from '@vue/compiler-sfc';
import ts from 'typescript';

const require = createRequire(import.meta.url);
const Vue = require('vue');
const { renderToString } = createRequire(require.resolve('vue'))('@vue/server-renderer');
const source = path => readFileSync(new URL(`../../resources/js/${path}`, import.meta.url), 'utf8');
const componentPath = 'shared/components/SupplyChainSyncButton.vue';

function setup({ endpoint = '/admin/partnership/members/7/supply-chain/sync', canSync = true, presentation } = {}) {
    const { descriptor } = parse(source(componentPath));
    const file = ts.createSourceFile('sync.ts', descriptor.scriptSetup.content, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    const statements = file.statements.filter(node => !ts.isImportDeclaration(node));
    const code = ts.transpileModule(statements.map(node => node.getText(file)).join('\n'), {
        compilerOptions: { target: ts.ScriptTarget.ESNext, module: ts.ModuleKind.CommonJS },
    }).outputText;
    const bindings = statements.flatMap(node => ts.isVariableStatement(node)
        ? node.declarationList.declarations.flatMap(d => ts.isIdentifier(d.name) ? [d.name.text] : [])
        : ts.isFunctionDeclaration(node) ? [node.name.text] : []);
    const props = Vue.reactive({ endpoint, canSync, presentation });
    const requests = [], messages = [], events = [];
    const dependencies = { ref: Vue.ref, computed: Vue.computed, defineProps: () => props,
        defineEmits: () => (...args) => events.push(args),
        useSnackbarStore: () => ({ showMessage: (...args) => messages.push(args) }),
        api: { post: url => new Promise((resolve, reject) => requests.push({ url, resolve, reject })) },
    };
    const context = new Function(...Object.keys(dependencies), `${code}\nreturn { ${bindings.join(',')} };`)(...Object.values(dependencies));
    const render = new Function('Vue', compile(descriptor.template.content, { mode: 'function', prefixIdentifiers: true }).code)(Vue);
    return { context: { ...props, ...context }, props, requests, messages, events, render };
}

async function renderButton(page) {
    const app = Vue.createSSRApp({ render: () => page.render(Vue.proxyRefs(page.context), []) });
    app.directive('tooltip', {});
    app.component('v-btn', { inheritAttrs: false, setup: (_, { attrs }) => () => Vue.h('button', attrs) });
    app.component('v-list-item', { inheritAttrs: false, setup: (_, { attrs, slots }) => () => Vue.h('button', attrs, [attrs.title, slots.append?.()]) });
    app.component('v-progress-circular', { setup: () => () => Vue.h('span', { 'data-progress': true }) });
    return renderToString(app);
}

test('only eligible unsynchronized rows render the synchronization action', async () => {
    assert.match(await renderButton(setup()), /aria-label="Sinkronisasi Internal"/);
    assert.doesNotMatch(await renderButton(setup({ canSync: false })), /<button/);
});

test('menu presentation preserves one POST, rejection message, retry and completion state', async () => {
    assert.doesNotMatch(await renderButton(setup({ presentation: 'menu', canSync: false })), /<button/);
    const page = setup({ presentation: 'menu' });
    assert.match(await renderButton(page), /role="menuitem"/);
    const pending = page.context.synchronize();
    await page.context.synchronize();
    assert.equal(page.requests.length, 1);
    const processing = await renderButton(page);
    assert.match(processing, /disabled/);
    assert.match(processing, /data-progress/);
    page.requests[0].resolve({ data: { success: false, message: 'Data ditolak internal.' } });
    await pending;
    assert.deepEqual(page.messages, [['Data ditolak internal.', 'error']]);
    assert.match(await renderButton(page), /role="menuitem"/);
    const retry = page.context.synchronize();
    assert.equal(page.requests[1].url, '/admin/partnership/members/7/supply-chain/sync');
    page.requests[1].resolve({ data: { success: true, message: 'Terintegrasi.' } });
    await retry;
    assert.doesNotMatch(await renderButton(page), /<button/);
    assert.deepEqual(page.events, [['processed'], ['processed']]);
});

test('a double click sends one request and displays the original success message before hiding', async () => {
    const page = setup();
    const pending = page.context.synchronize();
    await page.context.synchronize();
    assert.equal(page.requests.length, 1);
    assert.equal(page.requests[0].url, '/admin/partnership/members/7/supply-chain/sync');
    assert.equal(page.context.processing.value, true);
    page.requests[0].resolve({ data: { success: true, message: 'Customer sudah terintegrasi di internal.', data: { customer_number: '90000046' } } });
    await pending;
    assert.deepEqual(page.messages, [['Customer sudah terintegrasi di internal.', 'success']]);
    assert.equal(page.context.processing.value, false);
    assert.doesNotMatch(await renderButton(page), /<button/);
    assert.deepEqual(page.events, [['processed']]);
    await page.context.synchronize();
    assert.equal(page.requests.length, 1);
});

test('a failed internal response keeps the action available and permits another attempt', async () => {
    const page = setup();
    const pending = page.context.synchronize();
    page.requests[0].reject({ response: { data: { message: 'Nomor KTP sudah digunakan customer lain.' } } });
    await pending;
    assert.deepEqual(page.messages, [['Nomor KTP sudah digunakan customer lain.', 'error']]);
    assert.match(await renderButton(page), /<button/);
    assert.equal(page.context.processing.value, false);
    const retry = page.context.synchronize();
    assert.equal(page.requests.length, 2);
    page.requests[1].resolve({ data: { success: true, message: 'Sinkronisasi berhasil.' } });
    await retry;
    assert.doesNotMatch(await renderButton(page), /<button/);
});

test('a business rejection using HTTP 200 is not presented as synchronization success', async () => {
    const page = setup();
    const pending = page.context.synchronize();
    page.requests[0].resolve({ data: { success: false, message: 'Data ditolak internal.' } });
    await pending;
    assert.deepEqual(page.messages, [['Data ditolak internal.', 'error']]);
    assert.match(await renderButton(page), /<button/);
});

test('completion of a previous row does not hide the action of a newly displayed row', async () => {
    const page = setup();
    const pending = page.context.synchronize();
    page.props.endpoint = '/admin/partnership/members/8/supply-chain/sync';
    page.requests[0].resolve({ data: { success: true, message: 'Berhasil.' } });
    await pending;
    assert.match(await renderButton(page), /<button/);
    const next = page.context.synchronize();
    assert.equal(page.requests[1].url, '/admin/partnership/members/8/supply-chain/sync');
    page.requests[1].resolve({ data: { success: true, message: 'Berhasil.' } });
    await next;
});

function actionTemplate(path) {
    const { descriptor } = parse(source(path));
    const find = node => node.tag === 'template' && node.props?.some(prop => prop.type === 7 && prop.name === 'slot' && prop.arg?.content === 'item.actions')
        ? node : node.children?.map(find).find(Boolean);
    const node = find(baseParse(descriptor.template.content));
    return node.loc.source.replace(/^<template[^>]+>/, '<div>').replace(/<\/template>$/, '</div>');
}

for (const [path, endpoint] of [
    ['admin/pages/partnership/members.vue', '/admin/partnership/members/7/supply-chain/sync'],
    ['admin/pages/transactions/sales-orders.vue', '/admin/transactions/orders/7/supply-chain/sync'],
    ['admin/pages/inventory/Shipping.vue', '/admin/transactions/orders/7/supply-chain/sync'],
]) {
    test(`${path} supplies the correct API endpoint and respects server action eligibility`, async () => {
        const render = new Function('Vue', compile(actionTemplate(path), { mode: 'function', prefixIdentifiers: true }).code)(Vue);
        for (const eligible of [true, false]) {
            let configured;
            const context = { item: { id: 7, can_sync_supply_chain: eligible }, openGenealogyDialog() {}, openDetailDialog() {}, openEditDialog() {}, openResetPasswordDialog() {}, openDeactivationDialog() {}, openDetail() {}, printOrder() {}, refreshTable() {} };
            const app = Vue.createSSRApp({ render: () => render(context, []) });
            app.directive('tooltip', {});
            app.component('SupplyChainSyncButton', { props: ['endpoint', 'canSync', 'presentation'], setup(props) {
                configured = { ...props };
                const button = setup({ endpoint: props.endpoint, canSync: props.canSync, presentation: props.presentation });
                return () => button.render(Vue.proxyRefs(button.context), []);
            } });
            for (const tag of ['v-btn', 'v-menu', 'v-list', 'v-list-item', 'v-divider', 'v-progress-circular']) {
                app.component(tag, { inheritAttrs: false, setup: (_, { attrs, slots }) => () => Vue.h('div', attrs, slots.default?.()) });
            }
            const html = await renderToString(app);
            assert.equal(configured?.endpoint, endpoint);
            assert.equal(configured?.canSync, eligible);
            assert.equal(html.includes('aria-label="Sinkronisasi Internal"'), eligible);
        }
    });
}
