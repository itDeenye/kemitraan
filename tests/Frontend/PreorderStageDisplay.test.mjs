import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import { compileScript, parse } from '@vue/compiler-sfc';
import ts from 'typescript';

const require = createRequire(import.meta.url);
const Vue = require('vue');
const { renderToString } = createRequire(require.resolve('vue'))('@vue/server-renderer');
const memberPath = 'member/components/transactions/PreorderOriginCard.vue';
const adminPath = 'admin/components/transactions/PreorderChainInfo.vue';
const modules = new Map();

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

async function render(path, preorder) {
    const app = Vue.createSSRApp(loadModule(path).default, { preorder });
    // Only replace presentational Vuetify wrappers; both PO components and BaseBadge are real.
    for (const tag of ['v-card', 'v-icon', 'v-chip']) {
        app.component(tag, {
            props: ['color', 'size', 'variant', 'icon'],
            setup: (props, { slots }) => () => Vue.h('div', { 'data-color': props.color }, slots.default?.()),
        });
    }
    return renderToString(app);
}

function freeze(value) {
    if (value && typeof value === 'object') {
        Object.values(value).forEach(freeze);
        Object.freeze(value);
    }
    return value;
}

function preorderFor(steps) {
    const chain = steps.map(([sequence, status, statusLabel]) => ({
        sequence,
        transaction: {
            id: sequence, code: `PO-${sequence}`, status, status_label: statusLabel,
            payment_status: 'approved', payment_status_label: 'Disetujui',
            ordered_at: '2026-10-15T08:00:00+07:00', is_projected: false,
        },
        buyer: { id: sequence, name: 'Pembeli', code: `B${sequence}`, type: 'distributor' },
        seller: { id: sequence + 10, name: 'Penjual', code: null, type: 'warehouse' },
        is_projected: false,
    }));
    return freeze({
        current_transaction_id: 1, current_member_transaction_id: 1,
        origin: { buyer: chain[0].buyer, transaction: chain[0].transaction }, chain,
    });
}

function statusPanels(html) {
    return [...html.matchAll(/<div class="rounded-lg px-3 py-3 ([^"]+)"[^>]*>\s*<span[^>]*>\s*Status\s*<\/span>\s*<strong[^>]*>\s*([^<]+?)\s*<\/strong>/g)]
        .map(match => ({ classes: match[1], label: match[2].trim() }));
}

const blue = 'bg-[#eef3ff] text-[#3159a6]';
const green = 'bg-[#e5f7ee] text-[#147a4f]';
const red = 'bg-[#fde8e8] text-[#b42318]';
const orange = 'bg-[#fff7e9] text-[#b96500]';
const cases = [
    ['shipped', 'Dikirim', blue],
    ['received', 'Siap Diterima', green],
    ['processing', 'Diproses', blue],
    ['ready_to_pickup', 'Siap Diambil', blue],
    ['completed', 'Selesai', green],
    ['cancelled', 'Dibatalkan', red],
    ['rejected', 'Ditolak', red],
    ['waiting_payment', 'Menunggu Pembayaran', orange],
    ['reship_required', 'Perlu Dikirim Ulang', orange],
];

for (const sequence of [1, 2, 3]) {
    for (const [status, label, classes] of cases) {
        const mapped = sequence === 3 && ['shipped', 'received'].includes(status);
        test(`member stage ${sequence} ${status} renders ${mapped ? 'Diproses' : label} without mutating raw props`, async () => {
            const preorder = preorderFor([[sequence, status, label]]);
            const snapshot = structuredClone(preorder);
            const panels = statusPanels(await render(memberPath, preorder));
            assert.deepEqual(panels, [{ label: mapped ? 'Diproses' : label, classes: mapped ? blue : classes }]);
            assert.deepEqual(preorder, snapshot);
        });
    }
}

test('a full member chain keeps stage one and two actual statuses while stage three is processing', async () => {
    const preorder = preorderFor([[1, 'received', 'Siap Diterima'], [2, 'shipped', 'Dikirim'], [3, 'received', 'Siap Diterima']]);
    assert.deepEqual(statusPanels(await render(memberPath, preorder)), [
        { label: 'Siap Diterima', classes: green },
        { label: 'Dikirim', classes: blue },
        { label: 'Diproses', classes: blue },
    ]);
});

for (const [status, label, color] of [['shipped', 'Dikirim', 'info'], ['received', 'Siap Diterima', 'success']]) {
    test(`admin stage three still renders the real ${status} status and badge color`, async () => {
        const preorder = preorderFor([[3, status, label]]);
        const html = (await render(adminPath, preorder)).replace(/<!--.*?-->/g, '');
        assert.match(html, new RegExp(`data-color="${color}"[^>]*>${label}<`));
        assert.doesNotMatch(html, /Diproses/);
        assert.equal(preorder.chain[0].transaction.status, status);
    });
}

for (const preorder of [null, undefined]) {
    test(`member PO card remains hidden for ${preorder}`, async () => {
        const html = await render(memberPath, preorder);
        assert.doesNotMatch(html, /Alur Pesanan PO|Tahap|Status/);
    });
}
