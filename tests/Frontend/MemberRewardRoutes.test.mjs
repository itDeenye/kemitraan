import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import ts from 'typescript';

function routeGuard(authStore) {
    const source = ts.createSourceFile('router.ts', readFileSync(new URL('../../resources/js/member/router/index.ts', import.meta.url), 'utf8'), ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    const code = ts.transpileModule(source.statements.filter(statement => !ts.isImportDeclaration(statement) && !ts.isExportAssignment(statement)).map(statement => statement.getText(source)).join('\n'), {
        compilerOptions: { target: ts.ScriptTarget.ESNext, module: ts.ModuleKind.CommonJS },
    }).outputText;
    let guard;
    const injected = {
        createRouter: () => ({ beforeEach: callback => { guard = callback; } }),
        createWebHistory: () => ({}), autoRoutes: [],
        useAuthStore: () => authStore, getOpaqueRouteRedirect: () => null,
        document: {}, sessionStorage: { setItem: () => {} },
    };
    new Function(...Object.keys(injected), code)(...Object.values(injected));
    return guard;
}

for (const [path, key] of [
    ['/member/rewards/stockist', 'stockist_voucher'],
    ['/member/rewards/stockist/detail-id', 'stockist_voucher'],
    ['/member/rewards/sharing', 'sharing_profit'],
    ['/member/rewards/sharing/detail-id', 'sharing_profit'],
]) {
    test(`direct ${path} navigation rechecks current eligibility and denies stale access`, async () => {
        let refreshed = false;
        let destination;
        const authStore = { token: 'existing-session', sidebarMenus: [], user: { member: { reward_access: { [key]: true } } } };
        authStore.fetchUser = async () => {
            refreshed = true;
            authStore.user.member.reward_access[key] = false;
            return true;
        };
        await routeGuard(authStore)({ path, fullPath: path, meta: {} }, {}, value => { destination = value; });
        assert.equal(refreshed, true);
        assert.deepEqual(destination, { path: '/member/rewards' });
    });

    test(`eligible members can open ${path}`, async () => {
        let destination = 'not-called';
        const authStore = { token: 'existing-session', sidebarMenus: [], user: { member: { reward_access: { [key]: true } } }, fetchUser: async () => true };
        await routeGuard(authStore)({ path, fullPath: path, meta: {} }, {}, value => { destination = value; });
        assert.equal(destination, undefined);
    });
}

test('all member levels can visit point history without stockist reward access', async () => {
    let destination = 'not-called';
    const authStore = { token: 'existing-session', sidebarMenus: [], user: { member: {} }, fetchUser: async () => { throw new Error('Unneeded refresh'); } };
    await routeGuard(authStore)({ path: '/member/rewards/points', fullPath: '/member/rewards/points', meta: {} }, {}, value => { destination = value; });
    assert.equal(destination, undefined);
});
