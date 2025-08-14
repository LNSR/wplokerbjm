<template>
    <Header :header="props.header" />
    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
        <header>
            <h1 class="text-3xl md:text-4xl font-bold !mb-8 text-center">Pasang Iklan Lowongan Kerja</h1>
        </header>
        <!-- Benefit Section -->
        <section
            class="rounded-xl border-2 border-blue-500 dark:border-blue-400 shadow-md p-4 sm:p-6 md:p-8 mb-12 bg-white dark:bg-gray-900"
            aria-labelledby="section-benefit">
            <h2 id="section-benefit" class="text-2xl font-bold text-blue-600 dark:text-blue-400 mb-4 break-words">
                Tingkatkan Peluang Mendapatkan Kandidat Terbaik
            </h2>
            <p class="text-justify mb-4 text-gray-700 dark:text-gray-200 break-words">
                Sebarkan informasi lowongan kerja Anda ke ribuan pencari kerja di Banjarmasin dan sekitarnya melalui
                platform kami.
                Dengan jangkauan luas dan fitur pencarian yang efektif, Anda dapat menemukan kandidat yang tepat dengan
                cepat.
            </p>
            <div class="flex flex-row items-center gap-3 mt-6 p-4 bg-blue-200 dark:bg-blue-900/40 rounded-lg border border-blue-300 dark:border-blue-700 break-words"
                role="note">
                <i class="fas fa-lightbulb text-yellow-500 dark:text-yellow-300 text-xl mt-1" aria-hidden="true"></i>
                <span class="text-blue-800 dark:text-blue-200 text-justify">
                    <strong>Keuntungan:</strong> Iklan lowongan kerja Anda akan ditampilkan di website dan dipromosikan
                    ke media sosial kami dengan jangkauan ribuan pencari kerja.
                </span>
            </div>
        </section>

        <!-- Cara Memasang Section -->
        <section
            class="rounded-xl border-2 border-blue-500 dark:border-blue-400 shadow-md p-4 sm:p-6 md:p-8 mb-12 bg-white dark:bg-gray-900"
            aria-labelledby="section-cara">
            <h2 id="section-cara"
                class="text-2xl font-bold mb-6 text-center text-gray-900 dark:text-gray-100 break-words">
                Cara Memasang Lowongan Kerja
            </h2>
            <div class="relative">
                <div class="hidden md:block absolute left-1/2 top-4 bottom-4 w-[2px] bg-gradient-to-b from-blue-300 via-blue-500 to-blue-300 transform -translate-x-1/2"
                    aria-hidden="true"></div>
                <div class="grid grid-cols-1 md:grid-cols-2 md:gap-12 gap-8 pt-4">
                    <div class="md:pr-6">
                        <h3 class="text-xl font-semibold text-blue-600 dark:text-blue-400 mb-3 break-words">Hubungi Kami
                        </h3>
                        <p class="mb-4 text-gray-700 dark:text-gray-200 break-words">Silakan hubungi admin kami melalui:
                        </p>
                        <ul class="space-y-4">
                            <li class="flex items-center">
                                <span
                                    class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-gradient-to-r from-purple-600 via-pink-500 to-orange-400 dark:from-purple-700 dark:via-pink-600 dark:to-orange-500 mr-4 shadow-sm">
                                    <i class="fab fa-instagram text-xl text-white" aria-hidden="true"></i>
                                </span>
                                <div class="min-w-0 break-words">
                                    <span
                                        class="font-medium block text-gray-800 dark:text-gray-100 break-words">Instagram:</span>
                                    <a href="https://instagram.com/loker_banjarmasin" target="_blank" rel="noopener"
                                        class="text-blue-600 dark:text-blue-400 font-medium hover:underline break-words">
                                        @loker_banjarmasin
                                    </a>
                                </div>
                            </li>
                            <li class="flex items-center">
                                <span
                                    class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-[#25D366] dark:bg-[#128C7E] mr-4 shadow-sm">
                                    <i class="fab fa-whatsapp text-xl text-white" aria-hidden="true"></i>
                                </span>
                                <div class="min-w-0 break-words">
                                    <span
                                        class="font-medium block text-gray-800 dark:text-gray-100 break-words">WhatsApp:</span>
                                    <a href="https://wa.me/6283862447271" target="_blank" rel="noopener"
                                        class="text-blue-600 dark:text-blue-400 font-medium hover:underline break-words">
                                        +62 838-6244-7271
                                    </a>
                                </div>
                            </li>
                            <li class="flex items-center">
                                <span
                                    class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-[#EA4335] dark:bg-red-700 mr-4 shadow-sm">
                                    <i class="fas fa-envelope text-xl text-white" aria-hidden="true"></i>
                                </span>
                                <div class="min-w-0 break-words">
                                    <span
                                        class="font-medium block text-gray-800 dark:text-gray-100 text-wrap break-words">Email:</span>
                                    <a href="mailto:muhammadindra003@gmail.com"
                                        class="text-blue-600 dark:text-blue-400 font-medium hover:underline break-words">
                                        muhammadindra003@gmail.com
                                    </a>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <aside class="p-6 md:pl-8 bg-blue-50 dark:bg-blue-900/30 rounded-xl"
                        aria-labelledby="info-dibutuhkan">
                        <h3 id="info-dibutuhkan"
                            class="flex flex-row items-center justify-center text-lg font-semibold mb-3 text-blue-700 dark:text-blue-300 break-words">
                            <i class="fas fa-info-circle text-blue-500 dark:text-blue-300 mr-4" aria-hidden="true"></i>
                            <span>Informasi yang Dibutuhkan:</span>
                        </h3>
                        <ul class="space-y-2">
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 dark:text-green-400 mt-1 mr-2"
                                    aria-hidden="true"></i>
                                <span class="text-gray-800 dark:text-gray-100 break-words">Nama perusahaan</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 dark:text-green-400 mt-1 mr-2"
                                    aria-hidden="true"></i>
                                <span class="text-gray-800 dark:text-gray-100 break-words">Posisi yang dibutuhkan</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 dark:text-green-400 mt-1 mr-2"
                                    aria-hidden="true"></i>
                                <span class="text-gray-800 dark:text-gray-100 break-words">Persyaratan pekerjaan</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 dark:text-green-400 mt-1 mr-2"
                                    aria-hidden="true"></i>
                                <span class="text-gray-800 dark:text-gray-100 break-words">Lokasi kerja</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 dark:text-green-400 mt-1 mr-2"
                                    aria-hidden="true"></i>
                                <span class="text-gray-800 dark:text-gray-100 break-words">Kontak untuk lamaran</span>
                            </li>
                        </ul>
                    </aside>
                </div>
            </div>
        </section>

        <!-- Paket Section -->
        <!-- <section
            class="rounded-xl border-2 border-blue-500 dark:border-blue-400 shadow-md p-4 sm:p-6 md:p-8 mb-12 bg-white dark:bg-gray-900"
            aria-labelledby="section-paket">
            <h2 id="section-paket" class="text-2xl font-bold mb-8 text-gray-900 dark:text-gray-100 break-words">Paket
                Pemasangan Iklan</h2>
            <div class="py-12 px-4 text-center">
                <h3 class="text-4xl md:text-5xl font-bold text-blue-600 dark:text-blue-400 mb-2 break-words">Coming Soon
                </h3>
                <p class="text-lg text-gray-700 dark:text-gray-200 break-words">Informasi paket pemasangan akan segera
                    tersedia</p>
            </div>
            <div class="text-center mt-4 pt-2">
                <span class="italic text-gray-700 dark:text-gray-300 break-words">
                    <i class="fas fa-info-circle text-blue-500 dark:text-blue-300 mr-2" aria-hidden="true"></i>
                    Hubungi admin untuk informasi lebih lanjut mengenai biaya pemasangan
                </span>
            </div>
        </section> -->

        <!-- Syarat Section -->
        <section
            class="rounded-xl border-2 border-blue-500 dark:border-blue-400 shadow-md p-4 sm:p-6 md:p-8 mb-12 bg-white dark:bg-gray-900"
            aria-labelledby="section-syarat">
            <h2 id="section-syarat" class="text-2xl font-bold mb-4 text-gray-900 dark:text-gray-100 break-words">Syarat
                &amp; Ketentuan</h2>
            <div class="space-y-4">
                <p class="text-gray-700 dark:text-gray-200 break-words">
                    Dengan memasang iklan lowongan kerja di platform kami, Anda menyetujui ketentuan berikut:
                </p>
                <ol class="list-decimal pl-5 space-y-2 text-gray-700 dark:text-gray-200 break-words">
                    <li>Lowongan yang dipasang harus sesuai dengan peraturan ketenagakerjaan yang berlaku</li>
                    <li>Tidak memuat konten diskriminatif terhadap gender, agama, ras, atau suku tertentu</li>
                    <li>Informasi yang diberikan harus akurat dan dapat dipertanggungjawabkan</li>
                    <li>Tidak memungut biaya apa pun dari pelamar dalam proses rekrutmen</li>
                    <li>Kami berhak menolak iklan yang tidak sesuai dengan ketentuan</li>
                </ol>
            </div>
        </section>
        <FloatingActionButton />
    </main>
    <Footer />
</template>

<script setup lang="ts">
import { defineAsyncComponent } from 'vue';
import Header from '@/layouts/Header.vue';
import Footer from '@/layouts/Footer.vue';
import type { LayoutProps } from '@/types';
const props = defineProps<LayoutProps>();
const FloatingActionButton = defineAsyncComponent(() => import('@/components/Shared/FloatingActionButton.vue'));
</script>