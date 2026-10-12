<template>
    <div class="digital-card-container">
        <!-- Controls Header: Toggle Sisi & Tombol Unduh -->
        <div class="card-action-bar">
            <div class="side-toggle">
                <button
                    type="button"
                    class="toggle-btn"
                    :class="{ active: currentSide === 'front' }"
                    @click="currentSide = 'front'"
                >
                    <v-icon
                        icon="mdi-card-account-details-outline"
                        size="16"
                        class="mr-1"
                    />
                    Sisi Depan
                </button>
                <button
                    type="button"
                    class="toggle-btn"
                    :class="{ active: currentSide === 'back' }"
                    @click="currentSide = 'back'"
                >
                    <v-icon icon="mdi-flip-to-back" size="16" class="mr-1" />
                    Sisi Belakang
                </button>
            </div>

            <button
                type="button"
                class="download-btn"
                :disabled="isDownloading"
                @click="downloadPdf"
            >
                <v-progress-circular
                    v-if="isDownloading"
                    indeterminate
                    size="14"
                    width="2"
                    class="mr-1"
                />
                <v-icon v-else icon="mdi-download" size="16" class="mr-1" />
                <span>{{ isDownloading ? "Mengunduh..." : "Unduh PDF" }}</span>
            </button>
        </div>

        <!-- 3D Flip Card Container -->
        <div
            class="card-scene"
            @click="toggleSide"
            title="Klik kartu untuk membalik sisi"
        >
            <div
                class="card-flipper"
                :class="{ 'is-flipped': currentSide === 'back' }"
            >
                <!-- SISI DEPAN -->
                <div class="card-face card-front">
                    <img
                        :src="cardConfig.frontImage"
                        alt="Member Card Front"
                        class="card-bg-img"
                    />

                    <!-- Overlay Data Member -->
                    <div class="card-overlay-content">
                        <!-- Tingkat Kemitraan -->
                        <div class="member-level-text">
                            {{ cardConfig.levelTitle }}
                        </div>

                        <!-- Nama Member -->
                        <div class="member-name-text">
                            {{ member?.name || "Nama Mitra" }}
                        </div>

                        <!-- Kode Member -->
                        <div class="member-code-text">
                            {{ member?.code || "0000 0000 0000" }}
                        </div>

                        <!-- Foto Profil Circle -->
                        <div class="member-photo-frame">
                            <img
                                v-if="member?.image"
                                :src="member.image"
                                :alt="member?.name"
                                class="member-avatar-img"
                            />
                            <div v-else class="member-avatar-placeholder">
                                <v-icon
                                    icon="mdi-account"
                                    size="36"
                                    color="#888"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SISI BELAKANG -->
                <div class="card-face card-back">
                    <img
                        :src="cardConfig.backImage"
                        alt="Member Card Back"
                        class="card-bg-img"
                    />
                </div>
            </div>
        </div>

        <div class="card-hint">
            <v-icon icon="mdi-gesture-tap" size="14" class="mr-1" />
            <span>Klik kartu untuk membalik sisi depan / belakang</span>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed } from "vue";
import type { MemberProfile } from "@/member/types/profile";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const props = defineProps<{
    member?: MemberProfile["member"] | null;
}>();

const snackbar = useSnackbarStore();
const currentSide = ref<"front" | "back">("front");
const isDownloading = ref(false);

const toggleSide = () => {
    currentSide.value = currentSide.value === "front" ? "back" : "front";
};

// Konfigurasi template kartu berdasarkan level mitra
// reseller -> reseller
// agent -> AU (Agen Utama)
// distributor -> DU (Distributor Utama)
const cardConfig = computed(() => {
    const levelCode = (props.member?.level?.code || "").toUpperCase();
    const levelName = (props.member?.level?.name || "").toLowerCase();

    if (levelCode === "DST" || levelName.includes("distributor")) {
        return {
            levelTitle: "Distributor",
            frontImage: "/images/member card DU.png",
            backImage: "/images/member card DU blakang.png",
            prefix: "DU",
        };
    }

    if (
        levelCode === "AGT" ||
        levelName.includes("agent") ||
        levelName.includes("agen")
    ) {
        return {
            levelTitle: "Agen Utama",
            frontImage: "/images/member card AU.png",
            backImage: "/images/member card AU blakang.png",
            prefix: "AU",
        };
    }

    // Default: Reseller
    return {
        levelTitle: "Reseller",
        frontImage: "/images/member card reseller.png",
        backImage: "/images/member card reseller blakang.png",
        prefix: "Reseller",
    };
});

