<template>
    <div>
        <h1 class="text-h4 font-weight-bold mb-1">Laporan Biaya Pengiriman</h1>
        <p class="text-body-2 text-medium-emphasis mb-5">Biaya pengiriman tercatat pada transaksi yang tidak menagihkan ongkir kepada pembeli.</p>
        <BaseDataTable url="/admin/stc/shipping-cost-report" :headers="headers" v-model:search="search">
            <template #item.code="{ item }">
                <div class="d-flex flex-column ga-1 py-2">
                    <div class="text-body-2 font-weight-bold">{{ item.code }}</div>
                    <div class="text-caption text-medium-emphasis">{{ formatDateTime(item.datetime) }}</div>
                </div>
            </template>
            <template #item.is_preorder="{ item, column }">
                <BaseBadge type="transaction_type" :value="item.is_preorder" :align="column.align" />
            </template>
            <template #item.buyer_name="{ item }">
                <div class="d-flex flex-column ga-1 py-2">
                    <div class="text-body-2 font-weight-bold">{{ item.buyer_name || (item.buyer_type === 'customer' ? 'Pelanggan retail' : '-') }}</div>
                    <div class="text-caption text-medium-emphasis font-weight-regular">{{ item.buyer_code || '-' }}</div>
                </div>
            </template>
            <template #item.method="{ item, column }">
                <div class="d-flex flex-column ga-1 py-2">
                    <BaseBadge type="shipping_method" :value="item.method" :align="column.align" />
                    <div v-if="courierLabel(item)" class="text-caption text-medium-emphasis">{{ courierLabel(item) }}</div>
                </div>
            </template>
            <template #item.status="{ item, column }">
                <BaseBadge type="transaction_status" :value="item.status" :align="column.align" />
            </template>
            <template #item.base_cost="{ item }">
                <div class="text-body-2 font-weight-bold">{{ formatPrice(item.base_cost) }}</div>
            </template>
            <template #item.insurance="{ item }">{{ formatPrice(item.insurance) }}</template>
            <template #item.admin_fee="{ item }">{{ formatPrice(item.admin_fee) }}</template>
        </BaseDataTable>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import BaseDataTable from '@/shared/components/BaseDataTable.vue';
import BaseBadge from '@/shared/components/BaseBadge.vue';
import { useFormatter } from '@/shared/composables/useFormatter';
const search = ref('');
const { formatDateTime, formatPrice } = useFormatter();
const courierLabel = (item: { courier?: string | null; service?: string | null }) => {
    const courier = item.courier?.trim() || '';
    const service = item.service?.trim() || '';
    if (courier && (service.toLowerCase() === courier.toLowerCase() || service.toLowerCase().startsWith(`${courier.toLowerCase()} `))) {
        return service;
    }
    return [courier.toUpperCase(), service].filter(Boolean).join(' · ');
};
const headers = [
    { title: 'Tanggal Transaksi', key: 'datetime', type: 'date', filter: true, sortable: true, visible: false },
    { title: 'Kode Transaksi', key: 'code', type: 'text', filter: true, sortable: true },
    { title: 'Tipe Pesanan', key: 'is_preorder', type: 'select', filter: true, sortable: false, align: 'center', options: [
        { value: '0', label: 'Reguler' }, { value: '1', label: 'PO' },
    ] },
    { title: 'Pembeli', key: 'buyer_name', type: 'text', filter: true, sortable: false },
    { title: 'Kode Mitra', key: 'buyer_code', type: 'text', filter: true, sortable: false, visible: false },
    { title: 'Metode Pengiriman', key: 'method', type: 'select', filter: true, sortable: false, options: [
        { value: 'courier_express', label: 'Kurir Ekspres' },
    ] },
    { title: 'Resi', key: 'awb', sortable: false },
    { title: 'Status', key: 'status', align: 'center', sortable: false },
    { title: 'Ongkir (Rp)', key: 'base_cost', align: 'right', sortable: false },
    { title: 'Asuransi (Rp)', key: 'insurance', align: 'right', sortable: false },
    { title: 'Biaya Admin (Rp)', key: 'admin_fee', align: 'right', sortable: false },
];
</script>
