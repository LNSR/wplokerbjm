import type { SearchFilters } from './Search';
import type { MetaBox } from './wordpress/MetaBox';
import type { WPBasePost } from './wordpress/Wordpress';

export type JobSummary = Pick<MetaBox, 'jenis_pekerjaan' | 'pendidikan' | 'lokasi_pekerjaan' | 'gender' | 'pengalaman' | 'gaji_minimal' | 'gaji_maksimal' | 'umur_min' | 'umur_max' | 'deadline'>;

export interface CardJob extends WPBasePost, Pick<MetaBox, 'jenis_pekerjaan' | 'pendidikan' | 'lokasi_pekerjaan' | 'gender' | 'nama_perusahaan' | 'deadline' | 'status_pekerjaan'> {
  ringkasanPekerjaan?: JobSummary | null;
}

export type JobContactRow = Pick<MetaBox, 'email_kontak' | 'nomor_kontak' | 'situs_kontak'> | null | undefined;

export interface SortOption {
  value: 'desc' | 'asc'
  label: string
}

export interface BaseJobSearch
{
  jobs?: CardJob[] | null
  maxNumPages?: number | null
  filters?: SearchFilters
  total?: number | null
}
  

export interface DropdownOption {
  value: string
  label: string
  children?: DropdownOption[]
  isLoading?: boolean
  loadChildren?: () => Promise<DropdownOption[]>
  breadcrumbs?: string[]
  key?: string
}
