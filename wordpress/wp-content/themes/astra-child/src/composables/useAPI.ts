import { ref, type Ref } from "vue";
import { JobService } from "@/services/APIService";
import type {
  SearchFilters,
  LoadMoreFilters,
  SearchResponse,
  AutoSuggestResponse,
  LoadMoreResponse,
  SingleOverlayResponse,
} from "@/types";

export function useApi(): {
  loading: Ref<boolean>;
  fetchAutoSuggestions: (query: string) => Promise<AutoSuggestResponse>;
  searchJobs: (filters: SearchFilters) => Promise<SearchResponse>;
  loadMore: (filters: LoadMoreFilters) => Promise<LoadMoreResponse>;
  fetchSingleOverlay: (slug: string) => Promise<SingleOverlayResponse | null>;
} {
  const loading = ref(false);

  async function fetchAutoSuggestions(
    query: string
  ): Promise<AutoSuggestResponse> {
    loading.value = true;
    try {
      return await JobService.getAutoSuggestions(query);
    } finally {
      loading.value = false;
    }
  }

  async function searchJobs(filters: SearchFilters): Promise<SearchResponse> {
    loading.value = true;
    try {
      return await JobService.searchJobs(filters);
    } finally {
      loading.value = false;
    }
  }

  async function loadMore(filters: LoadMoreFilters): Promise<LoadMoreResponse> {
    loading.value = true;
    try {
      return await JobService.loadMoreJobs(filters);
    } finally {
      loading.value = false;
    }
  }

  async function fetchSingleOverlay(
    slug: string
  ): Promise<SingleOverlayResponse | null> {
    loading.value = true;
    try {
      return await JobService.fetchSingleOverlay(slug);
    } finally {
      loading.value = false;
    }
  }

  return {
    loading,
    fetchAutoSuggestions,
    searchJobs,
    loadMore,
    fetchSingleOverlay,
  };
}
