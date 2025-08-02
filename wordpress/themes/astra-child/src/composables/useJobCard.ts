import { computed } from "vue";

export const useJobCard = (
  variant: "featured" | "carousel" = "featured",
  selected: boolean = false
) => {
  const cardClass = computed(() => {
    let base = "";
    if (variant === "carousel") {
      base =
        "block group rounded-xl transition-all duration-300 cursor-pointer carousel-card max-w-full border-2 border-blue-400 shadow-md hover:shadow-lg hover:border-blue-600 hover:border-solid";
    } else if (variant === "featured") {
      base =
        "block group rounded-xl transition-all duration-300 cursor-pointer w-full max-w border-2 border-blue-400 shadow-lg hover:shadow-xl hover:border-blue-600 hover:scale-[1.02] hover:border-solid";
    }
    if (selected) {
      base += " ring-4 ring-blue-500 border-blue-700";
    }
    return base;
  });

  const bodyClass = computed(() => {
    if (variant === "carousel")
      return "card-body relative p-3 gap-0 flex flex-col min-h-[300px] h-full";
    if (variant === "featured")
      return "card-body relative p-4 gap-1 flex flex-col h-full";
    return "";
  });

  return {
    cardClass,
    bodyClass,
  };
};