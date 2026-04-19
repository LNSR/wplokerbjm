export function isJobGridEl()
{
    return typeof window !== "undefined" ? document.getElementById("job-grid") : null;
}