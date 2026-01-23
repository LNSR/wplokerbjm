import { gql } from 'urql';

export const GET_AUTO_SUGGESTIONS = gql`
  query GetAutoSuggestions($query: String!) {
    autoSuggestions(query: $query)
  }
`;

export const GET_CAROUSEL = gql`
  query GetCarousel {
    carousel {
      jobs {
        id
        title
        slug
        nama_perusahaan
        ringkasanPekerjaan {
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
        deadline
        status_pekerjaan
        permalink
        post_time
      }
      totalJobs
    }
  }
`;

export const GET_LOAD_MORE = gql`
  query GetLoadMore($paged: Int, $context: String, $filters: JobFiltersInput) {
    loadMore(paged: $paged, context: $context, filters: $filters) {
      jobs {
        id
        title
        slug
        nama_perusahaan
        ringkasanPekerjaan {
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
        deadline
        status_pekerjaan
        permalink
        post_time
      }
      context
      filters {
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
      total
      maxNumPages
    }
  }
`;

export const GET_JOB_GRID = gql`
  query GetJobGrid($paged: Int, $context: String, $title: String, $total_jobs: Int, $filters: JobFiltersInput) {
    jobGrid(paged: $paged, context: $context, title: $title, total_jobs: $total_jobs, filters: $filters) {
      jobs {
        id
        title
        slug
        nama_perusahaan
        ringkasanPekerjaan {
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
        deadline
        status_pekerjaan
        permalink
        post_time
      }
      total
      maxNumPages
      filters {
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
    }
  }
`;

export const GET_JOB_DETAIL = gql`
  query GetJobDetail($slug: String!) {
    jobDetail(slug: $slug) {
      job {
        id
        title
        slug
        nama_perusahaan
        tentang_perusahaan
        ringkasanPekerjaan {
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
        deadline
        status_pekerjaan
        permalink
        post_time
        duplicateNonce
      }
    }
  }
`;

export const GET_JOB_SCHEMA = gql`
  query GetJobSchema($ids: [Int!]!) {
    jobSchema(ids: $ids) {
      schemas
    }
  }
`;

export const GET_SEARCH_JOBS = gql`
  query GetSearchJobs($filters: JobFiltersInput!) {
    searchJobs(filters: $filters) {
      jobs {
        id
        title
        slug
        nama_perusahaan
        ringkasanPekerjaan {
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
        deadline
        status_pekerjaan
        permalink
        post_time
      }
      context
      title
      filters {
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
      total
      maxNumPages
    }
  }
`;

export const SYNC_BOOKMARK = gql`
  query SyncBookmark($ids: [Int!]!) {
    syncBookmark(ids: $ids) {
      id
      title
      slug
      nama_perusahaan
      ringkasanPekerjaan {
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
      deadline
      status_pekerjaan
      permalink
      post_time
    }
  }
`;

export const GET_RANK_MATH_HEAD = gql`
  query GetRankMathHead($url: String!) {
    rankMathHead(url: $url)
  }
`;