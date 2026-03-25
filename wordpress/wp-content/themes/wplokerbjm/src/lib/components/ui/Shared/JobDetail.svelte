<script module lang="ts">
  function extractImages(html: string): string[] {
    if (!html) return [];
    if (typeof DOMParser !== "undefined") {
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, "text/html");
      const imgs = doc.querySelectorAll("img");
      return Array.from(imgs)
        .map((img) => img.src || img.getAttribute("data-src") || "")
        .filter(Boolean);
    }
    const srcs: string[] = [];
    const imgRe = /<img\s[^>]*?(?:src|data-src)=("|')([^"']+)\1/gi;
    let match: RegExpExecArray | null;
    while ((match = imgRe.exec(html))) {
      if (match[2]) srcs.push(match[2]);
    }
    return srcs.filter(Boolean);
  }
</script>

<script lang="ts">
  import { schemaScriptAttach } from "@/utils";
  import { browser } from "$app/environment";
  import ViewerModule from "viewerjs";
  import "viewerjs/dist/viewer.min.css";
  import { SocialMediaPlatform } from "@/types";
  import { generalStore } from "$lib/stores/General.svelte";
  import { FormattingService } from "@/services/Formatting";
  import BookmarkButton from "@components/ui/Shared/BookmarkButton.svelte";
  import { onDestroy } from "svelte";
  import {
    ClockSolid,
    UserTieSolid,
    MapPinSolid,
    CircleInfoSolid,
    CircleCheckSolid,
    FileSignatureSolid,
    HandHoldingHeartSolid,
    AddressCardSolid,
    AddressBookSolid,
  } from "svelte-awesome-icons";
  import type { JobDetailResponse, JobSchemaResponse } from "@/types";
  import { SharedClock } from "$lib/utils/elements.svelte";
  import { page } from "$app/state";

  const { job }: { job: JobDetailResponse } = $props();

  let Viewer: any;

  const ringkasanPekerjaan = $derived(
    generalStore.useSummaryJob(job.ringkasanPekerjaan),
  );
  const contacts = $derived(generalStore.useContactsJob(job.contacts));
  const socialMediaItems = $derived(
    generalStore.useSocialMedia().socialMediaItems(job.social_media),
  );
  const timeAgo = $derived.by(() => {
    return generalStore.useTimeAgo(job.post_time);
  });

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

  class ViewerJSManager {
    static #eventHandlers: WeakMap<
      any,
      {
        onShown: () => void;
        onHide: () => void;
        onHidden: () => void;
      }
    > = new WeakMap();
    static viewerOptions(): Viewer.Options {
      const container = browser
        ? ((document.querySelector("#app") as HTMLElement) ?? document.body)
        : undefined;
      const opts: unknown = {
        hidden: true,
        container,
        focus: false,
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
      return opts as Viewer.Options;
    }

    static setupViewer(): void {
      if (!browser) return;
      if (!Viewer) {
        Viewer =
          (ViewerModule && (ViewerModule as any).default) ?? ViewerModule;
      }

      if (!galleryRef) return;

      if (viewer) return;

      viewer = new Viewer(galleryRef!, ViewerJSManager.viewerOptions());
    }

    static destroyViewer(): void {
      if (viewer) {
        try {
          viewer.destroy();
        } catch (e) {
          // ignore - library sometimes throws when already torn down
        }
        viewer = undefined;
      }
    }
  }

  async function onWysiwygImgClick(e: MouseEvent): Promise<void> {
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
      await ViewerJSManager.setupViewer();
      if (!viewer) return;
      viewer!.show();
      viewer!.view(imageIndex);
    }
  }

  // run the time effect separately from viewer teardown.  the previous
  // implementation destroyed the viewer on every tick because `now` is a
  // reactive value; that explain why the gallery would show exactly once and
  // then never again.
  $effect(() => {
    const stopTime = SharedClock.timeEffect();
    return () => stopTime();
  });

  // clean up the viewer when the component is removed from the DOM
  onDestroy(() => {
    ViewerJSManager.destroyViewer();
  });
</script>

