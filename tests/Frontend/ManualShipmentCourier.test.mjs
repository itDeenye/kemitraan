import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import test from 'node:test';
import * as Vue from 'vue';
import { parse } from '@vue/compiler-sfc';
import ts from 'typescript';

const pageUrl = new URL('../../resources/js/member/pages/transactions/sales/shipping.vue', import.meta.url);
const quote = {
    courier_code: 'sicepat',
    courier_name: 'Sicepat CARGO',
    service_type: 'GOKIL',
    cost: 35000,
};

async function shippingPage({ quotes = [quote], quoteError = false, requiresCourier = true, retail = false } = {}) {
    const { descriptor } = parse(readFileSync(pageUrl, 'utf8'));
    const source = ts.createSourceFile(fileURLToPath(pageUrl), descriptor.scriptSetup.content, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    const statements = source.statements.filter(statement => !ts.isImportDeclaration(statement));
    const bindings = statements.flatMap(statement => ts.isVariableStatement(statement)
        ? statement.declarationList.declarations.flatMap(declaration => ts.isIdentifier(declaration.name)
            ? [declaration.name.text]
            : declaration.name.elements.map(element => element.name.getText(source)))
        : []);
    const code = ts.transpileModule(statements.map(statement => statement.getText(source)).join('\n'), {
        compilerOptions: { target: ts.ScriptTarget.ESNext, module: ts.ModuleKind.CommonJS },
    }).outputText;
    const payloads = [];
    const order = {
        id: 42, code: 'TRX/TEST/42', type: retail ? 'retail' : 'stock', shipping_method: 'courier_manual',
        shipping: { method: 'courier_manual', courier: null, service: null, cost: 0 },
        seller: { name: 'Distributor', origin: { address: 'Alamat pengirim' } },
        buyer: { type: retail ? 'customer' : 'agent', name: 'Agen', destination: { name: null, address: 'Alamat tujuan' } },
        actions: { requires_manual_courier: requiresCourier, requires_shipment_items: false },
        items: [],
    };
    const dependencies = {
        computed: Vue.computed,
        ref: Vue.ref,
        reactive: Vue.reactive,
        watch: Vue.watch,
        onMounted: () => {},
        useRoute: () => ({ query: { id: '42' } }),
        useRouter: () => ({ replace: () => {} }),
        useSnackbarStore: () => ({ showMessage: () => {} }),
        useFormatter: () => ({ formatPrice: value => String(value), formatLocation: location => location?.address || '-' }),
        decodeRouteId: () => 42,
        encodeRouteId: value => String(value),
        saleService: {
            getOrderDetail: async () => ({ success: true, data: order }),
            getShipmentCouriers: async () => {
                if (quoteError) throw new Error('Pricing unavailable');
                return { success: true, data: { results: quotes } };
            },
            shipOrder: async (id, payload) => {
                assert.equal(id, 42);
                payloads.push(payload);
                return { success: false };
            },
        },
    };
    if (descriptor.scriptSetup.content.includes('useManualShipmentCourier')) {
        const module = await import('../../resources/js/member/composables/useManualShipmentCourier.ts');
        dependencies.useManualShipmentCourier = module.useManualShipmentCourier;
    }
    const scope = Vue.effectScope();
    const page = scope.run(() => new Function(...Object.keys(dependencies), `${code}\nreturn { ${bindings.join(',')} };`)(...Object.values(dependencies)));
    await page.fetchDetail();
    await Vue.nextTick();
    return { page, payloads, scope, input: page.courierForm || page.form };
}

test('selecting an estimate fills the cost and a new selection refreshes it', async t => {
    const { input, scope } = await shippingPage();
    t.after(() => scope.stop());
    input.selected_courier = quote;
    await Vue.nextTick();
    assert.equal(input.cost, 35000);
    input.cost = 27000;
    input.selected_courier = { ...quote, courier_code: 'jne', cost: 18000 };
    await Vue.nextTick();
    assert.equal(input.cost, 18000);
});

test('sending an automatically selected courier uses the edited sender cost', async t => {
    const { page, input, payloads, scope } = await shippingPage();
    t.after(() => scope.stop());
    input.selected_courier = quote;
    await Vue.nextTick();
    input.cost = 29000;
    page.form.tracking_number = 'AWB-AUTO-001';
    await page.submit();
    assert.deepEqual(payloads[0]?.courier, { name: 'sicepat', service: 'Sicepat CARGO', service_type: 'GOKIL', cost: 29000 });
});

test('manual entry is preserved until switching back to a selected estimate', async t => {
    const { input, scope } = await shippingPage();
    t.after(() => scope.stop());
    input.selected_courier = quote;
    await Vue.nextTick();
    Object.assign(input, { mode: 'manual', name: 'Kurir Sendiri', service: 'Reguler', cost: 16000 });
    await Vue.nextTick();
    assert.equal(input.cost, 16000);
    input.mode = 'automatic';
    await Vue.nextTick();
    assert.equal(input.cost, 35000);
    assert.equal(input.name, 'Kurir Sendiri');
});

test('manual courier submission works even when automatic pricing fails', async t => {
    const { page, input, payloads, scope } = await shippingPage({ quoteError: true });
    t.after(() => scope.stop());
    Object.assign(input, { mode: 'manual', name: ' Kurir Lokal ', service: ' Same Day ', service_type: 'MANUAL', cost: 15000 });
    page.form.tracking_number = 'AWB-MANUAL-001';
    await page.submit();
    assert.deepEqual(payloads[0]?.courier, { name: 'Kurir Lokal', service: 'Same Day', service_type: 'MANUAL', cost: 15000 });
});

test('regular member shipment can also override its courier, without a transit-only flag', async t => {
    const { page, input, payloads, scope } = await shippingPage({ requiresCourier: false });
    t.after(() => scope.stop());
    Object.assign(input, { mode: 'manual', name: 'Kurir Lokal', service: 'Reguler', service_type: 'MANUAL', cost: 0 });
    page.form.tracking_number = 'AWB-MEMBER-001';
    await page.submit();
    assert.deepEqual(payloads[0]?.courier, { name: 'Kurir Lokal', service: 'Reguler', service_type: 'MANUAL', cost: 0 });
});

test('invalid manual costs and incomplete courier data cannot be submitted', async t => {
    const { page, input, payloads, scope } = await shippingPage();
    t.after(() => scope.stop());
    input.selected_courier = quote;
    await Vue.nextTick();
    Object.assign(input, { mode: 'manual', name: 'Kurir Lokal', service: 'Reguler', service_type: 'MANUAL' });
    page.form.tracking_number = 'AWB-INVALID-001';
    for (const cost of [-1, 1.5, '', null, NaN, Infinity, 4294967296]) {
        input.cost = cost;
        await page.submit();
        assert.equal(payloads.length, 0, `must reject cost ${cost}`);
    }
    input.cost = 15000;
    input.name = ' ';
    await page.submit();
    assert.equal(payloads.length, 0, 'must reject empty courier name');
});

test('the largest storable sender cost is accepted without overflow', async t => {
    const { page, input, payloads, scope } = await shippingPage();
    t.after(() => scope.stop());
    Object.assign(input, { mode: 'manual', name: 'Kurir Lokal', service: 'Reguler', service_type: 'MANUAL', cost: 4294967295 });
    page.form.tracking_number = 'AWB-MAX-001';
    await page.submit();
    assert.equal(payloads[0]?.courier.cost, 4294967295);
});

test('legacy retail courier shipments do not require the new member courier form', async t => {
    const { page, payloads, scope } = await shippingPage({ requiresCourier: false, retail: true });
    t.after(() => scope.stop());
    page.form.tracking_number = 'AWB-LEGACY-POS-001';
    await page.submit();
    assert.equal(payloads.length, 1);
    assert.equal(payloads[0].courier, undefined);
});

test('recipient identity falls back to the buyer when its shipping snapshot has no name', async t => {
    const { page, scope } = await shippingPage();
    t.after(() => scope.stop());
    assert.equal(page.shippingRecipient?.value?.name, 'Agen');
    assert.equal(page.shippingRecipient?.value?.address, 'Alamat tujuan');
});
