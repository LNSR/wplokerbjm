<script>
  import { GlobalNavigateTo } from "$lib/stores/Route.svelte";
  const year = new Date().getFullYear();
</script>

<footer
  class="footer footer-center bg-[var(--wpl-global-color-4)] border-t-3 border-[var(--wpl-global-color-5)] rounded p-2 mt-4"
>
  <aside>
    <p class="text-lg font-bold flex items-center justify-center gap-2"></p>
    <a
      href="https://github.com/LNSR/wplokerbjm/"
      class="text-xl font-bold pb-2 hover:underline hover:text-[var(--wpl-global-color-1)] transition-colors"
    >
      <strong>© {year} - WPLokerBJM</strong>
    </a>
    <button
      onclick={() => GlobalNavigateTo("/kebijakan-privasi/")}
      class="hover:underline hover:text-[var(--wpl-global-color-1)] transition-colors cursor-pointer gap-2"
      ><strong>Kebijakan Privasi</strong></button
    >
  </aside>
</footer>