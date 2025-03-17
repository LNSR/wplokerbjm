<?php
namespace AstraChild\Views\PasangLoker;

/**
 * Pasang Lowongan View
 * 
 * Handles rendering of the job posting information page
 */
class PasangLowongan
{
    /**
     * Render the Pasang Lowongan page
     * 
     * @param array $options Options for customizing the page
     * @return void
     */
    public function render(array $options = []): void
    {
        $default_options = [
            'title' => 'Pasang Iklan Lowongan Kerja',
            'instagram' => '@loker_banjarmasin',
            'instagram_url' => 'https://instagram.com/loker_banjarmasin',
            'whatsapp' => '+62 838-6244-7271',
            'whatsapp_url' => 'https://wa.me/6283862447271',
            'email' => 'muhammadindra003@gmail.com',
        ];
        
        $options = array_merge($default_options, $options);
        ?>
        <!-- Overall container -->
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-8"><?php echo esc_html($options['title']); ?></h1>
            
            <?php $this->renderIntroSection(); ?>
            <?php $this->renderContactSection($options); ?>
            <?php $this->renderPricingSection(); ?>
            <?php $this->renderTermsSection(); ?>
        </div>
        <?php
    }
    
    /**
     * Render the introduction section
     * 
     * @return void
     */
    protected function renderIntroSection(): void
    {
        ?>
        <!-- Introduction -->
        <section class="bg-white rounded-xl shadow-md p-4 sm:p-6 md:p-8 mb-12">
            <h2 class="text-2xl font-bold text-blue-600 mb-4">Tingkatkan Peluang Mendapatkan Kandidat Terbaik</h2>
            <p class="text-gray-700 text-justify mb-4">
                Sebarkan informasi lowongan kerja Anda ke ribuan pencari kerja di Banjarmasin dan sekitarnya melalui platform kami.
                Dengan jangkauan luas dan fitur pencarian yang efektif, Anda dapat menemukan kandidat yang tepat dengan cepat.
            </p>
            <div class="flex items-center mt-6 p-4 bg-blue-50 rounded-lg border border-blue-100">
                <i class="fas fa-lightbulb text-yellow-500 text-xl mr-3"></i>
                <span class="text-blue-800 text-justify">
                    <strong>Keuntungan:</strong> Iklan lowongan kerja Anda akan ditampilkan di website dan dipromosikan ke media sosial kami dengan jangkauan ribuan pencari kerja.
                </span>
            </div>
        </section>
        <?php
    }
    
