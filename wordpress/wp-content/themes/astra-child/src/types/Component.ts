import type { SearchContext, SearchFilters, CardJob } from '@/types';
import type { Component, DefineComponent } from 'vue';

export interface LayoutProps {
  logo: string;
}

export type ComponentFactory = () => Promise<{ default?: Component } | Component> | (() => Component | DefineComponent) | Promise<{ default?: Component } | Component>;

export interface ComponentConfig {
  selector: string;
  // component can be a Vue Component, a Promise resolving to a module, or a factory function
  component: Component | DefineComponent | ComponentFactory;
}

// Props for the JobCard component (shared type)
export interface JobCardProps {
  jobdata: CardJob
  variant: 'featured' | 'carousel'
  permalink: string
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

export interface JobCarousel<T = unknown> {
  initSwiper: (slides: T[], onVirtualUpdate?: () => void) => void;
  updateSlides: (slides: T[]) => void;
  mountVirtualSlides: (jobs: CardJob[]) => void;
  getBatchSize: () => number;
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