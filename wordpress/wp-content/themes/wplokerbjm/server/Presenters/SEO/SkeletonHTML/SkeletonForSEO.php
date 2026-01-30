<?php

namespace WPLokerBJM\Presenters\SEO\SkeletonHTML;

use WPLokerBJM\Models\Schema\CustomFields;

class SkeletonForSEO
{
    public static function generateSEOHTML(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        // Check if single job (has 'title') or array of jobs (has 'id' in first element)
        if (isset($data['title'])) {
            // Single job detail
            $job = $data;
            ob_start();
            ?>
            <?php self::style(); ?>
            <div class="seo-job-detail">
                <h1><?php echo esc_html($job['title'] ?? ''); ?></h1>
                <?php if (!empty($job[CustomFields::NAMA_PERUSAHAAN])): ?>
                    <h2><?php echo esc_html($job[CustomFields::NAMA_PERUSAHAAN]); ?></h2>
                <?php endif; ?>
                <?php if (!empty($job[CustomFields::TENTANG_PERUSAHAAN])): ?>
                    <h2>Tentang Perusahaan</h2>
                    <div><?php echo wp_kses_post($job[CustomFields::TENTANG_PERUSAHAAN]); ?></div>
                <?php endif; ?>
                <?php if (!empty($job['ringkasanPekerjaan'])): ?>
                    <h2>Ringkasan Pekerjaan</h2>
                    <ul>
                        <?php foreach ($job['ringkasanPekerjaan'] as $key => $value): ?>
                            <?php if (!empty($value)): ?>
                                <li><?php echo esc_html(ucfirst($key) . ': ' . $value); ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if (!empty($job[CustomFields::DESKRIPSI_PEKERJAAN])): ?>
                    <h2>Deskripsi Pekerjaan</h2>
                    <div><?php echo wp_kses_post($job[CustomFields::DESKRIPSI_PEKERJAAN]); ?></div>
                <?php endif; ?>
                <?php if (!empty($job[CustomFields::PERSYARATAN])): ?>
                    <h2>Persyaratan</h2>
                    <div><?php echo wp_kses_post($job[CustomFields::PERSYARATAN]); ?></div>
                <?php endif; ?>
                <?php if (!empty($job[CustomFields::CARA_MELAMAR])): ?>
                    <h2>Cara Melamar</h2>
                    <div><?php echo wp_kses_post($job[CustomFields::CARA_MELAMAR]); ?></div>
                <?php endif; ?>
                <?php if (!empty($job[CustomFields::BENEFIT])): ?>
                    <h2>Benefit</h2>
                    <div><?php echo wp_kses_post($job[CustomFields::BENEFIT]); ?></div>
                <?php endif; ?>
                <?php if (!empty($job['contacts'])): ?>
                    <h2>Kontak</h2>
                    <ul>
                        <?php foreach ($job['contacts'] as $contact): ?>
                            <?php if (!empty($contact)): ?>
                                <li><?php echo esc_html($contact); ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if (!empty($job[CustomFields::SOCIAL_MEDIA])): ?>
                    <h2>Sosial Media</h2>
                    <ul>
                        <?php foreach ($job[CustomFields::SOCIAL_MEDIA] as $social): ?>
                            <?php if (!empty($social)): ?>
                                <li><?php echo esc_html($social); ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <?php
            return ob_get_clean();
        } elseif (is_array($data) && isset($data[0]['id'])) {
            // Array of jobs
            $jobs = $data;
            ob_start();
            ?>
            <?php self::style(); ?>
            <div class="seo-job-listings">
                <div>
                    <h1>Temukan Lowongan Kerja Terbaru di Sekitar</h1>
                    <p>Update setiap hari, mudah diakses, dan gratis!</p>
                </div>
                <div>
                    <h2>Lowongan Terbaru</h2>
                    <?php foreach ($jobs as $job): ?>
                        <a href="<?= esc_url(get_permalink($job['id'])); ?>"><?= esc_html(get_the_title($job['id'])); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        return '';
    }

    public static function pasangIklanHTML(): string
    {
        ob_start();
        self::style();
        ?>
        <div class="seo-pasang-iklan">
            <h1>Pasang Iklan Lowongan Kerja</h1>
            <h2>Tingkatkan Peluang Mendapatkan Kandidat Terbaik</h2>
            <p>Sebarkan informasi lowongan kerja Anda ke ribuan pencari kerja di Banjarmasin dan sekitarnya melalui platform
                kami. Dengan jangkauan luas dan fitur pencarian yang efektif, Anda dapat menemukan kandidat yang tepat dengan
                cepat.</p>
            <p>Keuntungan: Iklan lowongan kerja Anda akan ditampilkan di website dan dipromosikan ke media sosial kami dengan
                jangkauan ribuan pencari kerja.</p>
            <h2>Tentang Kami</h2>
            <p>Loker Banjarmasin adalah platform lowongan kerja yang dibuat untuk mendukung komunitas bisnis kecil dan lokal di Kalimantan — khususnya Banjarmasin dan sekitarnya. Kami terbuka untuk semua pemberi kerja, dengan fokus pada UMKM, usaha kecil, dan perekrut independen yang ingin membagikan peluang kerja secara mudah dan efektif.</p>
            <p>Situs ini tidak menyediakan tombol "lamar" langsung; pelamar akan diarahkan ke pihak HR melalui media sosial atau kontak yang Anda cantumkan, sehingga prosesnya tetap fleksibel dan personal. Setiap lowongan akan ditayangkan selama 1 bulan, kecuali bila Anda menyertakan batas waktu (deadline) yang berbeda.</p>
            <h2>Cara Memasang Lowongan Kerja</h2>
            <h3>Hubungi Kami</h3>
            <p>Silakan hubungi admin kami melalui:</p>
            <ul>
                <li>Instagram: <a href="https://instagram.com/loker_banjarmasin">@loker_banjarmasin</a></li>
                <li>Threads: <a href="https://threads.com/@loker_banjarmasin">@loker_banjarmasin</a></li>
                <li>WhatsApp: <a href="https://wa.me/6283862447271">+62 838-6244-7271</a></li>
                <li>Facebook: <a href="https://facebook.com/loker.banjarmasin.2025/">facebook.com/loker.banjarmasin.2025/</a></li>
            </ul>
            <h3>Informasi yang Dibutuhkan</h3>
            <ul>
                <li>Identitas pengirim loker</li>
                <li>Posisi yang dibutuhkan</li>
                <li>Persyaratan pekerjaan</li>
                <li>Lokasi kerja</li>
                <li>Kontak pengirim loker</li>
            </ul>
            <h2>Syarat & Ketentuan</h2>
            <p>Dengan memasang iklan lowongan kerja di platform kami, Anda menyetujui ketentuan berikut:</p>
            <ul>
                <li>Lowongan yang dipasang harus sesuai dengan peraturan ketenagakerjaan yang berlaku</li>
                <li>Tidak memuat konten diskriminatif terhadap gender, agama, ras, atau suku tertentu</li>
                <li>Informasi yang diberikan harus akurat dan dapat dipertanggungjawabkan</li>
                <li>Tidak memungut biaya apa pun dari pelamar dalam proses rekrutmen</li>
                <li>Kami berhak menolak iklan yang tidak sesuai dengan ketentuan</li>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function style(): void
    {
        ?>
        <style>
            .seo-job-detail,
            .seo-job-listings,
            .seo-pasang-iklan {
                position: absolute;
                left: -9999px;
                top: auto;
                width: 1px;
                height: 1px;
                overflow: hidden;
                color: blue;
            }
        </style>
        <?php
    }
}
