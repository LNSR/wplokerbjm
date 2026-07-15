import { graphql } from "@/services/graphql/config/tada";
import { FRAGMENT_JOB_CARD_FIELDS, FRAGMENT_JOB_FILTER_FIELDS, FRAGMENT_JOB_SUMMARY_FIELDS } from "../shared/job";

export const GET_AUTO_SUGGESTIONS = graphql(`
  query GetAutoSuggestions($query: String!) {
    autoSuggestions(query: $query)
  }
`);

export const GET_LOAD_MORE = graphql(`
  query GetLoadMore($paged: Int, $context: String, $filters: JobFiltersInput) {
    loadMore(paged: $paged, context: $context, filters: $filters) {
      jobs {
        ...JobCardFields
      }
      filters {
        ...JobFilterFields
      }
      total
      maxNumPages
    }
  }
`, [FRAGMENT_JOB_CARD_FIELDS, FRAGMENT_JOB_FILTER_FIELDS]);

export const GET_SEARCH_JOBS = graphql(`
  query GetSearchJobs($context: String, $filters: JobFiltersInput!) {
    searchJobs(context: $context, filters: $filters) {
      jobs {
        ...JobCardFields
      }
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
