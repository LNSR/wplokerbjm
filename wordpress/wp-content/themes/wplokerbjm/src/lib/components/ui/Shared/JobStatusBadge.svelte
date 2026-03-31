<script lang="ts">
import type { StatusPekerjaanString } from "@/types/wordpress/MetaBox";
  import {
    ExclamationTriangleSolid,
    ThumbTackSolid,
  } from "svelte-awesome-icons";

  const { label = "", status = "none" } = $props<{
    label?: string;
    status?: StatusPekerjaanString | '';
  }>();

  function statusClass(): string {
    switch (status) {
      case "Urgent":
        return "job-status-urgent";
      case "Pinned":
        return "job-status-pinned";
      default:
        return "";
    }
  }
</script>

{#if label}
  <span
    class={`flex items-center badge gap-1 px-3 py-1 font-semibold rounded ${statusClass()}`}
  >
    {#if status === "Urgent"}
      <ExclamationTriangleSolid class="h-4 w-4" aria-hidden="true" />
    {:else if status === "Pinned"}
      <ThumbTackSolid class="h-4 w-4" aria-hidden="true" />
    {/if}
    <span>{label}</span>
  </span>
{/if}

<style lang="postcss">
  @reference "@css/app.css";
  .job-status-urgent {
    @apply bg-red-600 text-white border border-red-700 shadow-sm text-xs;
  }

  .job-status-pinned {
    @apply bg-yellow-400 text-black border border-yellow-600 shadow-sm text-xs;
  }
</style>
