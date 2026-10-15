import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { parse } from '@vue/compiler-sfc';
import { computed, reactive, ref } from 'vue';
import ts from 'typescript';

const providerRates = [
    { courier_code: 'posindonesia', courier_name: 'POS Indonesia', service_type: 'REG', cost: 9000, etd: '2-3', insurance: 0, force_insurance: false },
    { courier_code: 'tiki', courier_name: 'TIKI Reguler', service_type: 'REG', cost: 10000, etd: '2', insurance: 0, force_insurance: false },
];

function productionBindings(path, names, dependencies) {
    const file = new URL(`../../resources/js/${path}`, import.meta.url);
    const script = parse(readFileSync(file, 'utf8')).descriptor.scriptSetup.content;
    const source = ts.createSourceFile(path, script, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    const printer = ts.createPrinter();
    const statements = source.statements.filter(ts.isVariableStatement).flatMap(statement =>
        statement.declarationList.declarations
            .filter(declaration => ts.isIdentifier(declaration.name) && names.includes(declaration.name.text))
            .map(declaration => `const ${printer.printNode(ts.EmitHint.Unspecified, declaration, source)};`),
    );
    assert.equal(statements.length, names.length, `${path}: production bindings not found`);
    const code = ts.transpileModule(statements.join('\n'), {
        compilerOptions: { target: ts.ScriptTarget.ESNext, module: ts.ModuleKind.CommonJS },
    }).outputText;
    return new Function(...Object.keys(dependencies), `${code}\nreturn { ${names.join(',')} };`)(...Object.values(dependencies));
}

function ratesFor(payload) {
    return providerRates.filter(rate => !payload.couriers?.length || payload.couriers.includes(rate.courier_code));
}

const snackbar = { showMessage: message => assert.fail(`Unexpected error: ${message}`) };

for (const kind of ['orders', 'sales']) {
    test(`${kind} checkout offers and selects courier rates outside the old whitelist`, async () => {
        const form = ref({ shipping_method: 'courier_express', address_id: 7, customer_id: 9, courier: null, shipping_cost: 0 });
        const availableRates = ref([]);
        const selectedRate = ref(null);
        const shippingCost = ref(0);
        const isLoadingRates = ref(false);
        const dependencies = {
            form, availableRates, selectedRate, shippingCost, isLoadingRates, snackbar,
            shippingOrigin: ref(null),
            checkoutOptions: ref({ shipping_methods: [{ code: 'pickup' }] }),
            formOptions: ref({ seller: { district_id: 123, subdistrict_id: 1234 } }),
            customerOptions: ref([{ id: 9, district_id: 234, subdistrict_id: 2345 }]),
            cartProducts: ref([{ product: { id: 12 }, quantity: 2 }]),
        };
        const getExpressRates = async payload => {
            assert.deepEqual(payload.items, [{ product_id: 12, quantity: 2 }]);
            assert.equal(kind === 'orders' ? payload.address_id : payload.customer_id, kind === 'orders' ? 7 : 9);
            return { success: true, data: { results: ratesFor(payload), origin: { name: 'Pengirim' }, shipping_method: 'courier_express' } };
        };
        dependencies[kind === 'orders' ? 'shippingService' : 'saleService'] = { getExpressRates };
        const page = productionBindings(`member/pages/transactions/${kind}/create.vue`, ['selectRate', 'calculateShipping'], dependencies);

        await page.calculateShipping();
        assert.deepEqual(availableRates.value.map(rate => rate.courier_code), ['posindonesia', 'tiki']);
        assert.equal(selectedRate.value.courier_code, 'posindonesia');
        assert.equal(shippingCost.value, 9000);
        page.selectRate(availableRates.value[1]);
        assert.equal(form.value.courier.courier_code, 'tiki');
        assert.equal(form.value.shipping_cost, 10000);
        assert.equal(isLoadingRates.value, false);
    });
}

for (const dialog of ['ShippingActionDialog', 'ShippingReshipDialog']) {
    test(`${dialog} loads all returned couriers and keeps the selected non-whitelisted courier`, async () => {
        const couriers = ref([]);
        const loadingCouriers = ref(false);
        const form = reactive({ selected_courier: null });
        const { fetchCouriers } = productionBindings(`admin/components/inventory/${dialog}.vue`, ['fetchCouriers'], {
            couriers, loadingCouriers, form, order: ref({ id: 42 }), snackbar,
            formatPrice: value => String(value),
            inventoryService: {
                getReshipCouriers: async (id, payload) => {
                    assert.equal(id, 42);
                    return { data: { success: true, data: { results: ratesFor(payload) } } };
                },
            },
        });

        await fetchCouriers(42);
        assert.deepEqual(couriers.value.map(rate => rate.courier_code), ['posindonesia', 'tiki']);
        assert.equal(form.selected_courier.courier_code, 'posindonesia');
        assert.equal(form.selected_courier.cost, 9000);
        assert.equal(loadingCouriers.value, false);
    });
}

test('return replacement can choose any courier offered for the replacement items', async () => {
    const courierOptions = ref([]);
    const loadingCouriers = ref(false);
    const selectedCourierIdx = ref(null);
    const { loadCourierOptions, selectedCourier } = productionBindings('admin/components/transactions/ReturnShipReplacementDialog.vue', ['loadCourierOptions', 'selectedCourier'], {
        courierOptions, loadingCouriers, selectedCourierIdx, selectedSchedule: ref(''), computed, snackbar,
        returnData: ref({ id: 42 }),
        formItems: ref([{ product_id: 12, order_quantity: 2 }]),
        transactionReturnService: {
            getCourierOptions: async (id, reference, payload) => {
                assert.equal(id, 42);
                assert.equal(reference, 'return_replacement');
                assert.deepEqual(payload.items, [{ product_id: 12, quantity: 2 }]);
                return { results: ratesFor(payload) };
            },
        },
    });

    await loadCourierOptions();
    assert.deepEqual(courierOptions.value.map(rate => rate.courier_code), ['posindonesia', 'tiki']);
    selectedCourierIdx.value = 1;
    assert.equal(selectedCourier.value.courier_code, 'tiki');
    assert.equal(selectedCourier.value.cost, 10000);
    assert.equal(loadingCouriers.value, false);
});
