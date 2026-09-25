interface BadgeData {
    label: string;
    color: string;
}

export function useBadge() {
    function getBadgeData(type: string, value: any): BadgeData {
        if (value === null || value === undefined) return { label: '-', color: 'default' };

        const strVal = String(value).toLowerCase();

        switch (type) {
            case 'status':
            case 'active':
                const isActive = value === true || value === 1 || strVal === 'aktif' || strVal === 'active';
                return {
                    label: isActive ? 'Aktif' : 'Nonaktif',
                    color: isActive ? 'success' : 'error'
                };
            
            case 'category':
                const catColors: Record<string, string> = {
                    'skincare': 'primary',
                    'makeup': 'secondary',
                    'sun care': 'accent',
                    'body care': 'info',
                    'hair care': 'success',
                    'cream malam': 'success',
                    'lotion': 'primary',
                    'parfum': 'secondary',
                    'bundling': 'warning',
                };
                return {
                    label: String(value),
                    color: catColors[strVal] || 'default'
                };

            case 'level':
                const levelColors: Record<string, string> = {
                    'distributor': 'primary',
                    'dst': 'primary',
                    'agent': 'info',
                    'agt': 'info',
                    'reseller': 'warning',
                    'rsl': 'warning',
                    'customer': 'default',
                    'warehouse': 'error',
                };
                const levelLabels: Record<string, string> = {
                    'distributor': 'Distributor',
                    'dst': 'Distributor',
                    'agent': 'Agen',
                    'agt': 'Agen',
                    'reseller': 'Reseller',
                    'rsl': 'Reseller',
                    'customer': 'Pelanggan',
                    'warehouse': 'Perusahaan',
                };
                return {
                    label: levelLabels[strVal] || String(value),
                    color: levelColors[strVal] || 'default'
                };

            case 'promo':
            case 'promotion':
                const promoLabels: Record<string, string> = {
                    'discount': 'Diskon',
                    'bundling': 'Bundling',
                    'voucher': 'Voucher',
                };
                const promoColors: Record<string, string> = {
                    'discount': 'primary',
                    'bundling': 'secondary',
                    'voucher': 'info',
                };
                return {
                    label: promoLabels[strVal] || String(value),
                    color: promoColors[strVal] || 'default'
                };

            case 'mutation':
                const mutColors: Record<string, string> = {
                    'in': 'success',
                    'out': 'error',
                };
                const mutLabels: Record<string, string> = {
                    'in': 'Masuk',
                    'out': 'Keluar'
                };
                return {
                    label: mutLabels[strVal] || String(value),
                    color: mutColors[strVal] || 'default'
                };

            case 'shipping_status':
                const shipStatusColors: Record<string, string> = {
                    'pending': 'warning',
                    'processing': 'secondary',
                    'ready_to_pickup': 'secondary',
                    'picked_up': 'success',
                    'shipped': 'info',
                    'received': 'success',
                    'completed': 'success',
                    'processed_packages': 'secondary',
                    'shipped_packages': 'info',
                    'canceled_packages': 'error',
                    'cancelled_packages': 'error',
                    'finished_packages': 'success',
                    'returned_packages': 'warning',
                    'reship_required': 'warning',
                };
                const shipStatusLabels: Record<string, string> = {
                    'pending': 'Menunggu Pengiriman',
                    'processing': 'Dikemas',
                    'ready_to_pickup': 'Siap Diambil',
                    'picked_up': 'Sudah Diambil',
                    'shipped': 'Dikirim',
                    'received': 'Siap Diterima',
                    'completed': 'Selesai',
                    'processed_packages': 'Paket Diproses',
                    'shipped_packages': 'Paket Dikirim',
                    'canceled_packages': 'Pengiriman Dibatalkan',
                    'cancelled_packages': 'Pengiriman Dibatalkan',
                    'finished_packages': 'Paket Diterima',
                    'returned_packages': 'Paket Dikembalikan',
                    'reship_required': 'Perlu Dikirim Ulang',
                };
                return {
                    label: shipStatusLabels[strVal] || String(value),
                    color: shipStatusColors[strVal] || 'default'
                };

            case 'pickup_status':
                const pickupStatus = getBadgeData('shipping_status', value);
                const pickupStatusLabels: Record<string, string> = {
                    'pending': 'Menunggu Proses',
                    'processing': 'Sedang Disiapkan',
                };
                return {
                    label: pickupStatusLabels[strVal] || pickupStatus.label,
                    color: pickupStatus.color,
                };

            case 'shipping_method':
                const shipMethodColors: Record<string, string> = {
                    'courier_manual': 'warning',
                    'courier_express': 'info',
                    'courier_instant': 'error',
                    'pickup': 'success',
                };
                const shipMethodLabels: Record<string, string> = {
                    'courier_manual': 'Kurir',
                    'courier_express': 'Kurir Ekspres',
                    'courier_instant': 'Kurir Instan',
                    'pickup': 'Ambil Sendiri',
                };
                return {
                    label: shipMethodLabels[strVal] || String(value),
                    color: shipMethodColors[strVal] || 'default'
                };

            case 'transaction_type':
                const transactionTypeColors: Record<string, string> = {
                    'false': 'info',
                    'true': 'warning',
                };
                const transactionTypeLabels: Record<string, string> = {
                    'false': 'Reguler',
                    'true': 'PO',
                };
                return {
                    label: transactionTypeLabels[strVal] || String(value),
                    color: transactionTypeColors[strVal] || 'default'
                };

            case 'transaction_status':
                const transactionColors: Record<string, string> = {
                    'pending': 'default',
                    'waiting_order_approval': 'info',
                    'waiting_stock_screening': 'info',
                    'waiting_payment': 'default',
                    'waiting_payment_approval': 'info',
                    'approved': 'success',
                    'rejected': 'error',
                    'processing': 'warning',
                    'shipped': 'info',
                    'received': 'success',
                    'completed': 'success',
                    'cancelled': 'error',
                    'reship_required': 'warning',
                };
                const transactionLabels: Record<string, string> = {
                    'pending': 'Menunggu Pembayaran',
                    'waiting_order_approval': 'Menunggu Verifikasi Pesanan',
                    'waiting_stock_screening': 'Menunggu Screening Stok',
                    'waiting_payment': 'Menunggu Pembayaran',
                    'waiting_payment_approval': 'Menunggu Verifikasi Pembayaran',
                    'approved': 'Disetujui',
                    'rejected': 'Ditolak',
                    'processing': 'Dikemas',
                    'shipped': 'Dikirim',
                    'received': 'Siap Diterima',
                    'completed': 'Selesai',
                    'cancelled': 'Dibatalkan',
                    'reship_required': 'Perlu Dikirim Ulang',
                };
                return {
                    label: transactionLabels[strVal] || String(value),
                    color: transactionColors[strVal] || 'default'
                };

            case 'payment_status':
                const paymentColors: Record<string, string> = {
                    'pending': 'default',
                    'submitted': 'info',
                    'approved': 'success',
                    'rejected': 'error',
                };
                const paymentLabels: Record<string, string> = {
                    'pending': 'Menunggu Pembayaran',
                    'submitted': 'Menunggu Verifikasi Pembayaran',
                    'approved': 'Disetujui',
                    'rejected': 'Ditolak',
                };
                return {
                    label: paymentLabels[strVal] || String(value),
                    color: paymentColors[strVal] || 'default'
                };

            case 'return_status':
                const returnColors: Record<string, string> = {
                    'submitted': 'warning',
                    'approved': 'info',
                    'waiting_member_shipment': 'info',
                    'received': 'secondary',
                    'replacement_shipped': 'info',
                    'return_in_transit': 'info',
                    'return_shipping_failed': 'error',
                    'received_by_company': 'secondary',
                    'replacement_in_transit': 'info',
                    'replacement_shipping_failed': 'error',
                    'completed': 'success',
                    'rejected': 'error',
                };
                const returnLabels: Record<string, string> = {
                    'submitted': 'Menunggu Keputusan Admin',
                    'approved': 'Disetujui',
                    'waiting_member_shipment': 'Menunggu Pengiriman Mitra',
                    'received': 'Barang Diterima',
                    'replacement_shipped': 'Barang Pengganti Dikirim',
                    'return_in_transit': 'Dikirim ke Perusahaan',
                    'return_shipping_failed': 'Pengiriman Gagal',
                    'received_by_company': 'Diterima Perusahaan',
                    'replacement_in_transit': 'Pengganti Dikirim',
                    'replacement_shipping_failed': 'Pengiriman Pengganti Gagal',
                    'completed': 'Selesai',
                    'rejected': 'Ditolak',
                };
                return {
                    label: returnLabels[strVal] || String(value),
                    color: returnColors[strVal] || 'default'
                };

            case 'receive_status':
                const receiveColors: Record<string, string> = {
                    'completed': 'success',
                    'received': 'success',
                    'ready_to_receive': 'warning',
                    'waiting_delivery': 'info',
                    'waiting': 'warning'
                };
                const receiveLabels: Record<string, string> = {
                    'completed': 'Selesai',
                    'received': 'Selesai',
                    'ready_to_receive': 'Siap Diterima',
                    'waiting_delivery': 'Menunggu Pengiriman Selesai',
                    'waiting': 'Siap Diterima'
                };
                return {
                    label: receiveLabels[strVal] || String(value),
                    color: receiveColors[strVal] || 'default'
                };

            case 'stockist_reward_status':
                const stockistColors: Record<string, string> = {
                    'available': 'success',
                    'used': 'primary',
                    'expired': 'error',
                };
                const stockistLabels: Record<string, string> = {
                    'available': 'Tersedia',
                    'used': 'Digunakan',
                    'expired': 'Kedaluwarsa',
                };
                return {
                    label: stockistLabels[strVal] || String(value),
                    color: stockistColors[strVal] || 'default'
                };

            case 'sharing_reward_status':
                const sharingColors: Record<string, string> = {
                    'submitted': 'info',
                    'pending': 'warning',
                    'approved': 'success',
                    'paid': 'success',
                };
                const sharingLabels: Record<string, string> = {
                    'submitted': 'Diajukan',
                    'pending': 'Menunggu',
                    'approved': 'Disetujui',
                    'paid': 'Ditransfer',
                };
                return {
                    label: sharingLabels[strVal] || String(value),
                    color: sharingColors[strVal] || 'default'
                };

            case 'reward_status':
                const rewardColors: Record<string, string> = {
                    'transferred': 'success',
                    'paid': 'success',
                    'waiting': 'warning',
                    'pending': 'warning',
                    'unqualified': 'error',
                };
                const rewardLabels: Record<string, string> = {
                    'transferred': 'Ditransfer',
                    'paid': 'Dibayar',
                    'waiting': 'Menunggu',
                    'pending': 'Belum Dibayar',
                    'unqualified': 'Tidak Terkualifikasi',
                };
                return {
                    label: rewardLabels[strVal] || String(value),
                    color: rewardColors[strVal] || 'default'
                };

            case 'company_bank_type':
                const cBankColors: Record<string, string> = {
                    'spread_payment': 'info',
                    'company': 'primary',
                };
                const cBankLabels: Record<string, string> = {
                    'spread_payment': 'Kemitraan',
                    'company': 'Perusahaan',
                };
                return {
                    label: cBankLabels[strVal] || String(value),
                    color: cBankColors[strVal] || 'default'
                };

            case 'registration_status':
                const regColors: Record<string, string> = {
                    'requested': 'warning',
                    'approved': 'success',
                    'rejected': 'error',
                };
                const regLabels: Record<string, string> = {
                    'requested': 'Menunggu',
                    'approved': 'Disetujui',
                    'rejected': 'Ditolak',
                };
                return {
                    label: regLabels[strVal] || String(value),
                    color: regColors[strVal] || 'default'
                };
            
            case 'upgrade_status':
                const upgradeColors: Record<string, string> = {
                    'requested': 'warning',
                    'scheduled': 'info',
                    'approved': 'success',
                    'rejected': 'error',
                };
                const upgradeLabels: Record<string, string> = {
                    'requested': 'Menunggu',
                    'scheduled': 'Dijadwalkan',
                    'approved': 'Disetujui',
                    'rejected': 'Ditolak',
                };
                return {
                    label: upgradeLabels[strVal] || String(value),
                    color: upgradeColors[strVal] || 'default'
                };

            case 'pickup_method':
                const pickupColors: Record<string, string> = {
                    'drop-off': 'info',
                    'pickup': 'success',
                };
                const pickupLabels: Record<string, string> = {
                    'drop-off': 'Drop-Off',
                    'pickup': 'Pickup',
                };
                return {
                    label: pickupLabels[strVal] || String(value),
                    color: pickupColors[strVal] || 'default'
                };

            default:
                return {
                    label: String(value),
                    color: 'default'
                };
        }
    }

    return {
        getBadgeData,
    };
}
