import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { parse } from '@vue/compiler-sfc';
import ts from 'typescript';
import * as Vue from 'vue';

function setup(referenceOverrides = {}) {
    const { descriptor } = parse(readFileSync(new URL('../../resources/js/admin/components/partnership/DistributorForm.vue', import.meta.url), 'utf8'));
    const source = ts.createSourceFile('form.ts', descriptor.scriptSetup.content, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    const statements = source.statements.filter(statement => !ts.isImportDeclaration(statement));
    const bindings = statements.flatMap(statement => ts.isVariableStatement(statement)
        ? statement.declarationList.declarations.flatMap(declaration => ts.isIdentifier(declaration.name)
            ? [declaration.name.text] : declaration.name.elements.map(element => element.name.getText(source)))
        : ts.isFunctionDeclaration(statement) ? [statement.name.text] : []);
    const code = ts.transpileModule(statements.map(statement => statement.getText(source)).join('\n'), {
        compilerOptions: { target: ts.ScriptTarget.ESNext, module: ts.ModuleKind.CommonJS },
    }).outputText;
    const regionCode = ts.transpileModule(readFileSync(new URL('../../resources/js/shared/composables/useRegionCascade.ts', import.meta.url), 'utf8')
        .replace(/^import .*;\r?\n/gm, ''), { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText;
    const regionExports = {};
    new Function('exports', 'ref', 'referenceService', regionCode)(regionExports, Vue.ref, {
        getProvinces: async () => [{ id: '35', name: 'Jawa Timur' }, { id: '32', name: 'Jawa Barat' }],
        getCities: async id => [{ id: `${id}01`, name: 'Kota' }],
        getDistricts: async id => [{ id: `${id}01`, name: 'Kecamatan' }],
        getSubdistricts: async id => [{ id: `${id}0001`, name: 'Kelurahan' }],
        ...referenceOverrides,
    });
    const dependencies = { ref: Vue.ref, computed: Vue.computed, reactive: Vue.reactive, watch: Vue.watch,
        defineEmits: () => () => {}, defineExpose: () => {}, useSnackbarStore: () => ({ showMessage: () => {} }),
        api: {}, useRegionCascade: regionExports.useRegionCascade,
        memberIdentityOptions: () => [], identityNumberSettings: () => ({}), identityNumberRule: () => true };
    const scope = Vue.effectScope();
    const context = scope.run(() => new Function(...Object.keys(dependencies), `${code}\nreturn {${bindings.join(',')}};`)(...Object.values(dependencies)));
    return { context, stop: () => scope.stop() };
}

test('same-as-KTP copies text and region and follows later KTP changes', async () => {
    const { context: c, stop } = setup();
    try {
        assert.equal(c.domicileSameAsKtp?.value, true);
        Object.assign(c.form, { address: 'Jl. KTP', province_id: '35', city_id: '3501', district_id: '350101', subdistrict_id: '3501010001' });
        await Vue.nextTick();
        assert.equal(c.form.domicile_address, 'Jl. KTP');
        assert.equal(c.form.domicile_province_id, '35');
        assert.equal(c.form.domicile_subdistrict_id, '3501010001');
        c.form.address = 'Alamat KTP baru';
        await Vue.nextTick();
        assert.equal(c.form.domicile_address, 'Alamat KTP baru');
    } finally { stop(); }
});

function deferred() {
    let resolve;
    const promise = new Promise(done => { resolve = done; });
    return { promise, resolve };
}

const flush = async () => {
    await Vue.nextTick();
    await new Promise(resolve => setImmediate(resolve));
};

test('latest domicile province wins when city responses arrive in reverse order', async () => {
    const older = deferred();
    const newer = deferred();
    const { context: c, stop } = setup({ getCities: id => id === '32' ? older.promise : newer.promise });
    try {
        c.domicileSameAsKtp.value = false;
        await flush();
        c.form.domicile_province_id = '32';
        const first = c.onDomicileProvinceChange('32');
        c.form.domicile_province_id = '35';
        const second = c.onDomicileProvinceChange('35');
        newer.resolve([{ id: '3501' }]);
        await second;
        older.resolve([{ id: '3201' }]);
        await first;
        assert.equal(c.form.domicile_province_id, '35');
        assert.deepEqual(c.domicileCities.value.map(city => city.id), ['3501']);
    } finally { stop(); }
});

test('rechecking same-as-KTP invalidates pending domicile initialization', async () => {
    const pending = deferred();
    let districtCalls = 0;
    const { context: c, stop } = setup({ getCities: () => pending.promise,
        getDistricts: async () => { districtCalls++; return [{ id: '350101' }]; } });
    try {
        Object.assign(c.form, { province_id: '35', city_id: '3501', district_id: '350101', subdistrict_id: '3501010001' });
        await flush();
        c.domicileSameAsKtp.value = false;
        await flush();
        c.cities.value = [{ id: '3201' }];
        Object.assign(c.form, { province_id: '32', city_id: '3201', district_id: '320101', subdistrict_id: '3201010001' });
        c.domicileSameAsKtp.value = true;
        await flush();
        pending.resolve([{ id: '3501' }]);
        await flush();
        assert.deepEqual(c.domicileCities.value.map(city => city.id), ['3201']);
        assert.equal(c.form.domicile_city_id, '3201');
        assert.equal(districtCalls, 0, 'invalidated initialization must not fetch old downstream regions');
    } finally { stop(); }
});

test('close and reopen invalidate old requests and clear stale choices', async () => {
    const pending = deferred();
    const { context: c, stop } = setup({ getCities: () => pending.promise });
    try {
        c.domicileSameAsKtp.value = false;
        await flush();
        c.form.domicile_province_id = '32';
        const request = c.onDomicileProvinceChange('32');
        c.close();
        await c.open();
        pending.resolve([{ id: '3201' }]);
        await request;
        await flush();
        assert.equal(c.form.domicile_province_id, null);
        assert.deepEqual(c.domicileCities.value, []);
        assert.equal(c.loadingDomicileCities.value, false);
    } finally { stop(); }
});

test('unchecked domicile cascade stays independent from KTP and rechecking copies back', async () => {
    const { context: c, stop } = setup();
    try {
        assert.ok(c.domicileSameAsKtp, 'same-as-KTP checkbox state exists');
        c.domicileSameAsKtp.value = false;
        await Vue.nextTick();
        Object.assign(c.form, { address: 'Jl. KTP', province_id: '35', city_id: '3501', district_id: '350101', subdistrict_id: '3501010001',
            domicile_address: 'Jl. Domisili', domicile_province_id: '32', domicile_city_id: '3201', domicile_district_id: '320101', domicile_subdistrict_id: '3201010001' });
        await c.onDomicileProvinceChange('32');
        assert.equal(c.form.city_id, '3501');
        assert.equal(c.form.domicile_city_id, null);
        assert.equal(c.domicileCities.value[0].id, '3201');
        c.form.address = 'KTP berubah';
        await Vue.nextTick();
        assert.equal(c.form.domicile_address, 'Jl. Domisili');
        c.domicileSameAsKtp.value = true;
        await Vue.nextTick();
        assert.equal(c.form.domicile_address, 'KTP berubah');
        assert.equal(c.form.domicile_province_id, '35');
    } finally { stop(); }
});
