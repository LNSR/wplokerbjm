<script module lang="ts">
  import {
    ClockSolid,
    EnvelopeSolid,
    PhoneSolid,
    GlobeSolid,
    UserTieSolid,
    MapPinSolid,
    CircleInfoSolid,
    CircleCheckSolid,
    FileSignatureSolid,
    HandHoldingHeartSolid,
    AddressCardSolid,
    AddressBookSolid,
    TwitterBrands,
    FacebookBrands,
    InstagramBrands,
    LinkedinBrands,
    YoutubeBrands,
    WhatsappBrands,
    TiktokBrands,
    ThreadsBrands,
    TelegramBrands,
  } from "svelte-awesome-icons";
  import { SvelteSet } from "svelte/reactivity";
  import type {
    JobContactRow,
    SocialMediaItem,
    CustomFields,
    JobSummary,
  } from "@/types";
  import { SocialMediaPlatform } from "@/types";
  import type { Component } from "svelte";
  import { browser } from "$app/environment";
  import ViewerModule from "viewerjs";
  import "viewerjs/dist/viewer.min.css";
  import BookmarkButton from "@components/ui/Shared/BookmarkButton.svelte";
  import { onMount } from "svelte";
  import type { JobDetailResponse } from "@/types";
  import { page } from "$app/state";
  import { showSummaryJob, showTimeAgo } from "$lib/composables/JobUI.svelte";

  interface ContactRow {
    type: "email" | "phone" | "website";
    icon: Component;
    label: string;
    value?: string;
    href: string;
  }

  class SocialMediaLinkBuilder {
    static #platforms = new Map<
      SocialMediaPlatform,
      { icon: Component; base_url: string }
    >([
      [
        SocialMediaPlatform["X / Twitter"],
        {
          icon: TwitterBrands,
          base_url: "https://x.com/",
        },
      ],
      [
        SocialMediaPlatform.Facebook,
        {
          icon: FacebookBrands,
          base_url: "https://facebook.com/",
        },
      ],
      [
        SocialMediaPlatform.Instagram,
        {
          icon: InstagramBrands,
          base_url: "https://instagram.com/",
        },
      ],
      [
        SocialMediaPlatform.LinkedIn,
        {
          icon: LinkedinBrands,
          base_url: "https://linkedin.com/in/",
        },
      ],
      [
        SocialMediaPlatform.Youtube,
        {
          icon: YoutubeBrands,
          base_url: "https://youtube.com/@",
        },
      ],
      [
        SocialMediaPlatform.WhatsApp,
        {
          icon: WhatsappBrands,
          base_url: "https://wa.me/",
        },
      ],
      [
        SocialMediaPlatform.TikTok,
        {
          icon: TiktokBrands,
          base_url: "https://tiktok.com/@",
        },
      ],
      [
        SocialMediaPlatform.Threads,
        {
          icon: ThreadsBrands,
          base_url: "https://threads.net/@",
        },
      ],
      [
        SocialMediaPlatform.Telegram,
        {
          icon: TelegramBrands,
          base_url: "https://t.me/",
        },
      ],
    ]);

    public static buildSocialMediaItems(
      socialMediaData: CustomFields["social_media"],
    ): SocialMediaItem[] {
      const processedItems: SocialMediaItem[] = [];
      const seen = new SvelteSet<string>();
      if (!socialMediaData) return processedItems;

      const items = socialMediaData
        .split(";")
        .map((s: string) => s.trim())
        .filter(Boolean);

      for (const item of items) {
        const idx = item.indexOf(":");
        if (idx === -1) continue;
        const platform = item.slice(0, idx).trim() as SocialMediaPlatform;
        const usernames = item.slice(idx + 1).trim();
        if (!platform || !usernames) continue;
        const usernameList = usernames
          .split(",")
          .map((u: string) => u.trim())
          .filter((u) => u);
        for (const username of usernameList) {
          const linkData = SocialMediaLinkBuilder.getLinkData(
            platform,
            username,
          );
          if (linkData) {
            const key = linkData.platform + linkData.username;
            if (!seen.has(key)) {
              seen.add(key);
              processedItems.push(linkData);
            }
          }
        }
      }
      return processedItems;
    }

    private static getLinkData(
      platform: SocialMediaPlatform,
      username: string,
    ) {
      const config = this.#platforms.get(platform);
      if (!config || !username) return null;
      if (platform === SocialMediaPlatform.WhatsApp)
        return this.getWhatsappLinkData(platform, config, username);
      if (platform === SocialMediaPlatform.LinkedIn)
        return this.getLinkedInLinkData(platform, config, username);
      return this.getDefaultLinkData(platform, config, username);
    }

    private static normalizeUrl(url: string): string {
      return url.replace(/^http:\/\//i, "https://");
    }

    private static formatPhone(number: string): string {
      if (!number) return "";
      number = number.replace(/[^\d+]/g, "");

      const match = number.match(/^\+(\d{1,5})(\d{0,})$/);
      if (match) {
        const countryCode = "+" + match[1];
        const rest = match[2] || "";
        const formattedRest = rest.replace(/(.{4})/g, "$1 ").trim();
        return (countryCode + " " + formattedRest).trim();
      } else {
        number = number.replace(/\D+/g, "");
        return number.replace(/(.{4})/g, "$1 ").trim();
      }
    }

    private static getWhatsappLinkData(
      platform: SocialMediaPlatform.WhatsApp,
      config: { icon: Component; base_url: string },
      username: string,
    ): SocialMediaItem {
      if (/^https?:\/\/wa\.me\/qr\/[A-Z0-9]+$/i.test(username)) {
        const normalized = this.normalizeUrl(username);
        return { platform, icon: config.icon, url: normalized, username };
      }
      const waMeMatch = /^(?:https?:\/\/)?wa\.me\/(\d+)$/i.exec(username);
      if (waMeMatch) {
        const number = this.formatPhone(waMeMatch[1]);
        return {
          platform,
          icon: config.icon,
          url: `https://wa.me/${number}`,
          username: `+${number}`,
        };
      }
      if (/^https?:\/\/((api|web)\.whatsapp\.com)/.test(username)) {
        const normalized = this.normalizeUrl(username);
        return { platform, icon: config.icon, url: normalized, username };
      }
      const cleanNumber = username.replace(/[^0-9]/g, "");
      return {
        platform,
        icon: config.icon,
        url: config.base_url + cleanNumber,
        username,
      };
    }

    private static getLinkedInLinkData(
      platform: SocialMediaPlatform.LinkedIn,
      config: { icon: Component; base_url: string },
      username: string,
    ): SocialMediaItem {
      if (/^https?:\/\//i.test(username)) {
        const normalized = this.normalizeUrl(username);
        return { platform, icon: config.icon, url: normalized, username };
      }
      const clean_username = username.replace(/^@/, "");
      const companyMatch = /^company[:/](.+)$/i.exec(clean_username);
      let url;
      if (companyMatch) {
        url = `https://linkedin.com/company/${companyMatch[1]}`;
      } else {
        url = config.base_url + clean_username;
      }
      return { platform, icon: config.icon, url, username };
    }

    private static getDefaultLinkData(
      platform: SocialMediaPlatform,
      config: { icon: Component; base_url: string },
      username: string,
    ): SocialMediaItem {
      if (/^https?:\/\//i.test(username)) {
        const normalized = this.normalizeUrl(username);
        return { platform, icon: config.icon, url: normalized, username };
      }
      const clean_username = username.replace(/^@/, "");
      const url = config.base_url + clean_username;
      return { platform, icon: config.icon, url, username };
    }
  }
</script>

<script lang="ts">
  const { job }: { job: JobDetailResponse } = $props();

  /**
   * Handler class to manage ViewerJS instance and image extraction from WYSIWYG content
   */
  class ViewerJSHandler {
    #Viewer: typeof ViewerModule = ViewerModule;
    #instance?: InstanceType<typeof ViewerModule>;
    public images = $derived(this.extractUniqueImagesFromJob(job));
    public galleryRef = $state<HTMLElement>();

    public onWysiwygImgClick = (e: MouseEvent): void => {
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

      const imageIndex = this.images.indexOf(src);
      if (imageIndex >= 0 && this.#instance) {
        this.#instance.show();
        this.#instance.view(imageIndex);
      }
    };

    public initialize() {
      if (this.images.length > 0) {
        this.setupViewer();
      }
    }

    public destroyViewer(): void {
      if (this.#instance) {
        this.galleryRef = undefined;
        this.#instance.destroy();
        this.#instance = undefined;
      }
    }

    private viewerOptions(): Viewer.Options {
      const container = browser
        ? ((document.querySelector("#app") as HTMLElement) ?? document.body)
        : undefined;
      const opts: Viewer.Options = {
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
      return opts;
    }

    private setupViewer(): void {
      if (!browser || !this.galleryRef || this.#instance) return;

      this.#instance = new this.#Viewer(this.galleryRef, this.viewerOptions());
    }

    private extractImages(html: string): string[] {
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

    private extractUniqueImagesFromJob(
      job: Partial<JobDetailResponse>,
    ): string[] {
      // WYSIWYG fields that may contain images
      const fieldsToExtract = [
        job.tentang_perusahaan,
        job.deskripsi_pekerjaan,
        job.persyaratan,
        job.cara_melamar,
        job.benefit,
      ];
      const allSrcs = fieldsToExtract
        .filter((field): field is string => Boolean(field))
        .flatMap((field) => this.extractImages(field));
      return [...new Set(allSrcs)];
    }
  }
  const viewerJSHandler = new ViewerJSHandler();

  function buildContactRows(jobData?: JobContactRow): ContactRow[] {
    if (!jobData) return [];
    const contacts: ContactRow[] = [];

    const contactFields: ContactRow[] = [
      {
        type: "email",
        icon: EnvelopeSolid,
        label: "Email",
        value: jobData.email_kontak ?? undefined,
        href: `mailto:${jobData.email_kontak}`,
      },
      {
        type: "phone",
        icon: PhoneSolid,
        label: "Telepon",
        value: jobData.nomor_kontak ?? undefined,
        href: `tel:${jobData.nomor_kontak}`,
      },
      {
        type: "website",
        icon: GlobeSolid,
        label: "Website",
        value: jobData.situs_kontak ?? undefined,
        href: jobData.situs_kontak
          ? jobData.situs_kontak.replace(/^http:\/\//i, "https://")
          : "",
      },
    ];

    contactFields.forEach((field) => {
      if (field.value) {
        const values = field.value
          .split(",")
          .map((v) => v.trim())
          .filter((v) => v);
        values.forEach((value) => {
          if (value) {
            contacts.push({
              type: field.type,
              icon: field.icon,
              label: field.label,
              value,
              href: field.href,
            });
          }
        });
      }
    });

    return contacts;
  }

  const ringkasanPekerjaan = $derived(
    showSummaryJob(job.ringkasanPekerjaan as JobSummary),
  );
  const contacts = $derived(buildContactRows(job.contacts));
  const socialMediaItems = $derived(
    SocialMediaLinkBuilder.buildSocialMediaItems(job.social_media),
  );
  const timeAgo = $derived(showTimeAgo(job.post_time));

  onMount(() => {
    viewerJSHandler.initialize();
    return () => {
      viewerJSHandler.destroyViewer();
    };
  });
</script>

<svelte:head>
  {#if page.data?.jobSchemaScript}
    {@html page.data.jobSchemaScript}
  {/if}
</svelte:head>

<article class="space-y-8">
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
                    class="text-[var(--wpl-global-color-1)] inline-block w-4 h-4 sm:w-5 sm:h-5 shrink-0"
                    aria-hidden="true"
                  />
                  <span class="font-bold ml-1">{job.nama_perusahaan}</span>
                </div>
              {/if}
              {#if job.post_time}
                <div class="items-center gap-2 mb-2">
                  <ClockSolid
                    class="text-[var(--wpl-global-color-1)] inline-block w-4 h-4 sm:w-5 sm:h-5 shrink-0"
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
            class="mt-2 min-w-0 grid grid-cols-[repeat(auto-fit,minmax(150px,1fr))] gap-4 text-sm md:text-base"
          >
            {#each ringkasanPekerjaan as row (row.label)}
              {const Icon = row.icon}
              <div class="max-w-full flex items-start gap-2">
                {#if Icon}
                  <Icon
                    class="text-[var(--wpl-global-color-1)] inline-block w-5 h-5 md:w-6 md:h-6 shrink-0"
                    aria-hidden="true"
                  />
                {/if}
                <div class="w-full min-w-0 break-words">
                  <div class="font-bold">{row.label}</div>
                  <div
                    class="text-[var(--wpl-global-color-1)] font-bold text-ellipsis"
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
          class="text-[var(--wpl-global-color-1)] inline-block w-5 h-5 md:w-7 md:h-7 shrink-0"
          aria-hidden="true"
        />
        <span class="font-semibold text-[var(--wpl-global-color-1)]"
          >Tentang Perusahaan</span
        >
      </h2>
      <div onclick={viewerJSHandler.onWysiwygImgClick} role="presentation">
        {@html job.tentang_perusahaan}
      </div>
    </section>
  {/if}

  <!-- Deskripsi Pekerjaan -->
  {#if job.deskripsi_pekerjaan}
    <section class="wysiwyg-content" aria-labelledby="job-description">
      <h2 id="job-description" class="text-xl flex items-center gap-2 mb-4">
        <CircleInfoSolid
          class="text-[var(--wpl-global-color-1)] inline-block w-5 h-5 md:w-6 md:h-6 shrink-0"
          aria-hidden="true"
        />
        <span class="font-semibold text-[var(--wpl-global-color-1)]"
          >Deskripsi Pekerjaan</span
        >
      </h2>
      <div onclick={viewerJSHandler.onWysiwygImgClick} role="presentation">
        {@html job.deskripsi_pekerjaan}
      </div>
    </section>
  {/if}

  <!-- Persyaratan -->
  {#if job.persyaratan}
    <section class="wysiwyg-content" aria-labelledby="requirements">
      <h2 id="requirements" class="text-2xl flex items-center gap-2">
        <CircleCheckSolid
          class="text-[var(--wpl-global-color-1)] inline-block w-5 h-5 md:w-7 md:h-7 shrink-0"
          aria-hidden="true"
        />
        <span class="font-semibold text-[var(--wpl-global-color-1)]"
          >Persyaratan</span
        >
      </h2>
      <div onclick={viewerJSHandler.onWysiwygImgClick} role="presentation">
        {@html job.persyaratan}
      </div>
    </section>
  {/if}

  <!-- Cara Melamar -->
  {#if job.cara_melamar}
    <section class="wysiwyg-content" aria-labelledby="how-to-apply">
      <h2 id="how-to-apply" class="text-2xl flex items-center gap-2">
        <FileSignatureSolid
          class="text-[var(--wpl-global-color-1)] inline-block w-5 h-5 md:w-7 md:h-7 shrink-0"
          aria-hidden="true"
        />
        <span class="font-semibold text-[var(--wpl-global-color-1)]"
          >Cara Melamar</span
        >
      </h2>
      <div onclick={viewerJSHandler.onWysiwygImgClick} role="presentation">
        {@html job.cara_melamar}
      </div>
    </section>
  {/if}

  <!-- Benefit -->
  {#if job.benefit}
    <section class="wysiwyg-content" aria-labelledby="benefits">
      <h2 id="benefits" class="text-2xl flex items-center gap-2 mb-4">
        <HandHoldingHeartSolid
          class="text-[var(--wpl-global-color-1)] inline-block w-5 h-5 md:w-7 md:h-7 shrink-0"
          aria-hidden="true"
        />
        <span class="font-semibold text-[var(--wpl-global-color-1)]"
          >Benefit</span
        >
      </h2>
      <div onclick={viewerJSHandler.onWysiwygImgClick} role="presentation">
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
            class="text-[var(--wpl-global-color-1)] w-5 h-5 md:w-7 md:h-7 shrink-0"
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
                {const Icon = contact.icon}
                <li class="flex items-center">
                  {#if Icon}
                    <Icon
                      class="text-[var(--wpl-global-color-1)] w-5 h-5 md:w-6 md:h-6 text-center inline-block shrink-0"
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
          class="text-[var(--wpl-global-color-1)] w-5 h-5 md:w-7 md:h-7 shrink-0"
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
            {const Icon = item.icon}
            <li class="flex items-center">
              {#if Icon}
                <Icon
                  class="text-[var(--wpl-global-color-1)] w-5 h-5 md:w-6 md:h-6 text-center inline-block shrink-0"
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
                  {item.username}
                </a>
              </div>
            </li>
          {/each}
        </ul>
      </nav>
    </section>
  {/if}
</article>
{#if viewerJSHandler.images.length > 0}
  <div bind:this={viewerJSHandler.galleryRef} style="display: none;">
    {#each viewerJSHandler.images as img (img)}
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
