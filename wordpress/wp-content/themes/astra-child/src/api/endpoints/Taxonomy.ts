import type { TaxonomyTermsResponse, TaxonomyTerm } from '@/types'
import { container } from '@/inversify.config'
import { ApiClient } from '@/api'

export interface TaxonomyApiInterface {
  getAllTerms(): Promise<TaxonomyTermsResponse>
  getTermsByType(type: string): Promise<TaxonomyTerm[]>
}

export const taxonomyApi: TaxonomyApiInterface = {
  /**
   * Get all taxonomy terms at once
   */
  async getAllTerms(): Promise<TaxonomyTermsResponse> {
    return await container.get<ApiClient>("ApiClient").get<TaxonomyTermsResponse>('/taxonomies/')
  },
  async getTermsByType(type: string): Promise<TaxonomyTerm[]> {
    return await container.get<ApiClient>("ApiClient").get<TaxonomyTerm[]>(`/taxonomies/${type}`)
  }
}