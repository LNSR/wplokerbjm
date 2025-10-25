import type { WPThemeData } from '@/types'
import { apiClient } from '@/services/api'

export interface WPThemeDataApiInterface {
  getThemeData(): Promise<WPThemeData>
}

export const wpThemeDataApi: WPThemeDataApiInterface = {
  /**
   * Get theme data from REST API
   */
  async getThemeData(): Promise<WPThemeData> {
    return (await apiClient.get<WPThemeData>('/theme-data/')).data
  }
}