export type OrderPrintDocumentType = "po" | "invoice";

interface OrderPrintFormatters {
    formatDate: (value: any) => string;
    formatPrice: (value: any) => string;
}

const escapeHtml = (value: unknown): string =>
    String(value ?? "-").replace(/[&<>"']/g, (character) => {
        const entities: Record<string, string> = {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;",
        };

        return entities[character];
    });

export const orderPrintDocumentStyles = (
    type: OrderPrintDocumentType,
): string =>
    type === "invoice"
        ? "<style>@media print { @page { size: landscape; margin: 1cm; } body { -webkit-print-color-adjust: exact; } }</style>"
        : "<style>@media print { body { -webkit-print-color-adjust: exact; } }</style>";

export const buildOrderPrintDocument = (
    data: any,
    type: OrderPrintDocumentType,
    { formatDate, formatPrice }: OrderPrintFormatters,
): string => {
    if (type === "po") {
        return `
            <div style="font-family: sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; font-size: 12px; color: #000;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <h2 style="margin: 0; font-size: 16px;">[KOP SURAT SARANA PEMESAN/DISTRIBUTOR / TOKO] SURAT</h2>
                    <h2 style="margin: 0; font-size: 16px;">PESANAN KOSMETIKA (PURCHASE ORDER)</h2>
                    <p style="margin: 5px 0; font-size: 14px;">Nomor: ${escapeHtml(data.code)}</p>
                </div>

                <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                    <div style="width: 48%;">
                        <table style="width: 100%; font-size: 12px;">
                            <tr><td colspan="3"><strong>Data Pemesan (Sarana):</strong></td></tr>
                            <tr><td style="width: 140px;">Nama Toko / Klinik</td><td style="width: 10px;">:</td><td>${escapeHtml(data.buyer?.name)}</td></tr>
                            <tr><td>NIB / Izin Usaha</td><td>:</td><td>........................................................</td></tr>
                            <tr><td>Nama Penanggung Jawab</td><td>:</td><td>${escapeHtml(data.buyer?.destination?.name || "-")}</td></tr>
                            <tr><td style="vertical-align: top;">Alamat Lengkap</td><td style="vertical-align: top;">:</td><td>${escapeHtml(data.buyer?.destination?.address || "-")}, ${escapeHtml(data.buyer?.destination?.subdistrict?.name || "-")}, ${escapeHtml(data.buyer?.destination?.district?.name || "-")}, ${escapeHtml(data.buyer?.destination?.city?.name || "-")}, ${escapeHtml(data.buyer?.destination?.province?.name || "-")}</td></tr>
                            <tr><td>No. Telp / WA</td><td>:</td><td>${escapeHtml(data.buyer?.destination?.phone || "-")}</td></tr>
                        </table>
                    </div>
                    <div style="width: 48%; border-left: 1px solid #000; padding-left: 15px;">
                        <table style="width: 100%; font-size: 12px;">
                            <tr><td colspan="3"><strong>Ditujukan Kepada Penyalur:</strong></td></tr>
                            <tr><td colspan="3"><strong>${escapeHtml(data.seller?.name || "PT DEENYE BERKAH ABADI")}</strong></td></tr>
                            <tr><td colspan="3">${escapeHtml(data.seller?.origin?.address || "-")}, ${escapeHtml(data.seller?.origin?.subdistrict?.name || "-")}, ${escapeHtml(data.seller?.origin?.district?.name || "-")}, ${escapeHtml(data.seller?.origin?.city?.name || "-")}, ${escapeHtml(data.seller?.origin?.province?.name || "-")}</td></tr>
                            <tr><td style="width: 80px;">Email / Telp</td><td style="width: 10px;">:</td><td>${escapeHtml(data.seller?.origin?.phone || "-")}</td></tr>
                        </table>
                    </div>
                </div>

                <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;" border="1">
                    <thead>
                        <tr style="background-color: #f2f2f2;">
                            <th style="text-align: center; padding: 8px;">No</th>
                            <th style="text-align: left; padding: 8px;">Nama Produk / Varian</th>
                            <th style="text-align: center; padding: 8px;">Kemasan<br>/ Isi</th>
                            <th style="text-align: center; padding: 8px;">No. BPOM</th>
                            <th style="text-align: center; padding: 8px;">Jumlah</th>
                            <th style="text-align: left; padding: 8px;">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${(data.details || [])
                            .map(
                                (item: any, index: number) => `
                            <tr>
                                <td style="text-align: center; padding: 8px;">${index + 1}</td>
                                <td style="padding: 8px;">${escapeHtml(item.product?.name)}</td>
                                <td style="text-align: center; padding: 8px;">-</td>
                                <td style="text-align: center; padding: 8px;">${escapeHtml(item.product?.bpom_number || "-")}</td>
                                <td style="text-align: center; padding: 8px;">${item.quantity} pcs</td>
                                <td style="padding: 8px;">-</td>
                            </tr>
                        `,
                            )
                            .join("")}
                    </tbody>
                </table>

                <div style="margin-bottom: 30px;">
                    <strong>Ketentuan Pengiriman:</strong><br>
                    1. Wajib melampirkan Faktur Penjualan & Surat Jalan resmi yang memuat Nomor Batch.<br>
                    2. Alamat Pengiriman: ${escapeHtml(data.buyer?.destination?.address || "-")}, ${escapeHtml(data.buyer?.destination?.subdistrict?.name || "-")}, ${escapeHtml(data.buyer?.destination?.district?.name || "-")}, ${escapeHtml(data.buyer?.destination?.city?.name || "-")}, ${escapeHtml(data.buyer?.destination?.province?.name || "-")}<br>
                    3. PIC Penerima di Lokasi: ${escapeHtml(data.buyer?.destination?.name || "-")} (No. HP: ${escapeHtml(data.buyer?.destination?.phone || "-")})<br>
                </div>

                <div style="display: flex; justify-content: space-between;">
                    <div style="width: 50%;">
                        Kota Pemesan, ........................ 20...<br>
                        <strong>Pemesan,</strong><br><br><br><br>
                        (....................................................)<br>
                        Penanggung Jawab Sarana / Stempel
                    </div>
                </div>
            </div>
        `;
    }

    return `
        <div style="font-family: sans-serif; max-width: 100%; margin: 0 auto; padding: 20px; font-size: 10px; color: #000;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid black; padding-bottom: 5px; margin-bottom: 10px;">
                <h2 style="margin: 0; font-size: 18px;">PT.Deenye Berkah Abadi</h2>
                <h2 style="margin: 0; font-size: 18px;">FAKTUR PENJUALAN</h2>
                <div style="font-size: 14px;">NO FAKTUR : <strong>${escapeHtml(data.invoice_number || "-")}</strong></div>
            </div>

            <div style="display: flex; justify-content: space-between; margin-bottom: 10px; padding-bottom: 10px;">
                <div style="width: 25%;">
                    <table style="width: 100%; font-size: 11px;">
                        <tr><td style="width: 80px; vertical-align: top;">Ijin Cbg PBF</td><td style="vertical-align: top;">:</td><td style="vertical-align: top;">-</td></tr>
                        <tr><td style="vertical-align: top;">Ijin Cbg PAK</td><td style="vertical-align: top;">:</td><td style="vertical-align: top;">-</td></tr>
                        <tr><td style="vertical-align: top;">Srt. CDOB OL</td><td style="vertical-align: top;">:</td><td style="vertical-align: top;">-</td></tr>
                        <tr><td style="vertical-align: top;">Srt. CDOB CCP</td><td style="vertical-align: top;">:</td><td style="vertical-align: top;">-</td></tr>
                    </table>
                </div>
                <div style="width: 25%;">
                    <table style="width: 100%; font-size: 11px;">
                        <tr><td style="width: 60px;">Telp</td><td>:</td><td>-</td></tr>
                        <tr><td>NPWP DNY</td><td>:</td><td>-</td></tr>
                        <tr><td>NPWP Lggn</td><td>:</td><td>-</td></tr>
                    </table>
                </div>
                <div style="width: 25%;">
                    <table style="width: 100%; font-size: 11px;">
                        <tr><td style="width: 60px;">Tanggal</td><td>:</td><td>${escapeHtml(formatDate(data.ordered_at))}</td></tr>
                        <tr><td>Jth Tempo</td><td>:</td><td>-</td></tr>
                        <tr><td>TOP</td><td>:</td><td>30 Hari</td></tr>
                        <tr><td>No SP</td><td>:</td><td>${escapeHtml(data.code || "-")}</td></tr>
                        <tr><td>No DO</td><td>:</td><td>${escapeHtml(data.shipping?.delivery_note_number || "-")}</td></tr>
                        <tr><td>Kd Lggn</td><td>:</td><td>${escapeHtml(data.buyer?.code)}</td></tr>
                    </table>
                </div>
                <div style="width: 25%;">
                    <strong>Kepada Yth.</strong><br>
                    ${escapeHtml(data.buyer?.name)}<br>
                    ${escapeHtml(data.buyer?.destination?.address || "-")}, ${escapeHtml(data.buyer?.destination?.subdistrict?.name || "-")}, ${escapeHtml(data.buyer?.destination?.district?.name || "-")}, ${escapeHtml(data.buyer?.destination?.city?.name || "-")}, ${escapeHtml(data.buyer?.destination?.province?.name || "-")}
                </div>
            </div>

            <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px; border-bottom: 1px dashed black;" border="0">
                <thead>
                    <tr style="border-top: 1px dashed black; border-bottom: 1px dashed black;">
                        <th style="text-align: left; padding: 4px;">JUMLAH</th>
                        <th style="text-align: left; padding: 4px;">KODE BARANG</th>
                        <th style="text-align: left; padding: 4px;">NAMA BARANG</th>
                        <th style="text-align: left; padding: 4px;">BATCH</th>
                        <th style="text-align: left; padding: 4px;">ED</th>
                        <th style="text-align: right; padding: 4px;">HARGA SATUAN</th>
                        <th style="text-align: right; padding: 4px;">GROSS</th>
                        <th style="text-align: right; padding: 4px;">DISC(%)</th>
                        <th style="text-align: right; padding: 4px;">SUB TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    ${(data.details || [])
                        .flatMap((item: any) => {
                            if (
                                item.product?.batches &&
                                item.product.batches.length > 0
                            ) {
                                return item.product.batches.map((batch: any) => ({
                                    code: item.product.code,
                                    name: item.product.name,
                                    batch_number: batch.batch_number,
                                    expiry_date: batch.expiry_date,
                                    quantity: batch.quantity,
                                    price: item.price,
                                    discount_percent:
                                        item.discount_percent || 0,
                                    net_price: item.net_price || item.price,
                                }));
                            }

                            return [
                                {
                                    code: item.product?.code,
                                    name: item.product?.name,
                                    batch_number:
                                        item.product?.batch_number || null,
                                    expiry_date:
                                        item.product?.expiry_date || null,
                                    quantity: item.quantity,
                                    price: item.price,
                                    discount_percent:
                                        item.discount_percent || 0,
                                    net_price: item.net_price || item.price,
                                },
                            ];
                        })
                        .map(
                            (row: any) => `
                            <tr>
                                <td style="padding: 4px;">${row.quantity} Pcs</td>
                                <td style="padding: 4px;">${escapeHtml(row.code)}</td>
                                <td style="padding: 4px;">${escapeHtml(row.name)}</td>
                                <td style="padding: 4px;">${escapeHtml(row.batch_number || "-")}</td>
                                <td style="padding: 4px;">${row.expiry_date ? escapeHtml(formatDate(row.expiry_date)) : "-"}</td>
                                <td style="text-align: right; padding: 4px;">Rp${formatPrice(row.price)}</td>
                                <td style="text-align: right; padding: 4px;">Rp${formatPrice(row.price * row.quantity)}</td>
                                <td style="text-align: right; padding: 4px;">${Number(row.discount_percent).toFixed(2)}</td>
                                <td style="text-align: right; padding: 4px;">Rp${formatPrice(row.net_price * row.quantity)}</td>
                            </tr>
                        `,
                        )
                        .join("")}
                </tbody>
            </table>

            <div style="border-bottom: 1px dashed black; padding-bottom: 5px; margin-bottom: 5px;">
                <em>Terbilang : ....................................................</em>
            </div>

            <div style="display: flex; justify-content: space-between;">
                <div style="width: 25%; font-size: 10px;">
                    Produk, Jumlah, Batch, Harga dan Kondisi Barang telah diperiksa & sesuai
                    <br><br><br><br><br><br><br>
                    <div style="border-top: 1px solid black; text-align: center; width: 80%;">Penerima</div>
                </div>
                <div style="width: 40%; font-size: 10px; border-left: 1px dashed black; border-right: 1px dashed black; padding: 0 10px;">
                    <strong>*</strong> Pembayaran Cek/Giro (an PT.Deenye Berkah Abadi), baru dianggap lunas setelah diuangkan / dipindahbukukan.<br>
                    <strong>*</strong> Barang yang telah diserahkan tidak dapat ditukar dengan barang lain / dikembalikan, kecuali ada perjanjian tertulis sebelumnya & barang kadaluarsa.<br>
                    Rek. a/n PT Deenye Berkah Abadi - Mandiri : 1410001 777762
                </div>
                <div style="width: 15%; text-align: center; font-size: 10px; padding: 0 10px;">
                    Png. Jawab PBF
                    <br><br><br><br><br><br><br>
                    <div style="border-top: 1px solid black;">apt. ________________</div>
                </div>
                <div style="width: 20%; font-size: 10px; padding-left: 10px;">
                    <table style="width: 100%; text-align: right;">
                        <tr><td style="text-align: left;">Gross</td><td>Rp</td><td>${formatPrice(data.totals?.product_total || 0)}</td></tr>
                        <tr><td style="text-align: left;">Discount</td><td>Rp</td><td>${formatPrice(data.totals?.discount_value || 0)}</td></tr>
                        <tr><td style="text-align: left;">Subtotal</td><td>Rp</td><td>${formatPrice((data.totals?.product_total || 0) - (data.totals?.discount_value || 0))}</td></tr>
                        <tr><td style="text-align: left;">Cash Disc 0.00%</td><td>Rp</td><td>0</td></tr>
                        <tr><td style="text-align: left;">Netto</td><td>Rp</td><td>${formatPrice((data.totals?.product_total || 0) - (data.totals?.discount_value || 0))}</td></tr>
                        <tr><td style="text-align: left;">PPN</td><td>Rp</td><td>0</td></tr>
                        <tr style="font-weight: bold; border-top: 1px dashed black;"><td style="text-align: left;">Harus Dibayar</td><td>Rp</td><td>${formatPrice(data.totals?.grand_total || 0)}</td></tr>
                        <tr><td style="text-align: left;">DPP Lain</td><td>Rp</td><td>0</td></tr>
                    </table>
                </div>
            </div>
        </div>
    `;
};
