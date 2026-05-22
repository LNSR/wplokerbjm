import type { ThemeScriptData } from "@/types";
import {
    applyThemeAttribute,
    prefersDarkMode,
    localStorageThemeActions,
} from "@/utils/theme";

void function inlineThemeScript()
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