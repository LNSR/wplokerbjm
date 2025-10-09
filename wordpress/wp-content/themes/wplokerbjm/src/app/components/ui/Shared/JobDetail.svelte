<script module lang="ts">
  interface LightboxController {
    open: () => void;
    close: () => void;
    toggle: () => void;
    openImage: (index: number) => void;
  }

  function getLabelClass(label: string): string {
    switch (label) {
      case "Jenis Pekerjaan":
        return "lg:ml-3 ml-5";
      case "Pendidikan":
      case "Pengalaman":
      case "Gender":
      case "Usia":
      case "Deadline":
      case "Gaji":
      case "Lokasi":
        return "ml-5";
      default:
        return "ml-4";
    }
  }
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
  import "$lib/localizations/svelte-lightbox";
  import { GeneralStore } from "$lib/stores/General.svelte";
  import { FormattingService } from "@/services/Formatting";
  import BookmarkButton from "@components/ui/Shared/BookmarkButton.svelte";
  import Adsense from "@components/adsense/Adsense.svelte";
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
  import { LightboxGallery, GalleryImage } from "svelte-lightbox";
  import type { SingleOverlayResponse } from "@/types";

  let { job }: { job: SingleOverlayResponse } = $props();

  const ringkasanPekerjaan = $derived(
    GeneralStore.useSummaryJob(job.ringkasanPekerjaan)
  );
  const contacts = $derived(GeneralStore.useContactsJob(job.contacts));
  const socialMediaItems = $derived(
    GeneralStore.useSocialMedia().socialMediaItems(job.social_media)
  );
  const timeAgo = GeneralStore.useTimeAgo(job.post_time);

  const allImages = $derived(
    [
      ...extractImages(job.tentangPerusahaan),
      ...extractImages(job.deskripsiPekerjaan),
      ...extractImages(job.persyaratan),
      ...extractImages(job.caraMelamar),
      ...extractImages(job.benefit),
    ].filter((v, i, a) => a.indexOf(v) === i)
  );
  let controller = $state<LightboxController | undefined>();

  function onWysiwygImgClick(e: MouseEvent): void {
    const target = e.target as HTMLElement;
    if (target.tagName === "IMG") {
      if (target.parentElement?.tagName === "A") {
        e.preventDefault();
      }

      const clickedAnchor = (target as HTMLImageElement).closest(
        "a"
      ) as HTMLAnchorElement | null;
      const clickedSrc =
        (clickedAnchor && clickedAnchor.href) ||
        (target as HTMLImageElement).src ||
        target.getAttribute("data-src") ||
        "";
      const imageIndex = allImages.indexOf(clickedSrc);
      if (controller && imageIndex >= 0) {
        controller.openImage(imageIndex);
      }
    }
  }
</script>

<div class="space-y-8">
  <!-- Job Title -->
  {#if job.title}
    <section class="top-0 backdrop-blur text-center">
      <Adsense adSlot="6531671839" />
      <div class="flex items-center justify-center gap-4">
        <h1 class="text-3xl font-bold">{job.title}</h1>
      </div>
      {#if job.post_time}
        <div
          class="text-sm mt-2 flex items-center justify-center gap-2 font-semibold text-center"
        >
          <ClockSolid
            class="text-[var(--wpl-global-color-1)] inline-block min-w-3 min-h-3 w-4 h-4 md:w-5 md:h-5"
            aria-hidden="true"
          />
          <span>Diupdate: {timeAgo()}</span>
          <BookmarkButton jobId={job.id || 0} variant="detail" />
        </div>
      {/if}
    </section>

    <div class="divider"></div>
  {/if}

  <!-- Nama Perusahaan -->
  {#if job.namaPerusahaan}
    <section>
      <h2 class="text-2xl md:text-3xl flex items-center gap-2 mb-4">
        <UserTieSolid
          class="text-[var(--wpl-global-color-1)] inline-block"
          aria-hidden="true"
        />
        <span class="font-bold">{job.namaPerusahaan}</span>
      </h2>
      <div class="divider"></div>
    </section>
  {/if}

  <!-- Tentang Perusahaan -->
  {#if job.tentangPerusahaan}
    <section class="wysiwyg-content">
      <h2 class="text-3xl flex items-center gap-2 mb-4">
        <MapPinSolid
          class="text-[var(--wpl-global-color-1)] inline-block"
          aria-hidden="true"
        />
        <span class="font-bold">Tentang Perusahaan</span>
      </h2>
      <div onclick={onWysiwygImgClick} role="none">
        {@html job.tentangPerusahaan}
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
            <div class="flex items-start lg:space-x-2 space-x-1 mb-2">
              {#if Icon}
                <Icon
                  class="text-[var(--wpl-global-color-1)] min-w-5 min-h-5 w-5 h-5 mt-1 inline-block align-middle"
                  aria-hidden="true"
                />
              {/if}
              <span
                class="ml-2 font-semibold text-lg text-wrap min-w-[40%] md:min-w-[20%]"
                >{row.label}</span
              >
              <span class={`${getLabelClass(row.label)} font-semibold`}>:</span>
              <span class="font-semibold">{@html row.value}</span>
            </div>
          {/each}
        </div>
      </div>
      <div class="divider"></div>
    </section>
  {/if}

  <!-- Deskripsi Pekerjaan -->
  {#if job.deskripsiPekerjaan}
    <section class="wysiwyg-content">
      <h2 class="text-xl flex items-center gap-2 mb-4">
        <CircleInfoSolid
          class="text-[var(--wpl-global-color-1)] inline-block"
          aria-hidden="true"
        />
        <span class="font-bold">Deskripsi Pekerjaan</span>
      </h2>
      <div onclick={onWysiwygImgClick} role="none">
        {@html job.deskripsiPekerjaan}
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
  {#if job.caraMelamar}
    <section class="wysiwyg-content">
      <h2 class="text-2xl flex items-center gap-2 mb-4">
        <FileSignatureSolid
          class="text-[var(--wpl-global-color-1)] inline-block"
          aria-hidden="true"
        />
        <span class="font-bold">Cara Melamar</span>
      </h2>
      <div onclick={onWysiwygImgClick} role="none">
        {@html job.caraMelamar}
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
                  class="block font-semibold break-all max-w-xs whitespace-normal text-[var(--wpl-global-color-1)] hover:underline"
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
                  class="block font-semibold break-all max-w-xs whitespace-normal text-[var(--wpl-global-color-1)] hover:underline"
                >
                  {item.platform === "Whatsapp"
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
  <Adsense adSlot="4495507760" />
</div>
{#if allImages.length}
  <LightboxGallery bind:programmaticController={controller}>
    {#each allImages as img}
      <GalleryImage>
        <img src={img} alt="" />
      </GalleryImage>
    {/each}
  </LightboxGallery>
{/if}
