import { useTheme } from "vuetify";

export function useThemeSettings() {
    const theme = useTheme();

    function toggleTheme() {
        const nextTheme = theme.global.current.value.dark ? "light" : "dark";
        
        if (typeof theme.change === "function") {
            theme.change(nextTheme);
        } else {
            theme.global.name.value = nextTheme;
        }
    }

    return {
        toggleTheme,
    };
}
