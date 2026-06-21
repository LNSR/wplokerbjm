export function isJobGridEl(): HTMLElement | null
{
    return typeof window !== "undefined" ? document.getElementById("job-grid") : null;
}