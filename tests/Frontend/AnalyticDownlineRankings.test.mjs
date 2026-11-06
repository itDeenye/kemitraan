import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import { compileScript, parse } from '@vue/compiler-sfc';
import ts from 'typescript';

const require = createRequire(import.meta.url);
const Vue = require('vue');
const { renderToString } = createRequire(require.resolve('vue'))('@vue/server-renderer');
const filename = new URL('../../resources/js/admin/components/user-analytic/AnalyticMemberRankings.vue', import.meta.url);
const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename: filename.pathname });
const script = compileScript(descriptor, { id: 'downline-rankings', inlineTemplate: true }).content;
const code = ts.transpileModule(script, {
    compilerOptions: { target: ts.ScriptTarget.ESNext, module: ts.ModuleKind.CommonJS },
}).outputText;
const componentModule = { exports: {} };
new Function('require', 'module', 'exports', code)(name => name === '@/shared/composables/useFormatter'
    ? { useFormatter: () => ({ formatPrice: value => new Intl.NumberFormat('id-ID').format(value) }) }
    : require(name), componentModule, componentModule.exports);

async function renderRankings(recruitment) {
    const app = Vue.createSSRApp(componentModule.exports.default, {
        sales: { distributors: [], agents: [], resellers: [] }, recruitment,
    });
    const wrapper = { setup: (_, { slots }) => () => Vue.h('div', [slots.prepend?.(), slots.default?.(), slots.append?.()].flat()) };
    for (const tag of ['v-row', 'v-col', 'v-card', 'v-icon', 'v-list', 'v-list-item', 'v-avatar', 'v-list-item-title', 'v-list-item-subtitle']) {
        app.component(tag, wrapper);
    }
    return (await renderToString(app)).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
}

test('analytics identifies both filtered network rankings as downlines and renders the API counts', async () => {
    const text = await renderRankings({
        distributors: [{ rank: 1, id: 1, code: 'DST-001', name: 'Distributor Satu', total_recruits: 12 }],
        agents: [{ rank: 1, id: 2, code: 'AGT-001', name: 'Agen Satu', total_recruits: 10 }],
    });
    assert.match(text, /Top Downline Distributor.*Distributor Satu.*DST-001.*12 mitra/);
    assert.match(text, /Top Downline Agen Utama.*Agen Satu.*AGT-001.*10 mitra/);
    assert.doesNotMatch(text, /Top Recruitment/);
});

test('an empty filtered network still shows both downline cards and their empty states', async () => {
    const text = await renderRankings({ distributors: [], agents: [] });
    assert.match(text, /Top Downline Distributor.*Belum ada data pada periode ini/);
    assert.match(text, /Top Downline Agen Utama.*Belum ada data pada periode ini/);
});
