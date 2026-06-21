import { graphql } from "@/services/graphql/config/tada";

export const FRAGMENT_JOB_SUMMARY_FIELDS = graphql(`
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

export const FRAGMENT_JOB_FILTER_FIELDS = graphql(`
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
    status_pekerjaan
    permalink
    post_time
  }
`, [FRAGMENT_JOB_SUMMARY_FIELDS]);

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
