import type { TaxonomyTermsResponse, TaxonomyTerm } from '@/types'
import { TaxonomyType } from '@/types'
import { container } from '@/inversify.config'
import { ApiClient } from '@/api'

export interface TaxonomyApiInterface {
  getAllTerms(): Promise<TaxonomyTermsResponse>
  getTermsByType(type: TaxonomyType): Promise<TaxonomyTerm[]>
}

export const taxonomyApi: TaxonomyApiInterface = {
  /**
   * Get all taxonomy terms at once
   */
  async getAllTerms(): Promise<TaxonomyTermsResponse> {
    return (await container.get<ApiClient>("ApiClient").get<TaxonomyTermsResponse>('/taxonomies/')).data
  },
  async getTermsByType(type: TaxonomyType): Promise<TaxonomyTerm[]> {
    return (await container.get<ApiClient>("ApiClient").get<TaxonomyTerm[]>(`/taxonomies/${type}`)).data
  }
}