/**
 * Helper untuk memuat gambar ke HTMLImageElement
 */
const loadImage = (src: string): Promise<HTMLImageElement> => {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.crossOrigin = "anonymous";
        img.onload = () => resolve(img);
        img.onerror = (e) => reject(e);
        img.src = src;
    });
};

/**
 * Render sisi depan ke Canvas resolusi asli (1004 x 638)
 */
const renderFrontCanvas = async (): Promise<HTMLCanvasElement> => {
    const canvas = document.createElement("canvas");
    canvas.width = 1004;
    canvas.height = 638;
    const ctx = canvas.getContext("2d");
    if (!ctx) throw new Error("Gagal membuat context canvas.");

    // 1. Gambar background kartu
    try {
        const bgImg = await loadImage(cardConfig.value.frontImage);
        ctx.drawImage(bgImg, 0, 0, 1004, 638);
    } catch (e) {
        console.error("Gagal memuat background kartu depan:", e);
    }

    // 2. Gambar teks Level (Serif bold Burgundy)
    ctx.font =
        "bold 46px 'Playfair Display', 'Times New Roman', Georgia, serif";
    ctx.fillStyle = "#8a242b";
    ctx.textBaseline = "middle";
    ctx.fillText(cardConfig.value.levelTitle, 93, 382);

    // 3. Gambar teks Nama Member
    ctx.font =
        "bold 32px -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
    ctx.fillStyle = "#222222";
    const memberName = props.member?.name || "Nama Mitra";
    ctx.fillText(memberName, 93, 452);

    // 4. Gambar teks Kode Member
    ctx.font =
        "bold 32px -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
    ctx.fillStyle = "#222222";
    const memberCode = props.member?.code || "0000 0000 0000";
    ctx.fillText(memberCode, 93, 516);

    // 5. Gambar foto profil di dalam lingkaran (cx: 832.5, cy: 439.5, radius: 125)
    const cx = 832.5;
    const cy = 439.5;
    const r = 125;

    ctx.save();
    ctx.beginPath();
    ctx.arc(cx, cy, r, 0, Math.PI * 2);
    ctx.closePath();
    ctx.clip();

    if (props.member?.image) {
        try {
            const avatarImg = await loadImage(props.member.image);
            // Gambar dengan aspect ratio cover
            const aspect = avatarImg.width / avatarImg.height;
            let drawW = r * 2;
            let drawH = r * 2;
            let drawX = cx - r;
            let drawY = cy - r;

            if (aspect > 1) {
                drawW = drawH * aspect;
                drawX = cx - drawW / 2;
            } else {
                drawH = drawW / aspect;
                drawY = cy - drawH / 2;
            }

            ctx.drawImage(avatarImg, drawX, drawY, drawW, drawH);
        } catch (e) {
            // Fallback avatar background jika CORS atau gagal muat
            ctx.fillStyle = "#e0e0e0";
            ctx.fill();
            ctx.font = "bold 72px sans-serif";
            ctx.fillStyle = "#888888";
            ctx.textAlign = "center";
            ctx.fillText(
                memberName.charAt(0).toUpperCase() || "M",
                cx,
                cy + 24,
            );
        }
    } else {
        // Fallback default avatar
        ctx.fillStyle = "#f0f0f0";
        ctx.fill();
        ctx.font = "bold 72px sans-serif";
        ctx.fillStyle = "#8a242b";
        ctx.textAlign = "center";
        ctx.fillText(memberName.charAt(0).toUpperCase() || "M", cx, cy + 24);
    }
    ctx.restore();

    return canvas;
};

/**
 * Render sisi belakang ke Canvas resolusi asli (1004 x 638)
 */
const renderBackCanvas = async (): Promise<HTMLCanvasElement> => {
    const canvas = document.createElement("canvas");
    canvas.width = 1004;
    canvas.height = 638;
    const ctx = canvas.getContext("2d");
    if (!ctx) throw new Error("Gagal membuat context canvas.");

    try {
        const bgImg = await loadImage(cardConfig.value.backImage);
        ctx.drawImage(bgImg, 0, 0, 1004, 638);
    } catch (e) {
        console.error("Gagal memuat background kartu belakang:", e);
    }

    return canvas;
};

