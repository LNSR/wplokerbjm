/* eslint-disable */
/* prettier-ignore */
import type { TadaDocumentNode, $tada } from 'gql.tada';
import { Kind, OperationTypeNode, DocumentNode, DefinitionNode } from '@0no-co/graphql.web';

declare module 'gql.tada' {
 interface setupCache {
    "\n  query GetAllTerms {\n    taxonomyTerms {\n      lokasiTerms\n      genderTerms\n      pendidikanTerms\n    }\n  }\n":
      TadaDocumentNode<{ taxonomyTerms: { lokasiTerms: unknown; genderTerms: unknown; pendidikanTerms: unknown; } | null; }, {}, void>;
    "\n  query GetLokasiTerms {\n    lokasiTerms\n  }\n":
      TadaDocumentNode<{ lokasiTerms: unknown; }, {}, void>;
    "\n  query GetGenderTerms {\n    genderTerms\n  }\n":
      TadaDocumentNode<{ genderTerms: unknown; }, {}, void>;
    "\n  query GetPendidikanTerms {\n    pendidikanTerms\n  }\n":
      TadaDocumentNode<{ pendidikanTerms: unknown; }, {}, void>;
    "\n  fragment JobSummaryField on JobSummary {\n    jenis_pekerjaan\n    pendidikan\n    gender\n    lokasi_pekerjaan\n    pengalaman\n    gaji_minimal\n    gaji_maksimal\n    umur_min\n    umur_max\n    deadline\n  }\n":
      TadaDocumentNode<{ jenis_pekerjaan: string | null; pendidikan: string | null; gender: string | null; lokasi_pekerjaan: string | null; pengalaman: number | null; gaji_minimal: number | null; gaji_maksimal: number | null; umur_min: number | null; umur_max: number | null; deadline: string | null; }, {}, { fragment: "JobSummaryField"; on: "JobSummary"; masked: true; }>;
    "\n  fragment JobFilterFields on JobFilters {\n    cari\n    lokasi_pekerjaan\n    gender\n    pendidikan\n    sort {\n      value\n      label\n    }\n    context\n  }\n":
      TadaDocumentNode<{ cari: string | null; lokasi_pekerjaan: (string | null)[] | null; gender: (string | null)[] | null; pendidikan: (string | null)[] | null; sort: { value: string | null; label: string | null; } | null; context: string | null; }, {}, { fragment: "JobFilterFields"; on: "JobFilters"; masked: true; }>;
    "\n  fragment JobCardFields on Job {\n    id\n    title\n    slug\n    nama_perusahaan\n    ringkasanPekerjaan {\n      ...JobSummaryField\n    }\n    deadline\n    status_pekerjaan\n    permalink\n    post_time\n  }\n":
      TadaDocumentNode<{ id: number | null; title: string | null; slug: string | null; nama_perusahaan: string | null; ringkasanPekerjaan: { [$tada.fragmentRefs]: { JobSummaryField: "JobSummary"; }; } | null; deadline: string | null; status_pekerjaan: number | null; permalink: string | null; post_time: string | null; }, {}, { fragment: "JobCardFields"; on: "Job"; masked: true; }>;
    "\n  query GetAutoSuggestions($query: String!) {\n    autoSuggestions(query: $query)\n  }\n":
      TadaDocumentNode<{ autoSuggestions: (string | null)[] | null; }, { query: string; }, void>;
    "\n  query GetCarousel {\n    carousel {\n      jobs {\n        ...JobCardFields\n      }\n      totalJobs\n    }\n  }\n":
      TadaDocumentNode<{ carousel: { jobs: ({ [$tada.fragmentRefs]: { JobCardFields: "Job"; }; } | null)[] | null; totalJobs: number | null; } | null; }, {}, void>;
    "\n  query GetLoadMore($paged: Int, $context: String, $filters: JobFiltersInput) {\n    loadMore(paged: $paged, context: $context, filters: $filters) {\n      jobs {\n        ...JobCardFields\n      }\n      context\n      filters {\n        ...JobFilterFields\n      }\n      total\n      maxNumPages\n    }\n  }\n":
      TadaDocumentNode<{ loadMore: { jobs: ({ [$tada.fragmentRefs]: { JobCardFields: "Job"; }; } | null)[] | null; context: string | null; filters: { [$tada.fragmentRefs]: { JobFilterFields: "JobFilters"; }; } | null; total: number | null; maxNumPages: number | null; } | null; }, { filters?: { sort?: { value?: string | null | undefined; label?: string | null | undefined; } | null | undefined; pendidikan?: (string | null)[] | null | undefined; lokasi_pekerjaan?: (string | null)[] | null | undefined; gender?: (string | null)[] | null | undefined; context?: string | null | undefined; cari?: string | null | undefined; } | null | undefined; context?: string | null | undefined; paged?: number | null | undefined; }, void>;
    "\n  query GetJobGrid($paged: Int, $context: String, $title: String, $total_jobs: Int, $filters: JobFiltersInput) {\n    jobGrid(paged: $paged, context: $context, title: $title, total_jobs: $total_jobs, filters: $filters) {\n      jobs {\n        ...JobCardFields\n      }\n      total\n      maxNumPages\n      filters {\n        ...JobFilterFields\n      }\n    }\n  }\n":
      TadaDocumentNode<{ jobGrid: { jobs: ({ [$tada.fragmentRefs]: { JobCardFields: "Job"; }; } | null)[] | null; total: number | null; maxNumPages: number | null; filters: { [$tada.fragmentRefs]: { JobFilterFields: "JobFilters"; }; } | null; } | null; }, { filters?: { sort?: { value?: string | null | undefined; label?: string | null | undefined; } | null | undefined; pendidikan?: (string | null)[] | null | undefined; lokasi_pekerjaan?: (string | null)[] | null | undefined; gender?: (string | null)[] | null | undefined; context?: string | null | undefined; cari?: string | null | undefined; } | null | undefined; total_jobs?: number | null | undefined; title?: string | null | undefined; context?: string | null | undefined; paged?: number | null | undefined; }, void>;
    "\n  query GetJobDetail($slug: String!) {\n    jobDetail(slug: $slug) {\n        id\n        title\n        slug\n        nama_perusahaan\n        tentang_perusahaan\n        ringkasanPekerjaan {\n          ...JobSummaryField\n        }\n        deskripsi_pekerjaan\n        persyaratan\n        cara_melamar\n        benefit\n        contacts {\n          email_kontak\n          nomor_kontak\n          situs_kontak\n        }\n        social_media\n        post_time\n        duplicateNonce\n      }\n  }\n":
      TadaDocumentNode<{ jobDetail: { id: number | null; title: string | null; slug: string | null; nama_perusahaan: string | null; tentang_perusahaan: string | null; ringkasanPekerjaan: { [$tada.fragmentRefs]: { JobSummaryField: "JobSummary"; }; } | null; deskripsi_pekerjaan: string | null; persyaratan: string | null; cara_melamar: string | null; benefit: string | null; contacts: { email_kontak: string | null; nomor_kontak: string | null; situs_kontak: string | null; } | null; social_media: string | null; post_time: string | null; duplicateNonce: string | null; } | null; }, { slug: string; }, void>;
    "\n  query GetJobSchema($ids: [Int], $slug: String, $type: String) {\n    jobSchema(ids: $ids, slug: $slug, type: $type) {\n      schemas\n    }\n  }\n":
      TadaDocumentNode<{ jobSchema: { schemas: (string | null)[] | null; } | null; }, { type?: string | null | undefined; slug?: string | null | undefined; ids?: (number | null)[] | null | undefined; }, void>;
    "\n  query GetSearchJobs($filters: JobFiltersInput!) {\n    searchJobs(filters: $filters) {\n      jobs {\n        ...JobCardFields\n      }\n      context\n      title\n      filters {\n        ...JobFilterFields\n      }\n      total\n      maxNumPages\n    }\n  }\n":
      TadaDocumentNode<{ searchJobs: { jobs: ({ [$tada.fragmentRefs]: { JobCardFields: "Job"; }; } | null)[] | null; context: string | null; title: string | null; filters: { [$tada.fragmentRefs]: { JobFilterFields: "JobFilters"; }; } | null; total: number | null; maxNumPages: number | null; } | null; }, { filters: { sort?: { value?: string | null | undefined; label?: string | null | undefined; } | null | undefined; pendidikan?: (string | null)[] | null | undefined; lokasi_pekerjaan?: (string | null)[] | null | undefined; gender?: (string | null)[] | null | undefined; context?: string | null | undefined; cari?: string | null | undefined; }; }, void>;
    "\n  query SyncBookmark($ids: [Int!]!) {\n    syncBookmark(ids: $ids) {\n      ...JobCardFields\n    }\n  }\n":
      TadaDocumentNode<{ syncBookmark: ({ [$tada.fragmentRefs]: { JobCardFields: "Job"; }; } | null)[] | null; }, { ids: number[]; }, void>;
    "\n  query GetRankMathHead($url: String!) {\n    rankMathHead(url: $url)\n  }\n":
      TadaDocumentNode<{ rankMathHead: string | null; }, { url: string; }, void>;
    "\n  query GetThemeData {\n    themeData {\n      data {\n        siteIconTags\n        logo {\n          logoUrl\n          logoSrcset\n          logoSizes\n          logoDecoding\n          logoWidth\n          logoHeight\n        }\n        disableTracking\n        wpRestNonce\n      }\n    }\n  }\n":
      TadaDocumentNode<{ themeData: { data: { siteIconTags: string | null; logo: { logoUrl: string | null; logoSrcset: string | null; logoSizes: string | null; logoDecoding: string | null; logoWidth: number | null; logoHeight: number | null; } | null; disableTracking: boolean | null; wpRestNonce: string | null; } | null; } | null; }, {}, void>;
    "\n  query GetThemeNonce {\n    themeData {\n      data {\n        wpRestNonce\n      }\n    }\n  }\n":
      TadaDocumentNode<{ themeData: { data: { wpRestNonce: string | null; } | null; } | null; }, {}, void>;
    "\n  mutation GetJWT($username: String, $password: String, $token: String) {\n    jwt(username: $username, password: $password, token: $token)\n  }\n":
      TadaDocumentNode<{ jwt: string | null; }, { token?: string | null | undefined; password?: string | null | undefined; username?: string | null | undefined; }, void>;
  }
}
