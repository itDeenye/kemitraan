import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import { baseParse, compile } from '@vue/compiler-dom';
import { parse } from '@vue/compiler-sfc';
import ts from 'typescript';

const require = createRequire(import.meta.url);
const Vue = require('vue');
const { renderToString } = createRequire(require.resolve('vue'))('@vue/server-renderer');
const source = path => readFileSync(new URL(`../../resources/js/${path}`, import.meta.url), 'utf8');
const transpile = code => ts.transpileModule(code, {
    compilerOptions: { target: ts.ScriptTarget.ESNext, module: ts.ModuleKind.CommonJS },
}).outputText;
const badgeExports = {};
new Function('exports', transpile(source('shared/composables/useBadge.ts')))(badgeExports);
const badge = badgeExports.useBadge();
const badgeDescriptor = parse(source('shared/components/BaseBadge.vue')).descriptor;
const badgeRender = new Function('Vue', compile(badgeDescriptor.template.content, { mode: 'function', prefixIdentifiers: true }).code)(Vue);
const badgeScript = transpile(badgeDescriptor.scriptSetup.content.replace(/^import .+;\r?$/gm, ''));
const ShippingBadge = {
    props: ['type', 'value', 'text', 'color', 'size', 'variant', 'chipClass', 'align', 'inline'],
    setup(props, { slots }) {
        const computed = new Function('computed', 'useBadge', 'defineProps', `${badgeScript}\nreturn { badgeLabel, badgeColor, alignClass };`)(
            Vue.computed, badgeExports.useBadge, () => props,
        );
        return () => badgeRender(Vue.proxyRefs({ ...props, ...computed, $slots: slots }), []);
    },
};

