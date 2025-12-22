// SearchForm filters taxonomy types
export enum TaxonomyType {
  lokasi = 'lokasi-pekerjaan',
  gender = 'gender',
  pendidikan = 'pendidikan'
}

// WordPress custom post types from MetaBox
export enum PostTypesMetabox {
  lowongan = 'lowongan',
}

export enum SocialMediaPlatform {
  WhatsApp = 'WhatsApp',
  Instagram = 'Instagram',
  Facebook = 'Facebook',
  'X / Twitter' = 'X / Twitter',
  Threads = 'Threads',
  TikTok = 'TikTok',
  LinkedIn = 'LinkedIn',
  Youtube = 'Youtube',
  Telegram = 'Telegram',
}

export interface Taxonomies {
  perusahaan?: string | string[] | null;
  'kategori-lowongan'?: string | string[] | null;
  'lokasi-pekerjaan'?: string | string[] | null;
  'jenis-pekerjaan'?: string | string[] | null;
  gender?: string | string[] | null;
  pendidikan?: string | string[] | null;
}

export type PostTypes = PostTypesMetabox 

export interface CustomFields {
  nama_perusahaan?: string | null;
  tentang_perusahaan?: string | null;
  deskripsi_pekerjaan?: string | null;
  umur_min?: number | null;
  umur_max?: number | null;
  pengalaman?: number | null;
  persyaratan?: string | null;
  cara_melamar?: string | null;
  benefit?: string | null;
  gaji_minimal?: number | null;
  gaji_maksimal?: number | null;
  deadline?: string | null;
  email_kontak?: string[] | null;
  nomor_kontak?: string[] | null;
  situs_kontak?: string[] | null;
  social_media?: Partial<Record<SocialMediaPlatform, string[]>> | null;
  status_pekerjaan?: (1 | 2 | 3) | null;
}

export interface MetaBox extends Taxonomies, CustomFields {}