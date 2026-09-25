import type {
    RouteLocationNormalized,
    RouteLocationRaw,
} from "vue-router";

const ROUTE_ID_PREFIX = "dny";
const ROUTE_ID_PATTERN = /^[1-9]\d*$/;

function normalizeRouteId(value: unknown): string {
    if (Array.isArray(value)) {
        return normalizeRouteId(value[0]);
    }

    return String(value ?? "").trim();
}

function calculateChecksum(id: string): string {
    let remainder = 0;

    for (const character of id) {
        remainder = (remainder * 10 + Number(character)) % 97;
    }

    return ((remainder * 31 + 17) % 97).toString(36);
}

function toBase64Url(value: string): string {
    return btoa(value)
        .replace(/\+/g, "-")
        .replace(/\//g, "_")
        .replace(/=+$/g, "");
}

function fromBase64Url(value: string): string {
    const base64 = value.replace(/-/g, "+").replace(/_/g, "/");
    const padding = "=".repeat((4 - (base64.length % 4)) % 4);

    return atob(`${base64}${padding}`);
}

/**
 * Menyamarkan ID database pada URL frontend. Token ini bersifat obfuscation;
 * otorisasi dan pembatasan akses tetap wajib dilakukan oleh API.
 */
export function encodeRouteId(value: unknown): string {
    const id = normalizeRouteId(value);

    if (!ROUTE_ID_PATTERN.test(id)) {
        return id;
    }

    return toBase64Url(
        `${ROUTE_ID_PREFIX}:${id}:${calculateChecksum(id)}`,
    );
}

/**
 * Mengembalikan token URL menjadi ID asli untuk pemanggilan API. ID numerik
 * lama tetap diterima agar tautan/bookmark sebelum perubahan masih berfungsi.
 */
export function decodeRouteId(value: unknown): string {
    const routeId = normalizeRouteId(value);

    if (ROUTE_ID_PATTERN.test(routeId)) {
        return routeId;
    }

    try {
        const [prefix, id, checksum] = fromBase64Url(routeId).split(":");

        if (
            prefix === ROUTE_ID_PREFIX &&
            ROUTE_ID_PATTERN.test(id) &&
            checksum === calculateChecksum(id)
        ) {
            return id;
        }
    } catch {
        // Token tidak valid akan dikembalikan sebagai string kosong.
    }

    return "";
}

function isRouteIdKey(key: string): boolean {
    return key === "id" || key.endsWith("_id") || key.endsWith("Id");
}

function encodeNumericValue(value: unknown): unknown {
    if (Array.isArray(value)) {
        return value.map((item) => encodeNumericValue(item));
    }

    const normalized = normalizeRouteId(value);

    return ROUTE_ID_PATTERN.test(normalized)
        ? encodeRouteId(normalized)
        : value;
}

/**
 * Membuat redirect pengganti bila URL masih memuat ID numerik mentah.
 */
export function getOpaqueRouteRedirect(
    route: RouteLocationNormalized,
): RouteLocationRaw | null {
    const routeName = route.name as string | symbol | null | undefined;
    const routePath = route.path;
    const routeHash = route.hash;
    const originalParams = route.params as Record<string, unknown>;
    const params: Record<string, unknown> = { ...originalParams };
    const query: Record<string, unknown> = {
        ...(route.query as Record<string, unknown>),
    };
    let changed = false;

    for (const [key, value] of Object.entries(params)) {
        if (!isRouteIdKey(key)) {
            continue;
        }

        const encoded = encodeNumericValue(value);
        if (JSON.stringify(encoded) !== JSON.stringify(value)) {
            params[key] = encoded as string | string[];
            changed = true;
        }
    }

    for (const [key, value] of Object.entries(query)) {
        if (!isRouteIdKey(key)) {
            continue;
        }

        const encoded = encodeNumericValue(value);
        if (JSON.stringify(encoded) !== JSON.stringify(value)) {
            query[key] = encoded as string | string[];
            changed = true;
        }
    }

    if (!changed) {
        return null;
    }

    if (routeName) {
        return {
            name: routeName,
            params,
            query,
            hash: routeHash,
            replace: true,
        } as RouteLocationRaw;
    }

    let path = routePath;
    for (const [key, value] of Object.entries(originalParams)) {
        if (!isRouteIdKey(key) || Array.isArray(value)) {
            continue;
        }

        const encoded = encodeNumericValue(value);
        if (encoded !== value) {
            path = path.replace(
                `/${encodeURIComponent(String(value))}`,
                `/${String(encoded)}`,
            );
        }
    }

    return {
        path,
        query,
        hash: routeHash,
        replace: true,
    } as RouteLocationRaw;
}
