interface UseRICOptions {
    timeout?: number;
    fallbackDelay?: number;
    fallback?: "timeout" | "animationFrame";
};

export function useRIC(
    callback: (deadline?: IdleDeadline) => void,
    { timeout, fallbackDelay = 0, fallback }: UseRICOptions = {},
): number
{
    if (typeof window === "undefined")
    {
        console.warn("useRIC called in a non-browser environment. Callback will not be scheduled.");
        return -1;
    }
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