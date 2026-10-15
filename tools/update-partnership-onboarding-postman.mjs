import fs from 'node:fs';
import { fileURLToPath } from 'node:url';

const collectionPath = fileURLToPath(new URL('../docs/DNY API.postman_collection.json', import.meta.url));
const collection = JSON.parse(fs.readFileSync(collectionPath, 'utf8'));
const rawVariable = (key) => `__raw_variable_${key}__`;
const stringify = (value) => JSON.stringify(value, null, 4)
    .replace(/"__raw_variable_(\w+)__"/g, '{{$1}}');
const parsePayload = (raw) => JSON.parse(raw.replace(/(:\s*)(\{\{(\w+)\}\})/g, '$1"__raw_variable_$3__"'));
const sampleDocuments = {
    identity_type: 'KTP',
    identity_no: '3578010101010001',
    identity_image_url: 'https://cdn.example.test/identity/mitra.jpg',
    nib: '0123456789012',
    npwp: '0123456789012345',
    image_url: 'https://cdn.example.test/profile/mitra.jpg',
};
const proof = 'https://cdn.example.test/payment/registration.jpg';
const contactInputs = (name) => ({
    bank_accounts: ['0012345678', '0098765432'].map((account_number, index) => ({
        bank_id: rawVariable('ref_bank_id'), account_name: name, account_number,
        city: 'Surabaya', branch: 'Tegalsari', is_active: true, is_default: index === 0,
    })),
});
const contacts = (name) => {
    const result = contactInputs(name);
    for (const bank of result.bank_accounts) {
        bank.bank_id = 1;
    }
    return result;
};
const resourceContacts = (name, distributor) => {
    const inputs = contacts(name);
    return {
        addresses: (distributor ? ['KTP', 'Domisili'] : ['Domisili']).map((label, index) => ({
            id: index + 1, label, recipient: name, phone: '+6281234567890', full_address: `Jalan DNY ${label}`,
            is_default: label === 'Domisili',
            region: { province_id: '11', province_name: 'Jawa Timur', city_id: '1101', city_name: 'Surabaya', city_type: 'Kota',
                district_id: '110101', district_name: 'Tegalsari', subdistrict_id: 1, subdistrict_name: 'Keputran',
                postal_code: 60265, country_id: 1, country_name: 'Indonesia' },
        })),
        bank_accounts: inputs.bank_accounts.map((bank, index) => ({ id: index + 1, ...bank, bank_code: 'BCA', bank_name: 'Bank Central Asia' })),
    };
};
const uploadDescription = 'Tiga upload terpisah untuk pendaftaran mitra: collection general untuk gambar identitas → identity_image_url; collection profile untuk foto profil → image_url; collection payment_proof untuk bukti transfer → payment_proof_image_url. Mulai sesi /media/uploads dengan filename, mime_type, size (byte), checksum opsional; unggah file sebagai multipart chunk pada /{id}/chunks/{chunk}, lalu POST /{id}/complete. Ambil data.url setelah status ready. Payload pendaftaran memakai URL hasil upload, bukan file/base64. Gambar identitas dan bukti transfer privat; foto profil publik. Simpan URL ketiga upload secara terpisah, jangan ditimpa dengan hasil upload berikutnya.';
const documentsDescription = (memberRegistration) => [
    '`identity_type`: KTP, KK, SIM, BPJS (Kesehatan), BPJS_KETENAGAKERJAAN, atau PASPOR. `identity_no` harus string: KTP/KK/SIM 16 angka; BPJS Kesehatan 13 angka; BPJS Ketenagakerjaan 11 atau 16 angka; PASPOR 8 karakter alfanumerik. Nol depan dipertahankan. Edit nomor/jenis menggunakan format ini; data lama yang tidak berubah tetap diterima.',
    '`identity_image_url`: URL gambar identitas hasil upload collection general (privat).',
    memberRegistration
        ? '`nib`: string opsional maksimal 20 karakter. NPWP tidak digunakan pada pendaftaran member; jangan kirim npwp.'
        : '`nib`: string opsional maksimal 20 karakter; `npwp`: string opsional maksimal 30 karakter. Nol depan dipertahankan.',
    '`image_url`: URL foto profil hasil upload collection profile (publik). Semua URL input maksimal 255 karakter.',
    '`bank_accounts[]` opsional, maksimal 10 rekening. Boleh tidak dikirim, null, atau []. Setiap rekening: bank_id (bank aktif), account_name (maksimal 50 karakter), account_number (string), city, branch, is_active, is_default.',
    'Maksimal satu rekening utama. Jika tidak dipilih, rekening aktif pertama menjadi utama. Nomor rekening tetap string agar nol depan tidak hilang.',
    uploadDescription,
].join('\n\n');
let updated = 0;

