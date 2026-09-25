import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const collectionPath = path.resolve(__dirname, '../docs/DNY API.postman_collection.json');
const environmentPath = path.resolve(__dirname, '../docs/DNY Local.postman_environment.json');

const descriptions = {
    account_name: 'Nama pemilik rekening bank.',
    account_number: 'Nomor rekening bank.',
    address: 'Alamat lengkap atau data alamat sesuai format endpoint.',
    address_id: 'ID alamat pengiriman milik member.',
    addresses: 'Daftar alamat member yang akan disimpan.',
    admin_fee: 'Biaya administrasi layanan pengiriman instan dalam rupiah.',
    awb: 'Nomor resi atau airway bill dari STC.',
    bank_account_id: 'ID rekening bank milik seller member yang menjadi tujuan pembayaran.',
    bank_account_name: 'Nama pemilik rekening bank pendaftar.',
    bank_account_number: 'Nomor rekening bank pendaftar.',
    bank_accounts: 'Daftar rekening bank member yang akan disimpan.',
    bank_branch: 'Nama cabang bank.',
    bank_city: 'Kota cabang bank.',
    bank_company_id: 'ID rekening bank perusahaan yang menjadi tujuan pembayaran.',
    bank_id: 'ID bank dari data referensi bank.',
    batch_number: 'Nomor batch produk yang dikirim atau diretur.',
    birth_date: 'Tanggal lahir dengan format YYYY-MM-DD.',
    branch: 'Nama cabang tempat rekening bank terdaftar.',
    cancel_active_transactions: 'Penanda untuk membatalkan transaksi aktif sekaligus melepaskan alokasi stok saat member dinonaktifkan.',
    category_id: 'ID kategori produk.',
    checksum: 'Checksum file untuk memeriksa keutuhan hasil unggahan.',
    chunk: 'Potongan file yang diunggah pada urutan saat ini.',
    chunk_size: 'Ukuran setiap potongan file dalam byte.',
    city: 'Nama kota tempat rekening bank terdaftar.',
    city_id: 'ID kota atau kabupaten dari data wilayah.',
    class: 'Class CSS ikon atau tampilan menu.',
    code: 'Kode unik data.',
    collection: 'Kelompok penggunaan media, misalnya product atau profile.',
    cost: 'Biaya pengiriman dalam rupiah.',
    country_id: 'ID negara dari data wilayah.',
    courier: 'Data kurir dan layanan pengiriman yang dipilih.',
    courier_code: 'Kode perusahaan kurir.',
    courier_name: 'Nama perusahaan atau layanan kurir.',
    couriers: 'Daftar kode kurir yang akan diminta atau ditawarkan.',
    current_password: 'Kata sandi saat ini untuk verifikasi pemilik akun.',
    customer_id: 'ID pelanggan yang melakukan pembelian.',
    customer_price: 'Harga jual produk untuk customer dalam rupiah.',
    data: 'Objek data status pengiriman yang dikirim oleh STC.',
    date: 'Waktu kejadian callback yang dikirim oleh STC.',
    delivery_note_number: 'Nomor surat jalan transaksi.',
    description: 'Keterangan lengkap data yang dikirim.',
    destination: 'Data alamat tujuan untuk perhitungan pengiriman instan.',
    destination_latitude: 'Koordinat lintang lokasi tujuan.',
    destination_longitude: 'Koordinat bujur lokasi tujuan.',
    details: 'Daftar produk dan perubahan jumlah stok.',
    device_name: 'Nama perangkat yang menggunakan token autentikasi.',
    district_id: 'ID kecamatan dari data wilayah.',
    domicile_address: 'Alamat domisili lengkap mitra.',
    drop_off_available: 'Penanda apakah paket dapat diserahkan langsung ke gerai kurir.',
    email: 'Alamat email aktif.',
    etd: 'Perkiraan lama pengiriman dari penyedia kurir.',
    expiry_date: 'Tanggal kedaluwarsa produk dengan format YYYY-MM-DD.',
    facebook: 'Nama akun atau tautan Facebook member.',
    filename: 'Nama file yang akan diunggah.',
    finished_at: 'Waktu paket dinyatakan tiba oleh STC.',
    force_insurance: 'Penanda apakah asuransi diwajibkan oleh kurir.',
    frontend_enabled: 'Penanda apakah akses aplikasi frontend diaktifkan.',
    full_address: 'Alamat lengkap penerima.',
    gender: 'Jenis kelamin sesuai pilihan yang tersedia.',
    goods_receive_id: 'ID penerimaan barang yang menjadi asal produk retur.',
    height: 'Tinggi kemasan produk dalam sentimeter.',
    icon: 'Class atau nama ikon menu.',
    id: 'ID data yang diperbarui; kosongkan untuk data baru.',
    identifier: 'Email atau username akun yang akan diproses.',
    identity_image_url: 'URL foto dokumen identitas.',
    identity_no: 'Nomor dokumen identitas.',
    identity_type: 'Jenis dokumen identitas, misalnya KTP.',
    image_url: 'URL gambar utama.',
    image_urls: 'Daftar URL foto bukti kondisi produk.',
    instagram: 'Nama akun atau tautan Instagram member.',
    insurance: 'Biaya asuransi pengiriman dalam rupiah.',
    is_active: 'Penanda apakah data berstatus aktif.',
    is_default: 'Penanda apakah data dijadikan pilihan utama.',
    is_package: 'Penanda apakah produk merupakan paket.',
    is_publish: 'Penanda apakah produk ditampilkan kepada pengguna.',
    items: 'Daftar item yang diproses beserta ID produk, jumlah, dan data tambahan sesuai tahap transaksi.',
    label: 'Nama singkat alamat, misalnya Rumah atau Kantor.',
    latitude: 'Koordinat lintang lokasi.',
    legal_name: 'Nama badan usaha resmi.',
    length: 'Panjang kemasan produk dalam sentimeter.',
    link: 'Tautan atau route tujuan menu.',
    logo_url: 'URL logo kurir atau perusahaan.',
    longitude: 'Koordinat bujur lokasi.',
    member_id: 'ID member yang terkait dengan data.',
    member_level_id: 'ID level member untuk harga tersebut.',
    member_prices: 'Daftar harga produk untuk setiap level member.',
    menu_id: 'ID menu induk untuk pengaturan hak akses.',
    menus: 'Daftar menu beserta izin akses yang akan disimpan.',
    method: 'Nama kejadian callback pengiriman dari STC.',
    mime_type: 'Tipe MIME file yang akan diunggah.',
    min_order: 'Minimum jumlah pesanan untuk level member.',
    mobile_phone: 'Nomor telepon seluler aktif.',
    name: 'Nama data sesuai konteks endpoint.',
    nib: 'Nomor Induk Berusaha.',
    note: 'Catatan atau alasan untuk proses tersebut.',
    npwp: 'Nomor Pokok Wajib Pajak perusahaan.',
    order: 'Urutan tampil menu.',
    order_id: 'ID pesanan pada sistem STC.',
    origin: 'Data alamat asal untuk perhitungan pengiriman instan.',
    origin_latitude: 'Koordinat lintang lokasi asal.',
    origin_longitude: 'Koordinat bujur lokasi asal.',
    original_name: 'Nama asli file sebelum diunggah.',
    parent_id: 'ID menu induk; gunakan null untuk menu utama.',
    password: 'Kata sandi yang akan digunakan akun.',
    password_confirmation: 'Pengulangan kata sandi baru untuk memastikan nilainya sama.',
    payment_method: 'Metode pembayaran transaksi, misalnya cash atau transfer.',
    phone: 'Nomor telepon yang dapat dihubungi.',
    pickup_method: 'Cara penyerahan paket kepada kurir, yaitu pickup atau drop_off.',
    pickup_pin: 'Kode verifikasi pengambilan barang.',
    pickup_schedule: 'Jadwal kurir mengambil paket.',
    point_value: 'Nilai poin yang terkait dengan level member.',
    price: 'Harga produk untuk level member dalam rupiah.',
    problem_at: 'Waktu masalah pengiriman dicatat oleh STC.',
    product_id: 'ID produk yang diproses.',
    province_id: 'ID provinsi dari data wilayah.',
    quantity: 'Jumlah unit produk.',
    reason: 'Alasan kejadian atau pengajuan.',
    receipt_url: 'URL bukti pembayaran utama.',
    recipient: 'Nama penerima pada alamat pengiriman.',
    refresh_token: 'Refresh token aktif untuk menerbitkan pasangan token baru.',
    rejected_at: 'Waktu pengiriman dibatalkan oleh STC.',
    replacement_sponsor_id: 'ID upline pengganti yang akan menerima jaringan downline.',
    return_finished_at: 'Waktu pengembalian paket ke pengirim dinyatakan selesai oleh STC.',
    return_max_days: 'Batas maksimum pengajuan retur dalam hitungan hari.',
    returned_at: 'Waktu paket mulai dikembalikan oleh STC.',
    reward_monthly_min_qty_agent: 'Minimum pembelian bulanan Agent untuk memperoleh reward.',
    reward_monthly_min_qty_distributor: 'Minimum pembelian bulanan Distributor untuk memperoleh reward.',
    reward_monthly_min_qty_reseller: 'Minimum pembelian bulanan Reseller untuk memperoleh reward.',
    reward_point_per_item: 'Jumlah poin yang diberikan untuk setiap unit produk.',
    reward_stockist_min_amount: 'Minimum nilai pembelian Distributor stokis untuk memperoleh reward.',
    reward_stockist_percentage_basis_points: 'Persentase reward stokis dalam basis point; 100 basis point sama dengan 1%.',
    role_id: 'ID peran administrator.',
    service: 'Kode layanan pengiriman yang dipilih.',
    service_type: 'Kode jenis layanan dari kurir.',
    services: 'Daftar layanan pengiriman instan yang diminta.',
    shipped_at: 'Waktu paket dinyatakan dikirim oleh STC.',
    shipping_cost_bearer: 'Pihak yang menanggung biaya pengiriman retur.',
    shipping_method: 'Metode pengiriman transaksi.',
    size: 'Ukuran file dalam byte.',
    sort_order: 'Urutan level member.',
    sorting_code: 'Kode sortir paket dari STC.',
    spread_payment_percentage: 'Persentase pembayaran spread untuk transaksi PO.',
    spread_receipt_url: 'URL bukti pembayaran spread untuk transaksi PO.',
    status: 'Status data yang akan disimpan.',
    subdistrict_id: 'ID kelurahan atau desa dari data wilayah.',
    tiktok: 'Nama akun atau tautan TikTok member.',
    timezone: 'Zona waktu lokasi pengiriman.',
    title: 'Judul atau nama tampilan data.',
    to_level_id: 'ID level tujuan downgrade member.',
    to_parent_member_id: 'ID upline tujuan setelah downgrade.',
    token: 'Token reset password dari tautan email.',
    total_chunks: 'Jumlah seluruh potongan file.',
    tracking_number: 'Nomor resi pengiriman manual.',
    type: 'Jenis data sesuai pilihan yang didukung endpoint.',
    unit: 'Satuan produk, misalnya pcs.',
    unit_price: 'Harga per unit produk dalam rupiah.',
    upgrade_agent_min_customer_count: 'Minimum jumlah customer untuk memenuhi syarat upgrade Agent.',
    upgrade_agent_min_qty_per_month: 'Minimum pembelian bulanan untuk memenuhi syarat upgrade Agent.',
    upgrade_agent_min_reseller_count: 'Minimum jumlah Reseller untuk memenuhi syarat upgrade Agent.',
    upgrade_agent_min_total_qty: 'Minimum total pembelian untuk memenuhi syarat upgrade Agent.',
    upgrade_reseller_min_customer_count: 'Minimum jumlah customer untuk memenuhi syarat upgrade Reseller.',
    upgrade_reseller_min_qty_per_month: 'Minimum pembelian bulanan untuk memenuhi syarat upgrade Reseller.',
    upgrade_reseller_min_total_qty: 'Minimum total pembelian untuk memenuhi syarat upgrade Reseller.',
    upline_ids: 'Daftar ID upline yang sharing profit-nya akan diproses.',
    use_voucher: 'Penanda apakah voucher reward yang tersedia digunakan.',
    username: 'Username akun.',
    value: 'Nilai konfigurasi yang akan disimpan.',
    vehicle: 'Jenis kendaraan untuk pengiriman instan.',
    video_url: 'URL video bukti kondisi produk.',
    warehouse_id: 'ID warehouse tempat stok disesuaikan.',
    weight: 'Berat produk atau paket dalam gram.',
    whatsapp: 'Nomor WhatsApp pelanggan.',
    width: 'Lebar kemasan produk dalam sentimeter.',
};

