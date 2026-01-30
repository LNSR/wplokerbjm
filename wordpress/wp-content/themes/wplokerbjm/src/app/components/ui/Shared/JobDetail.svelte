<script module lang="ts">
  function extractImages(html: string): string[] {
    if (!html) return [];
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, "text/html");
    const imgs = doc.querySelectorAll("img");
    return Array.from(imgs)
      .map((img) => img.src || img.getAttribute("data-src") || "")
      .filter(Boolean);
  }
</script>

<script lang="ts">
  import Viewer from "viewerjs";
  import "viewerjs/dist/viewer.min.css";
  import { SocialMediaPlatform } from "@/types";
  import { generalStore } from "$lib/stores/General.svelte";
  import { FormattingService } from "@/services/Formatting";
  import BookmarkButton from "@components/ui/Shared/BookmarkButton.svelte";
  import { SvelteDate } from "svelte/reactivity";
  import {
    ClockSolid,
    UserTieSolid,
    MapPinSolid,
    ClipboardCheckSolid,
    CircleInfoSolid,
    CircleCheckSolid,
    FileSignatureSolid,
    HandHoldingHeartSolid,
    AddressCardSolid,
    AddressBookSolid,
  } from "svelte-awesome-icons";
  import type { JobDetailResponse } from "@/types";
  import { timeEffect } from "@/app/lib/utils/elements.svelte";

  const { job }: { job: JobDetailResponse } = $props();

  const now = $state(new SvelteDate());

  const ringkasanPekerjaan = $derived(
    generalStore.useSummaryJob(job.ringkasanPekerjaan),
  );
  const contacts = $derived(generalStore.useContactsJob(job.contacts));
  const socialMediaItems = $derived(
    generalStore.useSocialMedia().socialMediaItems(job.social_media),
  );
  const timeAgo = $derived.by(() =>
    generalStore.useTimeAgo(job.post_time, now),
  );

  const allImages = $derived(
    [
      ...extractImages(job.tentang_perusahaan || ""),
      ...extractImages(job.deskripsi_pekerjaan || ""),
      ...extractImages(job.persyaratan || ""),
      ...extractImages(job.cara_melamar || ""),
      ...extractImages(job.benefit || ""),
    ].filter((v, i, a) => a.indexOf(v) === i),
  );
  let galleryRef = $state<HTMLElement>();
  let viewer = $state<Viewer>();

  const viewerOptions: unknown = {
    hidden: true,
    container: document.querySelector(".route-container") ?? document.body,
    focus: true,
    toolbar: {
      zoomIn: false,
      zoomOut: false,
      oneToOne: false,
      reset: false,
      prev: true,
      play: {
        show: false,
        size: "large",
      },
      next: true,
      rotateLeft: false,
      rotateRight: false,
      flipHorizontal: false,
      flipVertical: false,
    },
  };

  function setupViewer(): void {
    viewer = new Viewer(galleryRef!, viewerOptions as Viewer.Options);
    /** Aria-hidden issue best-effort fix for now */
    const viewerElement = (viewer as any).element;
    if (viewerElement) {
      const onShown = () => {
        viewerElement.removeAttribute("aria-hidden");
        viewerElement.removeAttribute("inert");
      };
      const onHide = () => {
        const focused = document.activeElement as HTMLElement | null;
        if (focused && viewerElement.contains(focused)) {
          focused.blur();
        }
      };
      const onHidden = () => {
        viewerElement.removeAttribute("aria-hidden");
        viewerElement.setAttribute("inert", "true");
      };
      viewerElement.addEventListener("shown", onShown);
      viewerElement.addEventListener("hide", onHide);
      viewerElement.addEventListener("hidden", onHidden);
      // Store references for cleanup
      (viewer as any)._eventHandlers = { onShown, onHide, onHidden };
    }
  }

  function onWysiwygImgClick(e: MouseEvent): void {
    const target = e.target as HTMLElement;
    if (target.tagName !== "IMG") return;

    const img = target as HTMLImageElement;
    const anchor = img.closest("a") as HTMLAnchorElement | null;

    anchor && e.preventDefault();

    const href = anchor?.href ?? "";
    const dataSrc = img.getAttribute("data-src") ?? "";

    const isImageUrl = (url: string | undefined | null): url is string =>
      typeof url === "string" &&
      /\.(avif|webp|jpe?g|png|gif|svg|bmp|tiff)(\?.*)?$/i.test(url);

    const src = isImageUrl(href)
      ? href
      : isImageUrl(img.src)
        ? img.src
        : isImageUrl(dataSrc)
          ? dataSrc
          : img.currentSrc || href || img.src || dataSrc || "";

    const imageIndex = allImages.indexOf(src);
    if (imageIndex >= 0) {
      if (!viewer) {
        setupViewer();
      }
      viewer!.show();
      viewer!.view(imageIndex);
    }
  }

  $effect(() => {
    timeEffect(now);
  });

  $effect(() => {
    return () => {
      if (viewer) {
        const viewerElement = (viewer as any).element;
        if (viewerElement && (viewer as any)._eventHandlers) {
          const { onShown, onHide, onHidden } = (viewer as any)._eventHandlers;
          viewerElement.removeEventListener("shown", onShown);
          viewerElement.removeEventListener("hide", onHide);
          viewerElement.removeEventListener("hidden", onHidden);
        }
        viewer.destroy();
        viewer = undefined;
      }
    };
  });
