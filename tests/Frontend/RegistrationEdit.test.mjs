import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import { compile, baseParse } from '@vue/compiler-dom';
import { parse } from '@vue/compiler-sfc';
import ts from 'typescript';

const require = createRequire(import.meta.url);
const Vue = require('vue');
const { renderToString } = createRequire(require.resolve('vue'))('@vue/server-renderer');
const path = 'admin/components/partnership/RegistrationEditDialog.vue';
const sourceFile = path => new URL(`../../resources/js/${path}`, import.meta.url);
const deferred = () => {
    let resolve;
    const promise = new Promise(done => { resolve = done; });
    return { promise, resolve };
};
const registration = (id = 7) => ({
    id, can_edit: true, status: { code: 'requested' },
    level: { id: 3, name: 'Reseller' }, sponsor: { id: 1, code: '0001/0001/0000', name: 'Agen' },
    applicant: { name: `Applicant ${id}`, mobile_phone: '+6281234567890', email: 'applicant@example.test',
        gender: 'Perempuan', birth_date: '1990-01-01' },
    identity: { type: 'SIM', number: '001234567890', image_url: 'https://example.test/id.jpg', nib: '123', npwp: '456' },
    image_url: 'https://example.test/profile.jpg', payment_proof_image_url: 'https://example.test/proof.jpg',
    address: { address: 'Jalan Lama', province_id: '35', city_id: '3501', district_id: '350101', subdistrict_id: 9, country_id: 1 },
    bank_accounts: [{ bank_id: 1, account_name: 'Applicant', account_number: '001234', city: 'Malang', branch: 'Utama', is_active: true, is_default: true }],
});

