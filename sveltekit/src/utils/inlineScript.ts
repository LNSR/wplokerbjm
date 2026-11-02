import type { ThemeScriptData } from "#src/types/index.js";
import {
    applyThemeAttribute,
    prefersDarkMode,
    localStorageThemeActions,
} from "#src/utils/theme.js";

void function inlineThemeScript(): void
{
    try
    {
        const stored = localStorageThemeActions({ get: true });
        if (stored) return applyThemeAttribute(stored);
        const chosen: ThemeScriptData[ 'themeList' ] = prefersDarkMode() ? "dark" : (stored ?? "light");
        applyThemeAttribute(chosen);
    } catch (e)
    {
        console.error("fail applying theme preferences", e);
    } finally
    {
        document.currentScript?.remove();
    }
}();