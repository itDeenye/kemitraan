import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import { parse } from '@vue/compiler-sfc';
import ts from 'typescript';

const require = createRequire(import.meta.url);
const Vue = require('vue');
const source = path => readFileSync(new URL(`../../resources/js/${path}`, import.meta.url), 'utf8');
const refreshEvent = 'admin:action-summary-refresh';
const transpile = code => ts.transpileModule(code, {
    compilerOptions: { target: ts.ScriptTarget.ESNext, module: ts.ModuleKind.CommonJS },
}).outputText;
const withoutImports = code => {
    const file = ts.createSourceFile('component.ts', code, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    return file.statements.filter(item => !ts.isImportDeclaration(item)).map(item => item.getText(file)).join('\n');
};
const deferred = () => {
    let resolve, reject;
    const promise = new Promise((res, rej) => { resolve = res; reject = rej; });
    return { promise, resolve, reject };
};
const flush = () => new Promise(resolve => setImmediate(resolve));
const summary = count => ({ data: { menus: [{ route: '/admin/transactions/payment-verification', count }] } });

function sidebar() {
    const script = parse(source('admin/components/AdminSidebar.vue')).descriptor.scriptSetup.content;
    const requests = [], mounts = [], unmounts = [], errors = [];
    const timers = new Map(), intervals = new Map();
    let nextTimer = 0;
    const window = new EventTarget();
    const route = Vue.reactive({ path: '/admin/transactions/payment-verification' });
    const menu = [{ id: 1, title: 'Transaksi', icon: '', badge: 99, children: [
        { id: 2, title: 'Pembayaran', icon: '', to: route.path, badge: 99 },
    ] }];
    const dependencies = {
        computed: Vue.computed, ref: Vue.ref, watch: Vue.watch,
        onMounted: callback => mounts.push(callback), onUnmounted: callback => unmounts.push(callback),
        defineModel: () => Vue.ref(false), useRoute: () => route,
        useAuthStore: () => ({ sidebarMenus: menu }),
        AdminActionSummaryService: { get: () => {
            const request = deferred(); requests.push(request); return request.promise;
        } },
        ADMIN_ACTION_SUMMARY_REFRESH_EVENT: refreshEvent,
        window, console: { error: (...args) => errors.push(args) },
        setInterval: callback => { const id = ++nextTimer; intervals.set(id, callback); return id; },
        clearInterval: id => intervals.delete(id),
        setTimeout: callback => { const id = ++nextTimer; timers.set(id, callback); return id; },
        clearTimeout: id => timers.delete(id),
    };
    const component = new Function(...Object.keys(dependencies), `${transpile(withoutImports(script))}\nreturn { menuItems, loadActionSummary };`)(...Object.values(dependencies));
    mounts.forEach(callback => callback());
    return {
        ...component, requests, errors, window, route, timers, intervals,
        runTimers: () => { const callbacks = [...timers.values()]; timers.clear(); callbacks.forEach(callback => callback()); },
        unmount: () => unmounts.forEach(callback => callback()),
    };
}

test('payment page uses the same actionable filters as its sidebar badge', () => {
    const script = parse(source('admin/pages/transactions/payment-verification.vue')).descriptor.scriptSetup.content;
    const file = ts.createSourceFile('page.ts', script, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    const declaration = file.statements.find(item => ts.isVariableStatement(item)
        && item.declarationList.declarations.some(item => item.name.getText(file) === 'queryParams'));
    const params = new Function('computed', `${transpile(declaration.getText(file))}\nreturn queryParams.value;`)(Vue.computed);
    assert.deepEqual(params, {
        'filter[buyer.type]': 'distributor', 'filter[seller.type]': 'warehouse',
        'filter[status]': 'submitted', 'filter[transaction.status][eq]': 'waiting_payment_approval',
    });
});

test('successful admin mutations notify badge refresh through the real API interceptor', () => {
    const window = new EventTarget();
    window.location = { pathname: '/admin/transactions/payment-verification' };
    const exports = {};
    new Function('require', 'exports', 'window', 'localStorage', transpile(source('shared/services/api.ts')))(
        require, exports, window, { getItem: () => null },
    );
    const interceptor = exports.default.interceptors.response.handlers[0].fulfilled;
    let notifications = 0;
    window.addEventListener(refreshEvent, () => notifications++);
    const response = (method, url, data = { success: true }, status = 200) => ({ config: { method, url }, data, status });
    for (const [method, url] of [
        ['post', '/admin/transactions/payments/1/approve'],
        ['post', '/admin/inventory/shipments/1/ship'],
        ['patch', '/admin/partnership/upgrades/1'],
        ['put', '/admin/rewards/sharing-profits/1'],
        ['delete', '/admin/partnership/registrations/1'],
    ]) {
        const item = response(method, url);
        assert.equal(interceptor(item), item);
    }
    assert.equal(notifications, 5);
    for (const item of [
        response('get', '/admin/dashboard/action-summary'),
        response('post', '/admin/auth/refresh'),
        response('post', '/member/transactions/orders/1/ship'),
        response('post', '/admin/inventory/shipments/1/couriers'),
        response('post', '/admin/transactions/payments/1/approve', { success: false }),
        response('post', '/admin/transactions/payments/1/approve', {}, 422),
    ]) interceptor(item);
    assert.equal(notifications, 5);
    window.location.pathname = '/member/transactions';
    interceptor(response('post', '/admin/transactions/payments/1/approve'));
    assert.equal(notifications, 5);
});

test('mutation refresh is debounced and clears child and parent badges at zero', async () => {
    const page = sidebar();
    page.requests[0].resolve(summary(4));
    await flush();
    assert.equal(page.menuItems.value[0].badge, 4);
    assert.equal(page.menuItems.value[0].children[0].badge, 4);
    for (let index = 0; index < 3; index++) page.window.dispatchEvent(new Event(refreshEvent));
    assert.equal(page.timers.size, 1);
    page.runTimers();
    assert.equal(page.requests.length, 2);
    page.requests[1].resolve(summary(0));
    await flush();
    assert.equal(page.menuItems.value[0].badge, undefined);
    assert.equal(page.menuItems.value[0].children[0].badge, undefined);
    page.unmount();
});

test('older summary responses cannot overwrite a newer mutation refresh', async () => {
    const page = sidebar();
    page.window.dispatchEvent(new Event(refreshEvent));
    page.runTimers();
    page.requests[1].resolve(summary(1));
    await flush();
    page.requests[0].resolve(summary(9));
    await flush();
    assert.equal(page.menuItems.value[0].badge, 1);
    page.unmount();
});

test('current failures preserve badges and periodic polling can recover', async () => {
    const page = sidebar();
    page.requests[0].resolve(summary(4));
    await flush();
    page.window.dispatchEvent(new Event(refreshEvent));
    page.runTimers();
    page.requests[1].reject(new Error('offline'));
    await flush();
    assert.equal(page.menuItems.value[0].badge, 4);
    assert.equal(page.errors.length, 1);
    [...page.intervals.values()][0]();
    page.requests[2].resolve(summary(2));
    await flush();
    assert.equal(page.menuItems.value[0].badge, 2);
    page.unmount();
});

test('unmount removes listeners, timers and ignores in-flight responses', async () => {
    const page = sidebar();
    page.window.dispatchEvent(new Event(refreshEvent));
    page.unmount();
    assert.equal(page.timers.size, 0);
    assert.equal(page.intervals.size, 0);
    page.window.dispatchEvent(new Event(refreshEvent));
    assert.equal(page.timers.size, 0);
    page.requests[0].resolve(summary(9));
    await flush();
    assert.notEqual(page.menuItems.value[0].badge, 9);
});
