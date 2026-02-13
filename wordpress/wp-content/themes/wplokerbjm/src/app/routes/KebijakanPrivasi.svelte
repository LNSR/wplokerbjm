<script lang="ts">
  import { CircleInfoSolid } from "svelte-awesome-icons";

  const sectionClass =
    "rounded-xl border-2 border-[var(--wpl-global-color-1)] shadow-md p-4 sm:p-6 md:p-8 mb-12 bg-[var(--wpl-global-color-5)]";

  const baseIconClass =
    "flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full mr-4 shadow-sm";
</script>

<main class="container mx-auto max-w-[90vw] lg:max-w-[60vw] space-y-8 mt-12">
  <div>
    <h1
      class="text-3xl md:text-4xl font-bold mt-8 mb-8 text-gray-900 dark:text-gray-100 text-center"
    >
      Kebijakan Privasi
    </h1>
  </div>

  <!-- Privacy Policy Section -->
  <section class={sectionClass} aria-labelledby="section-privacy">
    <h2
      id="section-privacy"
      class="text-2xl font-bold text-[var(--wpl-global-color-1)] break-words flex items-center mb-4"
    >
      <div
        class={baseIconClass +
          " bg-[var(--wpl-global-color-1)] text-[var(--wpl-global-color-4)]"}
      >
        <CircleInfoSolid />
      </div>
      <span>Penggunaan Data dan Privasi</span>
    </h2>
    <p class="text-justify mb-4 text-gray-700 dark:text-gray-200 break-words">
      Situs ini menggunakan Google Analytics (GA4) dan Google Tag Manager (GTM)
      untuk menganalisis lalu lintas pengunjung. Kami tidak mengumpulkan data
      pribadi pengguna secara langsung, dan data yang dikumpulkan hanya
      digunakan untuk meningkatkan pengalaman situs. Di masa depan, kami mungkin
      menyisipkan sedikit iklan tanpa merusak pengalaman pengguna. Data tidak
      dijual atau dibagikan dengan pihak ketiga.
    </p>
    <p class="text-justify mb-4 text-gray-700 dark:text-gray-200 break-words">
      Kami berkomitmen untuk melindungi privasi pengunjung dan mematuhi
      peraturan perlindungan data yang berlaku. Untuk pertanyaan lebih lanjut,
      hubungi admin kami melalui media sosial seperti Instagram atau WhatsApp.
    </p>
  </section>

  <!-- Cookies Section -->
  <section class={sectionClass} aria-labelledby="section-cookies">
    <h2
      id="section-cookies"
      class="text-2xl font-bold text-[var(--wpl-global-color-1)] break-words flex items-center mb-4"
    >
      <div
        class={baseIconClass +
          " bg-[var(--wpl-global-color-1)] text-[var(--wpl-global-color-4)]"}
      >
        <CircleInfoSolid />
      </div>
      <span>Kebijakan Cookies dan Penyimpanan Browser</span>
    </h2>
    <p class="text-justify mb-4 text-gray-700 dark:text-gray-200 break-words">
      Situs ini menggunakan cookies dan penyimpanan alami browser seperti
      (localStorage, sessionStorage, IndexedDB) untuk meningkatkan pengalaman
      pengguna. Cookies adalah file kecil yang disimpan di perangkat Anda untuk
      membantu situs berfungsi dengan baik. Kami menggunakan cookies untuk
      menganalisis lalu lintas, mengingat preferensi Anda, dan menyediakan
      konten yang relevan (termasuk iklan).
    </p>
    <p class="text-justify mb-4 text-gray-700 dark:text-gray-200 break-words">
      Penyimpanan alami browser digunakan untuk menyimpan data sementara seperti
      posisi scroll, status pencarian, bookmark lowongan, dan preferensi
      navigasi & tema, untuk memberikan pengalaman yang lebih lancar. Data ini
      tidak berisi informasi pribadi dan hanya digunakan untuk fungsionalitas
      situs.
    </p>
    <p class="text-justify mb-4 text-gray-700 dark:text-gray-200 break-words">
      Anda dapat mengelola pengaturan cookies melalui browser Anda. Namun,
      menonaktifkan cookies atau penyimpanan alami browser mungkin mempengaruhi
      fungsionalitas situs.
    </p>
  </section>

  <!-- Job Fraud Awareness Section -->
  <section class={sectionClass} aria-labelledby="section-fraud">
    <h2
      id="section-fraud"
      class="text-2xl font-bold text-[var(--wpl-global-color-1)] break-words flex items-center mb-4"
    >
      <div
        class={baseIconClass +
          " bg-[var(--wpl-global-color-1)] text-[var(--wpl-global-color-4)]"}
      >
        <CircleInfoSolid />
      </div>
      <span>Peringatan Penipuan Lowongan Kerja</span>
    </h2>
    <p class="text-justify mb-4 text-gray-700 dark:text-gray-200 break-words">
      Kami sangat prihatin dengan maraknya penipuan lowongan kerja di Indonesia.
      Sebagai situs papan informasi lowongan kerja, terkadang admin kami bisa
      khilaf, mohon selalu teliti dan verifikasi terhadap informasi lowongan
      kerja yang tercantum. Jangan pernah memberikan uang atau data pribadi ke
      pihak yang tidak terpercaya.
    </p>
    <p class="text-justify mb-4 text-gray-700 dark:text-gray-200 break-words">
      Jika Anda menjadi korban penipuan lowongan kerja, segera laporkan ke pihak
      yang berwenang seperti Kepolisian Republik Indonesia atau Kementerian
      Ketenagakerjaan. Kami juga siap membantu dengan melaporkan lowongan
      mencurigakan yang terdeteksi pada papan informasi kami.
    </p>
  </section>

  <!-- Changes to Policy Section -->
  <section class={sectionClass} aria-labelledby="section-changes">
    <h2
      id="section-changes"
      class="text-2xl font-bold text-[var(--wpl-global-color-1)] break-words flex items-center mb-4"
    >
      <div
        class={baseIconClass +
          " bg-[var(--wpl-global-color-1)] text-[var(--wpl-global-color-4)]"}
      >
        <CircleInfoSolid />
      </div>
      <span>Perubahan Kebijakan</span>
    </h2>
    <p class="text-justify mb-4 text-gray-700 dark:text-gray-200 break-words">
      Apabila suatu saat nanti kami harus mengubah Kebijakan Privasi kami, maka
      kami akan mencantumkannya di sini agar para pengguna dapat mengetahui
      informasi apa saja yang kami kumpulkan dan bagaimana kami menggunakan
      informasi tersebut. Data pribadi Anda akan digunakan sesuai dengan kebijakan privasi kami.
    </p>
    <p class="text-justify mb-4 text-gray-700 dark:text-gray-200 break-words">
      Apabila, sewaktu-waktu Anda ingin mengajukan pertanyaan ataupun memberikan
      komentar tentang Kebijakan Privasi kami, maka Anda dapat menghubungi kami
      lewat tombol "Kontak Admin".
    </p>
    <p class="text-right text-sm font-medium">
      Terakhir diubah 12 Februari 2026.
    </p>
  </section>
</main>
