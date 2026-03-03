<script lang="ts">
  import { PortalManager } from "$lib/utils/elements.svelte";
  import { onMount } from "svelte";

  let {
    open = $bindable(),
    username = $bindable(),
    password = $bindable(),
    error,
    loading,
    onClose,
    onLogin,
  }: {
    open: boolean;
    username: string;
    password: string;
    error: string;
    loading: boolean;
    onClose: () => void;
    onLogin: (e?: Event) => Promise<void>;
  } = $props();

  function handleKeydown(e: KeyboardEvent) {
    if (!open) return;
    if (e.key === "Escape" || e.key === "Esc") {
      try {
        if (typeof onClose === "function") onClose();
      } catch {}
    }
  }

  onMount(() => {
    document.addEventListener("keydown", handleKeydown);

    return () => {
      document.removeEventListener("keydown", handleKeydown);
    };
  });
</script>

{#if open}
  <div use:PortalManager.teleport={"#app"}>
    <div class="modal modal-open z-[1100]">
      <div class="modal-box">
        <h3 class="font-bold text-lg">Masuk</h3>
        <form
          onsubmit={(e) => {
            e.preventDefault();
            onLogin(e);
          }}
          class="space-y-4 mt-2"
        >
          <div class="form-control">
            <label for="current-username" class="label">
              <span class="font-semibold">Username</span>
            </label>
            <input
              id="current-username"
              class="input input-bordered w-full mt-2"
              placeholder="Masukkan username"
              bind:value={username}
              aria-label="username"
              autocomplete="username"
            />
          </div>

          <div class="form-control">
            <label for="current-password" class="label">
              <span class="font-semibold">Password</span>
            </label>
            <input
              id="current-password"
              type="password"
              class="input input-bordered w-full mt-2"
              placeholder="Masukkan password"
              bind:value={password}
              aria-label="password"
              autocomplete="current-password"
            />
          </div>

          {#if error}
            <p class="text-sm text-error">{error}</p>
          {/if}

          <div class="modal-action justify-end gap-2">
            <button
              class="btn btn-primary"
              type="submit"
              disabled={loading}
              aria-busy={loading}
            >
              {#if loading}
                Memproses...
              {:else}
                Masuk
              {/if}
            </button>
            <button
              class="btn btn-ghost"
              type="button"
              onclick={onClose}
              aria-label="Tutup"
            >
              Tutup
            </button>
          </div>
        </form>
      </div>

      <div
        role="button"
        tabindex="0"
        class="modal-backdrop"
        onclick={onClose}
        onkeydown={(e: KeyboardEvent) => {
          if (e.key === "Enter" || e.key === " ") onClose();
        }}
      ></div>
    </div>
  </div>
{/if}
