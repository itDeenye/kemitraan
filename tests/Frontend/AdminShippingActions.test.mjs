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
const { descriptor } = parse(readFileSync(new URL('../../resources/js/admin/pages/inventory/Shipping.vue', import.meta.url), 'utf8'));
const render = new Function('Vue', compile(descriptor.template.content, { mode: 'function', prefixIdentifiers: true }).code)(Vue);

for (const [status, canShip, canReship] of [
    ['processing', true, false], ['processing', false, false],
    ['reship_required', true, true], ['reship_required', false, false],
    ['shipped', false, false], ['ready_to_pickup', false, false],
    ['received', false, false], ['completed', false, false],
]) {
    test(`shipping ${status}: process buttons follow server actions ${canShip}/${canReship}`, async () => {
        const item = { id: 1, status, shipping_method: 'courier_express', actions: {
            can_ship: canShip, can_reship: canReship, can_track: true,
        } };
        const context = {
            headers: [], queryParams: {}, search: '', simulatingId: null,
            shippingActionTooltip: () => '', shippingActionColor: () => 'primary',
        };
        const app = Vue.createSSRApp({ render: () => render(context, []) });
        app.component('BaseDataTable', { setup: (_, { slots }) => () => Vue.h('div', slots['item.actions']({ item })) });
        app.component('v-btn', { setup: (_, { attrs }) => () => Vue.h('button', { 'data-icon': attrs.icon }) });
        app.component('v-menu', { setup: (_, { slots }) => () => slots.activator({ props: {} }) });
        for (const name of ['ShippingDetail', 'ShippingTrackingDialog', 'ShippingActionDialog', 'ShippingReshipDialog', 'v-chip', 'BaseBadge', 'v-list', 'v-list-item']) {
            app.component(name, { render: () => null });
        }
        app.directive('tooltip', {});
        const html = await renderToString(app);
        assert.equal(html.includes('mdi-truck-fast'), status === 'processing' && canShip);
        assert.equal(html.includes('mdi-truck-cargo-container'), canReship);
        assert.ok(html.includes('mdi-eye-outline'));
        assert.ok(html.includes('mdi-printer'));
        assert.ok(html.includes('mdi-map-marker-path'));
    });
}

test('shipping page uses the same company/distributor scope as its badge', () => {
    const code = descriptor.scriptSetup.content;
    const file = ts.createSourceFile('page.ts', code, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    const declaration = file.statements.find(item => ts.isVariableStatement(item)
        && item.declarationList.declarations.some(item => item.name.getText(file) === 'queryParams'));
    const script = ts.transpileModule(declaration.getText(file), {
        compilerOptions: { target: ts.ScriptTarget.ESNext, module: ts.ModuleKind.CommonJS },
    }).outputText;
    const filters = new Function('computed', `${script}\nreturn queryParams.value;`)(Vue.computed);
    assert.deepEqual(filters, { 'filter[buyer.type]': 'distributor', 'filter[seller.type]': 'warehouse' });
});