const queryDescriptions = {
    city_id: 'ID kota atau kabupaten untuk mengambil daftar kecamatan.',
    date_from: 'Tanggal awal periode data dengan format YYYY-MM-DD.',
    date_to: 'Tanggal akhir periode data dengan format YYYY-MM-DD.',
    depth: 'Jumlah tingkat jaringan yang ditampilkan.',
    district_id: 'ID kecamatan untuk mengambil daftar kelurahan atau desa.',
    field_search: 'Daftar kolom yang dipakai untuk pencarian umum, dipisahkan dengan koma.',
    'filter[admin_id]': 'Menyaring data berdasarkan ID administrator.',
    'filter[batch_number]': 'Menyaring data berdasarkan nomor batch produk.',
    'filter[buyer.id]': 'Menyaring transaksi berdasarkan ID pembeli.',
    'filter[buyer.type]': 'Menyaring transaksi berdasarkan jenis atau level pembeli.',
    'filter[category_id]': 'Menyaring data berdasarkan ID kategori produk.',
    'filter[expiry_date_from]': 'Batas awal tanggal kedaluwarsa dengan format YYYY-MM-DD.',
    'filter[expiry_date_to]': 'Batas akhir tanggal kedaluwarsa dengan format YYYY-MM-DD.',
    'filter[expiry_status]': 'Menyaring batch berdasarkan status kedaluwarsa.',
    'filter[is_active]': 'Menyaring data berdasarkan status aktif.',
    'filter[is_publish]': 'Menyaring produk berdasarkan status publikasi.',
    'filter[level_id]': 'Menyaring member berdasarkan ID level.',
    'filter[member_id]': 'Menyaring data berdasarkan ID member.',
    'filter[member_level_id]': 'Menyaring data berdasarkan ID level member.',
    'filter[month]': 'Menyaring data berdasarkan nomor bulan 1 sampai 12.',
    'filter[product_id]': 'Menyaring data berdasarkan ID produk.',
    'filter[status]': 'Menyaring data berdasarkan status proses.',
    'filter[transaction.status][ne]': 'Mengecualikan transaksi dengan status yang diberikan.',
    'filter[type]': 'Menyaring data berdasarkan jenisnya.',
    'filter[warehouse_id]': 'Menyaring data berdasarkan ID warehouse.',
    'filter[year]': 'Menyaring data berdasarkan tahun.',
    limit: 'Jumlah data maksimal yang ditampilkan pada satu halaman.',
    member_id: 'ID member yang menjadi titik awal data atau jaringan.',
    month: 'Nomor bulan 1 sampai 12.',
    month_from: 'Nomor bulan awal periode.',
    month_to: 'Nomor bulan akhir periode.',
    'name[eq]': 'Menyaring nama dengan kecocokan tepat.',
    page: 'Nomor halaman yang ingin ditampilkan, dimulai dari 1.',
    pagination: 'Penanda apakah respons menggunakan pagination.',
    pagination_bool: 'Penanda apakah respons menggunakan pagination.',
    parent_id: 'ID upline yang downline langsungnya akan ditampilkan.',
    province_id: 'ID provinsi untuk mengambil daftar kota atau kabupaten.',
    search: 'Kata kunci pencarian umum.',
    sort: 'Urutan data; awalan minus berarti urutan menurun dan beberapa kolom dipisahkan koma.',
    to_level_id: 'ID level tujuan untuk mencari calon sponsor yang sesuai.',
    warehouse_id: 'ID warehouse sumber stok.',
    year: 'Tahun periode data.',
    year_from: 'Tahun awal periode.',
    year_to: 'Tahun akhir periode.',
};

