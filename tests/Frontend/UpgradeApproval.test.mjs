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
const source = path => readFileSync(new URL(`../../resources/js/${path}`, import.meta.url), 'utf8');

test('terminal upgrade states have Indonesian labels', () => {
    const exports = {};
    new Function('exports', ts.transpileModule(source('shared/composables/useBadge.ts'), {
        compilerOptions: { module: ts.ModuleKind.CommonJS },
    }).outputText)(exports);
    const badge = exports.useBadge();
    assert.equal(badge.getBadgeData('upgrade_status', 'applied').label, 'Diterapkan');
    assert.equal(badge.getBadgeData('upgrade_status', 'cancelled').label, 'Dibatalkan');
});

test('registration badges show verification wording without changing states or colors', () => {
    const exports = {};
    new Function('exports', ts.transpileModule(source('shared/composables/useBadge.ts'), {
        compilerOptions: { module: ts.ModuleKind.CommonJS },
    }).outputText)(exports);
    const badge = exports.useBadge();
    assert.deepEqual(badge.getBadgeData('registration_status', 'requested'), { label: 'Menunggu Verifikasi', color: 'warning' });
    assert.deepEqual(badge.getBadgeData('registration_status', 'approved'), { label: 'Disetujui', color: 'success' });
    assert.deepEqual(badge.getBadgeData('registration_status', 'rejected'), { label: 'Ditolak', color: 'error' });
    assert.equal(badge.getBadgeData('upgrade_status', 'requested').label, 'Menunggu');
});

for (const status of ['requested', 'scheduled', 'approved', 'rejected', 'applied', 'cancelled']) {
    test(`upgrade ${status} only exposes review actions when requested`, async () => {
        const { descriptor } = parse(source('admin/pages/partnership/member-upgrades.vue'));
        const render = new Function('Vue', compile(descriptor.template.content, { mode: 'function', prefixIdentifiers: true }).code)(Vue);
        const context = { headers: [], search: '', formatDateTime: String, openDetail: () => {}, openActionDialog: () => {}, refreshTable: () => {} };
        const app = Vue.createSSRApp({ render: () => render(context, []) });
        let url;
        app.component('BaseDataTable', { setup: (_, { attrs, slots }) => () => {
            url = attrs.url;
            return Vue.h('div', slots['item.actions']({ item: { id: 1, member: { name: 'Mitra' }, status: { code: status } } }));
        } });
        app.component('v-btn', { setup: (_, { attrs }) => () => Vue.h('button', { 'data-icon': attrs.icon }) });
        app.component('UpgradeDetailDialog', { render: () => null });
        app.component('BaseBadge', { render: () => null });
        app.component('ApprovalActionDialog', { render: () => null });
        app.directive('tooltip', {});
        const html = await renderToString(app);
        assert.equal(html.includes('mdi-check-circle-outline'), status === 'requested');
        assert.equal(html.includes('mdi-close-circle-outline'), status === 'requested');
        assert.equal(new URL(url, 'https://example.test').searchParams.get('filter[status]'), 'requested');
    });
}
