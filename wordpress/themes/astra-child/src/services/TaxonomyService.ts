import { taxonomyApi } from '../api/endpoints/taxonomy'
import type { TaxonomyTerm } from '@/types/api'

export class TaxonomyService {
  static async fetchLokasiTerms(): Promise<TaxonomyTerm[]> {
    try {
      return await taxonomyApi.getTermsByType('lokasi')
    } catch (error) {
      console.error('Failed to fetch lokasi terms:', error)
      throw new Error('Failed to fetch lokasi terms')
    }
  }

  static async fetchGenderTerms(): Promise<TaxonomyTerm[]> {
    try {
      return await taxonomyApi.getTermsByType('gender')
    } catch (error) {
      console.error('Failed to fetch gender terms:', error)
      throw new Error('Failed to fetch gender terms')
    }
  }

  static async fetchPendidikanTerms(): Promise<TaxonomyTerm[]> {
    try {
      return await taxonomyApi.getTermsByType('pendidikan')
    } catch (error) {
      console.error('Failed to fetch pendidikan terms:', error)
      throw new Error('Failed to fetch pendidikan terms')
    }
  }
}