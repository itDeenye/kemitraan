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
const page = name => new URL(`../../resources/js/member/pages/rewards/${name}.vue`, import.meta.url);
const formatter = {
    formatPrice: value => Number(value ?? 0).toLocaleString('id-ID'),
    formatDateTime: value => String(value ?? ''),
    getMonthName: value => `Bulan ${value}`,
};

function setup(name, dependencies = {}) {
    const { descriptor } = parse(readFileSync(page(name), 'utf8'));
    const source = ts.createSourceFile(name, descriptor.scriptSetup.content, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    const statements = source.statements.filter(statement => !ts.isImportDeclaration(statement));
    const bindings = statements.flatMap(statement => ts.isVariableStatement(statement)
        ? statement.declarationList.declarations.flatMap(declaration => ts.isIdentifier(declaration.name)
            ? [declaration.name.text] : declaration.name.elements.map(element => element.name.getText(source)))
        : ts.isFunctionDeclaration(statement) ? [statement.name.text] : []);
    const code = ts.transpileModule(statements.map(statement => statement.getText(source)).join('\n'), {
        compilerOptions: { target: ts.ScriptTarget.ESNext, module: ts.ModuleKind.CommonJS },
    }).outputText;
    const injected = {
        ref: Vue.ref, computed: Vue.computed, onMounted: () => {},
        useRouter: () => ({ push: () => {} }), useRoute: () => ({ query: { year: '2026', month: '10' } }),
        useSnackbarStore: () => ({ showMessage: () => {} }), useFormatter: () => formatter,
        encodeRouteId: value => String(value),
        ...dependencies,
    };
    return new Function(...Object.keys(injected), `${code}\nreturn { ${bindings.join(',')} };`)(...Object.values(injected));
}

async function render(name, context) {
    const { descriptor } = parse(readFileSync(page(name), 'utf8'));
    const renderTemplate = new Function('Vue', compile(descriptor.template.content, { mode: 'function', prefixIdentifiers: true }).code)(Vue);
    const app = Vue.createSSRApp({ render: () => renderTemplate(Vue.proxyRefs({ ...formatter, encodeRouteId: value => String(value), ...context }), []) });
    for (const tag of new Set([...descriptor.template.content.matchAll(/<(v-[\w-]*|router-link)/g)].map(match => match[1]))) {
        app.component(tag, { setup: (_, { slots }) => () => Vue.h('div', [slots.prepend?.(), slots.default?.(), slots.append?.()]) });
    }
    return (await renderToString(app)).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
}

function growth(access) {
    return {
        period: { year: 2026, month: 9, range_label: 'Januari–September 2026' },
        summary: { year: 2026, month: 10, member_level: { name: 'Distributor' }, total_points: 21, minimum_points: 50, remaining_points: 29 },
        series: [], growth: { percent: 0, status: 'flat', label: 'Stabil' }, reward_access: access,
    };
}

for (const [name, access, voucher, sharing] of [
    ['ordinary member', { stockist_voucher: false, sharing_profit: false }, false, false],
    ['stockist without distributor downline', { stockist_voucher: true, sharing_profit: false }, true, false],
    ['eligible distributor stockist', { stockist_voucher: true, sharing_profit: true }, true, true],
]) {
    test(`reward home renders only allowed quick links for ${name}`, async () => {
        const home = setup('index');
        home.growthData.value = growth(access);
        home.isLoading.value = false;
        const text = await render('index', home);
        assert.equal(text.includes('Voucher'), voucher);
        assert.equal(text.includes('Sharing Profit'), sharing);
        assert.match(text, /Bulanan/);
        assert.match(text, /Tahunan/);
        assert.match(text, /Riwayat Poin/);
    });
    test(`all-menu page uses the same reward eligibility for ${name}`, async () => {
        const menus = setup('../menus/index', { useAuthStore: () => ({
            user: { member: { level: { code: 'DST', name: 'Distributor' }, reward_access: access } },
        }) });
        const text = await render('../menus/index', menus);
        assert.equal(text.includes('Reward Stokis'), voucher);
        assert.equal(text.includes('Sharing Profit'), sharing);
    });
}

test('point history link follows the realtime summary period instead of the chart period', () => {
    const navigations = [];
    const home = setup('index', { useRouter: () => ({ push: route => navigations.push(route) }) });
    home.growthData.value = growth({ stockist_voucher: false, sharing_profit: false });
    home.showPointHistory();
    assert.deepEqual(navigations[0], { path: '/member/rewards/points', query: { year: 2026, month: 10 } });
});

const item = id => ({ id, transaction: { id, code: `TRX-${id}`, is_preorder: false }, monthly_points: id === 1 ? 0 : 6, annual_points: id === 1 ? 2 : 9, approved_at: '2026-10-16T08:00:00+07:00' });
const response = (items, current = 1) => ({
    success: true, data: { results: items, pagination: { total_data: 2, total_page: 2, current } },
    summary: { monthly_points: 6, annual_points: 11, transaction_count: 2 }, available_years: [2026, 2025],
});

test('point history filters by period, paginates without duplication, and renders both recorded point values', async () => {
    const calls = [];
    const history = setup('points', { rewardService: { getPointHistory: async params => {
        calls.push(params);
        return params.page === 1 ? response([item(1)]) : response([item(1), item(2)], 2);
    } } });
    await history.fetchHistory();
    await history.fetchHistory(true);
    assert.deepEqual(calls.map(({ page, year, month }) => ({ page, year, month })), [
        { page: 1, year: 2026, month: 10 }, { page: 2, year: 2026, month: 10 },
    ]);
    assert.deepEqual(history.items.value.map(row => row.id), [1, 2]);
    const text = await render('points', history);
    assert.match(text, /Poin Bulanan\s+6/);
    assert.match(text, /Poin Tahunan\s+11/);
    assert.match(text, /TRX-1/);
    assert.match(text, /Bulanan\s+0/);
    assert.match(text, /Tahunan\s+2/);
});

test('a failed load-more keeps its page so retry cannot skip points', async () => {
    const history = setup('points', { rewardService: { getPointHistory: async params => {
        if (params.page === 2) throw new Error('Unavailable');
        return response([item(1)]);
    } } });
    await history.fetchHistory();
    await history.fetchHistory(true);
    assert.equal(history.currentPage.value, 1);
    assert.deepEqual(history.items.value.map(row => row.id), [1]);
});

test('a failed period change clears the previous pagination instead of offering page two of the new period', async () => {
    const history = setup('points', { rewardService: { getPointHistory: async params => {
        if (params.month === 9) throw new Error('Unavailable');
        return response([item(1)]);
    } } });
    await history.fetchHistory();
    history.selectedMonth.value = 9;
    await history.fetchHistory();
    assert.deepEqual(history.items.value, []);
    assert.equal(history.currentPage.value, 0);
    assert.equal(history.totalPages.value, 0);
});

test('late history responses cannot overwrite the newly selected period', async () => {
    const pending = [];
    const history = setup('points', { rewardService: { getPointHistory: params => new Promise(resolve => pending.push({ params, resolve })) } });
    const oldRequest = history.fetchHistory();
    history.selectedMonth.value = 9;
    const newRequest = history.fetchHistory();
    pending[1].resolve(response([item(2)]));
    await newRequest;
    pending[0].resolve(response([item(1)]));
    await oldRequest;
    assert.equal(history.selectedMonth.value, 9);
    assert.deepEqual(history.items.value.map(row => row.id), [2]);
});
