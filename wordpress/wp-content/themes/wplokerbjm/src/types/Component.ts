import type { SearchContext, SearchFilters } from '@/types';
import type { Component } from 'svelte';

export interface LayoutProps {
  logo: string;
}

export enum ThemeName {
  Light = 'light',
  Dark = 'dark',
}

export type ComponentModule = { default?: Component };

export type ComponentFactory = () => Promise<ComponentModule>;

/**
 * * Component loader accepted by the mounter:
 *  - a factory: () => import('...')                 (lazy)
 *  - an import() Promise: import('...')             (lazy)
 *  - a module object from top-level await: await import('...') (eager, but not a raw constructor)
 *
 * Do NOT pass a raw/static component constructor (e.g. `MyComponent`) directly.
 * ! This is no longer supported to avoid many edge-cases and errors.
 */
export type ComponentLoader = ComponentFactory | Promise<ComponentModule> | ComponentModule;

export interface ComponentConfig {
  selector: string;
  component: ComponentLoader;
}

export interface JobSummary {
  jenis_pekerjaan_taxo?: string | string[] | null;
  pendidikan_taxo?: string | string[] | null;
  pengalaman?: number | null;
  gender_taxo?: string | string[] | null;
  gaji_minimal?: number | null;
  gaji_maksimal?: number | null;
  umur_min?: number | null;
  umur_max?: number | null;
  lokasi_taxo?: string | string[] | null;
  deadline?: string | null;
}

export interface SocialMediaItem {
  platform: string;
  username?: string;
  icon: Component;
  url: string;
  color?: string;
}

export interface CardJob {
  id?: number;
  slug?: string;
  title: string;
  nama_perusahaan?: string;
  ringkasanPekerjaan?: JobSummary | null;
  deadline?: string | null;
  statusjob?: (0 | 2 | 3) | undefined; // 0: Normal, 2: Urgent, 3: Pinned
  permalink?: string;
  post_time?: string;
}

export interface JobContactRow {
  email_kontak?: string[];
  nomor_kontak?: string[];
  situs_kontak?: string[];
};


// Props for the JobCard component (shared type)
export interface JobCardProps {
  jobdata?: CardJob | undefined;
  variant?: 'featured' | 'carousel' | 'detail';
  permalink?: string | undefined;
  onClick?: (slug: string, event: MouseEvent, index: number) => void;
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