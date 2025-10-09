<script lang="ts">
  let { srLabel = "Memuat...", size = "md" } = $props<{
    srLabel?: string;
    size?: "sm" | "md" | "lg" | string;
  }>();

  const sizeClass =
    size === "sm" ? "h-5 w-5" : size === "lg" ? "h-12 w-12" : "h-8 w-8";
</script>

<span class="sr-only">{srLabel}</span>
<svg
  class={`animate-spin ${sizeClass} text-[var(--wpl-global-color-1)]`}
  xmlns="http://www.w3.org/2000/svg"
  fill="none"
  viewBox="0 0 24 24"
  role="img"
  aria-hidden="true"
>
  <circle
    class="opacity-25"
    cx="12"
    cy="12"
    r="10"
    stroke="currentColor"
    stroke-width="4"
  ></circle>
  <path
    class="opacity-75"
    fill="currentColor"
    d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
  ></path>
</svg>
