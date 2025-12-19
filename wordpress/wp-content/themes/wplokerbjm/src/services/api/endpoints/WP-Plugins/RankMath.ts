export interface RankMathApiInterface {
  getHead(url: string): Promise<{ success: boolean; head: string }>
}

export class RankMathApi implements RankMathApiInterface {
  /**
   * Get RankMath head data for a URL
   */
  async getHead(url: string): Promise<{ success: boolean; head: string }> {
    const response = await fetch(`${window.location.origin}/wp-json/rankmath/v1/getHead?url=${encodeURIComponent(url)}`, {
      credentials: 'include'
    })

    if (!response.ok) {
      throw new Error(`Failed to fetch RankMath head data: ${response.statusText}`)
    }

    return await response.json()
  }
}

export const rankMathApi = new RankMathApi()