const headerDescriptions = {
    Accept: 'Format respons yang diharapkan dari API.',
    'Content-Type': 'Format payload yang dikirim ke API.',
    'X-STC-Callback-Token': 'Token rahasia untuk memverifikasi callback dari STC.',
};

const variableDescriptions = {
    base_url: 'URL dasar API DNY versi 1.',
    admin_username: 'Username administrator untuk proses masuk.',
    admin_password: 'Kata sandi administrator untuk proses masuk.',
    admin_token: 'Access token administrator; tersimpan otomatis setelah proses masuk.',
    admin_refresh_token: 'Refresh token administrator; tersimpan otomatis setelah proses masuk.',
    member_username: 'Kode atau username member untuk proses masuk.',
    member_password: 'Kata sandi member untuk proses masuk.',
    member_token: 'Access token member; tersimpan otomatis setelah proses masuk.',
    member_refresh_token: 'Refresh token member; tersimpan otomatis setelah proses masuk.',
    category_id: 'ID kategori produk untuk request contoh.',
    product_id: 'ID produk untuk request contoh.',
    media_id: 'ID sesi atau data media hasil proses unggah.',
    media_url: 'URL media hasil unggah yang dipakai pada payload lain.',
    media_extension: 'Ekstensi media yang akan diambil.',
    member_id: 'ID member untuk request contoh.',
    network_parent_id: 'ID member yang dibuka sebagai induk pada pohon jaringan.',
    role_id: 'ID peran administrator untuk request contoh.',
    menu_id: 'ID menu untuk request contoh.',
    annual_reward_id: 'ID reward tahunan untuk request contoh.',
    ref_bank_id: 'ID bank dari endpoint referensi bank.',
    company_bank_id: 'ID rekening bank perusahaan.',
    warehouse_id: 'ID warehouse untuk request contoh.',
    province_id: 'ID provinsi dari endpoint referensi wilayah.',
    city_id: 'ID kota atau kabupaten dari endpoint referensi wilayah.',
    district_id: 'ID kecamatan dari endpoint referensi wilayah.',
    subdistrict_id: 'ID kelurahan atau desa dari endpoint referensi wilayah.',
    administrator_id: 'ID administrator untuk request contoh.',
    stockist_id: 'ID Distributor stokis untuk request contoh.',
    config_id: 'ID konfigurasi sistem untuk request contoh.',
    member_level_id: 'ID level member untuk request contoh.',
    audit_trail_id: 'ID audit trail untuk request contoh.',
    registration_id: 'ID pengajuan pendaftaran mitra.',
    upgrade_id: 'ID pengajuan upgrade level mitra.',
    downgrade_id: 'ID proses downgrade level mitra.',
    downline_member_id: 'ID downline yang digunakan pada request contoh.',
    new_parent_member_id: 'ID upline baru untuk pemindahan jaringan.',
    customer_id: 'ID customer untuk request contoh.',
    warehouse_stock_id: 'ID stok produk pada warehouse.',
    adjustment_id: 'ID penyesuaian stok.',
    member_address_id: 'ID alamat milik member.',
    purchase_order_id: 'ID pesanan pembelian member.',
    sales_order_id: 'ID pesanan penjualan member.',
    member_bank_account_id: 'ID rekening bank milik member.',
    trx_id: 'ID transaksi untuk request contoh.',
    payment_id: 'ID pembayaran untuk request contoh.',
    return_id: 'ID pengajuan retur barang.',
    monthly_reward_id: 'ID reward bulanan.',
    stockist_reward_id: 'ID reward Distributor stokis.',
    sharing_profit_id: 'ID sharing profit.',
    member_stock_adjustment_id: 'ID penyesuaian stok member.',
    current_year: 'Tahun berjalan yang dipakai pada request contoh.',
    current_month: 'Bulan berjalan yang dipakai pada request contoh.',
    stc_callback_token: 'Token rahasia callback STC.',
    stc_shipping_order_id: 'ID pesanan pengiriman pada sistem STC.',
    stc_shipping_awb: 'Nomor resi pengiriman dari STC.',
    origin_district_id: 'ID kecamatan lokasi asal untuk perhitungan ongkir.',
    origin_subdistrict_id: 'ID kelurahan lokasi asal untuk perhitungan ongkir.',
    destination_district_id: 'ID kecamatan lokasi tujuan untuk perhitungan ongkir.',
    destination_subdistrict_id: 'ID kelurahan lokasi tujuan untuk perhitungan ongkir.',
};