function updateProfile(item) {
    const edit = item.request.method === 'PUT' && item.request.url.raw.endsWith('/profile');
    let payload;
    if (edit) {
        payload = parsePayload(item.request.body.raw);
        Object.assign(payload, { identity_type: 'BPJS', identity_no: '0001234567890', identity_image_url: sampleDocuments.identity_image_url,
            image_url: sampleDocuments.image_url });
        item.request.body.raw = stringify(payload);
    }
    item.request.description = 'Detail profil memuat data akun/role, data pribadi, level/sponsor, status, identitas beserta gambar, NIB, NPWP yang tersimpan, foto profil, bukti transfer registrasi yang telah disetujui, social_media, stockist, alamat/rekening utama, seluruh addresses dan bank_accounts serta reward_summary. Tidak mengeluarkan password/PIN. URL identitas/bukti privat berupa signed URL sementara. NPWP tampil jika tersimpan (misalnya Distributor), tetapi bukan input pendaftaran member.'
        + (edit ? '\n\nBody edit profil tetap memakai name, email, phone, gender, birth_date, identity_type (KTP/KK/SIM/BPJS/BPJS_KETENAGAKERJAAN/PASPOR), identity_no, nib dan sosial media. Nomor yang diubah wajib sesuai jenis: KTP/KK/SIM 16 angka, BPJS Kesehatan 13 angka, BPJS Ketenagakerjaan 11 atau 16 angka, PASPOR 8 karakter alfanumerik. Jika identitas tidak dikirim atau tetap sama, nomor lama dipertahankan. Tambahkan identity_image_url dan image_url secara terpisah jika mengganti gambar (maksimal 255 karakter). Jika tidak dikirim, gambar lama tetap; null menghapus gambar.' : '')
        + '\n\n' + uploadDescription;
    for (const response of item.response || []) {
        if (response.code !== 200) { continue; }
        const body = JSON.parse(response.body);
        const member = body.data.member;
        member.npwp = sampleDocuments.npwp;
        member.payment_proof_image_url = proof;
        member.addresses = member.default_address ? [structuredClone(member.default_address), {
            ...structuredClone(member.default_address), id: 2, label: 'Kantor', full_address: 'Jalan Kantor Nomor 2', is_default: false,
        }] : [];
        member.bank_accounts = member.default_bank_account ? [structuredClone(member.default_bank_account), {
            ...structuredClone(member.default_bank_account), id: 2, account_number: '0012345678', is_default: false,
        }] : [];
        if (edit) {
            Object.assign(member, { name: payload.name, email: payload.email, gender: payload.gender,
                birth_date: payload.birth_date, nib: payload.nib, image: payload.image_url });
            member.mobile_phone = payload.phone?.replace(/^0/, '+62');
            member.identity = { type: payload.identity_type, number: payload.identity_no, image: payload.identity_image_url };
            response.originalRequest = structuredClone(item.request);
        }
        response.body = JSON.stringify(body, null, 4);
    }
}