function headers(path) {
    const script = parse(source(`admin/pages/${path}`)).descriptor.scriptSetup.content;
    const file = ts.createSourceFile('page.ts', script, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    const declaration = file.statements.find(item => ts.isVariableStatement(item)
        && item.declarationList.declarations.some(item => item.name.getText(file) === 'headers'));
    return new Function('getBadgeOptions', `${transpile(declaration.getText(file))}\nreturn headers;`)(badge.getBadgeOptions);
}

for (const path of ['transactions/sales-orders.vue', 'inventory/Shipping.vue']) {
    test(`${path} displays final pickup while retaining the courier method in the row`, async () => {
        const template = parse(source(`admin/pages/${path}`)).descriptor.template.content;
        const find = node => node.tag === 'template' && node.props?.some(prop => prop.type === 7 && prop.arg?.content === 'item.shipping_method')
            ? node : node.children?.map(find).find(Boolean);
        const slot = find(baseParse(template)).loc.source.replace(/^<template[^>]+>/, '<div>').replace(/<\/template>$/, '</div>');
        const render = new Function('Vue', compile(slot, { mode: 'function', prefixIdentifiers: true }).code)(Vue);
        for (const [preorder, mode, method, label] of [
            [true, 'upline_transit', 'courier_express', 'Ambil di Upline'],
            [true, 'direct', 'pickup', 'Ambil Sendiri'],
            [false, 'direct', 'pickup', 'Ambil Sendiri'],
        ]) {
            const item = { is_preorder: preorder, preorder_fulfillment_mode: mode, shipping_method: method, display_shipping_method: 'pickup' };
            const app = Vue.createSSRApp({ render: () => render({ item, column: {} }, []) });
            app.component('BaseBadge', ShippingBadge);
            app.component('v-chip', { setup: (_, { attrs, slots }) => () => Vue.h('span', { 'data-color': attrs.color }, slots.default?.()) });
            const text = await renderToString(app);
            assert.ok(text.includes(label), text);
            assert.doesNotMatch(text, /Kurir Ekspres/);
            assert.match(text, /data-color="success"/);
            assert.equal(item.shipping_method, method);
        }
    });
    test(`${path} courier filters cover all methods shown by the list`, () => {
        const options = headers(path).find(column => column.key === 'shipping_method').options;
        assert.deepEqual(options.map(option => option.value).sort(), ['courier_express', 'courier_instant', 'courier_manual', 'pickup']);
        for (const option of options) assert.equal(option.label, option.value === 'pickup'
            ? 'Ambil Sendiri / Ambil di Upline' : badge.getBadgeData('shipping_method', option.value).label);
    });
}

for (const [path, type] of [
    ['inventory/Shipping.vue', 'transaction_status'],
    ['transactions/sales-returns.vue', 'return_status'],
    ['partnership/member-upgrades.vue', 'upgrade_status'],
    ['partnership/member-downgrades.vue', 'upgrade_status'],
    ['partnership/registration-approvals.vue', 'registration_status'],
    ['rewards/stockist.vue', 'stockist_reward_status'],
]) {
    test(`${path} status filters use the same labels as the displayed badges`, () => {
        const options = headers(path).find(column => column.key === 'status').options;
        for (const option of options) assert.equal(option.label, badge.getBadgeData(type, option.value).label);
    });
}

test('return filters only offer statuses supported by the returns API', () => {
    const options = headers('transactions/sales-returns.vue').find(column => column.key === 'status').options;
    assert.deepEqual(options.map(option => option.value).sort(), [
        'completed', 'received_by_company', 'rejected', 'replacement_in_transit', 'replacement_shipping_failed',
        'return_in_transit', 'return_shipping_failed', 'submitted', 'waiting_member_shipment',
    ]);
});

test('upgrade filters include applied and cancelled states shown in history', () => {
    const options = headers('partnership/member-upgrades.vue').find(column => column.key === 'status').options;
    assert.deepEqual(options.map(option => option.value).sort(), ['applied', 'approved', 'cancelled', 'rejected', 'requested', 'scheduled']);
});

function table(pageHeaders) {
    const props = Vue.reactive({
        url: '/admin/transactions/orders', queryParams: {}, search: '', selected: [],
        headers: pageHeaders ?? [
            { key: 'buyer.code', title: 'Kode Mitra', filter: true, type: 'text' },
            { key: 'order_type.code', filterKey: 'is_preorder', title: 'Tipe Pesanan', type: 'select', options: [{ value: 0, label: 'Reguler' }] },
            { key: 'ordered_at', type: 'date' },
        ],
    });
    const requests = [], events = [], errors = [];
    const api = { get: (url, { params }) => new Promise((resolve, reject) => requests.push({ url, params, resolve, reject })) };
    const script = parse(source('shared/components/BaseDataTable.vue')).descriptor.scriptSetup.content;
    const file = ts.createSourceFile('table.ts', script, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    const code = file.statements.filter(item => !ts.isImportDeclaration(item)).map(item => item.getText(file)).join('\n');
    const component = new Function('ref', 'computed', 'watch', 'defineProps', 'defineEmits', 'defineExpose', 'api', 'console', `${transpile(code)}
        return { loadServerItems, handleApplyFilters, currentFilters, serverItems, totalItems, internalLoading, getFilterDisplayValue };`)(
        Vue.ref, Vue.computed, Vue.watch, () => props, () => (...args) => events.push(args), () => {}, api,
        { error: (...args) => errors.push(args) },
    );
    return { ...component, requests, events, errors };
}

const options = { page: 1, itemsPerPage: 10, sortBy: [] };
for (const path of ['transactions/sales-orders.vue', 'inventory/Shipping.vue']) {
    test(`${path} filters by the displayed final shipping choice rather than the transit courier`, async () => {
        const page = table(headers(path));
        page.currentFilters.value = { 'shipping_method[eq]': 'pickup' };
        const pending = page.loadServerItems(options);
        assert.equal(page.requests[0].params['filter[display_shipping_method]'], 'pickup');
        assert.equal('filter[shipping_method]' in page.requests[0].params, false);
        page.requests[0].resolve({ data: { data: { results: [], pagination: { total_data: 0 } } } });
        await pending;
    });
}
const response = (code, total) => ({ data: { data: { results: [{ code }], pagination: { total_data: total } } } });

test('new filters cannot be overwritten by an older unfiltered response', async () => {
    const page = table();
    const initial = page.loadServerItems(options);
    page.handleApplyFilters({ 'buyer.code[like]': '0001/0000/0000' });
    assert.equal(page.requests[1].params['filter[buyer.code][like]'], '%0001/0000/0000%');
    page.requests[1].resolve(response('FILTERED', 1));
    await new Promise(resolve => setImmediate(resolve));
    page.requests[0].resolve(response('OTHER-MEMBER', 19));
    await initial;
    assert.deepEqual(page.serverItems.value, [{ code: 'FILTERED' }]);
    assert.equal(page.totalItems.value, 1);
    assert.equal(page.events.filter(([name]) => name === 'data-loaded').length, 1);
});

test('stale failures do not clear results or stop the current filter loading', async () => {
    const page = table();
    const initial = page.loadServerItems(options);
    const filtered = page.loadServerItems(options);
    page.requests[0].reject(new Error('old request failed'));
    await initial;
    assert.equal(page.internalLoading.value, true);
    assert.equal(page.errors.length, 0);
    page.requests[1].resolve(response('FILTERED', 1));
    await filtered;
    assert.equal(page.internalLoading.value, false);
    assert.deepEqual(page.serverItems.value, [{ code: 'FILTERED' }]);
});

test('regular orders and date ranges are sent without losing zero values', async () => {
    const page = table();
    page.currentFilters.value = { 'order_type.code[eq]': 0, 'ordered_at[eq]': '2026-10-15::2026-10-15' };
    const loading = page.loadServerItems(options);
    assert.equal(page.requests[0].params['filter[is_preorder]'], 0);
    assert.equal(page.requests[0].params['filter[ordered_at][gte]'], '2026-10-15');
    assert.equal(page.requests[0].params['filter[ordered_at][lt]'], '2026-10-16');
    page.requests[0].resolve({ data: { data: { results: [], pagination: { total_data: 0 } } } });
    await loading;
    assert.equal(page.totalItems.value, 0);
});
