<script lang="ts">
  import { GlobalNavigateTo } from "$lib/stores/Route.svelte";
  const year = new Date().getFullYear();
</script>

<footer
  class="footer footer-center bg-[var(--wpl-global-color-4)] border-t-1 border-[var(--wpl-global-color-1)] rounded p-2 mt-4"
>
  <aside>
    <p class="text-lg font-bold flex items-center justify-center gap-2"></p>
    <a
      href="https://github.com/LNSR/wplokerbjm/"
      class="text-xl font-bold pb-2 hover:underline hover:text-[var(--wpl-global-color-1)] transition-colors"
    >
      <strong>© {year} - WPLokerBJM</strong>
    </a>
    <a
      href="/kebijakan-privasi"
      class="hover:underline hover:text-[var(--wpl-global-color-1)] transition-colors cursor-pointer gap-2"
      onclick={(e) => {
        e.preventDefault();
        GlobalNavigateTo("/kebijakan-privasi");
      }}><strong>Kebijakan Privasi</strong></a
    >
  </aside>
</footer>
