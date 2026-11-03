import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import { compile } from '@vue/compiler-dom';
import { parse } from '@vue/compiler-sfc';
import ts from 'typescript';

const require = createRequire(import.meta.url);
const Vue = require('vue');
const { renderToString } = createRequire(require.resolve('vue'))('@vue/server-renderer');
const source = path => readFileSync(new URL(`../../resources/js/${path}`, import.meta.url), 'utf8');
const transpile = content => ts.transpileModule(content, {
    compilerOptions: { target: ts.ScriptTarget.ESNext, module: ts.ModuleKind.CommonJS },
}).outputText;
const formatter = {
    formatDateTime: value => value || '-', formatPrice: value => Number(value || 0).toLocaleString('id-ID'),
    formatLocation: () => '-',
};

function setup(path, dependencies) {
    const script = parse(source(path)).descriptor.scriptSetup.content;
    const file = ts.createSourceFile(path, script, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    const statements = file.statements.filter(statement => !ts.isImportDeclaration(statement));
    const bindings = statements.flatMap(statement => ts.isVariableStatement(statement)
        ? statement.declarationList.declarations.flatMap(declaration => ts.isIdentifier(declaration.name)
            ? [declaration.name.text] : declaration.name.elements.map(element => element.name.getText(file)))
        : ts.isFunctionDeclaration(statement) ? [statement.name.text] : []);
    const injected = { ref: Vue.ref, computed: Vue.computed, onBeforeUnmount: () => {}, defineExpose: () => {},
        defineEmits: () => () => {}, useSnackbarStore: () => ({ showMessage: () => {} }),
        useFormatter: () => formatter, console: { error: () => {} }, ...dependencies };
    return new Function(...Object.keys(injected), `${transpile(statements.map(statement => statement.getText(file)).join('\n'))}\nreturn { ${bindings.join(',')} };`)(...Object.values(injected));
}

const dialogPath = 'admin/components/transactions/PaymentVerificationDialog.vue';
const historyPath = 'admin/pages/transactions/payment-verification-history.vue';
function payment(status) {
    return { id: 3, code: 'TRX-HISTORY-003', status: 'processing', seller: {}, buyer: {}, details: [], totals: {},
        shipping: null, shipping_method: 'pickup', payment: { id: 3, status, bank: {}, bill_amount: 100000,
            amount: 95000, receipt_url: null, note: 'Nominal diperiksa', verified_at: '2026-11-02 10:00:00' } };
}
function dialog(readOnly, status) {
    const calls = [];
    const state = setup(dialogPath, {
        defineProps: () => ({ readOnly }), transactionPaymentService: {
            getDetail: async id => { calls.push(['detail', id]); return payment(status); },
            getHistoryDetail: async id => { calls.push(['history', id]); return payment(status); },
            approve: async id => calls.push(['approve', id]), reject: async id => calls.push(['reject', id]),
        },
    });
    return { state, calls };
}

async function renderDialog(state) {
    const descriptor = parse(source(dialogPath)).descriptor;
    const render = new Function('Vue', compile(descriptor.template.content, { mode: 'function', prefixIdentifiers: true }).code)(Vue);
    const context = Vue.proxyRefs({ ...formatter, ...state });
    const app = Vue.createSSRApp({ render: () => render(context, []) });
    const tags = new Set([...descriptor.template.content.matchAll(/<([A-Z][\w-]*|v-[\w-]*)/g)].map(match => match[1]));
    for (const tag of tags) {
        app.component(tag, tag === 'v-dialog'
            ? { props: ['modelValue'], setup: (props, { slots }) => () => props.modelValue ? Vue.h('div', slots.default?.()) : null }
            : ['BaseBadge', 'PreorderChainInfo'].includes(tag) ? { render: () => null }
                : { setup: (_, { slots }) => () => Vue.h(tag === 'v-btn' ? 'button' : 'div', slots.default?.()) });
    }
    return (await renderToString(app)).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
}

for (const status of ['approved', 'rejected', 'submitted']) {
    test(`finance history dialog stays read only even for ${status} data`, async () => {
        const { state, calls } = dialog(true, status);
        await state.open(3);
        assert.deepEqual(calls, [['history', 3]]);
        state.openActionDialog('approve');
        assert.equal(state.actionDialog.value, false);
        await state.submitAction();
        state.openActionDialog('reject');
        await state.submitAction();
        assert.deepEqual(calls, [['history', 3]]);
        const text = await renderDialog(state);
        assert.match(text, /Detail Riwayat Verifikasi Pembayaran/);
        assert.match(text, /Hasil Verifikasi/);
        assert.match(text, /Waktu Verifikasi: 2026-11-02 10:00:00/);
        assert.match(text, /95\.000/);
        assert.match(text, /Nominal diperiksa/);
        assert.doesNotMatch(text, /Tolak Pembayaran|Apakah Anda yakin|Catatan \(Opsional\)/);
    });
}

test('the existing verification dialog retains its approval workflow outside history', async () => {
    const { state, calls } = dialog(false, 'submitted');
    await state.open(2);
    assert.deepEqual(calls, [['detail', 2]]);
    assert.match(await renderDialog(state), /Tolak Pembayaran/);
    state.openActionDialog('approve');
    assert.equal(state.actionDialog.value, true);
    await state.submitAction();
    assert.deepEqual(calls, [['detail', 2], ['approve', 3]]);
});

test('history page uses its read-only endpoint, status filter and detail dialog without verification actions', async () => {
    const descriptor = parse(source(historyPath)).descriptor;
    const state = setup(historyPath, {});
    assert.deepEqual(state.headers.find(header => header.key === 'status').options.map(option => option.value), ['approved', 'rejected']);
    assert.equal(state.headers.some(header => header.key === 'actions'), false);
    assert.equal(state.headers.some(header => header.key === 'detail'), true);
    assert.equal(state.headers.some(header => header.key === 'bill_amount'), false);
    assert.equal(state.headers.filter(header => header.key === 'amount').length, 1);
    let tableUrl;
    let readOnly;
    let detailButton;
    const render = new Function('Vue', compile(descriptor.template.content, { mode: 'function', prefixIdentifiers: true }).code)(Vue);
    const app = Vue.createSSRApp({ render: () => render(Vue.proxyRefs({ ...formatter, ...state }), []) });
    app.component('BaseDataTable', { props: ['url', 'headers'], setup: (props, { slots }) => {
        tableUrl = props.url;
        return () => Vue.h('div', slots['item.detail']?.({ item: { id: 3 } }));
    } });
    app.component('PaymentVerificationDialog', { props: { readOnly: Boolean }, setup: props => { readOnly = props.readOnly; return () => null; } });
    app.component('BaseBadge', { render: () => null });
    app.component('v-btn', { props: ['icon', 'variant', 'color', 'size'], setup: (props, { attrs }) => {
        detailButton = { ...props, class: attrs.class };
        return () => Vue.h('button');
    } });
    app.directive('tooltip', {});
    const html = await renderToString(app);
    assert.match(html, /Riwayat Verifikasi Pembayaran/);
    assert.equal(tableUrl, '/admin/transactions/payment-history');
    assert.equal(readOnly, true);
    assert.deepEqual(detailButton, { icon: 'mdi-eye-outline', variant: 'outlined', color: 'primary', size: 'small', class: 'rounded flex-shrink-0' });
    assert.doesNotMatch(source(historyPath), /\.approve\(|\.reject\(|\/approve|\/reject|openActionDialog/);
});
