// SearchForm filters taxonomy types
export type TaxonomyType = 'lokasi_pekerjaan' | 'gender' | 'pendidikan'

// WordPress custom post types from MetaBox
export enum PostTypesMetabox {
  Lowongan = 'lowongan',
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

export type StatusPekerjaanString = 'Normal' | 'Urgent' | 'Pinned';

export type StatusPekerjaanNumber = 0 | 1 | 2 | 3;

export interface Taxonomies {
  perusahaan?: string | string[] | (null | undefined);
  'kategori_lowongan'?: string | string[] | (null | undefined);
  'lokasi_pekerjaan'?: string | string[] | (null | undefined);
  'jenis_pekerjaan'?: string | string[] | (null | undefined);
  gender?: string | string[] | (null | undefined);
  pendidikan?: string | string[] | (null | undefined);
}

export interface CustomFields {
  nama_perusahaan: string | (null | undefined);
  tentang_perusahaan: string | (null | undefined);
  deskripsi_pekerjaan: string | (null | undefined);
  umur_min: number | (null | undefined);
  umur_max: number | (null | undefined);
  pengalaman: number | (null | undefined);
  persyaratan: string | (null | undefined);
  cara_melamar: string | (null | undefined);
  benefit: string | (null | undefined);
  gaji_minimal: number | (null | undefined);
  gaji_maksimal: number | (null | undefined);
  deadline: string | (null | undefined);
  email_kontak: string | (null | undefined);
  nomor_kontak: string | (null | undefined);
  situs_kontak: string | (null | undefined);
  social_media: string | (null | undefined);
  status_pekerjaan: StatusPekerjaanNumber | (null | undefined);
}

export interface MetaBox extends Taxonomies, CustomFields { }