function updateUploadExamples(item) {
    const member = item.request.url.raw.includes('/member/');
    const template = item.response.find((response) => response.code === 201);
    item.request.description = uploadDescription + '\n\nfilename wajib (bukan original_name); chunk_size dan total_chunks ditentukan server. Contoh tiga jenis upload tersedia sebagai saved examples.';
    if (member) {
        item.request.body.raw = stringify({ collection: 'general', filename: 'identitas.jpg',
            mime_type: 'image/jpeg', size: 102400, checksum: null });
    }
    if (!template) { return; }
    for (const [collection, label, filename] of [['general', 'Identitas', 'identitas.jpg'],
        ['profile', 'Foto Profil', 'profil.jpg'], ['payment_proof', 'Bukti Transfer', 'transfer.jpg']]) {
        const example = structuredClone(template);
        example.name = `201 - Upload ${label}`;
        example.originalRequest = structuredClone(item.request);
        example.originalRequest.body.raw = stringify({ collection, filename, mime_type: 'image/jpeg', size: 102400, checksum: null });
        const body = JSON.parse(example.body);
        Object.assign(body.data, { collection, original_name: filename, original_mime_type: 'image/jpeg', original_size: 102400,
            mime_type: null, size: null, total_chunks: Math.ceil(102400 / body.data.chunk_size), uploaded_chunks: 0, progress: 0, status: 'pending' });
        delete body.data.url;
        delete body.data.error;
        example.body = JSON.stringify(body, null, 4);
        const existing = item.response.findIndex((response) => response.name === example.name);
        if (existing >= 0) { item.response[existing] = example; } else { item.response.push(example); }
        if (member && collection === 'general') {
            template.originalRequest = structuredClone(item.request);
            template.body = example.body;
        }
    }
}