<svelte:head>
  {#if page.data?.job as JobDetailResponse && page.data.jobSchema as JobSchemaResponse}
    {@const jobId = page.data.job.id}
    {@const jobSchema = page.data.jobSchema}
    {@html schemaScriptAttach(jobSchema, "JobPosting", `jobposting-${jobId}`)}
  {/if}
</svelte:head>


<article class="space-y-8" style="contain: layout paint;">
  <!-- Title + Summary -->
  {#if job.title || (ringkasanPekerjaan && ringkasanPekerjaan.length)}
    <section
      class="overflow-hidden border-1 border-[var(--wpl-global-color-1)] p-6 rounded-xl bg-[var(--wpl-global-color-5)]"
    >
      <div class="grid grid-cols-1 pt-6">
        <div class="flex items-start gap-4 flex-col">
          <div class="w-full flex flex-col">
            {#if job.title}
              <div class="justify-between flex flex-row items-start gap-2">
                <h1 class="text-2xl md:text-3xl xl:text-4xl font-bold mb-5">
                  {job.title}
                </h1>
                <div class="flex items-center shrink-0">
                  <BookmarkButton
                    jobId={Number(job.id) || 0}
                    variant="detail"
                  />
                </div>
              </div>
            {/if}

            <div class="flex flex-col wrap-anywhere mb-4">
              {#if job.nama_perusahaan}
                <div class="items-center gap-2 mb-2">
                  <UserTieSolid
                    class="text-[var(--wpl-global-color-1)] inline-block w-4 h-4"
                    aria-hidden="true"
                  />
                  <span class="font-bold ml-1">{job.nama_perusahaan}</span>
                </div>
              {/if}
              {#if job.post_time}
                <div class="items-center gap-2 mb-2">
                  <ClockSolid
                    class="text-[var(--wpl-global-color-1)] inline-block w-4 h-4"
                    aria-hidden="true"
                  />
                  <time class="font-bold ml-1" datetime={job.post_time}
                    >Diupdate: {timeAgo}</time
                  >
                </div>
              {/if}
            </div>
          </div>
        </div>

        {#if ringkasanPekerjaan && ringkasanPekerjaan.length}
          <div
            class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-4 text-sm md:text-base"
          >
            {#each ringkasanPekerjaan as row (row.label)}
              {@const Icon = row.icon}
              <div class="flex items-start gap-2">
                {#if Icon}
                  <Icon
                    class="text-[var(--wpl-global-color-1)] inline-block w-5 h-5"
                    aria-hidden="true"
                  />
                {/if}
                <div class="w-full">
                  <div class="font-bold">{row.label}</div>
                  <div
                    class="text-[var(--wpl-global-color-1)] font-bold wrap-anywhere"
                  >
                    {@html row.value}
                  </div>
                </div>
              </div>
            {/each}
          </div>
        {/if}
      </div>
    </section>
  {/if}

  <!-- Tentang Perusahaan -->
  {#if job.tentang_perusahaan}
    <section class="wysiwyg-content" aria-labelledby="about-company">
      <h2 id="about-company" class="text-2xl flex items-center gap-2 mb-4">
        <MapPinSolid
          class="text-[var(--wpl-global-color-1)] inline-block"
          aria-hidden="true"
        />
        <span class="font-semibold text-[var(--wpl-global-color-1)]"
          >Tentang Perusahaan</span
        >
      </h2>
      <div onclick={onWysiwygImgClick} role="presentation">
        {@html job.tentang_perusahaan}
      </div>
    </section>
  {/if}

  <!-- Deskripsi Pekerjaan -->
  {#if job.deskripsi_pekerjaan}
    <section class="wysiwyg-content" aria-labelledby="job-description">
      <h2 id="job-description" class="text-xl flex items-center gap-2 mb-4">
        <CircleInfoSolid
          class="text-[var(--wpl-global-color-1)] inline-block"
          aria-hidden="true"
        />
        <span class="font-semibold text-[var(--wpl-global-color-1)]"
          >Deskripsi Pekerjaan</span
        >
      </h2>
      <div onclick={onWysiwygImgClick} role="presentation">
        {@html job.deskripsi_pekerjaan}
      </div>
    </section>
  {/if}

  <!-- Persyaratan -->
  {#if job.persyaratan}
    <section class="wysiwyg-content" aria-labelledby="requirements">
      <h2 id="requirements" class="text-2xl flex items-center gap-2">
        <CircleCheckSolid
          class="text-[var(--wpl-global-color-1)] inline-block"
          aria-hidden="true"
        />
        <span class="font-semibold text-[var(--wpl-global-color-1)]"
          >Persyaratan</span
        >
      </h2>
      <div onclick={onWysiwygImgClick} role="presentation">
        {@html job.persyaratan}
      </div>
    </section>
  {/if}

  <!-- Cara Melamar -->
  {#if job.cara_melamar}
    <section class="wysiwyg-content" aria-labelledby="how-to-apply">
      <h2 id="how-to-apply" class="text-2xl flex items-center gap-2">
        <FileSignatureSolid
          class="text-[var(--wpl-global-color-1)] inline-block"
          aria-hidden="true"
        />
        <span class="font-semibold text-[var(--wpl-global-color-1)]"
          >Cara Melamar</span
        >
      </h2>
      <div onclick={onWysiwygImgClick} role="presentation">
        {@html job.cara_melamar}
      </div>
    </section>
  {/if}

  <!-- Benefit -->
  {#if job.benefit}
    <section class="wysiwyg-content" aria-labelledby="benefits">
      <h2 id="benefits" class="text-2xl flex items-center gap-2 mb-4">
        <HandHoldingHeartSolid
          class="text-[var(--wpl-global-color-1)] inline-block"
          aria-hidden="true"
        />
        <span class="font-semibold text-[var(--wpl-global-color-1)]"
          >Benefit</span
        >
      </h2>
      <div onclick={onWysiwygImgClick} role="presentation">
        {@html job.benefit}
      </div>
    </section>
  {/if}

  <!-- Kontak -->
  {#if contacts && contacts.length}
    <section class="aside-content" aria-labelledby="contacts-heading">
      <h2
        id="contacts-heading"
        class="text-2xl flex items-center justify-between mb-4"
      >
        <span class="flex items-center gap-2">
          <AddressCardSolid
            class="text-[var(--wpl-global-color-1)]"
            aria-hidden="true"
          />
          <span class="font-semibold text-[var(--wpl-global-color-1)]"
            >Kontak</span
          >
        </span>
      </h2>
      <address class="not-italic">
        <div class="grid grid-cols-1 gap-4 mt-4">
          <div
            class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4"
          >
            <ul class="space-y-3">
              {#each contacts as contact (contact.label + contact.value)}
                {@const Icon = contact.icon}
                <li class="flex items-center">
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
                      rel="noopener noreferrer nofollow"
                      class="block font-semibold wrap-anywhere max-w-full whitespace-normal text-[var(--wpl-global-color-1)] hover:underline"
                      >{contact.value}</a
                    >
                  </div>
                </li>
              {/each}
            </ul>
          </div>
        </div>
      </address>
    </section>
  {/if}

  <!-- Sosial Media -->
  {#if socialMediaItems && socialMediaItems.length}
    <section class="aside-content" aria-labelledby="social-media-heading">
      <h2
        id="social-media-heading"
        class="text-2xl flex items-center gap-2 mb-4"
      >
        <AddressBookSolid
          class="text-[var(--wpl-global-color-1)]"
          aria-hidden="true"
        />
        <span class="font-semibold text-[var(--wpl-global-color-1)]"
          >Sosial Media</span
        >
      </h2>
      <nav aria-label="Sosial media links">
        <ul
          class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4"
        >
          {#each socialMediaItems as item (item.platform + item.username)}
            {@const Icon = item.icon}
            <li class="flex items-center">
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
                  rel="noopener noreferrer nofollow"
                  class="block font-semibold break-words max-w-full whitespace-normal text-[var(--wpl-global-color-1)] hover:underline"
                >
                  {item.platform === SocialMediaPlatform.WhatsApp
                    ? item.username
                      ? FormattingService.formatPhone(item.username)
                      : ""
                    : (item.username ?? "")}
                </a>
              </div>
            </li>
          {/each}
        </ul>
      </nav>
    </section>
  {/if}
</article>
{#if allImages.length}
  <div bind:this={galleryRef} class="hidden">
    {#each allImages as img}
      <img src={img} alt="" />
    {/each}
  </div>
{/if}

<style lang="postcss">
  @reference "@css/app.css";
  @utility job-detail-base-content {
    @apply rounded-xl border-1 border-[var(--wpl-global-color-1)] bg-[var(--wpl-global-color-5)];
  }
  .wysiwyg-content {
    @apply job-detail-base-content px-3;
  }
  .aside-content {
    @apply job-detail-base-content p-4;
  }
</style>
