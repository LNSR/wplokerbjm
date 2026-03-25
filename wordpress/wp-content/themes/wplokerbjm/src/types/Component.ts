import type { SearchTitle ,SearchContext, SearchFilters, MetaBox, WPBasePost } from '@/types';
import type { Component } from 'svelte';
export interface LayoutProps {
  logo: string;
}

export interface ComponentConfig {
  selector: string;
  component: Component | Promise<Component>;
}

export type JobSummary = Pick<MetaBox, 'jenis_pekerjaan' | 'pendidikan' | 'lokasi_pekerjaan' | 'gender' | 'pengalaman' | 'gaji_minimal' | 'gaji_maksimal' | 'umur_min' | 'umur_max' | 'deadline'>;

export interface SocialMediaItem {
  platform: string;
  username?: string;
  icon: Component;
  url: string;
  color?: string;
}

export interface CardJob extends WPBasePost, Pick<MetaBox, 'jenis_pekerjaan' | 'pendidikan' | 'lokasi_pekerjaan' | 'gender' | 'nama_perusahaan' | 'deadline' | 'status_pekerjaan'> {
  ringkasanPekerjaan?: JobSummary | null;
}

export type JobContactRow = Pick<MetaBox, 'email_kontak' | 'nomor_kontak' | 'situs_kontak'> | null | undefined;


// Props for the JobCard component (shared type)
export interface JobCardProps {
  jobdata?: CardJob | undefined;
  variant?: 'featured' | 'carousel' | 'bookmark' | 'detail';
  permalink?: string | undefined;
  onClick?: (slug: string, event: MouseEvent, index: number) => void;
  isSelected?: boolean;
}

export interface JobGridProps {
  jobs?: CardJob[];
  maxNumPages?: number;
  context?: SearchContext;
  filters?: Partial<SearchFilters>;
  title?: SearchTitle;
  total?: number;
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