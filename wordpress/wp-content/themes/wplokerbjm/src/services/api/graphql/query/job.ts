import { graphql } from 'gql.tada';

const FRAGMENT_JOB_SUMMARY_FIELDS = graphql(`
  fragment JobSummaryField on JobSummary {
    jenis_pekerjaan
    pendidikan
    gender
    lokasi_pekerjaan
    pengalaman
    gaji_minimal
    gaji_maksimal
    umur_min
    umur_max
    deadline
  }
`);

const FRAGMENT_JOB_FILTER_FIELDS = graphql(`
  fragment JobFilterFields on JobFilters {
    cari
    lokasi_pekerjaan
    gender
    pendidikan
    sort {
      value
      label
    }
    context
  }
`);

export const FRAGMENT_JOB_CARD_FIELDS = graphql(`
  fragment JobCardFields on Job {
    id
    title
    slug
    nama_perusahaan
    ringkasanPekerjaan {
      ...JobSummaryField
    }
    deadline
    status_pekerjaan
    permalink
    post_time
  }
`, [FRAGMENT_JOB_SUMMARY_FIELDS]);

export const GET_AUTO_SUGGESTIONS = graphql(`
  query GetAutoSuggestions($query: String!) {
    autoSuggestions(query: $query)
  }
`);

export const GET_CAROUSEL = graphql(`
  query GetCarousel {
    carousel {
      jobs {
        ...JobCardFields
      }
      totalJobs
    }
  }
`, [FRAGMENT_JOB_CARD_FIELDS]);

export const GET_LOAD_MORE = graphql(`
  query GetLoadMore($paged: Int, $context: String, $filters: JobFiltersInput) {
    loadMore(paged: $paged, context: $context, filters: $filters) {
      jobs {
        ...JobCardFields
      }
      context
      filters {
        ...JobFilterFields
      }
      total
      maxNumPages
    }
  }
`, [FRAGMENT_JOB_CARD_FIELDS, FRAGMENT_JOB_FILTER_FIELDS]);

export const GET_JOB_GRID = graphql(`
  query GetJobGrid($paged: Int, $context: String, $title: String, $total_jobs: Int, $filters: JobFiltersInput) {
    jobGrid(paged: $paged, context: $context, title: $title, total_jobs: $total_jobs, filters: $filters) {
      jobs {
        ...JobCardFields
      }
      total
      maxNumPages
      filters {
        ...JobFilterFields
      }
    }
  }
`, [FRAGMENT_JOB_CARD_FIELDS, FRAGMENT_JOB_FILTER_FIELDS]);

export const GET_JOB_DETAIL = graphql(`
  query GetJobDetail($slug: String!) {
    jobDetail(slug: $slug) {
        id
        title
        slug
        nama_perusahaan
        tentang_perusahaan
        ringkasanPekerjaan {
          ...JobSummaryField
        }
        deskripsi_pekerjaan
        persyaratan
        cara_melamar
        benefit
        contacts {
          email_kontak
          nomor_kontak
          situs_kontak
        }
        social_media
        post_time
        duplicateNonce
      }
  }
`, [FRAGMENT_JOB_SUMMARY_FIELDS]);

export const GET_JOB_SCHEMA = graphql(`
  query GetJobSchema($ids: [Int], $slug: String, $type: String) {
    jobSchema(ids: $ids, slug: $slug, type: $type) {
      schemas
    }
  }
`);

export const GET_SEARCH_JOBS = graphql(`
  query GetSearchJobs($filters: JobFiltersInput!) {
    searchJobs(filters: $filters) {
      jobs {
        ...JobCardFields
      }
      context
      title
      filters {
        ...JobFilterFields
      }
      total
      maxNumPages
    }
  }
`, [FRAGMENT_JOB_CARD_FIELDS, FRAGMENT_JOB_FILTER_FIELDS]);

export const SYNC_BOOKMARK = graphql(`
  query SyncBookmark($ids: [Int!]!) {
    syncBookmark(ids: $ids) {
      ...JobCardFields
    }
  }
`, [FRAGMENT_JOB_CARD_FIELDS, FRAGMENT_JOB_SUMMARY_FIELDS]);

export const GET_RANK_MATH_HEAD = graphql(`
  query GetRankMathHead($url: String!) {
    rankMathHead(url: $url)
  }
`);