</script>

<div class="space-y-8">
  <!-- Job Title -->
  {#if job.title}
    <section class="top-0 text-center">
      <div class="flex items-center justify-center gap-4">
        <h1 class="text-3xl font-bold">{job.title}</h1>
      </div>
      {#if job.post_time}
        <div
          class="text-md mt-2 flex items-center justify-center gap-2 font-semibold text-center"
        >
          <ClockSolid
            class="text-[var(--wpl-global-color-1)] inline-block min-w-3 min-h-3 w-4 h-4 md:w-5 md:h-5"
            aria-hidden="true"
          />
          <span>Diupdate: {timeAgo()}</span>
          <BookmarkButton jobId={Number(job.id) || 0} variant="detail" />
        </div>
      {/if}
    </section>

    <div class="divider"></div>
  {/if}

  <!-- Nama Perusahaan -->
  {#if job.nama_perusahaan}
    <section>
      <h2 class="text-2xl md:text-3xl flex items-center gap-2 mb-4">
        <UserTieSolid
          class="text-[var(--wpl-global-color-1)] inline-block"
          aria-hidden="true"
        />
        <span class="font-bold">{job.nama_perusahaan}</span>
      </h2>
      <div class="divider"></div>
    </section>
  {/if}

  <!-- Tentang Perusahaan -->
  {#if job.tentang_perusahaan}
    <section class="wysiwyg-content">
      <h2 class="text-3xl flex items-center gap-2 mb-4">
        <MapPinSolid
          class="text-[var(--wpl-global-color-1)] inline-block"
          aria-hidden="true"
        />
        <span class="font-bold">Tentang Perusahaan</span>
      </h2>
      <div onclick={onWysiwygImgClick} role="none">
        {@html job.tentang_perusahaan}
      </div>
      <div class="divider"></div>
    </section>
  {/if}

  <!-- Ringkasan Pekerjaan -->
  {#if ringkasanPekerjaan && ringkasanPekerjaan.length}
    <section>
      <h2 class="flex items-center gap-2 mb-4 text-2xl">
        <ClipboardCheckSolid
          class="text-[var(--wpl-global-color-1)]"
          aria-hidden="true"
        />
        <span class="font-bold">Ringkasan Pekerjaan</span>
      </h2>
      <div class="gap-4 mt-4">
        <div class="gap-x-6 gap-y-2 ml-1">
          {#each ringkasanPekerjaan as row (row.label)}
            {@const Icon = row.icon}
            <div class="flex items-start mb-2">
              {#if Icon}
                <Icon
                  class="text-[var(--wpl-global-color-1)] min-w-5 min-h-5 w-5 h-5 mt-1 inline-block align-middle"
                  aria-hidden="true"
                />
              {/if}
              <div class="ml-2 flex w-full">
                <span class="w-40 md:w-56 flex-shrink-0 font-semibold text-lg"
                  >{row.label}</span
                >
                <span class="mx-2 font-semibold">:</span>
                <span class="flex-1 font-semibold break-words"
                  >{@html row.value}</span
                >
              </div>
            </div>
          {/each}
        </div>
      </div>
      <div class="divider"></div>
    </section>
  {/if}

  <!-- Deskripsi Pekerjaan -->
  {#if job.deskripsi_pekerjaan}
    <section class="wysiwyg-content">
      <h2 class="text-xl flex items-center gap-2 mb-4">
        <CircleInfoSolid
          class="text-[var(--wpl-global-color-1)] inline-block"
          aria-hidden="true"
        />
        <span class="font-bold">Deskripsi Pekerjaan</span>
      </h2>
      <div onclick={onWysiwygImgClick} role="none">
        {@html job.deskripsi_pekerjaan}
      </div>
      <div class="divider"></div>
    </section>
  {/if}

  <!-- Persyaratan -->
  {#if job.persyaratan}
    <section class="wysiwyg-content">
      <h2 class="text-2xl flex items-center gap-2 mb-4">
        <CircleCheckSolid
          class="text-[var(--wpl-global-color-1)] inline-block"
          aria-hidden="true"
        />
        <span class="font-bold">Persyaratan</span>
      </h2>
      <div onclick={onWysiwygImgClick} role="none">
        {@html job.persyaratan}
      </div>
      <div class="divider"></div>
    </section>
  {/if}

  <!-- Cara Melamar -->
  {#if job.cara_melamar}
    <section class="wysiwyg-content">
      <h2 class="text-2xl flex items-center gap-2 mb-4">
        <FileSignatureSolid
          class="text-[var(--wpl-global-color-1)] inline-block"
          aria-hidden="true"
        />
        <span class="font-bold">Cara Melamar</span>
      </h2>
      <div onclick={onWysiwygImgClick} role="none">
        {@html job.cara_melamar}
      </div>
      <div class="divider"></div>
    </section>
  {/if}

  <!-- Benefit -->
  {#if job.benefit}
    <section class="wysiwyg-content">
      <h2 class="text-2xl flex items-center gap-2 mb-4">
        <HandHoldingHeartSolid
          class="text-[var(--wpl-global-color-1)] inline-block"
          aria-hidden="true"
        />
        <span class="font-bold">Benefit</span>
      </h2>
      <div onclick={onWysiwygImgClick} role="none">
        {@html job.benefit}
      </div>
      <div class="divider"></div>
    </section>
  {/if}

  <!-- Kontak -->
  {#if contacts && contacts.length}
    <section>
      <h2 class="text-2xl flex items-center justify-between mb-4">
        <span class="flex items-center gap-2">
          <AddressCardSolid
            class="text-[var(--wpl-global-color-1)]"
            aria-hidden="true"
          />
          <span class="font-bold">Kontak</span>
        </span>
      </h2>
      <div class="grid grid-cols-1 gap-4 mt-4">
        <div
          class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4"
        >
          {#each contacts as contact (contact.label + contact.value)}
            {@const Icon = contact.icon}
            <div class="flex items-center">
              {#if Icon}
                <Icon
                  class="text-[var(--wpl-global-color-1)] w-6 text-center text-xl inline-block"
                  aria-hidden="true"
                />
              {/if}
              <div class="ml-2 font-semibold text-md">
                <span class="block font-semibold">{contact.label}:</span>
                <a
                  href={contact.href}
                  target="_blank"
                  rel="noopener noreferrer"
                  class="block font-semibold break-words max-w-full whitespace-normal text-[var(--wpl-global-color-1)] hover:underline"
                  >{contact.value}</a
                >
              </div>
            </div>
          {/each}
        </div>
      </div>
      <div class="divider"></div>
    </section>
  {/if}

  <!-- Sosial Media -->
  {#if socialMediaItems && socialMediaItems.length}
    <section>
      <h2 class="text-2xl flex items-center gap-2 mb-4">
        <AddressBookSolid
          class="text-[var(--wpl-global-color-1)]"
          aria-hidden="true"
        />
        <span class="font-bold">Sosial Media</span>
      </h2>
      <div class="grid grid-cols-1 gap-4 mt-4">
        <div
          class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4"
        >
          {#each socialMediaItems as item (item.platform + item.username)}
            {@const Icon = item.icon}
            <div class="flex items-center">
              {#if Icon}
                <Icon
                  class="text-[var(--wpl-global-color-1)] w-6 text-center text-xl inline-block"
                  aria-hidden="true"
                />
              {/if}
              <div class="ml-2 font-semibold text-md">
                <span class="block">{item.platform}:</span>
                <a
                  href={item.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  class="block font-semibold break-words max-w-full whitespace-normal text-[var(--wpl-global-color-1)] hover:underline"
                >
                  {item.platform === SocialMediaPlatform.WhatsApp
                    ? item.username
                      ? FormattingService.formatPhone(item.username)
                      : ""
                    : (item.username ?? "")}
                </a>
              </div>
            </div>
          {/each}
        </div>
      </div>
      <div class="divider"></div>
    </section>
  {/if}
</div>
{#if allImages.length}
  <div bind:this={galleryRef} class="hidden">
    {#each allImages as img}
      <img src={img} alt="" />
    {/each}
  </div>
{/if}
