import type { MetaBox } from './wordpress/MetaBox';
import type { WPBasePost } from './wordpress/Wordpress';
import type { JobSummary, JobContactRow, BaseJobSearch } from './Shared';
import type { SearchTitle } from './Search';

// ! API response types

type JobDetailPostMetaData = Omit<WPBasePost, keyof Pick<WPBasePost, 'permalink' | 'post_type'>>;
export interface JobDetailResponse extends JobDetailPostMetaData, Pick<MetaBox, 'nama_perusahaan' | 'tentang_perusahaan' | 'deskripsi_pekerjaan' | 'persyaratan' | 'cara_melamar' | 'benefit' | 'social_media'>
{
  dpNonce?: string | null; // Nonce for plugin 'Yoast Duplicate Post'
  ringkasanPekerjaan?: JobSummary;
  contacts?: JobContactRow;
  post_time: string;
}

export interface JobSchemaResponse
{
  schemas: (string | null)[];
  type: "ItemList" | "JobPosting";
}

// Extended response for initial search operations
export interface SearchResponse extends BaseJobSearch
{
  title?: SearchTitle
  shouldScroll?: boolean
  total: number
}


/**
 * @interface RankMathHeadData
 * @internal isnt really used, but useful to recognize shape
 * @remarks Rankmath API returns string in fact
 */
export interface RankMathHeadData
{
  title?: string;
  description?: string;
  canonical?: string;
  robots?: string;
  keywords?: string;
  author?: string;
  og_title?: string;
  og_description?: string;
  og_image?: string;
  og_image_secure_url?: string;
  og_image_width?: string;
  og_image_height?: string;
  og_image_alt?: string;
  og_image_type?: string;
  og_locale?: string;
  og_type?: string;
  og_url?: string;
  og_site_name?: string;
  article_publisher?: string;
  og_updated_time?: string;
  og_video?: string;
  og_audio?: string;
  og_determiner?: string;
  twitter_title?: string;
  twitter_description?: string;
  twitter_image?: string;
  twitter_card?: string;
  twitter_label1?: string;
  twitter_data1?: string;
  twitter_label2?: string;
  twitter_data2?: string;
  twitter_site?: string;
  twitter_creator?: string;
  // Twitter App Card fields
  twitter_app_name_iphone?: string;
  twitter_app_id_iphone?: string;
  twitter_app_url_iphone?: string;
  twitter_app_name_ipad?: string;
  twitter_app_id_ipad?: string;
  twitter_app_url_ipad?: string;
  twitter_app_name_googleplay?: string;
  twitter_app_id_googleplay?: string;
  twitter_app_url_googleplay?: string;
  twitter_app_description?: string;
  twitter_app_country?: string;
  // Twitter Player Card fields
  twitter_player?: string;
  twitter_player_width?: string;
  twitter_player_height?: string;
  twitter_player_stream?: string;
  twitter_player_stream_content_type?: string;
  fb_app_id?: string;
  fb_admins?: string;
  article_author?: string;
  article_published_time?: string;
  article_modified_time?: string;
  article_section?: string;
  article_tag?: string;
  // Webmaster verification tags
  google_verify?: string;
  bing_verify?: string;
  baidu_verify?: string;
  yandex_verify?: string;
  pinterest_verify?: string;
  norton_verify?: string;
  schema?: Record<string, any> | Record<string, any>[];
}