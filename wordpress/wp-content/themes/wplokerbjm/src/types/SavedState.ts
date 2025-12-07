// Homepage job grid saved state types
import type { SearchContext, SearchFilters, Job } from '@/types';
export interface SearchState {
  jobs: Job[]
  context: SearchContext
  title: string
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