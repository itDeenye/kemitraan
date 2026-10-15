const documents = {
    KTP: { title: "KTP", maxLength: 16, hint: "16 digit angka", pattern: /^[0-9]{16}$/ },
    KK: { title: "Kartu Keluarga (KK)", maxLength: 16, hint: "16 digit angka", pattern: /^[0-9]{16}$/ },
    SIM: { title: "SIM", maxLength: 16, hint: "16 digit angka", pattern: /^[0-9]{16}$/ },
    BPJS: { title: "BPJS Kesehatan", maxLength: 13, hint: "13 digit angka", pattern: /^[0-9]{13}$/ },
    BPJS_KETENAGAKERJAAN: {
        title: "BPJS Ketenagakerjaan", maxLength: 16,
        hint: "11 digit KPJ atau 16 digit NIK", pattern: /^(?:[0-9]{11}|[0-9]{16})$/,
    },
    PASPOR: { title: "Paspor", maxLength: 8, hint: "8 karakter huruf atau angka", pattern: /^[A-Za-z0-9]{8}$/ },
};

export function identityNumberSettings(type: string) {
    return documents[type as keyof typeof documents] ?? documents.KTP;
}

export function memberIdentityOptions(types: string[] = Object.keys(documents)) {
    return types.map(value => ({ value, title: documents[value as keyof typeof documents]?.title ?? value }));
}

export function identityNumberRule(type: string, value: unknown, previous?: { type: string; number: string }): true | string {
    if (value === "" || value === null || value === undefined) return true;
    if (previous && type === previous.type && value === previous.number) return true;
    const settings = identityNumberSettings(type);
    return (typeof value === "string" && value.match(settings.pattern)?.[0] === value)
        || `Nomor ${settings.title} harus terdiri dari ${settings.hint}.`;
}