const dataTableDescription = [
    '### Sistem filter query',
    '',
    'Gunakan nama field atau alias yang tersedia pada endpoint. Field yang tidak didukung akan diabaikan. Nama bertitik seperti `buyer.type` dinormalisasi menjadi alias `buyer_type`.',
    '',
    '#### Filter',
    '',
    '| Format | Keterangan | Contoh |',
    '| --- | --- | --- |',
    '| `filter[field]=value` | Nilai harus sama dengan value. | `filter[status]=waiting_payment` |',
    '| `filter[field][eq]=value` | Sama dengan. | `filter[id][eq]=10` |',
    '| `filter[field][ne]=value` | Tidak sama dengan. | `filter[status][ne]=cancelled` |',
    '| `filter[field][gt]=value` | Lebih besar dari. | `filter[id][gt]=10` |',
    '| `filter[field][gte]=value` | Lebih besar dari atau sama dengan. | `filter[ordered_at][gte]=2026-01-01` |',
    '| `filter[field][lt]=value` | Lebih kecil dari. | `filter[id][lt]=100` |',
    '| `filter[field][lte]=value` | Lebih kecil dari atau sama dengan. | `filter[ordered_at][lte]=2026-12-31` |',
    '| `filter[field][like]=value` | Pencarian pola LIKE. Gunakan `%25` sebagai karakter `%` pada URL. | `filter[code][like]=%25TRX%25` |',
    '| `filter[field][in]=value` | Nilai termasuk dalam daftar yang dipisahkan koma. | `filter[id][in]=1,2,3` |',
    '| `filter[field][not_in]=value` | Nilai tidak termasuk dalam daftar yang dipisahkan koma. | `filter[status][not_in]=cancelled,rejected` |',
    '',
    '#### Pencarian, urutan, dan pagination',
    '',
    '| Parameter | Keterangan | Contoh |',
    '| --- | --- | --- |',
    '| `search` | Pencarian umum pada kolom yang didukung endpoint. | `search=serum` |',
    '| `field_search` | Membatasi pencarian ke kolom tertentu; beberapa kolom dipisahkan koma. | `field_search=name,code` |',
    '| `sort` | Urut naik menggunakan nama field, urut turun menggunakan awalan `-`; beberapa field dipisahkan koma. | `sort=-created_at,name` |',
    '| `limit` | Jumlah data per halaman, minimal 1 dan maksimal 100. Default 10. | `limit=20` |',
    '| `page` | Nomor halaman, dimulai dari 1. Default 1. | `page=2` |',
    '| `pagination=false` | Menampilkan seluruh hasil tanpa pagination. | `pagination=false` |',
    '| `pagination_bool=false` | Nama lama yang masih didukung untuk menonaktifkan pagination. | `pagination_bool=false` |',
].join('\n');