function updateItems(items) {
    for (const item of items) {
        if (item.item) { updateItems(item.item); continue; }
        const url = item.request?.url?.raw || '';
        if (/\/(admin|member)\/media\/uploads$/.test(url) && item.request.method === 'POST') {
            updateUploadExamples(item); updated++; continue;
        }
        if (/\/member\/profile(?:\/photo)?$/.test(url)) {
            updateProfile(item); updated++; continue;
        }
        const registration = /\/(admin\/partnership|member\/network)\/registrations(?:[/?]|$)/.test(url);
        const memberRegistration = registration && url.includes('/member/network/');
        const member = /\/admin\/partnership\/members(?:[/?]|$)/.test(url) && ['Daftar Member', 'Detail Member', 'Perbarui Data Mitra'].includes(item.name);
        const distributor = /\/admin\/partnership\/distributors(?:[/?]|$)/.test(url);
        if (!registration && !member && !distributor) { continue; }
        updated++;
        const create = item.request.method === 'POST' && !/\/(approve|reject)$/.test(url);
        const edit = member && item.request.method === 'PUT';
        if (create || edit) {
            const payload = parsePayload(item.request.body.raw);
            Object.assign(payload, sampleDocuments);
            if (memberRegistration) { delete payload.npwp; }
            if (registration) { payload.payment_proof_image_url = proof; }
            if (registration && url.includes('/member/')) {
                payload.level_id = rawVariable('member_level_id');
                payload.upline_member_id = null;
            }
            if (create && !url.includes('/member/network/')) {
                delete payload.addresses;
                Object.assign(payload, { address: 'Jalan DNY KTP', province_id: '{{province_id}}', city_id: '{{city_id}}',
                    district_id: '{{district_id}}', subdistrict_id: rawVariable('subdistrict_id'), country_id: 1 });
                if (distributor) {
                    Object.assign(payload, { domicile_address: 'Jalan DNY Domisili',
                        domicile_province_id: '{{domicile_province_id}}', domicile_city_id: '{{domicile_city_id}}',
                        domicile_district_id: '{{domicile_district_id}}', domicile_subdistrict_id: rawVariable('domicile_subdistrict_id'),
                        domicile_country_id: 1 });
                }
                for (const key of ['bank_id', 'bank_account_name', 'bank_account_number', 'bank_city', 'bank_branch']) { delete payload[key]; }
                Object.assign(payload, contactInputs(payload.name));
            }
            item.request.body.raw = stringify(payload);
            item.request.description = documentsDescription(memberRegistration) + (edit
                ? '\n\nEdit mitra: addresses dan bank_accounts wajib dikirim sebagai array sesuai kontrak lama, boleh []. Id hanya boleh milik mitra tersebut. NIB/NPWP dan identitas yang tidak dikirim mempertahankan nilai lama. Tidak menerima null untuk bank_accounts saat edit.'
                : '\n\nAlamat pendaftaran tetap memakai field address, province_id, city_id, district_id, subdistrict_id, country_id; bukan addresses[]. Distributor manual memakai address/wilayah sebagai alamat KTP dan domicile_address dengan domicile_province_id, domicile_city_id, domicile_district_id, domicile_subdistrict_id sebagai alamat/wilayah domisili terpisah; domicile_country_id opsional. Jika semua field wilayah domisili tidak dikirim, wilayah KTP dipakai sebagai fallback kompatibilitas. Jika salah satu dikirim, keempat field wilayah wajib lengkap dan hierarki harus sesuai. Array rekening pada pendaftaran tidak memakai id. Field rekening tunggal lama tetap diterima jika bank_accounts tidak dikirim.') + (registration
                ? '\n\n`payment_proof_image_url`: URL hasil upload collection payment_proof; opsional. Bukti transfer, profil, NIB dan identitas tampil di riwayat/detail approval; NPWP hanya input pendaftaran Distributor. Saat disetujui, profil/identitas/NIB (dan NPWP Distributor) serta semua kontak dipindahkan ke mitra; bukti transfer tetap pada pengajuan.'
                : (distributor ? '\n\nDistributor manual langsung menjadi mitra; pendaftaran Distributor lewat /partnership/registrations perlu approval.' : ''))
                + (url.includes('/member/network/') ? '\n\n`level_id` wajib. Distributor dapat memilih AGT/RSL; Agen Utama hanya RSL. `upline_member_id` opsional khusus Distributor mendaftarkan RSL.' : '');
        } else if (/\/(approve|reject)$/.test(url)) {
            item.request.description = 'Body tetap hanya note. Dokumen, alamat dan rekening dibaca dari pengajuan, bukan diunggah ulang pada aksi approval. Respons detail memuat identity, nib, npwp (nullable; input hanya untuk Distributor), image_url, payment_proof_image_url, address dan bank_accounts.';
        } else if (!url.endsWith('/options')) {
            item.request.description = registration
                ? 'Riwayat memuat identity, nib, npwp (nullable; bukan input pendaftaran member), image_url, payment_proof_image_url. Detail juga memuat address, bank_accounts serta rekening utama. URL gambar privat pada respons berupa signed URL sementara untuk audience member/admin.'
                : 'Daftar/detail memuat identity, nib, npwp dan image. Detail memuat semua addresses/bank_accounts. Detail/daftar mitra juga memuat payment_proof_image_url dari pengajuan yang telah disetujui.';
        }
        for (const response of item.response || []) {
            if ((create || edit) && !/Tanpa Rekening|Rekening Default Tidak Aktif|NPWP Tidak Didukung/.test(response.name)) {
                response.originalRequest = structuredClone(item.request);
            }
            if (response.code < 200 || response.code >= 300) { continue; }
            const body = JSON.parse(response.body);
            if (url.endsWith('/options')) {
                body.data.identity_types = ['KTP', 'KK', 'SIM', 'BPJS', 'BPJS_KETENAGAKERJAAN', 'PASPOR'];
            } else {
                const list = Array.isArray(body.data?.results);
                for (const data of list ? body.data.results : [body.data]) {
                    if (!data) { continue; }
                    data.nib = sampleDocuments.nib;
                    data.npwp = sampleDocuments.npwp;
                    if (registration) {
                        if (data.level?.code !== 'DST') { data.npwp = null; }
                        data.image_url = sampleDocuments.image_url;
                        data.payment_proof_image_url = proof;
                        data.identity = { type: sampleDocuments.identity_type, number: sampleDocuments.identity_no,
                            image_url: sampleDocuments.identity_image_url, nib: data.nib, npwp: data.npwp };
                        if (data.level?.code === 'AGT') { data.level.name = 'Agen Utama'; }
                        if (data.status?.code === 'requested') { data.applicant.username = ''; }
                        if (list) {
                            for (const key of ['address', 'bank', 'note', 'addresses', 'bank_accounts', 'submitted_by', 'network_distributor']) { delete data[key]; }
                            delete data.applicant.gender;
                            delete data.applicant.birth_date;
                        } else {
                            data.submitted_by ??= null;
                            data.network_distributor ??= null;
                            delete data.addresses;
                            Object.assign(data, contacts(data.applicant.name));
                            if (data.address) { data.address.address = 'Jalan DNY KTP'; }
                            if (data.bank) { data.bank.account_number = data.bank_accounts[0].account_number; }
                        }
                    } else {
                        data.image = sampleDocuments.image_url;
                        data.identity = { type: sampleDocuments.identity_type,
                            [distributor ? 'no' : 'number']: sampleDocuments.identity_no, image: sampleDocuments.identity_image_url,
                            ...(!distributor ? { image_filename: list ? null : '' } : {}) };
                        if (member) { data.payment_proof_image_url = proof; }
                        if (!list) {
                            Object.assign(data, resourceContacts(data.name, distributor));
                            if (distributor && data.address) { data.address.address = data.addresses[0].full_address; }
                            if (distributor && data.bank) { data.bank.account_number = data.bank_accounts[0].account_number; }
                        }
                    }
                }
            }
            response.body = JSON.stringify(body, null, 4);
        }
        if (create) {
            const template = item.response.find((response) => response.code === 422);
            if (template) {
                const failure = structuredClone(template);
                const payload = parsePayload(item.request.body.raw);
                payload.bank_accounts = [{ bank_id: rawVariable('ref_bank_id'), account_name: payload.name,
                    account_number: '0012345678', is_active: false, is_default: true }];
                failure.name = '422 - Rekening Default Tidak Aktif';
                failure.originalRequest = structuredClone(item.request);
                failure.originalRequest.body.raw = stringify(payload);
                const message = 'Rekening default harus berstatus aktif.';
                failure.body = JSON.stringify({ message, error_code: 'validation', errors: { 'bank_accounts.0.is_default': [message] } }, null, 4);
                const existing = item.response.findIndex((response) => response.name === failure.name);
                if (existing >= 0) { item.response[existing] = failure; } else { item.response.push(failure); }
                if (memberRegistration) {
                    const rejected = structuredClone(template);
                    const rejectedPayload = parsePayload(item.request.body.raw);
                    rejectedPayload.npwp = sampleDocuments.npwp;
                    rejected.name = '422 - NPWP Tidak Didukung';
                    rejected.originalRequest = structuredClone(item.request);
                    rejected.originalRequest.body.raw = stringify(rejectedPayload);
                    const rejectedMessage = 'NPWP tidak digunakan pada pendaftaran member.';
                    rejected.body = JSON.stringify({ message: rejectedMessage, error_code: 'validation',
                        errors: { npwp: [rejectedMessage] } }, null, 4);
                    const rejectedIndex = item.response.findIndex((response) => response.name === rejected.name);
                    if (rejectedIndex >= 0) { item.response[rejectedIndex] = rejected; } else { item.response.push(rejected); }
                }
            }
            const success = item.response.find((response) => response.code >= 200 && response.code < 300);
            if (success) {
                const optional = structuredClone(success);
                const payload = parsePayload(item.request.body.raw);
                for (const key of ['bank_id', 'bank_account_name', 'bank_account_number', 'bank_city', 'bank_branch']) { delete payload[key]; }
                payload.bank_accounts = [];
                optional.name = `${success.code} - Tanpa Rekening`;
                optional.originalRequest = structuredClone(item.request);
                optional.originalRequest.body.raw = stringify(payload);
                const body = JSON.parse(optional.body);
                body.data.bank_accounts = [];
                body.data.bank = registration ? null : { bank_id: 0, bank_name: null, account_name: null, account_number: null, city: null, branch: null };
                optional.body = JSON.stringify(body, null, 4);
                const existing = item.response.findIndex((response) => response.name === optional.name);
                if (existing >= 0) { item.response[existing] = optional; } else { item.response.push(optional); }
            }
        }
    }
}

