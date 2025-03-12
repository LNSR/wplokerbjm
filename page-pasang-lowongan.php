<?php
/**
 * Template Name: Pasang Iklan Loker
 * 
 * Template for displaying the job posting information page
 */

get_header();
?>

<div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-8">Pasang Iklan Lowongan Kerja</h1>
    
    <!-- Introduction -->
    <section class="bg-white rounded-xl shadow-md p-6 mb-12">
        <h2 class="text-2xl font-bold text-blue-600 mb-4">Tingkatkan Peluang Mendapatkan Kandidat Terbaik</h2>
        <p class="text-gray-700 mb-4">
            Sebarkan informasi lowongan kerja Anda ke ribuan pencari kerja di Banjarmasin dan sekitarnya melalui platform kami.
            Dengan jangkauan luas dan fitur pencarian yang efektif, Anda dapat menemukan kandidat yang tepat dengan cepat.
        </p>
        <div class="flex items-center mt-6 p-4 bg-blue-50 rounded-lg border border-blue-100">
            <i class="fas fa-lightbulb text-yellow-500 text-xl mr-3"></i>
            <span class="text-blue-800">
                <strong>Keuntungan:</strong> Iklan lowongan kerja Anda akan ditampilkan di website dan dipromosikan ke media sosial kami dengan jangkauan ribuan pencari kerja.
            </span>
        </div>
    </section>
    
    <!-- Contact Information -->
    <section class="bg-white rounded-xl shadow-md p-6 mb-12">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Cara Memasang Lowongan Kerja</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
                <h3 class="text-xl font-semibold text-blue-600 mb-3">Hubungi Kami</h3>
                <p class="mb-4">Silakan hubungi admin kami melalui:</p>
                
                <!-- Instagram Contact -->
                <div class="flex items-center mb-4">
                    <div class="w-8 h-8 flex items-center justify-center rounded-full bg-gradient-to-br from-purple-600 via-pink-600 to-yellow-500 mr-4">
                        <i class="fab fa-instagram text-white text-xl"></i>
                    </div>
                    <div>
                        <span class="font-medium block">Instagram:</span>
                        <a href="https://instagram.com/loker_banjarmasin" target="_blank" class="text-blue-600 font-medium hover:underline">@loker_banjarmasin</a>
                    </div>
                </div>
                
                <!-- Email Contact -->
                <div class="flex items-center mb-4">
                    <div class="w-8 h-8 flex items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-blue-700 mr-4">
                        <i class="fas fa-envelope text-white text-xl"></i>
                    </div>
                    <div>
                        <span class="font-medium block">Email:</span>
                        <a href="mailto:muhammadindra003@gmail.com" class="text-blue-600 font-medium hover:underline">muhammadindra003@gmail.com</a>
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-50 p-6 rounded-lg border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Informasi yang Dibutuhkan:</h3>
                <ul class="space-y-2 text-gray-700">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                        <span>Nama perusahaan</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                        <span>Posisi yang dibutuhkan</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                        <span>Persyaratan pekerjaan</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                        <span>Lokasi kerja</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                        <span>Kontak untuk lamaran</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                        <span>Logo perusahaan (opsional)</span>
                    </li>
                </ul>
            </div>
        </div>
    </section>
    
    <!-- Pricing -->
    <section class="bg-white rounded-xl shadow-md p-6 mb-12">
        <h2 class="text-2xl font-bold text-gray-900 mb-8">Paket Pemasangan Iklan</h2>
        
        <div class="bg-gradient-to-r from-blue-500 to-blue-700 text-white p-6 rounded-lg mb-6">
            <h3 class="text-xl font-bold mb-2">Paket Reguler</h3>
            <ul class="space-y-2 mb-4">
                <li class="flex items-start">
                    <i class="fas fa-star text-yellow-300 mt-1 mr-2"></i>
                    <span>Publikasi di website selama 30 hari</span>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-star text-yellow-300 mt-1 mr-2"></i>
                    <span>Posting di Instagram @loker_banjarmasin</span>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-star text-yellow-300 mt-1 mr-2"></i>
                    <span>Termasuk logo perusahaan</span>
                </li>
            </ul>
            <p class="mt-4 text-sm">* Silakan hubungi admin untuk informasi biaya pemasangan</p>
        </div>
        
        <div class="bg-gradient-to-r from-purple-500 to-purple-700 text-white p-6 rounded-lg">
            <h3 class="text-xl font-bold mb-2">Paket Premium</h3>
            <ul class="space-y-2 mb-4">
                <li class="flex items-start">
                    <i class="fas fa-star text-yellow-300 mt-1 mr-2"></i>
                    <span>Semua fitur Paket Reguler</span>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-star text-yellow-300 mt-1 mr-2"></i>
                    <span>Tampil di halaman utama (featured)</span>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-star text-yellow-300 mt-1 mr-2"></i>
                    <span>Re-posting di Instagram Stories</span>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-star text-yellow-300 mt-1 mr-2"></i>
                    <span>Diprioritaskan dalam hasil pencarian</span>
                </li>
            </ul>
            <p class="mt-4 text-sm">* Silakan hubungi admin untuk informasi biaya pemasangan</p>
        </div>
    </section>
    
    <!-- Terms and Conditions -->
    <section class="bg-white rounded-xl shadow-md p-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-4">Syarat & Ketentuan</h2>
        
        <div class="text-gray-700 space-y-4">
            <p>
                Dengan memasang iklan lowongan kerja di platform kami, Anda menyetujui ketentuan berikut:
            </p>
            <ol class="list-decimal pl-5 space-y-2">
                <li>Lowongan yang dipasang harus sesuai dengan peraturan ketenagakerjaan yang berlaku</li>
                <li>Tidak memuat konten diskriminatif terhadap gender, agama, ras, atau suku tertentu</li>
                <li>Informasi yang diberikan harus akurat dan dapat dipertanggungjawabkan</li>
                <li>Tidak memungut biaya apa pun dari pelamar dalam proses rekrutmen</li>
                <li>Kami berhak menolak iklan yang tidak sesuai dengan ketentuan</li>
            </ol>
        </div>
        
        <div class="mt-8 text-center">
            <a href="https://instagram.com/loker_banjarmasin" target="_blank" class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                <i class="fab fa-instagram mr-2"></i>
                Pasang Lowongan Sekarang
            </a>
        </div>
    </section>
</div>

<?php
get_footer();
?>