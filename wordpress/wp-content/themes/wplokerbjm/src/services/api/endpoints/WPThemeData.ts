import type { WPLokerBJMThemedData } from '@/types'
import { apiClient } from '@/services/api'

export interface WPThemeDataApiInterface {
  getThemeData(): Promise<WPLokerBJMThemedData>
}

export const wpThemeDataApi: WPThemeDataApiInterface = {
  /**
   * Get theme data from REST API
   */
  async getThemeData(): Promise<WPLokerBJMThemedData> {
    return (await apiClient.get<WPLokerBJMThemedData>('/theme-data/')).data
  }
}