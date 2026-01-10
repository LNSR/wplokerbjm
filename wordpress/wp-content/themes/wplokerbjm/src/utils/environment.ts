import type { WPLokerBJMThemedData } from '@/types';
import { parseProps, removePropsScriptFromElement } from '@/utils';
import { APIService } from '@/services/APIService';

let cachedThemeData: WPLokerBJMThemedData | undefined;

export function isDevelopmentMode(): boolean {
    const viteDev = typeof import.meta !== 'undefined' && Boolean((import.meta as any).env?.DEV);
    const wpEnvDev = typeof import.meta !== 'undefined' && (import.meta as any).env?.WP_ENV === 'development';
    return viteDev || wpEnvDev;
}

export function getThemeData(): WPLokerBJMThemedData | undefined {
    if (cachedThemeData !== undefined) return cachedThemeData;
    if (typeof document === 'undefined') return undefined;

    // First, try to get from JSON props
    const props = parseProps(document, 'id="wp-theme-data"') as unknown as WPLokerBJMThemedData;
    if (Object.keys(props).length > 0) {
        cachedThemeData = props;
        !isDevelopmentMode() && removePropsScriptFromElement(document, 'id="wp-theme-data"');
        return cachedThemeData;
    }

    const dataApi = async () => await APIService.getThemeData()

    // Fallback to API for headless setups
    dataApi().then(data => {
        if (data) {
            cachedThemeData = data;
        }
    }).catch(err => {
        console.warn('Failed to fetch theme data from API:', err);
    });

    return undefined;
}