function describe(field, requestPath) {
    if (field === 'name') {
        if (requestPath.includes('Administrator / Profil')) {
            return 'Nama lengkap administrator.';
        }

        if (requestPath.includes('Tambah Distributor') || requestPath.includes('Ajukan Distributor')) {
            return 'Nama lengkap calon Distributor.';
        }

        if (requestPath.includes('Data Member')) {
            return 'Nama lengkap member.';
        }

        if (requestPath.includes('Warehouse/Company')) {
            return 'Nama warehouse.';
        }

        if (requestPath.includes('Kurir') || requestPath.includes('Instant')) {
            return 'Nama penyedia layanan pengiriman.';
        }

        if (requestPath.includes('Retur Pembelian')) {
            return 'Nama penyedia layanan pengiriman retur.';
        }

        if (requestPath.includes('Produk')) {
            return 'Nama produk atau kategori produk.';
        }

        if (requestPath.includes('Pelanggan')) {
            return 'Nama lengkap pelanggan.';
        }

        if (requestPath.includes('Stockist')) {
            return 'Nama lokasi Distributor stokis.';
        }

        if (requestPath.includes('Sistem dan Konfigurasi / Administrator')) {
            return 'Nama lengkap administrator.';
        }

        if (requestPath.includes('Level Mitra')) {
            return 'Nama level mitra.';
        }

        if (requestPath.includes('Pendaftaran Mitra')) {
            return 'Nama lengkap calon mitra.';
        }

        if (requestPath.includes('Profil & Akun')) {
            return 'Nama lengkap member.';
        }

        return descriptions.name;
    }

    if (field === 'type') {
        if (requestPath.includes('Bank Perusahaan')) {
            return 'Jenis rekening bank perusahaan.';
        }

        if (requestPath.includes('Peran')) {
            return 'Jenis peran administrator.';
        }

        if (requestPath.includes('Konfigurasi Sistem')) {
            return 'Kode kelompok konfigurasi sistem.';
        }

        if (requestPath.includes('Instant')) {
            return 'Jenis layanan pengiriman instan.';
        }

        if (requestPath.includes('Penyesuaian Stok')) {
            return 'Jenis perubahan stok: masuk (in) atau keluar (out).';
        }

        if (requestPath.includes('Barang Pengganti / Express')) {
            return 'Kode jenis layanan kurir express.';
        }

        return descriptions.type;
    }

    if (field === 'status' && requestPath.includes('Mitra Distributor')) {
        return 'Status awal akun Distributor.';
    }

    if (field === 'status' && requestPath.includes('Data Member')) {
        return 'Status aktif atau nonaktif member.';
    }

    if (field === 'code' && requestPath.includes('Produk')) {
        return 'Kode unik produk.';
    }

    if (field === 'description') {
        if (requestPath.includes('Daftar Produk')) {
            return 'Deskripsi produk.';
        }

        if (requestPath.includes('Kategori Produk')) {
            return 'Deskripsi kategori produk.';
        }

        if (requestPath.includes('Level Mitra')) {
            return 'Deskripsi level mitra.';
        }

        if (requestPath.includes('Sistem dan Konfigurasi / Menu')) {
            return 'Deskripsi fungsi menu.';
        }

        if (requestPath.includes('Retur Barang')) {
            return 'Penjelasan kondisi barang dan kebutuhan retur.';
        }
    }

    if (field === 'value' && requestPath.includes('Konfigurasi')) {
        return 'Objek nilai konfigurasi sesuai kelompok yang diperbarui.';
    }

    return descriptions[field];
}

