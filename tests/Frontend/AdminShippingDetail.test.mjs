import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import { compile } from '@vue/compiler-dom';
import { parse } from '@vue/compiler-sfc';

const require = createRequire(import.meta.url);
const Vue = require('vue');
const { renderToString } = createRequire(require.resolve('vue'))('@vue/server-renderer');
const { descriptor } = parse(readFileSync(new URL('../../resources/js/admin/components/inventory/ShippingDetail.vue', import.meta.url), 'utf8'));
const render = new Function('Vue', compile(descriptor.template.content, { mode: 'function', prefixIdentifiers: true }).code)(Vue);

async function renderDetail(shipping) {
    const order = {
        id: 72, code: 'TRX-PICKUP-72', ordered_at: '2026-10-16T09:00:00+07:00',
        status: 'processing', shipping_method: shipping.method, shipping,
        details: [], totals: { shipping_cost: 0 },
    };
    const context = {
        order, dialog: true, loading: false, isPickup: shipping.method === 'pickup',
        formatDate: value => value || '-',
        formatDateTime: value => value ? `Tanggal ${value}` : '-',
        formatPrice: value => String(value ?? 0), formatLocation: () => '-',
        getShippingItems: () => [], close: () => {},
    };
    const app = Vue.createSSRApp({ render: () => render(context, []) });
    const tags = new Set([...descriptor.template.content.matchAll(/<([A-Z][\w-]*|v-[\w-]*)/g)].map(match => match[1]));
    for (const tag of tags) {
        app.component(tag, ['BaseBadge', 'PreorderChainInfo'].includes(tag)
            ? { render: () => null }
            : { setup: (_, { slots }) => () => Vue.h('div', slots.default?.()) });
    }
    return (await renderToString(app)).replace(/<[^>]*>/g, ' ').replace(/&amp;/g, '&').replace(/\s+/g, ' ').trim();
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
