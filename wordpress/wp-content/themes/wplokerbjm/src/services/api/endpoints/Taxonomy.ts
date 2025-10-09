import type { TaxonomyTerm } from '@/types'
import { TaxonomyType } from '@/types'
import { apiClient } from '@/services/api'

type TaxonomyTermsResponse = {
  lokasiTerms: TaxonomyTerm[]
  genderTerms: TaxonomyTerm[]
  pendidikanTerms: TaxonomyTerm[]
}


export interface TaxonomyApiInterface {
  getAllTerms(): Promise<TaxonomyTermsResponse>
  getTermsByType(type: TaxonomyType): Promise<TaxonomyTerm[]>
}

export const taxonomyApi: TaxonomyApiInterface = {
  /**
   * Get all taxonomy terms at once
   */
  async getAllTerms(): Promise<TaxonomyTermsResponse> {
    return (await apiClient.get<TaxonomyTermsResponse>('/taxonomies/')).data
  },
  async getTermsByType(type: TaxonomyType): Promise<TaxonomyTerm[]> {
    return (await apiClient.get<TaxonomyTerm[]>(`/taxonomies/${type}`)).data
  }
}