import { computed } from "vue";

export function useFilter(searchStore: any, taxonomyStore: any) {
  return computed(() => {
    const SEMUA_VALUE = "";
    const filters: {
      key: "lokasi" | "gender" | "pendidikan";
      label: string;
      values: string[];
      names: string[];
    }[] = [];
    if (searchStore.filters.lokasi && searchStore.filters.lokasi.length) {
      const filtered = searchStore.filters.lokasi.filter(
        (slug: string) => slug !== SEMUA_VALUE
      );
      if (filtered.length) {
        filters.push({
          key: "lokasi",
          label: "Lokasi",
          values: filtered,
          names: filtered.map((slug: string) =>
            taxonomyStore.getTermNameBySlug("lokasi", slug)
          ),
        });
      }
    }
    if (searchStore.filters.gender && searchStore.filters.gender.length) {
      const filtered = searchStore.filters.gender.filter(
        (slug: string) => slug !== SEMUA_VALUE
      );
      if (filtered.length) {
        filters.push({
          key: "gender",
          label: "Gender",
          values: filtered,
          names: filtered.map((slug: string) =>
            taxonomyStore.getTermNameBySlug("gender", slug)
          ),
        });
      }
    }
    if (
      searchStore.filters.pendidikan &&
      searchStore.filters.pendidikan.length
    ) {
      const filtered = searchStore.filters.pendidikan.filter(
        (slug: string) => slug !== SEMUA_VALUE
      );
      if (filtered.length) {
        filters.push({
          key: "pendidikan",
          label: "Pendidikan",
          values: filtered,
          names: filtered.map((slug: string) =>
            taxonomyStore.getTermNameBySlug("pendidikan", slug)
          ),
        });
      }
    }
    return filters;
  });
}

export function removeFilter(searchStore: any, key: string, value: string) {
  const arr = Array.isArray(searchStore.filters[key])
    ? [...(searchStore.filters[key] as string[])]
    : [];
  const idx = arr.indexOf(value);
  if (idx !== -1) {
    arr.splice(idx, 1);
    searchStore.filters[key] = arr;
  }
}
