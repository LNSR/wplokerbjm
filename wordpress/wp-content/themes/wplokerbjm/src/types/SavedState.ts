// Homepage job grid saved state types
import type { SearchContext, SearchFilters, CardJob, SearchTitle } from '@/types';
export interface SearchState {
  jobs: CardJob[]
  context: SearchContext
  title: SearchTitle
  totalJobs: number
  maxNumPages: number
  page: number
  filters: SearchFilters
  loading: boolean
  error: string | null
  timestamp?: number
  serverLastJobUpdate?: number
}

export interface CarouselState {
  slideIndex: number
}