// Component types
import type { SearchContext, SearchFilters, CardJob } from '@/types';

export interface LayoutProps {
 header: string;
}

export interface ComponentConfig {
  selector: string
  component: any
  /** If true, ensure the app router is installed on the shared root app before mounting */
  useRouter?: boolean
}

// Props for the JobCard component (shared type)
export interface JobCardProps {
  jobdata: CardJob
  variant: 'featured' | 'carousel'
  permalink: string
  selected?: boolean
  onClick?: (slug: string, event: MouseEvent, index: number) => void
}

export interface JobGridProps {
  jobs?: CardJob[];
  maxNumPages?: number;
  context?: SearchContext;
  filters?: Partial<SearchFilters>;
  title?: string;
  totalJobs?: number;
}

export interface CarouselProps {
  jobs: CardJob[];
  totalJobs?: number;
}

export interface SortOption {
  value: 'desc' | 'asc'
  label: string
}

// Component props interfaces
export interface SearchFormProps {
  currentSearch?: string
  currentLokasi?: string[]
  currentGender?: string[]
  currentPendidikan?: string[]
  currentSort: SortOption
  archiveLink?: string
}