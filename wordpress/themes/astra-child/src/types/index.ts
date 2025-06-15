// Re-export API types
export type * from './api'
export type * from './job'

// Component types
export interface Suggestion {
  id: number
  title: string
}

// Use TaxonomyTerm from API types instead of separate Term interface
export type { TaxonomyTerm as Term } from './api'

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

export interface ComponentConfig {
  selector: string
  component: any
  onMount?: (element: Element, props: any) => void
}