function setup(component = path, overrides = {}) {
    const { descriptor } = parse(readFileSync(sourceFile(component), 'utf8'));
    const source = ts.createSourceFile(component, descriptor.scriptSetup.content, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    const statements = source.statements.filter(statement => !ts.isImportDeclaration(statement));
    const bindings = statements.flatMap(statement => ts.isVariableStatement(statement)
        ? statement.declarationList.declarations.flatMap(declaration => ts.isIdentifier(declaration.name)
            ? [declaration.name.text] : declaration.name.elements.map(element => element.name.getText(source)))
        : ts.isFunctionDeclaration(statement) ? [statement.name.text] : []);
    const code = ts.transpileModule(statements.map(statement => statement.getText(source)).join('\n'), {
        compilerOptions: { target: ts.ScriptTarget.ESNext, module: ts.ModuleKind.CommonJS },
    }).outputText;
    const identity = {};
    new Function('exports', ts.transpileModule(readFileSync(sourceFile('shared/utils/member-identity.ts'), 'utf8'), {
        compilerOptions: { module: ts.ModuleKind.CommonJS },
    }).outputText)(identity);
    const calls = { saved: [], messages: [], events: [], regions: [] };
    const dependencies = {
        ...identity, ref: Vue.ref, reactive: Vue.reactive, computed: Vue.computed, nextTick: Vue.nextTick,
        onBeforeUnmount: () => {}, defineExpose: () => {}, defineEmits: () => (...args) => calls.events.push(args),
        useSnackbarStore: () => ({ showMessage: (...args) => calls.messages.push(args) }),
        useFormatter: () => ({ formatDateTime: String }), useBadge: () => ({ getBadgeOptions: () => [] }),
        referenceService: { getBanks: async () => [{ id: 1, name: 'Bank' }] },
        useRegionCascade: () => ({
            ...Object.fromEntries(['provinces', 'cities', 'districts', 'subdistricts'].map(key => [key, Vue.ref([])])),
            ...Object.fromEntries(['loadingProvinces', 'loadingCities', 'loadingDistricts', 'loadingSubdistricts'].map(key => [key, Vue.ref(false)])),
            fetchProvinces: async () => {}, reset: () => {},
            initFromExisting: async region => calls.regions.push({ province_id: region.province_id, city_id: region.city_id }),
            onProvinceChange: async () => {}, onCityChange: async () => {}, onDistrictChange: async () => {},
        }),
        api: {
            get: async () => ({ data: { success: true, data: registration() } }),
            patch: async (url, payload) => {
                calls.saved.push(structuredClone({ url, payload }));
                return { data: { success: true, message: 'Tersimpan' } };
            },
        }, ...overrides,
    };
    const originalGet = dependencies.api.get;
    dependencies.api.get = async url => url.endsWith('/options') ? { data: { success: true, data: {
        edit_levels: [{ id: 1, code: 'DST', name: 'Distributor' }, { id: 2, code: 'AGT', name: 'Agen Utama' }, { id: 3, code: 'RSL', name: 'Reseller' }],
        edit_uplines: [{ id: 1, label: 'Agen', level_code: 'AGT' }, { id: 2, label: 'Distributor', level_code: 'DST' }],
    } } } : originalGet(url);
    const context = new Function(...Object.keys(dependencies), `${code}\nreturn { ${bindings.join(',')} };`)(...Object.values(dependencies));
    return { context, descriptor, calls };
}

async function fields({ context, descriptor }, item, actionOnly = false) {
    const result = [];
    let template = descriptor.template.content;
    if (actionOnly) {
        const find = node => node.tag === 'template' && node.props?.some(prop => prop.type === 7 && prop.name === 'slot' && prop.arg?.content === 'item.actions')
            ? node : node.children?.map(find).find(Boolean);
        template = find(baseParse(template)).loc.source.replace(/^<template[^>]+>/, '<div>').replace(/<\/template>$/, '</div>');
    }
    const renderTemplate = new Function('Vue', compile(template, { mode: 'function', prefixIdentifiers: true }).code)(Vue);
    const app = Vue.createSSRApp({ render: () => renderTemplate(Vue.proxyRefs({ ...context, item }), []) });
    app.directive('tooltip', {});
    const tags = new Set([...descriptor.template.content.matchAll(/<([A-Z][\w-]*|v-[\w-]*)/g)].map(match => match[1]));
    for (const tag of tags) app.component(tag, {
        inheritAttrs: false,
        setup: (_, { attrs, slots }) => () => {
            result.push({ ...attrs, tag });
            if (tag === 'BaseDataTable') return Vue.h('div', null, slots['item.actions']?.({ item }));
            if (tag === 'v-menu') return Vue.h('div', null, [slots.activator?.({ props: {} }), slots.default?.()]);
            return Vue.h('div', null, slots.default?.());
        },
    });
    await renderToString(app);
    return result;
}

test('approval list offers edit only when requested and explicitly editable, then refreshes on save', async () => {
    const page = setup('admin/pages/partnership/registration-approvals.vue');
    for (const [status, canEdit, expected] of [['requested', true, 1], ['requested', false, 0], ['approved', true, 0], ['rejected', true, 0]]) {
        const rendered = await fields(page, { id: 7, applicant: { name: 'Applicant' }, status: { code: status }, can_edit: canEdit });
        const buttons = rendered.filter(field => field.icon === 'mdi-pencil-outline');
        assert.equal(buttons.length, expected);
        if (expected) {
            let opened;
            page.context.editRef.value = { open: id => { opened = id; } };
            buttons[0].onClick();
            assert.equal(opened, 7);
        }
        const dialog = rendered.find(field => field.tag === 'RegistrationEditDialog');
        let refreshed = 0;
        page.context.approvalTable.value = { refresh: () => refreshed++ };
        dialog.onUpdated();
        assert.equal(refreshed, 1);
    }
});

test('edit loads applicant, identity, documents, address and banks; only PATCHes editable fields', async () => {
    const page = setup();
    await page.context.open(7);
    assert.equal(page.context.form.value.mobile_phone, '+6281234567890');
    assert.equal(page.context.form.value.identity_no, '001234567890');
    assert.equal(page.calls.regions[0].city_id, '3501');
    const rendered = await fields(page);
    for (const label of ['Level', 'Upline']) assert.equal(rendered.find(field => field.label === label).readonly, undefined);
    const number = rendered.find(field => field.label === 'Nomor Identitas');
    assert.equal(number.rules.every(rule => rule('001234567890') === true), true);
    assert.equal(number.rules.every(rule => rule('123') === true), false);
    assert.deepEqual(rendered.filter(field => field.tag === 'MediaUpload').map(field => field.collection), ['profile', 'general', 'payment_proof']);
    page.context.form.value.name = 'Dikoreksi';
    page.context.formRef.value = { validate: async () => ({ valid: true }), resetValidation() {} };
    await page.context.submit();
    assert.equal(page.calls.saved.length, 1);
    const { url, payload } = page.calls.saved[0];
    assert.equal(url, '/admin/partnership/registrations/7');
    assert.equal(payload.name, 'Dikoreksi');
    assert.equal(payload.image_url, registration().image_url);
    assert.equal(payload.identity_image_url, registration().identity.image_url);
    assert.equal(payload.payment_proof_image_url, registration().payment_proof_image_url);
    assert.equal(payload.bank_accounts[0].account_number, '001234');
    assert.equal(payload.bank_accounts[0].branch, 'Utama');
    assert.equal(payload.level_id, 3);
    assert.equal(payload.upline_member_id, 1);
    for (const key of ['level', 'sponsor', 'status', 'username', 'customer_number', 'member_id']) assert.equal(key in payload, false);
    assert.deepEqual(page.calls.events, [['updated']]);
    assert.deepEqual(page.calls.messages.at(-1), ['Tersimpan', 'success']);
    assert.equal(page.context.dialog.value, false);
});

test('failed edit keeps corrections open and shows field errors without approving or syncing', async () => {
    const page = setup(undefined, { api: {
        get: async () => ({ data: { success: true, data: registration() } }),
        patch: async () => { throw { response: { data: { message: 'Nomor sudah digunakan.', errors: { mobile_phone: ['Nomor sudah digunakan.'] } } } }; },
    } });
    await page.context.open(7);
    page.context.form.value.mobile_phone = '+6289999999999';
    page.context.formRef.value = { validate: async () => ({ valid: true }) };
    await page.context.submit();
    assert.equal(page.context.dialog.value, true);
    assert.equal(page.context.form.value.mobile_phone, '+6289999999999');
    assert.deepEqual(page.context.errors.value.mobile_phone, ['Nomor sudah digunakan.']);
    assert.deepEqual(page.calls.events, []);
    assert.deepEqual(page.calls.messages.at(-1), ['Nomor sudah digunakan.', 'error']);
});

test('edit blocks save and close during uploads, and prevents duplicate saves', async () => {
    const pending = deferred();
    let saves = 0;
    const page = setup(undefined, { api: {
        get: async () => ({ data: { success: true, data: registration() } }),
        patch: async () => { saves++; return pending.promise; },
    } });
    await page.context.open(7);
    page.context.formRef.value = { validate: async () => ({ valid: true }) };
    page.context.mediaUploads.value.identity = true;
    await page.context.submit();
    page.context.close();
    assert.equal(saves, 0);
    assert.equal(page.context.dialog.value, true);
    page.context.mediaUploads.value.identity = false;
    const saving = page.context.submit();
    await new Promise(resolve => setImmediate(resolve));
    await page.context.submit();
    page.context.close();
    assert.equal(saves, 1);
    assert.equal(page.context.dialog.value, true);
    pending.resolve({ data: { success: true } });
    await saving;
    assert.equal(page.context.dialog.value, false);
});

test('late requests do not overwrite another registration, and processed applications cannot edit', async () => {
    const pending = deferred();
    const page = setup(undefined, { api: { get: async url => url.endsWith('/7')
        ? pending.promise : { data: { success: true, data: registration(8) } } } });
    const first = page.context.open(7);
    await page.context.open(8);
    pending.resolve({ data: { success: true, data: registration(7) } });
    await first;
    assert.equal(page.context.currentId.value, 8);
    assert.equal(page.context.form.value.name, 'Applicant 8');
    page.context.close();
    const blocked = setup(undefined, { api: { get: async () => ({ data: { success: true, data: { ...registration(), can_edit: false } } }) } });
    await blocked.context.open(7);
    assert.equal(blocked.context.dialog.value, false);
    assert.match(blocked.calls.messages.at(-1)[0], /tidak dapat diedit/i);
});

test('region changes clear descendants and bank defaults stay valid', async () => {
    const page = setup();
    await page.context.open(7);
    await page.context.changeProvince('34');
    assert.equal(page.context.form.value.city_id, null);
    assert.equal(page.context.form.value.district_id, null);
    assert.equal(page.context.form.value.subdistrict_id, null);
    page.context.addBank();
    page.context.setDefaultBank(1);
    assert.deepEqual(page.context.form.value.bank_accounts.map(bank => bank.is_default), [false, true]);
    page.context.removeBank(1);
    assert.equal(page.context.form.value.bank_accounts[0].is_default, true);
});

test('changing level clears incompatible upline and distributor has no upline', async () => {
    const page = setup();
    await page.context.open(7);
    page.context.form.value.level_id = 2;
    page.context.changeLevel();
    assert.equal(page.context.form.value.upline_member_id, null);
    assert.deepEqual(page.context.availableUplines.value.map(item => item.level_code), ['DST']);
    page.context.form.value.upline_member_id = 2;
    page.context.form.value.level_id = 1;
    page.context.changeLevel();
    assert.equal(page.context.form.value.upline_member_id, null);
    assert.equal(page.context.availableUplines.value.length, 0);
});

test('registration media match the inline avatar and caption layout of Edit Mitra', async () => {
    const page = setup();
    await page.context.open(7);
    const rendered = await fields(page);
    assert.equal(rendered.filter(item => item.tag === 'v-card' && item.class?.includes('registration-section')).length, 4);
    assert.ok(rendered.some(item => item.tag === 'v-card-text' && item.class?.includes('bg-grey-lighten-4')));
    const uploads = rendered.filter(item => item.tag === 'MediaUpload');
    assert.deepEqual(uploads.map(item => item.size), [92, 92, 92]);
    assert.ok(uploads.every(item => item.rounded === true && item.class.includes('flex-shrink-0')));
    assert.match(page.descriptor.template.content, /registration-media-item d-flex align-center ga-3/);
    assert.match(page.descriptor.template.content, /JPG, PNG, atau WEBP/);
    assert.doesNotMatch(page.descriptor.template.content, /registration-media-card|#preview/);
});

const levelOptions = {
    can_change_level: true, message: 'Mitra memenuhi syarat.',
    levels: [{ id: 1, code: 'DST', name: 'Distributor' }, { id: 2, code: 'AGT', name: 'Agen Utama' }, { id: 3, code: 'RSL', name: 'Reseller' }],
    uplines: [{ id: 2, label: 'Distributor', level_code: 'DST' }],
};
const memberDetail = { id: 7, name: 'Member', code: '0001/0001/0001', level: { id: 3, code: 'RSL', name: 'Reseller' } };

test('Data Mitra only shows level correction for eligible members', async () => {
    const page = setup('admin/pages/partnership/members.vue');
    for (const allowed of [true, false]) {
        const rendered = await fields(page, { ...memberDetail, can_change_level: allowed, status: { code: 1 } });
        const button = rendered.find(item => item.tag === 'v-list-item' && item.title === 'Ubah Level');
        assert.equal(Boolean(button), allowed);
        if (allowed) {
            let opened;
            page.context.levelChangeDialogRef.value = { open: id => { opened = id; } };
            button.onClick();
            assert.equal(opened, 7);
        }
    }
});

test('Data Mitra keeps synchronization outside the secondary action menu beside the three primary buttons', async () => {
    const page = setup('admin/pages/partnership/members.vue');
    const item = { ...memberDetail, can_change_level: true, can_sync_supply_chain: true, status: { code: 1 } };
    const rendered = await fields(page, item, true);
    assert.deepEqual(rendered.filter(field => field.tag === 'v-btn').map(field => field['aria-label']), ['Detail Mitra', 'Edit Mitra', 'Aksi Lainnya']);
    assert.equal(rendered.find(field => field.tag === 'v-menu').eager, '');
    assert.deepEqual(rendered.filter(field => field.tag === 'v-list-item').map(field => field.title), ['Jaringan Mitra', 'Ubah Level', 'Reset Password', 'Nonaktifkan Mitra']);
    const sync = rendered.find(field => field.tag === 'SupplyChainSyncButton');
    assert.equal(sync.presentation, undefined);
    assert.ok(rendered.indexOf(sync) < rendered.findIndex(field => field.tag === 'v-menu'));
    assert.equal(sync.canSync ?? sync['can-sync'], true);
    assert.equal(sync.endpoint, '/admin/partnership/members/7/supply-chain/sync');
    const opened = [];
    page.context.memberDetailRef.value = { open: id => opened.push(['detail', id]) };
    page.context.memberEditDialogRef.value = { open: id => opened.push(['edit', id]) };
    rendered.find(field => field['aria-label'] === 'Detail Mitra').onClick();
    rendered.find(field => field['aria-label'] === 'Edit Mitra').onClick();
    assert.deepEqual(opened, [['detail', 7], ['edit', 7]]);
    let refreshed = 0;
    page.context.memberTable.value = { refresh: () => { refreshed++; } };
    sync.onProcessed();
    assert.equal(refreshed, 1);
    const inactive = await fields(page, { ...item, can_change_level: false, status: { code: 0 } }, true);
    assert.doesNotMatch(inactive.filter(field => field.tag === 'v-list-item').map(field => field.title).join(' '), /Ubah Level|Nonaktifkan Mitra/);
});

test('direct level correction loads eligibility and submits its own endpoint without changing identifiers', async () => {
    let saved;
    const page = setup('admin/components/partnership/MemberLevelChangeDialog.vue', { api: {
        get: async url => ({ data: { success: true, data: url.endsWith('/level-change-options') ? levelOptions : memberDetail } }),
        post: async (url, payload) => { saved = { url, payload }; return { data: { success: true, message: 'Level diperbarui.' } }; },
    } });
    await page.context.open(7);
    assert.deepEqual(page.context.targetLevels.value.map(item => item.id), [1, 2]);
    const rendered = await fields(page);
    assert.equal(rendered.filter(field => field.tag === 'v-textarea').length, 0);
    assert.equal(rendered.filter(field => field.tag === 'v-alert').length, 0);
    page.context.form.value = { level_id: 1, upline_member_id: null };
    page.context.formRef.value = { validate: async () => ({ valid: true }) };
    await page.context.submit();
    assert.equal(saved.url, '/admin/partnership/members/7/change-level');
    assert.deepEqual(saved.payload, { level_id: 1, upline_member_id: null });
    assert.equal(page.context.dialog.value, false);
    assert.deepEqual(page.calls.events, [['updated']]);
});

test('ineligible member cannot submit correction and rejection preserves form data', async () => {
    let saves = 0;
    const page = setup('admin/components/partnership/MemberLevelChangeDialog.vue', { api: {
        get: async url => ({ data: { success: true, data: url.endsWith('/level-change-options') ? { ...levelOptions, can_change_level: false } : memberDetail } }),
        post: async () => { saves++; throw { response: { data: { message: 'Mitra sudah bertransaksi.' } } }; },
    } });
    await page.context.open(7);
    const rendered = await fields(page);
    assert.equal(rendered.find(field => field.tag === 'v-alert')?.type, 'warning');
    page.context.formRef.value = { validate: async () => ({ valid: true }) };
    await page.context.submit();
    assert.equal(saves, 0);
    page.context.options.value.can_change_level = true;
    page.context.form.value.level_id = 2;
    page.context.form.value.upline_member_id = 1;
    await page.context.submit();
    assert.equal(page.context.dialog.value, true);
    assert.equal(page.context.form.value.level_id, 2);
    assert.equal(page.context.form.value.upline_member_id, 1);
    assert.deepEqual(page.calls.messages.at(-1), ['Mitra sudah bertransaksi.', 'error']);
});

test('shipping report preserves filters and removes the two unwanted charge columns', async () => {
    const page = setup('admin/pages/stc/shipping-cost-report.vue');
    const table = (await fields(page)).find(item => item.tag === 'BaseDataTable');
    assert.equal(table.url, '/admin/stc/shipping-cost-report');
    assert.deepEqual(table.headers.filter(header => header.filter).map(header => header.key), ['datetime', 'code', 'is_preorder', 'buyer_name', 'buyer_code', 'method']);
    assert.ok(table.headers.some(header => header.key === 'base_cost'));
    assert.ok(table.headers.some(header => header.key === 'insurance'));
    assert.ok(table.headers.some(header => header.key === 'admin_fee'));
    assert.ok(!table.headers.some(header => ['buyer_shipping_cost', 'uncharged_cost'].includes(header.key)));
    assert.equal(table.headers.find(header => header.key === 'buyer_code').visible, false);
    assert.equal(table.headers.find(header => header.key === 'datetime').visible, false);
    assert.deepEqual(table.headers.find(header => header.key === 'method').options, [{ value: 'courier_express', label: 'Kurir Ekspres' }]);
    assert.doesNotMatch(page.descriptor.template.content, /<v-alert/);
});

async function renderShippingReport(item) {
    const page = setup('admin/pages/stc/shipping-cost-report.vue', {
        useFormatter: () => ({ formatDateTime: String, formatPrice: value => Number(value ?? 0).toLocaleString('id-ID') }),
    });
    const badges = {};
    new Function('exports', ts.transpileModule(readFileSync(sourceFile('shared/composables/useBadge.ts'), 'utf8'), {
        compilerOptions: { module: ts.ModuleKind.CommonJS },
    }).outputText)(badges);
    const { getBadgeData } = badges.useBadge();
    const render = new Function('Vue', compile(page.descriptor.template.content, { mode: 'function', prefixIdentifiers: true }).code)(Vue);
    const app = Vue.createSSRApp({ render: () => render(Vue.proxyRefs(page.context), []) });
    app.component('v-alert', { setup: (_, { slots }) => () => Vue.h('aside', slots.default?.()) });
    app.component('BaseDataTable', { props: ['headers'], setup: (props, { slots }) => () => Vue.h('table', [
        Vue.h('thead', Vue.h('tr', props.headers.filter(column => column.visible !== false).map(column => Vue.h('th', column.title)))),
        Vue.h('tbody', Vue.h('tr', props.headers.filter(column => column.visible !== false).map(column => Vue.h('td', { 'data-column': column.key },
            slots[`item.${column.key}`]?.({ item, column }) ?? String(item[column.key] ?? ''))))),
    ]) });
    app.component('BaseBadge', { props: ['type', 'value'], setup: props => () => {
        const badge = getBadgeData(props.type, props.value);
        return Vue.h('span', { 'data-badge': props.type, 'data-color': badge.color }, badge.label);
    } });
    return { html: await renderToString(app), context: page.context };
}

test('shipping report stacks buyer code under the name and uses the shared transaction badge labels and colors', async () => {
    for (const [status, label, color] of [['completed', 'Selesai', 'success'], ['shipped', 'Dikirim', 'info']]) {
        const { html } = await renderShippingReport({ buyer_name: 'Eko Syamsudin', buyer_code: '0011/0000/0000',
            method: 'courier_express', courier: 'JNE', service: 'REG', status, base_cost: 7000, insurance: 1000, admin_fee: 0, is_preorder: false });
        assert.match(html, /data-column="buyer_name"[^>]*><div class="d-flex flex-column ga-1 py-2"><div[^>]*font-weight-bold[^>]*>Eko Syamsudin<\/div><div class="text-caption text-medium-emphasis font-weight-regular">0011\/0000\/0000<\/div><\/div><\/td>/);
        assert.equal((html.match(/0011\/0000\/0000/g) || []).length, 1);
        assert.doesNotMatch(html, /<th>Kode Mitra<\/th>|<th>Tanggal Transaksi<\/th>/);
        assert.match(html, new RegExp(`data-badge="transaction_status" data-color="${color}"[^>]*>${label}`));
        assert.match(html, /JNE · REG/);
        assert.doesNotMatch(html, /Ditagihkan ke Pembeli|Tidak Ditagihkan/);
        assert.match(html, /<h1[^>]*>Laporan Biaya Pengiriman<\/h1>/);
        assert.doesNotMatch(html, /Laporan Ongkir Non-Pembeli/);
    }
});

test('shipping report shows courier service without dangling separators and preserves the retail fallback', async () => {
    const { html, context } = await renderShippingReport({ buyer_type: 'customer', method: 'courier_express', status: 'shipped', courier: 'JNE', is_preorder: false });
    assert.match(html, /Pelanggan retail/);
    assert.match(html, /JNE/);
    assert.equal(context.courierLabel({ courier: ' JNE ', service: ' REG ' }), 'JNE · REG');
    assert.equal(context.courierLabel({ courier: ' JNE ', service: '' }), 'JNE');
    assert.equal(context.courierLabel({ courier: '', service: ' Instant ' }), 'Instant');
    assert.equal(context.courierLabel({ courier: null, service: '  ' }), '');
    assert.equal(context.courierLabel({ courier: 'spx', service: 'SPX Standard' }), 'SPX Standard');
    assert.equal(context.courierLabel({ courier: 'sicepat', service: 'Sicepat REGULER' }), 'Sicepat REGULER');
});

test('shipping report shows PO and regular badges while keeping the timestamp under the transaction code', async () => {
    for (const [isPreorder, label, color] of [[false, 'Reguler', 'info'], [true, 'PO', 'warning']]) {
        const { html } = await renderShippingReport({ code: 'TRX-PO', datetime: '2026-10-21 10:00:00',
            method: 'courier_express', is_preorder: isPreorder, status: 'shipped' });
        assert.match(html, new RegExp(`data-badge="transaction_type" data-color="${color}"[^>]*>${label}`));
        assert.match(html, /data-column="code"[^>]*><div class="d-flex flex-column ga-1 py-2"><div[^>]*>TRX-PO<\/div><div[^>]*>2026-10-21 10:00:00<\/div><\/div>/);
    }
});
