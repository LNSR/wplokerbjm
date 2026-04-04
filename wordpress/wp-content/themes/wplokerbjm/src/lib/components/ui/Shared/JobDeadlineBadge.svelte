<script lang="ts">
  import { CalendarSolid } from "svelte-awesome-icons";

  const { text = "", status = "unknown" } = $props<{
    text?: string;
    status?:
      | "upcoming"
      | "soon"
      | "last_day"
      | "expired_yesterday"
      | "expired"
      | "today"
      | "unknown";
  }>();

  function deadlineClass(): string {
    switch (status) {
      case "upcoming":
        return "job-deadline-upcoming";
      case "soon":
        return "job-deadline-soon";
      case "last_day":
      case "today":
        return "job-deadline-last-day";
      case "expired_yesterday":
        return "job-deadline-expired-yesterday";
      case "expired":
        return "job-deadline-expired";
      default:
        return "";
    }
  }
</script>

{#if text}
  <span
    class={`flex badge gap-1 px-3 py-1 font-semibold rounded ${deadlineClass()}`}
  >
    <CalendarSolid class="h-4 w-4" aria-hidden="true" />
    <span>{text}</span>
  </span>
{/if}

<style lang="postcss">
  @reference "@css/app.css";
  .job-deadline-upcoming {
    @apply bg-blue-600 text-white border border-blue-800 text-xs;
  }

  .job-deadline-soon {
    @apply bg-yellow-400 text-black border border-yellow-600 text-xs;
  }

  .job-deadline-last-day {
    @apply bg-red-600 text-white border border-red-800 text-xs;
  }

  .job-deadline-expired-yesterday {
    @apply bg-gray-500 text-white border border-gray-700 text-xs;
  }

  .job-deadline-expired {
    @apply bg-gray-400 text-black border border-gray-700 text-xs;
  }
</style>
