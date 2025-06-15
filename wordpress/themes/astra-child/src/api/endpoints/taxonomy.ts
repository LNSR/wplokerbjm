import { apiClient } from '../client'
import type { TaxonomyTermsResponse, TaxonomyTerm } from '@/types'

export interface TaxonomyApiInterface {
  getAllTerms(): Promise<TaxonomyTermsResponse>
  getTermsByType(type: string): Promise<TaxonomyTerm[]>
}

export const taxonomyApi: TaxonomyApiInterface = {
  /**
   * Get all taxonomy terms at once
   */
  async getAllTerms(): Promise<TaxonomyTermsResponse> {
    return await apiClient.get<TaxonomyTermsResponse>('/taxonomies/')
  },
  async getTermsByType(type: string): Promise<TaxonomyTerm[]> {
    return await apiClient.get<TaxonomyTerm[]>(`/taxonomies/${type}`)
  }
}