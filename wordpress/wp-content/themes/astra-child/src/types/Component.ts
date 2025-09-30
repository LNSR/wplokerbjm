import type { SearchContext, SearchFilters } from '@/types';
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


export interface JobSummary {
  jenis_pekerjaan_taxo?: string | string[] | null;
  pendidikan_taxo?: string | string[] | null;
  pengalaman?: string | number | null;
  gender_taxo?: string | string[] | null;
  gaji_minimal?: string | number | null;
  gaji_maksimal?: string | number | null;
  umur_min?: string | number | null;
  umur_max?: string | number | null;
  lokasi_taxo?: string | string[] | null;
  deadline?: string | null;
}

// Shape returned by RESTData::getCardData()
export interface CardJob {
  id?: number;
  slug?: string;
  title: string;
  nama_perusahaan?: string;
  ringkasanPekerjaan?: JobSummary | null;
  deadline?: string | null;
  statusjob?: number | string | null;
  permalink?: string;
  post_time?: string;
}

export type DisplayedJob = CardJob & {
  timeAgo: string
  deadlineInfo: { text: string; style: string }
  statusInfo: { label: string; color: string }
}

export interface JobContactRow {
  email_kontak?: string[];
  nomor_kontak?: string[];
  situs_kontak?: string[];
};


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

export type DropdownOption = {
  value: string
  label: string
  children?: DropdownOption[]
  isLoading?: boolean
  hasMoreChildren?: boolean
  loadChildren?: () => Promise<DropdownOption[]>
  __breadcrumbs?: string[]
  __key?: string
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