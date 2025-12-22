import type { WPLokerBJMThemedData } from '@/types';
import { parseProps, removePropsScriptFromElement, isDevelopmentMode } from '@/utils';
import { APIService } from '@/services/APIService';

export class WPThemeDataStore {
  private static cachedThemeData: WPLokerBJMThemedData | undefined;

  public static getThemeData(): WPLokerBJMThemedData | undefined {
    if (this.cachedThemeData !== undefined) return this.cachedThemeData;

    if (typeof document === 'undefined') return undefined;

    // First, try to get from JSON props
    const props = parseProps(document, 'id="wp-theme-data"') as unknown as WPLokerBJMThemedData;
    if (Object.keys(props).length > 0) {
      this.cachedThemeData = props;
      !isDevelopmentMode() && removePropsScriptFromElement(document, 'id="wp-theme-data"');
      return this.cachedThemeData;
    }

    // Fallback to API for headless setups
    this.fetchFromAPI().then(data => {
      if (data) {
        this.cachedThemeData = data;
      }
    }).catch(err => {
      console.warn('Failed to fetch theme data from API:', err);
    });

    return undefined;
  }

  private static async fetchFromAPI(): Promise<WPLokerBJMThemedData | undefined> {
    try {
      return await APIService.getThemeData()
    } catch (error) {
      console.error('Error fetching theme data from API:', error);
      return undefined;
    }
  }
}