function rawFields(raw) {
    const fields = [];
    const seen = new Set();
    const matches = raw.matchAll(/"([^"\\]+)"\s*:/g);

    for (const match of matches) {
        const field = match[1];

        if (!seen.has(field)) {
            seen.add(field);
            fields.push(field);
        }
    }

    return fields;
}

function bodyFields(body) {
    if (body.mode === 'raw') {
        return rawFields(body.raw || '');
    }

    const parameters = [...(body.formdata || []), ...(body.urlencoded || [])];

    return parameters.map((parameter) => parameter.key).filter(Boolean);
}

function markdownDescription(fields, requestPath) {
    if (fields.length === 0) {
        return 'Keterangan payload\n\nTidak ada field payload. Kirim objek JSON kosong.';
    }

    const rows = fields.map((field) => {
        const description = describe(field, requestPath);

        if (!description) {
            throw new Error(`Keterangan payload belum tersedia untuk field "${field}" pada ${requestPath}.`);
        }

        return `\`${field}\` : ${description}  `;
    });

    return [
        'Keterangan payload',
        '',
        ...rows,
    ].join('\n');
}

function queryDescription(key) {
    const description = queryDescriptions[key];

    if (!description) {
        throw new Error(`Keterangan query parameter belum tersedia untuk "${key}".`);
    }

    return description;
}

