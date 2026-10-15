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
const { descriptor } = parse(readFileSync(new URL('../../resources/js/admin/pages/product/products.vue', import.meta.url), 'utf8'));

function productPage({ failure, confirmed = true } = {}) {
    const source = ts.createSourceFile('products.ts', descriptor.scriptSetup.content, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    const code = ts.transpileModule(source.statements.filter(item => !ts.isImportDeclaration(item)).map(item => item.getText(source)).join('\n'), {
        compilerOptions: { target: ts.ScriptTarget.ESNext, module: ts.ModuleKind.CommonJS },
    }).outputText;
    const requests = [], messages = [], confirmations = [], refreshes = [];
    const dependencies = {
        ref: Vue.ref, onMounted: () => {}, console: { error: () => {} },
        useSnackbarStore: () => ({ showMessage: (...message) => messages.push(message) }),
        productService: { deleteProduct: async id => { requests.push(id); if (failure) throw failure; } },
    };
    const page = new Function(...Object.keys(dependencies), `${code}\nreturn { deleteProduct, confirmDialogRef, productTable };`)(...Object.values(dependencies));
    page.confirmDialogRef.value = { open: async options => { confirmations.push(options); return confirmed; } };
    page.productTable.value = { refresh: () => refreshes.push(true) };
    return { ...page, requests, messages, confirmations, refreshes };
}

for (const canDelete of [true, false, undefined]) {
    test(`delete button is shown only when the API explicitly allows deletion: ${canDelete}`, async () => {
        const render = new Function('Vue', compile(descriptor.template.content, { mode: 'function', prefixIdentifiers: true }).code)(Vue);
        const context = { headers: [], search: '', openFormDialog: () => {}, openDetailDialog: () => {}, deleteProduct: () => {} };
        const app = Vue.createSSRApp({ render: () => render(context, []) });
        app.component('BaseDataTable', { setup: (_, { slots }) => () => slots['item.actions']({ item: { id: 1, can_delete: canDelete } }) });
        app.component('v-btn', { setup: (_, { attrs }) => () => Vue.h('button', { 'data-icon': attrs.icon }) });
        for (const name of ['ProductForm', 'ProductDetail', 'BaseConfirm', 'BaseBadge']) app.component(name, { render: () => null });
        app.directive('tooltip', {});
        const html = await renderToString(app);
        assert.equal(html.includes('mdi-trash-can-outline'), canDelete === true);
        assert.ok(html.includes('mdi-pencil-outline'));
        assert.ok(html.includes('mdi-eye-outline'));
    });
}

test('blocked or missing permissions never open confirmation or submit deletion', async () => {
    const page = productPage();
    for (const can_delete of [false, undefined]) await page.deleteProduct({ id: 1, name: 'Produk', can_delete });
    assert.deepEqual(page.requests, []);
    assert.deepEqual(page.confirmations, []);
    assert.deepEqual(page.refreshes, []);
    assert.equal(page.messages.length, 2);
});

test('unused products can be deleted after confirmation', async () => {
    const page = productPage();
    await page.deleteProduct({ id: 1, name: 'Produk', can_delete: true });
    assert.deepEqual(page.requests, [1]);
    assert.equal(page.confirmations.length, 1);
    assert.equal(page.refreshes.length, 1);
    assert.deepEqual(page.messages, [['Berhasil menghapus produk', 'success']]);
});

test('declining confirmation preserves the product', async () => {
    const page = productPage({ confirmed: false });
    await page.deleteProduct({ id: 1, name: 'Produk', can_delete: true });
    assert.equal(page.confirmations.length, 1);
    assert.deepEqual(page.requests, []);
    assert.deepEqual(page.messages, []);
});

test('stale deletion permissions display the authoritative API rejection message', async () => {
    const message = 'Produk sudah digunakan dalam transaksi dan tidak dapat dihapus.';
    const page = productPage({ failure: { response: { data: { message } } } });
    await page.deleteProduct({ id: 1, name: 'Produk', can_delete: true });
    assert.deepEqual(page.requests, [1]);
    assert.deepEqual(page.refreshes, []);
    assert.deepEqual(page.messages, [[message, 'error']]);
});
