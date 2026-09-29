import "vuetify/styles";
import "@mdi/font/css/materialdesignicons.css";
import { createVuetify } from "vuetify";
import { aliases, mdi } from "vuetify/iconsets/mdi";
import { VDataTable } from "vuetify/components/VDataTable";

/**
 * Shared Vuetify instance configuration.
 * Setiap panel (public, member, admin) menggunakan instance ini.
 * Tema disesuaikan untuk branding perusahaan kosmetik.
 */
export function createAppVuetify() {
    return createVuetify({
        components: {
            VDataTable,
        },
        icons: {
            defaultSet: "mdi",
            aliases,
            sets: { mdi },
        },
        theme: {
            defaultTheme: "light",
            themes: {
                light: {
                    dark: false,
                    colors: {
                        primary: "#A01526",
                        secondary: "#7C0C18",
                        accent: "#D4A05B",
                        success: "#4CAF50",
                        warning: "#FB8C00",
                        error: "#E53935",
                        info: "#1E88E5",
                        background: "#F9F9F9",
                        surface: "#FFFFFF",
                        "surface-variant": "#F5EBEB",
                        "on-surface": "#2D2D2D",
                        "on-surface-variant": "#2D2D2D",
                        "card-secondary": "#e8e8e8",
                    },
                },
                dark: {
                    dark: true,
                    colors: {
                        primary: "#E04A5D",
                        secondary: "#B8283B",
                        accent: "#E6BD8A",
                        success: "#66BB6A",
                        warning: "#FFA726",
                        error: "#EF5350",
                        info: "#42A5F5",
                        background: "#181415",
                        surface: "#261F20",
                        "surface-variant": "#3D3133",
                        "on-surface": "#F5EBEB",
                        "on-surface-variant": "#F5EBEB",
                    },
                },
            },
        },
        defaults: {
            VBtn: {
                rounded: "lg",
                variant: "flat",
            },
            VCard: {
                rounded: "lg",
                elevation: 0,
            },
            VTextField: {
                variant: "outlined",
                density: "comfortable",
                rounded: "lg",
            },
            VTextarea: {
                variant: "outlined",
                density: "comfortable",
                rounded: "lg",
            },
            VSelect: {
                variant: "outlined",
                density: "comfortable",
                rounded: "lg",
            },
            VChip: {
                rounded: "lg",
            },
            VDataTable: {
                hover: true,
            },
        },
    });
}