function structuredUrl(rawUrl) {
    const questionMarkIndex = rawUrl.indexOf('?');

    if (questionMarkIndex < 0) {
        return rawUrl;
    }

    const base = rawUrl.slice(0, questionMarkIndex);
    const queryString = rawUrl.slice(questionMarkIndex + 1);
    const baseMatch = base.match(/^(\{\{[^}]+}})(?:\/(.*))?$/);

    if (!baseMatch) {
        throw new Error(`Format URL tidak dikenali: ${rawUrl}`);
    }

    const query = queryString.split('&').filter(Boolean).map((part) => {
        const equalIndex = part.indexOf('=');
        const encodedKey = equalIndex < 0 ? part : part.slice(0, equalIndex);
        const encodedValue = equalIndex < 0 ? '' : part.slice(equalIndex + 1);
        const key = decodeURIComponent(encodedKey);

        return {
            key,
            value: decodeURIComponent(encodedValue),
            description: queryDescription(key),
        };
    });

    return {
        raw: rawUrl,
        host: [baseMatch[1]],
        path: baseMatch[2] ? baseMatch[2].split('/') : [],
        query,
    };
}

function updateUrl(url) {
    const normalizedUrl = typeof url === 'string' ? structuredUrl(url) : url;

    for (const parameter of normalizedUrl?.query || []) {
        parameter.description = queryDescription(parameter.key);
    }

    return normalizedUrl;
}

