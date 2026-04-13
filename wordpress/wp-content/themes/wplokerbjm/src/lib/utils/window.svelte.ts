import { page } from "$app/state";
import { browser } from "$app/environment";
import { MediaQuery } from "svelte/reactivity";

const mobileMq = new MediaQuery("(max-width: 767.98px)");

export type UseRICOptions = {
    timeout?: number;
    fallbackDelay?: number;
    fallback?: "timeout" | "animationFrame";
};

export function useRIC(
    callback: (deadline?: IdleDeadline) => void,
    options: UseRICOptions = {},
): number
{
    if (typeof window === "undefined")
    {
        console.warn("useRIC called in a non-browser environment. Callback will not be scheduled.");
        return -1;
    }
    const { timeout, fallbackDelay = 0, fallback = "timeout" } = options;
    const env: Window = window;

    if (typeof env.requestIdleCallback === "function")
    {
        return env.requestIdleCallback(callback, { timeout });
    }

    if (fallback === "animationFrame" && typeof env.requestAnimationFrame === "function")
    {
        env.requestAnimationFrame(() => callback(undefined));
        return -1;
    }

    return env.setTimeout(() => callback(undefined), fallbackDelay);
}

export function isMobile(): boolean
{
    // use try/catch to ignore violation `Outside component initialization`
    try
    {
        if (!browser) return page.data.deviceType.isMobile;
        return mobileMq.current;
    } catch (err)
    {
        console.error("isMobile error:", err);
        return false;
    }
}