/**
 * Helper mengubah Data URL JPEG menjadi Uint8Array
 */
const dataUrlToBytes = (dataUrl: string): Uint8Array => {
    const base64 = dataUrl.split(",")[1];
    const binary = atob(base64);
    const len = binary.length;
    const bytes = new Uint8Array(len);
    for (let i = 0; i < len; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes;
};

/**
 * Generator PDF 2 halaman standar murni dalam format PDF 1.4
 */
const createTwoPagePdfBlob = (
    img1Bytes: Uint8Array,
    img2Bytes: Uint8Array,
    width = 1004,
    height = 638,
): Blob => {
    const stream1 = `q ${width} 0 0 ${height} 0 0 cm /Img1 Do Q`;
    const stream2 = `q ${width} 0 0 ${height} 0 0 cm /Img2 Do Q`;

    const obj1 = `<< /Type /Catalog /Pages 2 0 R >>`;
    const obj2 = `<< /Type /Pages /Kids [3 0 R 6 0 R] /Count 2 >>`;
    const obj3 = `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${width} ${height}] /Contents 4 0 R /Resources << /XObject << /Img1 5 0 R >> >> >>`;
    const obj4 = `<< /Length ${stream1.length} >>\nstream\n${stream1}\nendstream`;
    const obj6 = `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${width} ${height}] /Contents 7 0 R /Resources << /XObject << /Img2 8 0 R >> >> >>`;
    const obj7 = `<< /Length ${stream2.length} >>\nstream\n${stream2}\nendstream`;

    const parts: (string | Uint8Array)[] = [];
    const offsets: number[] = [];
    let currentOffset = 0;

    const write = (strOrBytes: string | Uint8Array) => {
        parts.push(strOrBytes);
        if (typeof strOrBytes === "string") {
            currentOffset += strOrBytes.length;
        } else {
            currentOffset += strOrBytes.byteLength;
        }
    };

    write("%PDF-1.4\n%\xFF\xFF\xFF\xFF\n");

    const writeObj = (num: number, header: string, dataBytes?: Uint8Array) => {
        offsets[num] = currentOffset;
        write(`${num} 0 obj\n`);
        if (dataBytes) {
            write(`${header}\nstream\n`);
            write(dataBytes);
            write("\nendstream\nendobj\n");
        } else {
            write(`${header}\nendobj\n`);
        }
    };

    writeObj(1, obj1);
    writeObj(2, obj2);
    writeObj(3, obj3);
    writeObj(4, obj4);
    writeObj(
        5,
        `<< /Type /XObject /Subtype /Image /Width ${width} /Height ${height} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ${img1Bytes.length} >>`,
        img1Bytes,
    );
    writeObj(6, obj6);
    writeObj(7, obj7);
    writeObj(
        8,
        `<< /Type /XObject /Subtype /Image /Width ${width} /Height ${height} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ${img2Bytes.length} >>`,
        img2Bytes,
    );

    const xrefOffset = currentOffset;
    write(`xref\n0 9\n0000000000 65535 f \n`);
    for (let i = 1; i <= 8; i++) {
        const offStr = String(offsets[i]).padStart(10, "0");
        write(`${offStr} 00000 n \n`);
    }
    write(
        `trailer\n<< /Size 9 /Root 1 0 R >>\nstartxref\n${xrefOffset}\n%%EOF\n`,
    );

    return new Blob(parts, { type: "application/pdf" });
};

/**
 * Handle proses download PDF
 */
const downloadPdf = async () => {
    if (isDownloading.value) return;
    isDownloading.value = true;

    try {
        // 1. Render kedua sisi ke Canvas
        const [frontCanvas, backCanvas] = await Promise.all([
            renderFrontCanvas(),
            renderBackCanvas(),
        ]);

        // 2. Export ke JPEG
        const frontJpeg = frontCanvas.toDataURL("image/jpeg", 0.95);
        const backJpeg = backCanvas.toDataURL("image/jpeg", 0.95);

        // 3. Buat file PDF
        const frontBytes = dataUrlToBytes(frontJpeg);
        const backBytes = dataUrlToBytes(backJpeg);
        const pdfBlob = createTwoPagePdfBlob(frontBytes, backBytes, 1004, 638);

        // 4. Unduh file PDF ke perangkat user
        const url = URL.createObjectURL(pdfBlob);
        const safeCode = (props.member?.code || "member").replace(
            /[^a-zA-Z0-9_-]/g,
            "_",
        );
        const a = document.createElement("a");
        a.href = url;
        a.download = `ID_Card_${cardConfig.value.prefix}_${safeCode}.pdf`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        snackbar.showMessage(
            "Digital Card Mitra berhasil diunduh dalam format PDF.",
        );
    } catch (e: any) {
        console.error("Gagal mengunduh Digital Card:", e);
        snackbar.showMessage(
            "Gagal mengunduh Digital Card. Silakan coba lagi.",
            "error",
        );
    } finally {
        isDownloading.value = false;
    }
};
</script>

<style scoped>
.digital-card-container {
    margin-bottom: 24px;
    width: 100%;
}

.card-action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
}