function updateHeaders(headers, requestPath) {
    for (const header of headers || []) {
        const description = headerDescriptions[header.key];

        if (!description) {
            throw new Error(`Keterangan header belum tersedia untuk "${header.key}" pada ${requestPath}.`);
        }

        header.description = description;
    }
}

function updateBody(body, requestPath) {
    if (!body?.mode) {
        return 0;
    }

    const fields = bodyFields(body);

    if (body.mode === 'raw') {
        const raw = (body.raw || '').trim();

        if (raw.startsWith('{') || raw.startsWith('[')) {
            body.description = markdownDescription(fields, requestPath);
            body.options = body.options || {};
            body.options.raw = body.options.raw || {};
            body.options.raw.language = 'json';
        }
    } else {
        delete body.description;
    }

    const parameters = [...(body.formdata || []), ...(body.urlencoded || [])];

    for (const parameter of parameters) {
        if (!parameter.key) {
            continue;
        }

        const description = describe(parameter.key, requestPath);

        if (!description) {
            throw new Error(`Keterangan payload belum tersedia untuk field "${parameter.key}" pada ${requestPath}.`);
        }

        parameter.description = description;
    }

    return fields.length;
}

function baseRequestDescription(description) {
    const markers = [
        '\n\n### Keterangan payload',
        '\n\nKeterangan payload',
        '\n\n### Sistem filter query',
    ];
    const markerIndexes = markers
        .map((marker) => description.indexOf(marker))
        .filter((index) => index >= 0);
    const endIndex = markerIndexes.length > 0 ? Math.min(...markerIndexes) : description.length;

    return description.slice(0, endIndex).trim();
}

function updateRequest(request, requestPath) {
    if (!request) {
        return 0;
    }

    request.url = updateUrl(request.url);
    updateHeaders(request.header, requestPath);
    const fieldCount = updateBody(request.body, requestPath);

    if (
        ['POST', 'PUT', 'PATCH'].includes(request.method)
        && request.body?.mode === 'raw'
        && request.body.description
    ) {
        request.description = request.body.description;
    } else {
        delete request.description;
    }

    return fieldCount;
}

function updateVariables(variables, sourceName) {
    for (const variable of variables || []) {
        const description = variableDescriptions[variable.key];

        if (!description) {
            throw new Error(`Keterangan variable belum tersedia untuk "${variable.key}" pada ${sourceName}.`);
        }

        variable.description = description;
    }
}

let requestCount = 0;
let requestFieldCount = 0;
let responseExampleCount = 0;
let exampleFieldCount = 0;

function updateItems(items, parents = []) {
    for (const item of items) {
        const requestPath = [...parents, item.name].join(' / ');

        if (item.item) {
            updateItems(item.item, [...parents, item.name]);
            continue;
        }

        if (!item.request) {
            continue;
        }

        requestFieldCount += updateRequest(item.request, requestPath);
        requestCount += 1;

        for (const response of item.response || []) {
            response.description = response.description || `Contoh respons ${response.name} untuk request ${item.name}.`;
            exampleFieldCount += updateRequest(response.originalRequest, requestPath);
            responseExampleCount += 1;
        }
    }
}

const collection = JSON.parse(fs.readFileSync(collectionPath, 'utf8'));
collection.info.description = `${baseRequestDescription(collection.info.description || '')}\n\n${dataTableDescription}`;
updateVariables(collection.variable, 'collection');
updateItems(collection.item || []);
fs.writeFileSync(collectionPath, `${JSON.stringify(collection, null, 4)}\n`);

const environment = JSON.parse(fs.readFileSync(environmentPath, 'utf8'));
updateVariables(environment.values, 'environment');
fs.writeFileSync(environmentPath, `${JSON.stringify(environment, null, 4)}\n`);

console.log([
    `${requestCount} request utama dan ${requestFieldCount} field payload diperbarui.`,
    `${responseExampleCount} contoh respons dan ${exampleFieldCount} field payload contoh diperbarui.`,
    `${collection.variable?.length || 0} variable collection dan ${environment.values?.length || 0} variable environment diberi keterangan.`,
].join('\n'));
