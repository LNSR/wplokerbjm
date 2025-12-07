<script lang="ts">
  import { RefreshSolid } from "svelte-awesome-icons";

  interface Props {
    size?: string;
    spin?: boolean;
    title?: string | null;
    extraClass?: string;
  }

  let { size = "h-5 w-5", spin = false, title = null, extraClass = "" }: Props = $props();
</script>

<RefreshSolid
  class={`${size} ${extraClass} ${spin ? 'animate-spin' : ''}`}
  aria-hidden={title ? "false" : "true"}
  role={title ? "img" : "presentation"}
  aria-label={title ?? undefined}
/>
