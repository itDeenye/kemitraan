import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { parse } from '@vue/compiler-sfc';
import { computed, ref } from 'vue';
import ts from 'typescript';

const source = path => readFileSync(new URL(`../../resources/js/${path}`, import.meta.url), 'utf8');
const transpile = code => ts.transpileModule(code, {
    compilerOptions: { target: ts.ScriptTarget.ESNext, module: ts.ModuleKind.CommonJS },
}).outputText;
const evaluate = (code, dependencies) => new Function(...Object.keys(dependencies), code)(...Object.values(dependencies));

function clock() {
    let time = Date.parse('2030-01-01T00:00:00Z');
    let mount;
    let unmount;
    let interval;
    let cleared;
    const exports = {};
    evaluate(transpile(source('member/composables/usePaymentUploadCountdown.ts').replace(/^import .+$/gm, '')), {
        exports, computed, ref, Date: { now: () => time, parse: Date.parse },
        onMounted: callback => { mount = callback; }, onBeforeUnmount: callback => { unmount = callback; },
        setInterval: callback => { interval = callback; return 42; }, clearInterval: id => { cleared = id; },
    });
    const state = exports.usePaymentUploadCountdown();
    mount();
    return {
        state, advance: milliseconds => { time += milliseconds; interval(); },
        stop: () => { unmount(); assert.equal(cleared, 42); },
    };
}

test('countdown uses server time despite device clock skew and stops exactly at the deadline', () => {
    const { state, advance, stop } = clock();
    state.setDeadline('2026-11-24T09:00:00+07:00', '2026-11-23T09:00:00+07:00');
    assert.equal(state.countdown.value, '24:00:00');
    advance(24 * 3600 * 1000 - 1000);
    assert.equal(state.countdown.value, '00:00:01');
    assert.equal(state.expired.value, false);
    advance(1000);
    assert.equal(state.expired.value, true);
    advance(60000);
    assert.equal(state.countdown.value, '00:00:00');
    stop();
});

test('null and invalid deadlines never expire PO or legacy orders', () => {
    const { state, stop } = clock();
    for (const value of [null, '', 'not-a-date']) {
        state.setDeadline(value);
        assert.equal(state.hasDeadline.value, false);
        assert.equal(state.expired.value, false);
    }
    stop();
});

function paymentPage({ late = false, finishUploadLate = false } = {}) {
    const time = clock();
    const calls = [];
    const data = {
        id: 7, status: { code: 'waiting_payment' }, actions: { can_upload_payment: !late },
        summary: { bill_amount: 100000 },
        payment_upload_expires_at: late ? '2026-11-23T08:59:59+07:00' : '2026-11-24T09:00:00+07:00',
        server_time: '2026-11-23T09:00:00+07:00',
    };
    const descriptor = parse(source('member/pages/transactions/orders/payment.vue')).descriptor;
    const file = ts.createSourceFile('payment.ts', descriptor.scriptSetup.content, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    const script = file.statements.filter(statement => !ts.isImportDeclaration(statement)).map(statement => statement.getText(file)).join('\n');
    const state = evaluate(transpile(script) + '\nreturn { fetchOrder, order, canSubmit, selectedFile, submitPaymentProof, isPaymentExpired };', {
        ref, computed, onMounted: () => {}, useRouter: () => ({ replace: path => calls.push(['replace', path]), push: path => calls.push(['push', path]) }),
        useRoute: () => ({ query: { id: '7' } }), decodeRouteId: Number, encodeRouteId: String,
        useFormatter: () => ({ formatPrice: String, formatDateTime: String }),
        useSnackbarStore: () => ({ showMessage: () => {} }), usePaymentUploadCountdown: () => time.state,
        useMediaUpload: () => ({
            isUploading: ref(false), progress: ref(0), upload: async () => {
                calls.push(['upload']);
                if (finishUploadLate) time.advance(24 * 3600 * 1000);
                return '/proof.png';
            },
        }),
        purchaseService: { getOrderDetail: async () => ({ success: true, data }), payOrder: async () => { calls.push(['payment']); return { success: true }; } },
    });
    return { ...time, state, calls };
}

test('upload page disables late proofs and prevents submission even without the scheduler', async () => {
    const { state, calls, stop } = paymentPage({ late: true });
    await state.fetchOrder();
    state.selectedFile.value = { name: 'proof.png' };
    assert.equal(state.isPaymentExpired.value, true);
    assert.equal(state.canSubmit.value, false);
    await state.submitPaymentProof();
    assert.deepEqual(calls, []);
    stop();
});

test('a file finishing upload after the deadline is not submitted as payment', async () => {
    const { state, calls, stop } = paymentPage({ finishUploadLate: true });
    await state.fetchOrder();
    state.selectedFile.value = { name: 'proof.png' };
    await state.submitPaymentProof();
    assert.deepEqual(calls, [['upload']]);
    assert.equal(state.isPaymentExpired.value, true);
    stop();
});

test('a timely proof can still be submitted normally', async () => {
    const { state, calls, stop } = paymentPage();
    await state.fetchOrder();
    state.selectedFile.value = { name: 'proof.png' };
    await state.submitPaymentProof();
    assert.equal(calls.filter(call => call[0] === 'payment').length, 1);
    stop();
});
