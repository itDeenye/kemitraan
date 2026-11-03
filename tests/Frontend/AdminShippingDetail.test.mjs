import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import { compile } from '@vue/compiler-dom';
import { compileScript, parse } from '@vue/compiler-sfc';
import ts from 'typescript';

const require = createRequire(import.meta.url);
const Vue = require('vue');
const { renderToString } = createRequire(require.resolve('vue'))('@vue/server-renderer');
const { descriptor } = parse(readFileSync(new URL('../../resources/js/admin/components/inventory/ShippingDetail.vue', import.meta.url), 'utf8'));
const render = new Function('Vue', compile(descriptor.template.content, { mode: 'function', prefixIdentifiers: true }).code)(Vue);
const modules = new Map();
const sourceFile = ts.createSourceFile('ShippingDetail.ts', descriptor.scriptSetup.content, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
const presentationNames = ['distributorBuyer', 'isDropship', 'formatExpiryDate'];
const presentationCode = ts.transpileModule(sourceFile.statements.filter(statement => ts.isVariableStatement(statement)
    && statement.declarationList.declarations.some(declaration => presentationNames.includes(declaration.name.getText(sourceFile))))
    .map(statement => statement.getText(sourceFile)).join('\n'), {
    compilerOptions: { target: ts.ScriptTarget.ESNext, module: ts.ModuleKind.CommonJS },
}).outputText;

function loadModule(path) {
    if (modules.has(path)) return modules.get(path);
    const filename = new URL(`../../resources/js/${path}`, import.meta.url);
    const source = readFileSync(filename, 'utf8');
    const content = path.endsWith('.vue')
        ? compileScript(parse(source, { filename: filename.pathname }).descriptor, {
            id: path, inlineTemplate: true,
        }).content
        : source;
    const code = ts.transpileModule(content, {
        compilerOptions: { target: ts.ScriptTarget.ESNext, module: ts.ModuleKind.CommonJS },
    }).outputText;
    const module = { exports: {} };
    const resolve = name => name.startsWith('@/')
        ? loadModule(name.slice(2).endsWith('.vue') ? name.slice(2) : `${name.slice(2)}.ts`)
        : require(name);
    new Function('require', 'module', 'exports', code)(resolve, module, module.exports);
    modules.set(path, module.exports);
    return module.exports;
}

async function renderDetail(shipping, overrides = {}) {
    const order = {
        id: 72, code: 'TRX-PICKUP-72', ordered_at: '2026-10-16T09:00:00+07:00',
        status: 'processing', shipping_method: shipping.method, shipping,
        details: [], totals: { shipping_cost: 0 },
        ...overrides,
    };
    const context = {
        order, dialog: true, loading: false, isPickup: shipping.method === 'pickup',
        formatDate: value => value || '-',
        formatDateTime: value => value ? `Tanggal ${value}` : '-',
        formatPrice: value => String(value ?? 0), formatLocation: value => value?.address || '-',
        getShippingItems: productId => shipping.items?.filter(item => item.product_id === productId) || [], close: () => {},
        ...Vue.proxyRefs(new Function('computed', 'order', 'formatDate', `${presentationCode}\nreturn { ${presentationNames.join(',')} };`)(
            Vue.computed, Vue.ref(order), value => value || '-',
        )),
    };
    const app = Vue.createSSRApp({ render: () => render(context, []) });
    const tags = new Set(['v-chip', ...[...descriptor.template.content.matchAll(/<([A-Z][\w-]*|v-[\w-]*)/g)].map(match => match[1])]);
    for (const tag of tags) {
        app.component(tag, tag === 'BaseBadge'
            ? loadModule('shared/components/BaseBadge.vue').default
            : tag === 'PreorderChainInfo'
                ? { render: () => null }
                : { setup: (_, { slots }) => () => Vue.h('div', slots.default?.()) });
    }
    return (await renderToString(app)).replace(/<[^>]*>/g, ' ').replace(/mdi-[\w-]+/g, ' ').replace(/&amp;/g, '&').replace(/\s+/g, ' ').trim();
}

for (const [mode, destinationType] of [
    ['upline_transit', 'distributor'],
    ['upline_transit', 'agent'],
    ['direct', 'reseller'],
    ['direct', undefined],
]) {
    test(`shipping detail keeps the physical ${destinationType || 'fallback buyer'} destination separate for ${mode} orders`, async () => {
        const text = await renderDetail({ method: 'courier_express', items: [] }, {
            code: 'TRX/CMP/DST/000027/I1LLEJ',
            is_preorder: true,
            preorder_fulfillment_mode: mode,
            display_shipping_method: mode === 'upline_transit' ? 'pickup' : 'courier_express',
            seller: { type: 'agent', name: 'Penjual Tahap Ini', origin: {
                type: 'warehouse', name: 'Warehouse Utama DNY', address: 'Alamat Gudang',
            } },
            buyer: { type: 'distributor', name: 'Distributor Pembeli', destination: {
                type: destinationType, name: 'Pak Budi', code: '0001/0000/0000', address: 'Jl. Surabaya',
            } },
            preorder: { origin: { buyer: { type: 'reseller', name: 'Abhimata' } } },
        });

        assert.match(text, /Dikirim Dari Warehouse Utama DNY Alamat Gudang Perusahaan/);
        assert.match(text, /Distributor pembeli Distributor Pembeli - Distributor Tujuan pengiriman/);
        assert.match(text, /Tujuan pengiriman Pak Budi 0001\/0000\/0000 Jl\. Surabaya Rincian Produk/);
        assert.doesNotMatch(text, /Dropship ke|Tujuan pengiriman aktual|Abhimata/);
        if (mode === 'upline_transit') assert.match(text, /Metode Penerimaan Ambil di Upline/);
        else assert.doesNotMatch(text, /Ambil di Upline/);
    });
}

test('distributor on the company purchase step stays separate from the root reseller and delivery address', async () => {
    const overrides = {
        buyer: { id: 99, type: 'reseller', name: 'Reseller Pemesan', destination: {
            id: 99, type: 'reseller', name: 'Penerima Reseller', code: 'RSL-099', address: 'Alamat Pengiriman Reseller',
        } },
        preorder: { chain: [
            { seller: { type: 'agent' }, buyer: { id: 99, type: 'reseller', name: 'Reseller Pemesan' } },
            { seller: { type: 'warehouse' }, buyer: { id: 1, type: 'distributor', name: 'Distributor Pembeli', code: 'DST-001' } },
        ] },
    };
    const before = structuredClone(overrides);
    const text = await renderDetail({ method: 'courier_express', items: [] }, overrides);
    assert.match(text, /Distributor pembeli Distributor Pembeli DST-001 Distributor/);
    assert.match(text, /Tujuan pengiriman Penerima Reseller RSL-099 Alamat Pengiriman Reseller/);
    assert.doesNotMatch(text, /Dropship ke|Tujuan pengiriman aktual/);
    assert.deepEqual(overrides, before);
});

test('a non-distributor order without a company purchase step does not invent a distributor', async () => {
    const text = await renderDetail({ method: 'courier_manual', items: [] }, {
        buyer: { type: 'agent', destination: { type: 'agent', name: 'Agen Penerima', address: 'Alamat Agen' } },
    });
    assert.match(text, /Dikirim Ke Agen Penerima - Alamat Agen Agen Utama/);
    assert.doesNotMatch(text, /Distributor pembeli|Dropship ke/);
});

test('warehouse pickup keeps its buyer information without adding the delivery-only distributor section', async () => {
    const text = await renderDetail({ method: 'pickup', items: [] }, {
        buyer: { type: 'distributor', destination: { type: 'distributor', name: 'Pembeli Datang' } },
    });
    assert.match(text, /Pembeli Pembeli Datang/);
    assert.doesNotMatch(text, /Distributor pembeli|Tujuan pengiriman aktual|Dropship ke/);
});

for (const expiry of [undefined, null, '', '-', 'Invalid Date', 'not-a-date', '0000-00-00', '2027-12-21']) {
    test(`shipment batch expiry ${String(expiry)} renders a valid date or a dash`, async () => {
        const valid = expiry === '2027-12-21';
        const text = await renderDetail({ method: 'courier_express', items: [{
            product_id: 195, quantity: 1, batch_number: '-', expiry_date: expiry,
        }] }, {
            details: [{ id: 1, product: { id: 195, name: 'Produk', code: 'PRD-195' }, quantity: 1 }],
        });
        assert.match(text, new RegExp(`Expired Date: ${valid ? '2027-12-21' : '-'}(?: |$)`));
        assert.doesNotMatch(text, /Invalid Date|not-a-date|0000-00-00/);
    });
}

for (const missing of [null, '', undefined]) {
    test(`pickup detail remains informative with missing delivery note and schedule (${String(missing)})`, async () => {
        const text = await renderDetail({
            method: 'pickup', delivery_note_number: missing, schedule_at: missing,
            verification_status: 'pending', items: [],
        });

        assert.match(text, /Detail Pengambilan\s+Nomor Surat Jalan\s+-\s+Jadwal Pengambilan\s+-/);
        assert.doesNotMatch(text, /Kurir & Layanan|Nomor Resi|Invalid Date/);
    });
}

test('pickup detail shows the persisted delivery note and scheduled collection', async () => {
    const text = await renderDetail({
        method: 'pickup', delivery_note_number: 'SJ-PICKUP-72',
        schedule_at: '2026-10-17 10:30:00', verification_status: 'ready_to_pickup', items: [],
    });

    assert.match(text, /Nomor Surat Jalan\s+SJ-PICKUP-72/);
    assert.match(text, /Jadwal Pengambilan\s+Tanggal 2026-10-17 10:30:00/);
    assert.equal(text.match(/SJ-PICKUP-72/g)?.length, 1);
});

test('courier detail keeps its courier information separate from member collection details', async () => {
    const text = await renderDetail({
        method: 'courier_express', courier: 'JNE', service: 'REG',
        delivery_note_number: '', tracking_number: 'AWB-72',
        pickup_method: 'PICKUP', pickup_number: 'PICKUP-72',
        pickup_schedule: '2026-10-17 09:00:00', items: [],
    });

    assert.match(text, /Kurir & Layanan\s+JNE -\s+REG/);
    assert.match(text, /Nomor Surat Jalan\s+-/);
    assert.match(text, /Nomor Resi\s+AWB-72/);
    assert.match(text, /Jadwal Pickup Kurir\s+Tanggal 2026-10-17 09:00:00/);
    assert.doesNotMatch(text, /Jadwal Pengambilan/);
});
