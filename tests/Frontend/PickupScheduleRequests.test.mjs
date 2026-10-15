import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import * as Vue from 'vue';
import { parse } from '@vue/compiler-sfc';
import ts from 'typescript';

function deferred() {
    let resolve, reject;
    const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
    return { promise, resolve, reject };
}

const scheduleResponse = time => ({ data: { success: true, data: { results: [
    { time, is_available: true },
] } } });
const flushRequests = () => new Promise(resolve => setImmediate(resolve));

function dialog(name, { detailRequest } = {}) {
    const file = new URL(`../../resources/js/admin/components/inventory/${name}.vue`, import.meta.url);
    const script = parse(readFileSync(file, 'utf8')).descriptor.scriptSetup.content;
    const source = ts.createSourceFile('dialog.ts', script, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    const code = ts.transpileModule(source.statements.filter(s => !ts.isImportDeclaration(s)).map(s => s.getText(source)).join('\n'), {
        compilerOptions: { target: ts.ScriptTarget.ESNext, module: ts.ModuleKind.CommonJS },
    }).outputText;
    const requests = [];
    const messages = [];
    const dependencies = {
        ref: Vue.ref, reactive: Vue.reactive, computed: Vue.computed,
        defineEmits: () => () => {}, defineExpose: () => {},
        console: { error: () => {} },
        useSnackbarStore: () => ({ showMessage: (message, level) => messages.push({ message, level }) }),
        useFormatter: () => ({ formatDateTime: String, formatPrice: String, formatLocation: String }),
        inventoryService: {
            getShipmentDetail: async id => detailRequest ? detailRequest(id) : ({ data: { data: {
                id, shipping_method: 'courier_express', shipping: {}, details: [],
            } } }),
            getReshipCouriers: async (_id, payload) => {
                assert.deepEqual(payload, { couriers: [] });
                return { data: { success: true, data: { results: [] } } };
            },
            getExpressSchedules: () => {
                const request = deferred();
                requests.push(request);
                return request.promise;
            },
        },
    };
    const page = new Function(...Object.keys(dependencies), `${code}\nreturn { open, close, fetchSchedules, onPickupMethodChange, form, schedules, loadingSchedules, loadingData, order };`)(...Object.values(dependencies));
    return { ...page, requests, messages };
}

for (const name of ['ShippingActionDialog', 'ShippingReshipDialog']) {
    test(`${name}: opening an express shipment does not request schedules`, async () => {
        const page = dialog(name);
        await page.open({ id: 42 });
        assert.equal(page.requests.length, 0);
        assert.equal(page.loadingSchedules.value, false);
        assert.deepEqual(page.schedules.value, []);
    });

    test(`${name}: repeated loading shares one pending schedules request`, async () => {
        const page = dialog(name);
        await page.open({ id: 42 });
        page.fetchSchedules();
        page.fetchSchedules();
        assert.equal(page.requests.length, 1);
        assert.equal(page.loadingSchedules.value, true);
        page.requests[0].resolve(scheduleResponse('2026-10-15 14:00:00'));
        await flushRequests();
        assert.equal(page.schedules.value.length, 1);
        assert.equal(page.form.pickup_schedule, '2026-10-15 14:00:00');
        assert.equal(page.loadingSchedules.value, false);
        assert.deepEqual(page.messages, []);
    });

    test(`${name}: selecting pickup requests schedules`, async () => {
        const page = dialog(name);
        await page.open({ id: 42 });
        page.form.pickup_method = 'PICKUP';
        page.onPickupMethodChange();
        assert.equal(page.requests.length, 1);
        page.requests[0].resolve(scheduleResponse('2026-10-15 14:00:00'));
        await flushRequests();
        assert.equal(page.form.pickup_schedule, '2026-10-15 14:00:00');
        assert.deepEqual(page.messages, []);
    });

    test(`${name}: current failure reports once and pickup can retry successfully`, async () => {
        const page = dialog(name);
        await page.open({ id: 42 });
        page.form.pickup_method = 'PICKUP';
        page.onPickupMethodChange();
        assert.equal(page.requests.length, 1);
        page.requests[0].reject(new Error('network unavailable'));
        await flushRequests();
        assert.equal(page.messages.length, 1);
        assert.equal(page.messages[0].level, 'error');
        assert.match(page.messages[0].message, /jadwal pickup/i);
        assert.equal(page.loadingSchedules.value, false);
        assert.equal(page.form.pickup_schedule, '');
        page.onPickupMethodChange();
        assert.equal(page.requests.length, 2);
        page.requests[1].resolve(scheduleResponse('2026-10-15 15:00:00'));
        await flushRequests();
        assert.equal(page.form.pickup_schedule, '2026-10-15 15:00:00');
        assert.equal(page.messages.length, 1);
        assert.equal(page.loadingSchedules.value, false);
    });

    for (const outcome of ['success', 'error']) {
        test(`${name}: stale ${outcome} after close does not change schedules or report errors`, async () => {
            const page = dialog(name);
            await page.open({ id: 42 });
            page.form.pickup_method = 'PICKUP';
            page.onPickupMethodChange();
            page.close();
            if (outcome === 'success') page.requests[0].resolve(scheduleResponse('2026-10-15 14:00:00'));
            else page.requests[0].reject(new Error('old request failed'));
            await flushRequests();
            assert.deepEqual(page.schedules.value, []);
            assert.equal(page.form.pickup_schedule, '');
            assert.deepEqual(page.messages, []);
            assert.equal(page.loadingSchedules.value, false);
        });

        test(`${name}: stale ${outcome} cannot overwrite a reopened dialog or stop its loading`, async () => {
            const page = dialog(name);
            await page.open({ id: 42 });
            page.form.pickup_method = 'PICKUP';
            page.onPickupMethodChange();
            page.close();
            await page.open({ id: 43 });
            page.form.pickup_method = 'PICKUP';
            page.onPickupMethodChange();
            assert.equal(page.requests.length, 2);
            if (outcome === 'success') page.requests[0].resolve(scheduleResponse('2026-10-15 14:00:00'));
            else page.requests[0].reject(new Error('old request failed'));
            await flushRequests();
            assert.deepEqual(page.schedules.value, []);
            assert.equal(page.form.pickup_schedule, '');
            assert.deepEqual(page.messages, []);
            assert.equal(page.loadingSchedules.value, true);
            page.requests[1].resolve(scheduleResponse('2026-10-15 15:00:00'));
            await flushRequests();
            assert.equal(page.form.pickup_schedule, '2026-10-15 15:00:00');
            assert.equal(page.schedules.value[0].time, '2026-10-15 15:00:00');
            assert.equal(page.loadingSchedules.value, false);
        });
    }

    test(`${name}: reopening clears previously loaded schedules and selects a fresh schedule`, async () => {
        const page = dialog(name);
        await page.open({ id: 42 });
        page.form.pickup_method = 'PICKUP';
        page.onPickupMethodChange();
        page.requests[0].resolve(scheduleResponse('2026-10-15 14:00:00'));
        await flushRequests();
        page.close();
        await page.open({ id: 43 });
        assert.deepEqual(page.schedules.value, []);
        assert.equal(page.form.pickup_schedule, '');
        page.form.pickup_method = 'PICKUP';
        page.onPickupMethodChange();
        assert.equal(page.requests.length, 2);
        page.requests[1].resolve(scheduleResponse('2026-10-15 15:00:00'));
        await flushRequests();
        assert.equal(page.form.pickup_schedule, '2026-10-15 15:00:00');
    });

    test(`${name}: a closed dialog's late detail cannot start a schedule request`, async () => {
        const detail = deferred();
        const page = dialog(name, { detailRequest: () => detail.promise });
        const opening = page.open({ id: 42 });
        page.close();
        detail.resolve({ data: { data: { id: 42, shipping_method: 'courier_express', details: [] } } });
        await opening;
        assert.equal(page.requests.length, 0);
        assert.equal(page.order.value, null);
        assert.deepEqual(page.messages, []);
    });
}