.side-toggle {
    display: flex;
    background: #f1f3f5;
    padding: 3px;
    border-radius: 8px;
}

.toggle-btn {
    border: none;
    background: transparent;
    padding: 5px 12px;
    font-size: 12px;
    font-weight: 600;
    color: #666;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
}

.toggle-btn.active {
    background: #fff;
    color: var(--primary, #b71c1c);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
}

.download-btn {
    border: 1px solid var(--primary, #b71c1c);
    background: #fff;
    color: var(--primary, #b71c1c);
    padding: 6px 14px;
    font-size: 12px;
    font-weight: 700;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    transition: all 0.2s ease;
}

.download-btn:hover {
    background: #fff0f0;
}

.download-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* 3D Scene Card */
.card-scene {
    perspective: 1000px;
    width: 100%;
    cursor: pointer;
    user-select: none;
}

.card-flipper {
    position: relative;
    width: 100%;
    aspect-ratio: 1004 / 638;
    transform-style: preserve-3d;
    transition: transform 0.6s cubic-bezier(0.4, 0.2, 0.2, 1);
    border-radius: 14px;
    box-shadow:
        0 10px 25px -5px rgba(0, 0, 0, 0.15),
        0 8px 10px -6px rgba(0, 0, 0, 0.1);
}

.card-flipper.is-flipped {
    transform: rotateY(180deg);
}

.card-face {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    border-radius: 14px;
    overflow: hidden;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
}

.card-front {
    z-index: 2;
    transform: rotateY(0deg);
}

.card-back {
    transform: rotateY(180deg);
}

.card-bg-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    pointer-events: none;
}

/* Card Text Overlay */
.card-overlay-content {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.member-level-text {
    position: absolute;
    left: 9.3%;
    top: 59.8%;
    transform: translateY(-50%);
    font-family: "Playfair Display", "Times New Roman", Georgia, serif;
    font-size: clamp(14px, 4.5vw, 25px);
    font-weight: 700;
    color: #8a242b;
    line-height: 1.1;
    letter-spacing: -0.2px;
}

.member-name-text {
    position: absolute;
    left: 9.3%;
    top: 70.8%;
    transform: translateY(-50%);
    font-family:
        -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    font-size: clamp(11px, 3.2vw, 18px);
    font-weight: 700;
    color: #1e1e1e;
    line-height: 1.1;
    max-width: 58%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.member-code-text {
    position: absolute;
    left: 9.3%;
    top: 80.8%;
    transform: translateY(-50%);
    font-family:
        -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    font-size: clamp(11px, 3.2vw, 18px);
    font-weight: 700;
    color: #1e1e1e;
    line-height: 1.1;
    letter-spacing: 0.5px;
}

/* Photo Circle Frame */
/* Center is cx=832.5/1004 = 82.9%, cy=439.5/638 = 68.9%, radius=125/1004 = 12.45% */
.member-photo-frame {
    position: absolute;
    left: 82.9%;
    top: 68.9%;
    transform: translate(-50%, -50%);
    width: 24.9%;
    aspect-ratio: 1;
    border-radius: 50%;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
}

.member-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.member-avatar-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f4f4f4;
}

.card-hint {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 8px;
    font-size: 11px;
    color: #888;
}
</style>
