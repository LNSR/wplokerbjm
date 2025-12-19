export function isDevelopmentMode(): boolean {
    const viteDev = typeof import.meta !== 'undefined' && Boolean((import.meta as any).env?.DEV);
    const wpEnvDev = typeof import.meta !== 'undefined' && (import.meta as any).env?.WP_ENV === 'development';
    return viteDev || wpEnvDev;
}