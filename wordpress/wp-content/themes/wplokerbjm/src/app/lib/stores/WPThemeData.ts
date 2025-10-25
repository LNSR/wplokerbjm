import type { WPThemeData } from '@/types';
import { parseProps, removePropsScriptFromElement, isDevelopmentMode } from '@/utils/elements';
import { APIService } from '@/services/APIService';

export class WPThemeDataStore {
  private static cachedThemeData: WPThemeData | undefined;

  public static getThemeData(): WPThemeData | undefined {
    if (this.cachedThemeData !== undefined) return this.cachedThemeData;

    if (typeof document === 'undefined') return undefined;

    // First, try to get from JSON props
    const props = parseProps(document, 'id="wp-theme-data"') as unknown as WPThemeData;
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

  private static async fetchFromAPI(): Promise<WPThemeData | undefined> {
    try {
      return await APIService.getThemeData()
    } catch (error) {
      console.error('Error fetching theme data from API:', error);
      return undefined;
    }
  }
}