function updateDistributorApprovals() {
    const requests = [];
    function collect(items) {
        for (const item of items) {
            if (item.item) { collect(item.item); } else if (item.request) { requests.push(item); }
        }
    }
    collect(collection.item);
    const findRequest = (url, method = 'GET') => requests.find((item) => item.request.method === method && item.request.url.raw.split('?')[0] === `{{base_url}}${url}`);
    const workflow = 'Pengajuan RSL oleh Agen Utama wajib disetujui Distributor langsung terlebih dahulu. Selama distributor_approval.status.code = pending/rejected, pengajuan tidak tampil di daftar/detail/hitungan approval admin dan admin tidak bisa memprosesnya. Persetujuan Distributor hanya meneruskan ke admin, belum membuat akun atau mengirim kredensial. Pengajuan langsung oleh Distributor (termasuk memilih Agen sebagai sponsor) dan data lama tetap memakai alur admin sebelumnya. next_approval = distributor/admin/null; status.code tetap requested sampai keputusan final. Filter distributor_approval_status mendukung pending/approved/rejected/not_required.';
    const emptyApproval = { status: { code: 'not_required', label: 'Tidak memerlukan persetujuan Distributor' }, distributor: null, note: null, processed_at: null };
    for (const item of requests.filter((item) => /\/(admin\/partnership|member\/network)\/registrations(?:[/?]|$)/.test(item.request.url.raw))) {
        const baseDescription = (item.request.description || '').split(`\n\n${workflow}`)[0];
        item.request.description = `${baseDescription}\n\n${workflow}`;
        if (item.request.url.raw.endsWith('/options')) { continue; }
        for (const response of item.response || []) {
            if (response.code < 200 || response.code >= 300) { continue; }
            const body = JSON.parse(response.body);
            for (const data of Array.isArray(body.data?.results) ? body.data.results : [body.data]) {
                if (!data?.status) { continue; }
                data.next_approval = data.status.code === 'requested' ? 'admin' : null;
                data.distributor_approval = structuredClone(emptyApproval);
            }
            response.body = JSON.stringify(body, null, 4);
        }
    }
    const detail = findRequest('/member/network/registrations/{{registration_id}}');
    const sample = JSON.parse(detail.response.find((response) => response.code === 200).body).data;
    function applicant(status = 'pending') {
        const data = structuredClone(sample);
        const distributor = { id: 1, code: '0001/0000/0000', name: 'Distributor Peninjau' };
        const agent = { id: 2, code: '0001/0001/0000', name: 'Agen Pengaju', level_code: 'AGT' };
        Object.assign(data, {
            level: { id: 3, code: 'RSL', name: 'Reseller' }, sponsor: agent,
            submitted_by: { ...agent, level_name: 'Agen Utama' },
            network_distributor: { ...distributor, level_code: 'DST', level_name: 'Distributor' },
            status: status === 'rejected' ? { code: 'rejected', label: 'Ditolak' } : { code: 'requested', label: 'Menunggu' },
            next_approval: status === 'pending' ? 'distributor' : status === 'approved' ? 'admin' : null,
            processed_by: null, processed_at: null, submitted_at: '2026-10-13T10:00:00+07:00',
            note: status === 'rejected' ? 'Bukti transfer belum sesuai.' : null,
            distributor_approval: {
                status: { code: status, label: status === 'pending' ? 'Menunggu persetujuan Distributor' : status === 'approved' ? 'Disetujui Distributor' : 'Ditolak Distributor' },
                distributor, note: status === 'pending' ? null : status === 'approved' ? 'Dokumen telah diperiksa.' : 'Bukti transfer belum sesuai.',
                processed_at: status === 'pending' ? null : '2026-10-13T11:00:00+07:00',
            },
        });
        data.applicant.username = '';
        return data;
    }
    const statuses = { 200: 'OK', 401: 'Unauthorized', 403: 'Forbidden', 404: 'Not Found', 422: 'Unprocessable Entity' };
    function example(item, name, code, body, payload) {
        const originalRequest = structuredClone(item.request);
        if (payload !== undefined) { originalRequest.body.raw = stringify(payload); }
        return { name, originalRequest, status: statuses[code], code, _postman_previewlanguage: 'json',
            header: [{ key: 'Content-Type', value: 'application/json' }], cookie: [], body: JSON.stringify(body, null, 4) };
    }
    function saveExample(item, response) {
        const existing = item.response.findIndex((saved) => saved.name === response.name);
        if (existing >= 0) { item.response[existing] = response; } else { item.response.push(response); }
    }
    const create = findRequest('/member/network/registrations', 'POST');
    saveExample(create, example(create, '200 - Agen Menunggu Distributor', 200, { success: true, message: 'Pendaftaran mitra berhasil diajukan.', data: applicant() }));
    for (const action of ['approve', 'reject']) {
        const item = findRequest(`/admin/partnership/registrations/{{registration_id}}/${action}`, 'POST');
        saveExample(item, example(item, '422 - Belum Disetujui Distributor', 422, {
            message: 'Pengajuan harus disetujui Distributor terlebih dahulu.', error_code: 'process_error',
        }));
    }
    const adminDetail = findRequest('/admin/partnership/registrations/{{registration_id}}');
    saveExample(adminDetail, example(adminDetail, '404 - Belum Masuk Approval Admin', 404, { message: 'Data yang diminta tidak ditemukan.', error_code: 'process_error' }));

    const pendingList = applicant();
    for (const key of ['submitted_by', 'network_distributor', 'address', 'bank', 'bank_accounts', 'note']) { delete pendingList[key]; }
    delete pendingList.applicant.gender;
    delete pendingList.applicant.birth_date;
    const list = findRequest('/member/network/registrations');
    const listBody = JSON.parse(list.response.find((response) => response.code === 200).body);
    listBody.data.results = [pendingList];
    const endpoints = [
        { name: 'Daftar Approval Distributor', method: 'GET', suffix: '', message: 'Daftar persetujuan pendaftaran mitra berhasil dimuat.', data: listBody.data },
        { name: 'Detail Approval Distributor', method: 'GET', suffix: '/{{registration_id}}', message: 'Detail persetujuan pendaftaran mitra berhasil dimuat.', data: applicant() },
        { name: 'Setujui Pendaftaran', method: 'POST', suffix: '/{{registration_id}}/approve', message: 'Pendaftaran mitra disetujui Distributor dan diteruskan ke admin.', data: applicant('approved'), payload: { note: 'Dokumen telah diperiksa.' } },
        { name: 'Tolak Pendaftaran', method: 'POST', suffix: '/{{registration_id}}/reject', message: 'Pendaftaran mitra ditolak Distributor.', data: applicant('rejected'), payload: { note: 'Bukti transfer belum sesuai.' } },
    ];
    const folder = { name: 'Approval Pendaftaran (Distributor)', description: workflow, item: endpoints.map((endpoint) => {
        const path = `member/network/registration-approvals${endpoint.suffix}`;
        const request = { auth: { type: 'bearer', bearer: [{ key: 'token', value: '{{member_access_token}}', type: 'string' }] },
            method: endpoint.method, header: [{ key: 'Accept', value: 'application/json' }],
            url: { raw: `{{base_url}}/${path}`, host: ['{{base_url}}'], path: path.split('/') },
            description: workflow + '\n\nToken member harus milik Distributor yang ditugaskan. Distributor lain mendapat 404; Agen/Reseller mendapat 403. Approval note opsional; penolakan note wajib, maksimal 1000 karakter. Tidak menerima dokumen ulang atau pengubahan status/ID Distributor lewat body. Daftar memuat riwayat yang ditugaskan kepada Distributor tersebut; hapus filter pending untuk melihat semua keputusan.' };
        if (endpoint.payload) {
            request.header.push({ key: 'Content-Type', value: 'application/json' });
            request.body = { mode: 'raw', raw: stringify(endpoint.payload), options: { raw: { language: 'json' } } };
        }
        if (endpoint.suffix === '') {
            request.url.query = [{ key: 'filter[distributor_approval_status]', value: 'pending' }, { key: 'sort', value: '-id' }, { key: 'page', value: '1' }, { key: 'limit', value: '10' }];
            request.url.raw += '?filter[distributor_approval_status]=pending&sort=-id&page=1&limit=10';
        }
        const item = { name: endpoint.name, request, response: [] };
        item.response.push(example(item, '200 - Berhasil', 200, { success: true, message: endpoint.message, data: endpoint.data }),
            example(item, '401 - Belum Login', 401, { message: 'Silakan masuk untuk melanjutkan.', error_code: 'process_error' }),
            example(item, '403 - Bukan Distributor', 403, { message: 'Anda tidak memiliki akses untuk melakukan proses ini.', error_code: 'process_error' }));
        if (endpoint.suffix) {
            item.response.push(example(item, '404 - Pengajuan Tidak Ditemukan di Jaringan Distributor', 404, { message: 'Data yang diminta tidak ditemukan.', error_code: 'process_error' }));
        }
        if (endpoint.method === 'POST') {
            item.response.push(example(item, '422 - Sudah Diproses Distributor', 422, { message: 'Persetujuan Distributor sudah diproses.', error_code: 'process_error' }));
            if (endpoint.suffix.endsWith('/reject')) {
                item.response.push(example(item, '422 - Alasan Penolakan Wajib', 422, { message: 'Alasan penolakan wajib diisi.', error_code: 'validation', errors: { note: ['Alasan penolakan wajib diisi.'] } }, {}));
                item.response.push(example(item, '422 - Pengajuan Sudah Ditolak', 422, { message: 'Pengajuan sudah diproses dan tidak dapat diproses kembali.', error_code: 'process_error' }));
            }
        }
        return item;
    }) };
    const memberFolder = collection.item.find((item) => item.name === 'Member');
    const networkFolder = memberFolder.item.find((item) => item.name === 'Jaringan');
    const existing = networkFolder.item.findIndex((item) => item.name === folder.name);
    if (existing >= 0) { networkFolder.item[existing] = folder; } else { networkFolder.item.push(folder); }
    updated += endpoints.length;
}

updateItems(collection.item);
updateDistributorApprovals();
collection.variable ??= [];
for (const key of ['province_id', 'city_id', 'district_id', 'subdistrict_id']) {
    const domicileKey = `domicile_${key}`;
    if (!collection.variable.some(variable => variable.key === domicileKey)) {
        const original = collection.variable.find(variable => variable.key === key);
        collection.variable.push({ key: domicileKey, value: original?.value ?? '', type: 'string',
            description: `Wilayah domisili Distributor; ${key} biasa digunakan untuk alamat KTP.` });
    }
}
fs.writeFileSync(collectionPath, `${JSON.stringify(collection, null, 2)}\n`);
console.log(`${updated} request pendaftaran, approval, distributor, profil dan upload diperbarui.`);
