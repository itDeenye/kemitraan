import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import * as Vue from 'vue';
import { parse } from '@vue/compiler-sfc';
import ts from 'typescript';

const url = new URL('../../resources/js/admin/components/inventory/ShippingReshipDialog.vue', import.meta.url);

function dialog(order) {
    const { descriptor } = parse(readFileSync(url, 'utf8'));
    const source = ts.createSourceFile('dialog.ts', descriptor.scriptSetup.content, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    const code = ts.transpileModule(source.statements.filter(s => !ts.isImportDeclaration(s)).map(s => s.getText(source)).join('\n'), {
        compilerOptions: { target: ts.ScriptTarget.ESNext, module: ts.ModuleKind.CommonJS },
    }).outputText;
    const dependencies = {
        ref: Vue.ref, reactive: Vue.reactive, computed: Vue.computed,
        defineEmits: () => () => {}, defineExpose: () => {},
        useSnackbarStore: () => ({ showMessage: () => {} }),
        useFormatter: () => ({ formatDateTime: String, formatPrice: String }),
        inventoryService: { getShipmentDetail: async () => ({ data: { data: order } }) },
    };
    return new Function(...Object.keys(dependencies), `${code}\nreturn { open, form, flattenItems };`)(...Object.values(dependencies));
}

test('reship keeps existing delivery note and every persisted product batch', async () => {
    const page = dialog({ shipping_method: 'courier_manual', shipping: { delivery_note_number: 'SJ-OLD-001' }, details: [{
        product: { id: 7, name: 'Produk', code: 'P7', batches: [
            { quantity: 1, batch_number: 'B1', expiry_date: '2027-02-01' },
            { quantity: 2, batch_number: 'B2', expiry_date: '2027-03-01' },
        ] }, quantity: 3,
    }] });
    await page.open({ id: 42 });
    assert.equal(page.form.delivery_note_number, 'SJ-OLD-001');
    assert.deepEqual(page.flattenItems(), [
        { product_id: 7, quantity: 1, batch_number: 'B1', expiry_date: '2027-02-01' },
        { product_id: 7, quantity: 2, batch_number: 'B2', expiry_date: '2027-03-01' },
    ]);
});

test('reship without persisted batches offers one editable row without inventing metadata', async () => {
    const page = dialog({ shipping_method: 'courier_manual', shipping: {}, details: [{ product: { id: 7, name: 'Produk' }, quantity: 3 }] });
    await page.open({ id: 42 });
    assert.equal(page.form.delivery_note_number, '');
    assert.deepEqual(page.flattenItems(), [{ product_id: 7, quantity: 3, batch_number: '', expiry_date: '' }]);
});