    /**
     * Render the contact information section
     * 
     * @param array $options Contact information
     * @return void
     */
    protected function renderContactSection(array $options): void
    {
        ?>
        <!-- Contact Information -->
        <section class="bg-white rounded-xl shadow-md p-4 sm:p-6 md:p-8 mb-12">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Cara Memasang Lowongan Kerja</h2>
            
            <!-- Container with relative positioning for the divider -->
            <div class="relative">
                <!-- Vertical divider - only visible on md screens and up -->
                <div class="hidden md:block absolute left-1/2 top-4 bottom-4 w-[2px] bg-gradient-to-b from-blue-300 via-blue-500 to-blue-300 transform -translate-x-1/2"></div>
                
                <!-- Grid with wider gap to accommodate the divider -->
                <div class="grid grid-cols-1 md:grid-cols-2 md:gap-12 gap-8 pt-4">
                    <!-- Left column with contact info -->
                    <div class="md:pr-6">
                        <h3 class="text-xl font-semibold text-blue-600 mb-3">Hubungi Kami</h3>
                        <p class="mb-4">Silakan hubungi admin kami melalui:</p>
                        
                        <!-- Instagram Contact -->
                        <div class="flex items-center mb-4">
                            <div class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-gradient-to-r from-purple-600 via-pink-500 to-orange-400 mr-4 shadow-sm">
                                <i class="fab fa-instagram text-white text-xl"></i>
                            </div>
                            <div>
                                <span class="font-medium block">Instagram:</span>
                                <a href="<?php echo esc_url($options['instagram_url']); ?>" target="_blank" class="text-blue-600 font-medium hover:underline">
                                    <?php echo esc_html($options['instagram']); ?>
                                </a>
                            </div>
                        </div>

                        <!-- WhatsApp Contact -->
                        <div class="flex items-center mb-4">
                            <div class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-[#25D366] mr-4 shadow-sm">
                                <i class="fab fa-whatsapp text-white text-xl"></i>
                            </div>
                            <div>
                                <span class="font-medium block">WhatsApp:</span>
                                <a href="<?php echo esc_url($options['whatsapp_url']); ?>" target="_blank" class="text-blue-600 font-medium hover:underline">
                                    <?php echo esc_html($options['whatsapp']); ?>
                                </a>
                            </div>
                        </div>
                        
                        <!-- Email Contact -->
                        <div class="flex items-center mb-4">
                            <div class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-[#EA4335] mr-4 shadow-sm">
                                <i class="fas fa-envelope text-white text-xl"></i>
                            </div>
                            <div>
                                <span class="font-medium block">Email:</span>
                                <a href="mailto:<?php echo esc_attr($options['email']); ?>" class="text-blue-600 font-medium hover:underline">
                                    <?php echo esc_html($options['email']); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right column with requirements -->
                    <div class="bg-gray-50 p-6 rounded-lg border border-gray-200 md:pl-8">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">Informasi yang Dibutuhkan:</h3>
                        <ul class="space-y-2 text-gray-700">
                            <?php $this->renderRequirementItem('Nama perusahaan'); ?>
                            <?php $this->renderRequirementItem('Posisi yang dibutuhkan'); ?>
                            <?php $this->renderRequirementItem('Persyaratan pekerjaan'); ?>
                            <?php $this->renderRequirementItem('Lokasi kerja'); ?>
                            <?php $this->renderRequirementItem('Kontak untuk lamaran'); ?>
                            <?php $this->renderRequirementItem('Logo perusahaan (opsional)'); ?>
                        </ul>
                    </div>
                </div>
            </div>
        </section>
        <?php
    }
    
    /**
     * Render a requirement item
     * 
     * @param string $text Requirement text
     * @return void
     */
    protected function renderRequirementItem(string $text): void
    {
        ?>
        <li class="flex items-start">
            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
            <span><?php echo esc_html($text); ?></span>
        </li>
        <?php
    }
    
    /**
     * Render the pricing section
     * 
     * @return void
     */
    protected function renderPricingSection(): void
    {
        ?>
        <!-- Pricing -->
        <section class="bg-white rounded-xl shadow-md p-4 sm:p-6 md:p-8 mb-12">
            <h2 class="text-2xl font-bold text-gray-900 mb-8">Paket Pemasangan Iklan</h2>
            
            <!-- Grid container for pricing packages -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 md:gap-6 pt-2">
                <?php 
                // Regular package
                $regularContent = $this->renderPricingPackageContent('Paket Reguler', [
                    'Publikasi di website selama 1 bulan',
                    'Posting di Instagram',
                    'Posting di Facebook',
                    'Posting di TikTok'
                ]);
                ?>
                <div class="transform transition-all duration-300 hover:scale-[1.02] hover:-translate-y-1">
                    <?php $this->renderGradientContainer($regularContent, 'blue-500/80', 'blue-700/80'); ?>
                </div>
                
                <?php
                // Premium package
                $premiumContent = $this->renderPricingPackageContent('Paket Premium', [
                    'Semua fitur Paket Reguler',
                    'Tampil di halaman utama (featured)',
                    'Re-posting di Instagram Stories',
                    'Diprioritaskan dalam hasil pencarian'
                ]);
                ?>
                <div class="transform transition-all duration-300 hover:scale-[1.02] hover:-translate-y-1">
                    <?php $this->renderGradientContainer($premiumContent, 'purple-500/80', 'purple-700/80'); ?>
                </div>
            </div>
            
            <!-- Contact note -->
            <div class="text-center mt-4 pt-2">
                <span class="text-gray-600 italic">
                    <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                    Hubungi admin untuk informasi lebih lanjut mengenai biaya pemasangan
                </span>
            </div>
        </section>
        <?php
    }
    
    /**
     * Render a feature item
     * 
     * @param string $text Feature text
     * @return void
     */
    protected function renderFeatureItem(string $text): void
    {
        ?>
        <li class="flex items-start py-1">
            <i class="fas fa-star text-yellow-300 mt-0.5 mr-2 flex-shrink-0"></i>
            <span class="text-sm sm:text-base"><?php echo esc_html($text); ?></span>
        </li>
        <?php
    }
    
    /**
     * Renders a pricing package content
     * 
     * @param string $title The package title
     * @param array $features List of features included in the package
     * @return string HTML content for the pricing package
     */
    protected function renderPricingPackageContent(string $title, array $features): string
    {
        ob_start();
        ?>
        <div>
            <h3 class="text-xl font-bold mb-3"><?php echo esc_html($title); ?></h3>
            <ul class="space-y-2">
                <?php foreach ($features as $feature): ?>
                    <?php $this->renderFeatureItem($feature); ?>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render the terms and conditions section
     * 
     * @return void
     */
    protected function renderTermsSection(): void
    {
        ?>
        <!-- Terms and Conditions -->
        <section class="bg-white rounded-xl shadow-md p-4 sm:p-6 md:p-8 mb-12">
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
        </section>
        <?php
    }

    /**
     * Render a card section
     * 
     * @param string $content Card content
     * @param string $classes Additional classes for the card
     * @return void
     */
    protected function renderCard($content, $classes = ''): void
    {
        ?>
        <section class="bg-white rounded-xl shadow-md hover:shadow-lg transition-all duration-200 p-4 sm:p-6 md:p-8 mb-12 <?php echo esc_attr($classes); ?>">
            <?php echo $content; ?>
        </section>
        <?php
    }

    /**
     * Render a gradient container
     */
    protected function renderGradientContainer(string $content, string $from = 'blue-500/80', string $to = 'blue-700/80'): void
    {
        ?>
        <div class="bg-gradient-to-r from-<?php echo $from; ?> to-<?php echo $to; ?> text-white p-5 sm:p-6 rounded-xl shadow-md h-full">
            <?php echo $content; ?>
        </div>
        <?php
    }
}