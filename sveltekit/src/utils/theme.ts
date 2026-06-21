import type { ThemeScriptData } from "@/types";

export function isTheme(value: string | null | undefined): value is ThemeScriptData["themeList"] {
    const VALID_THEMES: ThemeScriptData["themeList"][] = ["dark", "light", "lavender"];
    return value !== null && VALID_THEMES.includes(value as ThemeScriptData["themeList"]);
}

export function localStorageThemeActions(action: { save?: ThemeScriptData["themeList"], get?: boolean }) {

    const { save, get } = action;
    const KEY: ThemeScriptData["localStorageKey"] = "wplokerbjm-theme";
    const actions = {
        save: (theme: ThemeScriptData["themeList"]) => { return localStorage.setItem(KEY, theme); },
        get: () => {
            const storedTheme = localStorage.getItem(KEY) as ThemeScriptData["themeList"] | null;
            return isTheme(storedTheme) ? storedTheme : null;
        },
    }

    if (save) return void window.scheduler.postTask(() => actions.save(save), { priority: "background" });
    if (get) return actions.get();
}


export function prefersDarkMode(): boolean {
    try {
        return window.matchMedia?.("(prefers-color-scheme: dark)")?.matches ?? false;
    } catch (error) {
        console.error("Error checking prefers-color-scheme:", error);
        return false;
    }
}

export function applyThemeAttribute(theme: ThemeScriptData["themeList"]): void {
    const root = document.documentElement;
    const attributeName: ThemeScriptData["elements"]["attribute"] = "data-theme";
    const className: ThemeScriptData["elements"]["class"] = "wplokerbjm-dark-mode-enable";
    root.setAttribute(attributeName, theme);
    root.classList.toggle(className, theme === "dark");
}

export function applyThemeViewTransition(theme: ThemeScriptData["themeList"], cb?: () => void): void {
    if (
        typeof document.startViewTransition !== "function" ||
        document.activeViewTransition ||
        window.matchMedia("(prefers-reduced-motion: reduce)").matches
    ) return void window.requestAnimationFrame(() => { applyThemeAttribute(theme); cb?.(); });


    return void document.startViewTransition?.(() => {
        applyThemeAttribute(theme); cb?.();
    });
}