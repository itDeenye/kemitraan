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
const file = path => new URL(`../../resources/js/${path}`, import.meta.url);
const formatter = {
    formatPrice: value => Number(value ?? 0).toLocaleString('id-ID'),
    formatDate: value => String(value ?? ''),
    formatDateTime: value => String(value ?? ''),
    formatLocation: value => value?.address ?? '-',
};

function setup(path, dependencies = {}) {
    const { descriptor } = parse(readFileSync(file(path), 'utf8'));
    const source = ts.createSourceFile(path, descriptor.scriptSetup.content, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    const statements = source.statements.filter(statement => !ts.isImportDeclaration(statement));
    const bindings = statements.flatMap(statement => ts.isVariableStatement(statement)
        ? statement.declarationList.declarations.flatMap(declaration => ts.isIdentifier(declaration.name)
            ? [declaration.name.text]
            : declaration.name.elements.map(element => element.name.getText(source)))
        : ts.isFunctionDeclaration(statement) ? [statement.name.text] : []);
    const code = ts.transpileModule(statements.map(statement => statement.getText(source)).join('\n'), {
        compilerOptions: { target: ts.ScriptTarget.ESNext, module: ts.ModuleKind.CommonJS },
    }).outputText;
    const injected = {
        ref: Vue.ref, reactive: Vue.reactive, computed: Vue.computed,
        onMounted: () => {}, defineExpose: () => {}, defineEmits: () => () => {},
        useSnackbarStore: () => ({ showMessage: () => {} }),
        useFormatter: () => formatter,
        console: { error: () => {} },
        ...dependencies,
    };
    return new Function(...Object.keys(injected), `${code}\nreturn { ${bindings.join(',')} };`)(...Object.values(injected));
}

async function render(path, context, components = {}) {
    const { descriptor } = parse(readFileSync(file(path), 'utf8'));
    const renderTemplate = new Function('Vue', compile(descriptor.template.content, { mode: 'function', prefixIdentifiers: true }).code)(Vue);
    const app = Vue.createSSRApp({ render: () => renderTemplate({ ...formatter, encodeRouteId: value => String(value), ...context }, []) });
    const tags = new Set([...descriptor.template.content.matchAll(/<([A-Z][\w-]*|v-[\w-]*|router-link)/g)].map(match => match[1]));
    for (const tag of tags) {
        app.component(tag, components[tag] ?? { setup: (_, { slots }) => () => Vue.h('div', [
            slots.prepend?.(), slots.default?.(), slots.append?.(),
        ]) });
    }
    app.directive('tooltip', {});
    app.directive('ripple', {});
    return (await renderToString(app)).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
}

const product = {
    id: 7, code: 'PRD7', name: 'Produk Uji', bpom_number: null, image: null,
    description: 'Produk', prices: { customer: 10000, members: [] },
    category: { id: 1, name: 'Kategori' }, dimensions_cm: { length: 1, width: 1, height: 1 },
    weight_grams: 100, unit: 'pcs', is_publish: true, is_active: true,
    monthly_points: 0, annual_points: 7,
};

function productForm({ detail = product, unavailable = false } = {}) {
    const submitted = [];
    const form = setup('admin/components/product/ProductForm.vue', {
        productService: {
            getProduct: async () => { if (unavailable) throw new Error('Unavailable'); return detail; },
            createProduct: async payload => submitted.push({ type: 'create', payload: structuredClone(Vue.toRaw(payload)) }),
            updateProduct: async (id, payload) => submitted.push({ type: 'update', id, payload: structuredClone(Vue.toRaw(payload)) }),
        },
        categoryService: { getCategories: async () => [] },
    });
    form.formRef.value = { validate: async () => ({ valid: true }), resetValidation: () => {} };
    return { form, submitted };
}

test('creating a product submits the default one point in both periods', async () => {
    const { form, submitted } = productForm();
    await form.open();
    await form.save();
    assert.equal(submitted[0].payload.monthly_points, 1);
    assert.equal(submitted[0].payload.annual_points, 1);
});

test('editing hydrates and submits explicit zero monthly points independently from annual points', async () => {
    const { form, submitted } = productForm();
    await form.open(product);
    assert.equal(form.form.monthly_points, 0);
    assert.equal(form.form.annual_points, 7);
    await form.save();
    assert.equal(submitted[0].type, 'update');
    assert.equal(submitted[0].id, 7);
    assert.equal(submitted[0].payload.monthly_points, 0);
    assert.equal(submitted[0].payload.annual_points, 7);
});

test('edit fallback retains point values and reopening create resets them to one', async () => {
    const { form, submitted } = productForm({ unavailable: true });
    await form.open(product);
    await form.save();
    assert.equal(submitted[0].payload.monthly_points, 0);
    assert.equal(submitted[0].payload.annual_points, 7);
    await form.open();
    await form.save();
    assert.equal(submitted[1].payload.monthly_points, 1);
    assert.equal(submitted[1].payload.annual_points, 1);
});

test('point input validation accepts zero and rejects fractions, negative or missing numbers', () => {
    const { form } = productForm();
    assert.equal(typeof form.rules.points, 'function');
    const valid = form.rules.points('Poin');
    for (const value of [0, 1, 1000, 4294967295, '0', '8']) assert.equal(valid(value), true);
    for (const value of [-1, 1.5, 4294967296, '', ' ', null, undefined, NaN, Infinity, 'abc', true, false, [], {}]) assert.notEqual(valid(value), true);
});

test('rendered product point inputs convert entered zero and seven into numeric API values', async () => {
    const { form, submitted } = productForm();
    await form.open();
    const inputs = new Map();
    await render('admin/components/product/ProductForm.vue', Vue.proxyRefs(form), {
        'v-text-field': {
            props: ['modelValue', 'modelModifiers'], emits: ['update:modelValue'],
            setup(props, { attrs, emit }) {
                inputs.set(attrs.name, { value: props.modelValue, enter: value => emit('update:modelValue', value) });
                return () => Vue.h('input');
            },
        },
    });
    assert.equal(inputs.get('monthly_points')?.value, 1);
    assert.equal(inputs.get('annual_points')?.value, 1);
    inputs.get('monthly_points').enter('0');
    inputs.get('annual_points').enter('7');
    await form.save();
    assert.equal(submitted[0].payload.monthly_points, 0);
    assert.equal(submitted[0].payload.annual_points, 7);
});

for (const [path, context] of [
    ['admin/components/product/ProductDetail.vue', { dialog: true, product, loading: false }],
    ['member/components/transactions/ProductDetailModal.vue', { isOpen: true, product, isLoading: false }],
]) {
    test(`${path} shows both point values including zero without per-unit labels`, async () => {
        const text = await render(path, context);
        assert.match(text, /Poin Bulanan\s+0/, path);
        assert.match(text, /Poin Tahunan\s+7/, path);
        assert.doesNotMatch(text, /per unit/i, path);
    });
}

test('product form uses monthly and annual point labels without per-unit wording', async () => {
    const { form } = productForm();
    await form.open();
    const text = await render('admin/components/product/ProductForm.vue', Vue.proxyRefs(form));
    assert.match(text, /Poin Bulanan/);
    assert.match(text, /Poin Tahunan/);
    assert.doesNotMatch(text, /per unit/i);
});

const items = [
    { id: 1, product: { ...product, monthly_points: 2, annual_points: 0 }, quantity: 3,
        price: 10000, net_price: 10000, subtotal: 30000, preorder_quantity: 0,
        points: 999, monthly_points: 6, annual_points: 0 },
    { id: 2, product: { ...product, id: 8, name: 'Produk Kedua', monthly_points: 1, annual_points: 4 }, quantity: 3,
        price: 10000, net_price: 10000, subtotal: 30000, preorder_quantity: 0,
        points: 999, monthly_points: 3, annual_points: 12 },
];
const totals = { product_total: 60000, grand_total: 60000, total_monthly_points: 9, total_annual_points: 12 };
const order = {
    id: 42, code: 'TRX42', invoice_number: 'INV42', status: { code: 'completed', label: 'Selesai' },
    seller: { name: 'Penjual' }, buyer: { name: 'Pembeli' }, items, details: items,
    summary: totals, totals, actions: {}, shipping: { method: 'pickup' }, shipping_method: 'pickup', payment: {},
};

const transactionPages = [
    'member/pages/transactions/orders/[id].vue',
    'member/pages/transactions/orders/payment.vue',
    'member/pages/transactions/sales/[id].vue',
    'member/pages/transactions/sales/payment.vue',
    'admin/components/transactions/OrderDetailDialog.vue',
    'admin/components/transactions/PaymentVerificationDialog.vue',
    'admin/components/transactions/StockScreeningDialog.vue',
];

for (const path of [...transactionPages.filter(path => path.startsWith('admin/')), 'admin/components/inventory/ShippingDetail.vue']) {
    test(`${path} hides courier pickup type until a courier is selected without hiding final receiving method`, async () => {
        for (const courier of [undefined, null, '', '   ', 'JNE']) {
            const data = { ...order, is_preorder: true, preorder_fulfillment_mode: 'upline_transit', display_shipping_method: 'pickup', shipping_method: 'courier_express',
                shipping: { method: 'courier_express', courier, pickup_method: 'PICKUP', items: [] } };
            const text = await render(path, {
                order: data, payment: data, isLoading: false, loading: false, dialog: true,
                isPickup: false, isPickupOrder: false, form: {}, courierForm: {}, getShippingItems: () => [],
                shippingCourierLabel: courier,
                spreadUpload: { isUploading: { value: false } }, availableCouriers: [], $route: { query: {} },
            });
            assert.match(text, /Ambil di Upline/);
            assert.equal(/Tipe Pickup Kurir/.test(text), courier === 'JNE', `courier=${String(courier)}`);
        }
    });
    for (const [displayMethod, expected, mode, method] of [
        ['pickup', 'pickup', 'upline_transit', 'courier_express'],
        [undefined, 'courier_express', 'upline_transit', 'courier_express'],
        ['pickup', 'pickup', 'direct', 'pickup'],
    ]) {
        test(`${path} shows final shipping ${String(displayMethod)} (${mode}) separately from the operational courier`, async () => {
            const data = { ...order, is_preorder: true, preorder_fulfillment_mode: mode, display_shipping_method: displayMethod, shipping_method: method,
                shipping: { method: 'courier_express', courier: 'JNE', service: 'REG', items: [] } };
            const text = await render(path, {
                order: data, payment: data, isLoading: false, loading: false, dialog: true,
                isPickup: false, isPickupOrder: false, form: {}, courierForm: {}, getShippingItems: () => [],
                shippingCourierLabel: 'JNE - REG',
                spreadUpload: { isUploading: { value: false } }, availableCouriers: [], $route: { query: {} },
            }, { BaseBadge: { props: ['type', 'value'], setup: (props, { slots }) => () => props.type === 'shipping_method'
                ? Vue.h('span', [`Metode:${props.value}`, slots.default?.()]) : null } });
            assert.match(text, new RegExp(`Metode:${expected}`));
            assert.match(text, /JNE/);
            if (displayMethod === 'pickup' && mode === 'upline_transit') {
                assert.match(text, /Ambil di Upline/);
                assert.match(text, /Kurir ke Lokasi Pengambilan/);
            } else {
                assert.doesNotMatch(text, /Ambil di Upline/);
            }
        });
    }
}

for (const path of transactionPages) {
    test(`${path} renders line snapshots and two summary totals without duplicating them`, async () => {
        const text = await render(path, {
            order, payment: order, isLoading: false, loading: false, dialog: true,
            isPickup: true, isPickupOrder: true, form: {}, courierForm: {},
            spreadUpload: { isUploading: { value: false } }, availableCouriers: [], $route: { query: {} },
        });
        assert.match(text, /Poin Bulanan:\s+6\s+·\s+Poin Tahunan:\s+0/);
        assert.match(text, /Poin Bulanan:\s+3\s+·\s+Poin Tahunan:\s+12/);
        assert.match(text, /Total Poin Bulanan\s+9/);
        assert.match(text, /Total Poin Tahunan\s+12/);
        assert.equal((text.match(/Total Poin Bulanan/g) || []).length, 1);
        assert.equal((text.match(/Total Poin Tahunan/g) || []).length, 1);
        assert.doesNotMatch(text, /999/);
    });
}

test('printed admin invoice includes points for each batch row and preserves transaction totals', async () => {
    let html = '';
    const documentOrder = structuredClone(order);
    documentOrder.details[0].product.batches = [
        { batch_number: 'A', expiry_date: null, quantity: 1 },
        { batch_number: 'B', expiry_date: null, quantity: 2 },
    ];
    const dialog = setup('admin/components/transactions/OrderDetailDialog.vue', {
        defineProps: () => ({}),
        transactionOrderService: { getDocument: async () => documentOrder },
        window: { open: () => ({ document: { open: () => {}, write: content => { html = content; }, close: () => {} } }) },
    });
    await dialog.printDocument('invoice', 42);
    const text = html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ');
    assert.match(text, /Poin Bulanan:\s+2\s+·\s+Poin Tahunan:\s+0/);
    assert.match(text, /Poin Bulanan:\s+4\s+·\s+Poin Tahunan:\s+0/);
    assert.match(text, /Poin Bulanan:\s+3\s+·\s+Poin Tahunan:\s+12/);
    assert.match(text, /Total Poin Bulanan\s+9/);
    assert.match(text, /Total Poin Tahunan\s+12/);
});
