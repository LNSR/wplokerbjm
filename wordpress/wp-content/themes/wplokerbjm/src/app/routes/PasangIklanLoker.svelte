<script lang="ts">
  import {
    LightbulbSolid,
    InstagramBrands,
    WhatsappBrands,
    EnvelopeSolid,
    CircleInfoSolid,
    CircleCheckSolid,
  } from "svelte-awesome-icons";

  const sectionClass =
    "rounded-xl border-2 border-blue-500 dark:border-blue-400 shadow-md p-4 sm:p-6 md:p-8 mb-12 bg-white dark:bg-gray-900";

  const baseIconClass =
    "flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full mr-4 shadow-sm";

  const linkClass =
    "!text-[var(--wpl-global-color-1)] dark:!text-blue-400 font-medium hover:underline break-words";
</script>

<main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
  <div>
    <h1
      class="text-3xl md:text-4xl font-bold mt-8 mb-8 text-gray-900 dark:text-gray-100 text-center"
    >
      Pasang Iklan Lowongan Kerja
    </h1>
  </div>

  <!-- Benefit Section -->
  <section class={sectionClass} aria-labelledby="section-benefit">
    <h2
      id="section-benefit"
      class="text-2xl text-center font-bold text-[var(--wpl-global-color-1)] dark:text-blue-400 mb-4 break-words"
    >
      Tingkatkan Peluang Mendapatkan Kandidat Terbaik
    </h2>
    <p class="text-justify mb-4 text-gray-700 dark:text-gray-200 break-words">
      Sebarkan informasi lowongan kerja Anda ke ribuan pencari kerja di
      Banjarmasin dan sekitarnya melalui platform kami. Dengan jangkauan luas
      dan fitur pencarian yang efektif, Anda dapat menemukan kandidat yang tepat
      dengan cepat.
    </p>

    <div
      class="flex flex-row items-center gap-3 mt-6 p-4 bg-blue-200 dark:bg-blue-900/40 rounded-lg border border-blue-300 dark:border-blue-700 break-words"
      role="note"
    >
      <LightbulbSolid
        class="flex-shrink-0 text-yellow-500 dark:text-yellow-300 text-xl mt-1"
        aria-hidden="true"
      />
      <span class="text-blue-800 dark:text-blue-200 text-justify">
        <strong>Keuntungan:</strong> Iklan lowongan kerja Anda akan ditampilkan di
        website dan dipromosikan ke media sosial kami dengan jangkauan ribuan pencari
        kerja.
      </span>
    </div>
  </section>

  <!-- Cara Memasang Section -->
  <section class={sectionClass} aria-labelledby="section-cara">
    <h2
      id="section-cara"
      class="text-2xl font-bold mb-6 text-center text-gray-900 dark:text-gray-100 break-words"
    >
      Cara Memasang Lowongan Kerja
    </h2>
    <div class="relative">
      <div
        class="hidden md:block absolute left-1/2 top-4 bottom-4 w-[2px] bg-gradient-to-b from-blue-300 via-blue-500 to-blue-300 transform -translate-x-1/2"
        aria-hidden="true"
      ></div>
      <div class="grid grid-cols-1 md:grid-cols-2 md:gap-12 gap-8 pt-4">
        <div class="md:pr-5">
          <aside
            class="p-3 md:pl-8 bg-blue-50 dark:bg-blue-900/30 rounded-xl"
            aria-labelledby="info-dibutuhkan"
          >
            <h3
              class="text-xl text-center font-semibold text-[var(--wpl-global-color-1)] dark:text-blue-400 mb-3 break-words"
            >
              Hubungi Kami
            </h3>
            <p class="mb-2 text-gray-700 dark:text-gray-200 break-words">
              Silakan hubungi admin kami melalui:
            </p>
            <ul class="space-y-4">
              <li class="flex items-center">
                <span
                  class={`${baseIconClass} bg-gradient-to-r from-purple-600 via-pink-500 to-orange-400 dark:from-purple-700 dark:via-pink-600 dark:to-orange-500`}
                >
                  <InstagramBrands
                    class="text-xl text-white"
                    aria-hidden="true"
                  />
                </span>
                <div class="min-w-0 break-words">
                  <span
                    class="font-medium block text-gray-800 dark:text-gray-100 break-words"
                    >Instagram:</span
                  >
                  <a
                    href="https://instagram.com/loker_banjarmasin"
                    target="_blank"
                    rel="noopener noreferrer"
                    class={linkClass}
                  >
                    @loker_banjarmasin
                  </a>
                </div>
              </li>
              <li class="flex items-center">
                <span class={`${baseIconClass} bg-[#25D366] dark:bg-[#128C7E]`}>
                  <WhatsappBrands
                    class="text-xl text-white"
                    aria-hidden="true"
                  />
                </span>
                <div class="min-w-0 break-words">
                  <span
                    class="font-medium block text-gray-800 dark:text-gray-100 break-words"
                    >WhatsApp:</span
                  >
                  <a
                    href="https://wa.me/6283862447271"
                    target="_blank"
                    rel="noopener noreferrer"
                    class={linkClass}
                  >
                    +62 838-6244-7271
                  </a>
                </div>
              </li>
              <li class="flex items-center">
                <span class={`${baseIconClass} bg-[#EA4335] dark:bg-red-700`}>
                  <EnvelopeSolid
                    class="text-xl text-white"
                    aria-hidden="true"
                  />
                </span>
                <div class="min-w-0 break-words">
                  <span
                    class="font-medium block text-gray-800 dark:text-gray-100 text-wrap break-words"
                    >Email:</span
                  >
                  <a href="mailto:muhammadindra003@gmail.com" class={linkClass}>
                    muhammadindra003@gmail.com
                  </a>
                </div>
              </li>
            </ul>
          </aside>
        </div>
        <aside
          class="p-3 md:pl-8 bg-blue-50 dark:bg-blue-900/30 rounded-xl"
          aria-labelledby="info-dibutuhkan"
        >
          <h3
            id="info-dibutuhkan"
            class="flex flex-row items-center justify-center text-center text-lg font-semibold mb-3 text-blue-700 dark:text-blue-300 break-words"
          >
            <CircleInfoSolid
              class="text-[var(--wpl-global-color-1)] dark:text-blue-300 items-start mr-3"
              aria-hidden="true"
            />
            <span>Informasi yang Dibutuhkan:</span>
          </h3>
          <ul class="space-y-2 px-4 py-2">
            <li class="flex items-start">
              <CircleCheckSolid
                class="text-green-500 dark:text-green-400 mr-3"
                aria-hidden="true"
              />
              <span class="text-gray-800 dark:text-gray-100 break-words"
                >Identitas pengirim loker</span
              >
            </li>
            <li class="flex items-start">
              <CircleCheckSolid
                class="text-green-500 dark:text-green-400 mr-3"
                aria-hidden="true"
              />
              <span class="text-gray-800 dark:text-gray-100 break-words"
                >Posisi yang dibutuhkan</span
              >
            </li>
            <li class="flex items-start">
              <CircleCheckSolid
                class="text-green-500 dark:text-green-400 mr-3"
                aria-hidden="true"
              />
              <span class="text-gray-800 dark:text-gray-100 break-words"
                >Persyaratan pekerjaan</span
              >
            </li>
            <li class="flex items-start">
              <CircleCheckSolid
                class="text-green-500 dark:text-green-400 mr-3"
                aria-hidden="true"
              />
              <span class="text-gray-800 dark:text-gray-100 break-words"
                >Lokasi kerja</span
              >
            </li>
            <li class="flex items-start">
              <CircleCheckSolid
                class="text-green-500 dark:text-green-400 mr-3"
                aria-hidden="true"
              />
              <span class="text-gray-800 dark:text-gray-100 break-words"
                >Kontak pengirim loker</span
              >
            </li>
          </ul>
        </aside>
      </div>
    </div>
  </section>

  <!-- Syarat Section -->
  <section class={sectionClass} aria-labelledby="section-syarat">
    <h2
      id="section-syarat"
      class="text-2xl font-bold text-center mb-4 text-gray-900 dark:text-gray-100 break-words"
    >
      Syarat &amp; Ketentuan
    </h2>
    <div class="space-y-4">
      <p class="text-gray-700 dark:text-gray-200 break-words mb-2">
        Dengan memasang iklan lowongan kerja di platform kami, Anda menyetujui
        ketentuan berikut:
      </p>
      <ol
        class="list-decimal pl-5 space-y-2 text-gray-700 dark:text-gray-200 break-words"
      >
        <li>
          Lowongan yang dipasang harus sesuai dengan peraturan ketenagakerjaan
          yang berlaku
        </li>
        <li>
          Tidak memuat konten diskriminatif terhadap gender, agama, ras, atau
          suku tertentu
        </li>
        <li>
          Informasi yang diberikan harus akurat dan dapat dipertanggungjawabkan
        </li>
        <li>
          Tidak memungut biaya apa pun dari pelamar dalam proses rekrutmen
        </li>
        <li>Kami berhak menolak iklan yang tidak sesuai dengan ketentuan</li>
      </ol>
    </div>
  </section>
</main>
