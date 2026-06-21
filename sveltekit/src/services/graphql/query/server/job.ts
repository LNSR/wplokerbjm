import { graphql } from "@/services/graphql/config/tada";
import { FRAGMENT_JOB_SUMMARY_FIELDS } from "../shared/job";

export const GET_JOB_DETAIL = graphql(`
  query GetJobDetail($slug: String!) {
    jobDetail(slug: $slug) {
        id
        title
        # slug
        # permalink
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
        dpNonce
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

export const GET_RANK_MATH_HEAD = graphql(`
  query GetRankMathHead($url: String!) {
    rankMathHead(url: $url)
  }
`);
