<?php

namespace WPLokerBJM\Presenters\SEO\SkeletonHTML;

use WPLokerBJM\Models\Schema\CustomFields;

/**
 * Poor man SSR, but this is more efficent and more better DX than migrating frontend to SvelteKit
 */
class SkeletonForSEO
{
    public static function generateSEOHTML(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        // Check if single job (has 'title') or array of jobs (has 'id' in first element)
        if (isset($data['title'])) {
            // Single job detail - produce markup aligned with new Svelte structure
            $job = $data;
            ob_start();
            ?>
            <?php self::style(); ?>
            <?php self::header(); ?>
            <?php self::JobDetailHTML($job); ?>
            <?php self::footer(); ?>
            <?php
            return ob_get_clean();
        } elseif (is_array($data) && isset($data[0]['id'])) {
            // Array of jobs
            $jobs = $data;
            ob_start();
            ?>
            <?php self::style(); ?>
            <?php self::header(); ?>
            <?php self::JobCardHTML($jobs); ?>
            <?php self::footer(); ?>
            <?php
            return ob_get_clean();
        }

        return '';
    }

    private static function JobDetailHTML(array $job): void
    {
        ?>
        <article class="seo-job-detail">
            <header>
                <h1><?php echo esc_html($job['title'] ?? ''); ?></h1>
                <?php if (!empty($job[CustomFields::NAMA_PERUSAHAAN])): ?>
                    <div><?php echo esc_html($job[CustomFields::NAMA_PERUSAHAAN]); ?></div>
                <?php endif; ?>
                <?php if (!empty($job['post_time'])): ?>
                    <div><time datetime="<?php echo esc_attr($job['post_time']); ?>">Diupdate:
                            <?php echo esc_html(date('M j, Y', strtotime($job['post_time']))); ?></time></div>
                <?php endif; ?>
            </header>

            <?php if (!empty($job['ringkasanPekerjaan'])): ?>
                <section>
                    <h2>Ringkasan Pekerjaan</h2>
                    <div>
                        <ul>
                            <?php
                            $s = $job['ringkasanPekerjaan'];

                            $fmtCurrency = function ($v) {
                                if ($v === null || $v === '')
                                    return '';
                                $n = (int) $v;
                                return 'Rp ' . number_format($n, 0, ',', '.');
                            };

                            // Jenis Pekerjaan
                            if (!empty($s['jenis_pekerjaan'])) {
                                $val = is_array($s['jenis_pekerjaan']) ? implode(', ', $s['jenis_pekerjaan']) : $s['jenis_pekerjaan'];
                                echo '<li><strong>Jenis Pekerjaan:</strong> ' . esc_html($val) . '</li>';
                            }

                            // Pendidikan
                            if (!empty($s['pendidikan'])) {
                                $val = is_array($s['pendidikan']) ? implode(', ', $s['pendidikan']) : $s['pendidikan'];
                                echo '<li><strong>Pendidikan:</strong> ' . esc_html($val) . '</li>';
                            }

                            // Pengalaman
                            if (!empty($s['pengalaman'])) {
                                echo '<li><strong>Pengalaman:</strong> Minimal ' . esc_html($s['pengalaman']) . ' Tahun Pengalaman</li>';
                            }

                            // Gender
                            if (!empty($s['gender'])) {
                                $val = is_array($s['gender']) ? implode(', ', $s['gender']) : $s['gender'];
                                echo '<li><strong>Gender:</strong> ' . esc_html($val) . '</li>';
                            }

                            // Gaji (combine minimal/maksimal)
                            $gmin = $s['gaji_minimal'] ?? null;
                            $gmax = $s['gaji_maksimal'] ?? null;
                            if ($gmin || $gmax) {
                                if ($gmin && $gmax) {
                                    $g = $fmtCurrency($gmin) . ' - ' . $fmtCurrency($gmax);
                                } elseif ($gmin) {
                                    $g = 'Sekitar ' . $fmtCurrency($gmin);
                                } else {
                                    $g = 'Maksimal ' . $fmtCurrency($gmax);
                                }
                                echo '<li><strong>Gaji:</strong> ' . esc_html($g) . '</li>';
                            }

                            // Usia (umur)
                            $umin = $s['umur_min'] ?? null;
                            $umax = $s['umur_max'] ?? null;
                            if ($umin || $umax) {
                                if ($umin && $umax) {
                                    $u = $umin . ' - ' . $umax . ' Tahun';
                                } elseif ($umin) {
                                    $u = 'Minimal ' . $umin . ' Tahun';
                                } else {
                                    $u = 'Maksimal ' . $umax . ' Tahun';
                                }
                                echo '<li><strong>Usia:</strong> ' . esc_html($u) . '</li>';
                            }

                            // Lokasi
                            if (!empty($s['lokasi_pekerjaan'])) {
                                $val = is_array($s['lokasi_pekerjaan']) ? implode(', ', $s['lokasi_pekerjaan']) : $s['lokasi_pekerjaan'];
                                echo '<li><strong>Lokasi:</strong> ' . esc_html($val) . '</li>';
                            }

                            // Deadline
                            if (!empty($s['deadline'])) {
                                $dl = $s['deadline'];
                                $formatted = false !== strtotime($dl) ? date('j F Y', strtotime($dl)) : $dl;
                                echo '<li><strong>Deadline:</strong> ' . esc_html($formatted) . '</li>';
                            }
                            ?>
                        </ul>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!empty($job[CustomFields::TENTANG_PERUSAHAAN])): ?>
                <section aria-labelledby="about-company">
                    <h2 id="about-company">Tentang Perusahaan</h2>
                    <div><?php echo wp_kses_post($job[CustomFields::TENTANG_PERUSAHAAN]); ?></div>
                </section>
            <?php endif; ?>

            <?php if (!empty($job[CustomFields::DESKRIPSI_PEKERJAAN])): ?>
                <section aria-labelledby="job-description">
                    <h2 id="job-description">Deskripsi Pekerjaan</h2>
                    <div><?php echo wp_kses_post($job[CustomFields::DESKRIPSI_PEKERJAAN]); ?></div>
                </section>
            <?php endif; ?>

            <?php if (!empty($job[CustomFields::PERSYARATAN])): ?>
                <section aria-labelledby="requirements">
                    <h2 id="requirements">Persyaratan</h2>
                    <div><?php echo wp_kses_post($job[CustomFields::PERSYARATAN]); ?></div>
                </section>
            <?php endif; ?>

            <?php if (!empty($job[CustomFields::CARA_MELAMAR])): ?>
                <section aria-labelledby="how-to-apply">
                    <h2 id="how-to-apply">Cara Melamar</h2>
                    <div><?php echo wp_kses_post($job[CustomFields::CARA_MELAMAR]); ?></div>
                </section>
            <?php endif; ?>

            <?php if (!empty($job[CustomFields::BENEFIT])): ?>
                <section aria-labelledby="benefits">
                    <h2 id="benefits">Benefit</h2>
                    <div><?php echo wp_kses_post($job[CustomFields::BENEFIT]); ?></div>
                </section>
            <?php endif; ?>

            <?php /* Kontak: expand emails / phones / sites to labelled list like Svelte */ ?>
            <?php if (!empty($job['contacts']) && is_array($job['contacts'])): ?>
                <section aria-labelledby="contacts-heading">
                    <h2 id="contacts-heading">Kontak</h2>
                    <address>
                        <ul>
                            <?php
                            $c = $job['contacts'];

                            // Emails (comma separated)
                            if (!empty($c[CustomFields::EMAIL_KONTAK])) {
                                $emails = array_filter(array_map('trim', explode(',', $c[CustomFields::EMAIL_KONTAK])));
                                foreach ($emails as $em) {
                                    echo '<li><strong>Email:</strong> <a href="mailto:' . esc_attr($em) . '">' . esc_html($em) . '</a></li>';
                                }
                            }

                            // Phones (comma separated)
                            if (!empty($c[CustomFields::NOMOR_KONTAK])) {
                                $phones = array_filter(array_map('trim', explode(',', $c[CustomFields::NOMOR_KONTAK])));
                                foreach ($phones as $ph) {
                                    echo '<li><strong>Telepon:</strong> <a href="tel:' . esc_attr($ph) . '">' . esc_html($ph) . '</a></li>';
                                }
                            }

                            // Websites (comma separated)
                            if (!empty($c[CustomFields::SITUS_KONTAK])) {
                                $sites = array_filter(array_map('trim', explode(',', $c[CustomFields::SITUS_KONTAK])));
                                foreach ($sites as $site) {
                                    $href = preg_replace('/^http:\/\//i', 'https://', $site);
                                    $display = preg_replace('/^https?:\/\//i', '', $site);
                                    echo '<li><strong>Website:</strong> <a href="' . esc_url($href) . '">' . esc_html($display) . '</a></li>';
                                }
                            }
                            ?>
                        </ul>
                    </address>
                </section>
            <?php endif; ?>

            <?php if (!empty($job[CustomFields::SOCIAL_MEDIA])): ?>
                <section aria-labelledby="social-media-heading">
                    <h2 id="social-media-heading">Sosial Media</h2>
                    <nav>
                        <ul>
                            <?php
                            $platformMap = [
                                'X / Twitter' => 'https://twitter.com/',
                                'Twitter' => 'https://twitter.com/',
                                'Facebook' => 'https://facebook.com/',
                                'Instagram' => 'https://instagram.com/',
                                'LinkedIn' => 'https://linkedin.com/in/',
                                'Youtube' => 'https://youtube.com/@',
                                'WhatsApp' => 'https://wa.me/',
                                'TikTok' => 'https://tiktok.com/@',
                                'Threads' => 'https://threads.net/@',
                                'Telegram' => 'https://t.me/',
                            ];

                            $items = preg_split('/;\s*/', $job[CustomFields::SOCIAL_MEDIA]);
                            foreach ($items as $entry) {
                                if (!trim($entry))
                                    continue;
                                $parts = explode(':', $entry, 2);
                                $platform = trim($parts[0]);
                                $usernames = isset($parts[1]) ? $parts[1] : '';
                                $nameDisplay = esc_html($platform);

                                $usernameList = array_filter(array_map('trim', explode(',', $usernames)));
                                foreach ($usernameList as $username) {
                                    $href = null;
                                    if (preg_match('/^https?:\/\//i', $username)) {
                                        $href = $username;
                                    } elseif (isset($platformMap[$platform])) {
                                        $href = $platformMap[$platform] . ltrim($username, '@');
                                    }

                                    if ($href) {
                                        echo '<li><strong>' . $nameDisplay . ':</strong> <a href="' . esc_url($href) . '">' . esc_html($username) . '</a></li>';
                                    } else {
                                        echo '<li><strong>' . $nameDisplay . ':</strong> ' . esc_html($username) . '</li>';
                                    }
                                }
                            }
                            ?>
                        </ul>
                    </nav>
                </section>
            <?php endif; ?>
        </article>
        <?php
    }

    private static function JobCardHTML(array $jobs): void
    {
        ?>
        <div class="seo-job-listings">
            <div>
                <h1>Temukan Lowongan Kerja di Kalimantan terutama Banjarmasin, Banjarbaru, Martapura dan sekitarnya</h1>
                <p>Update berkala, mudah diakses, dan tanpa kewajiban biaya!</p>
            </div>
            <div>
                <h2>Lowongan Terbaru(max 1 Bulan)</h2>
                <?php foreach ($jobs as $job): ?>
                    <article>
                        <h3><a href="<?php echo esc_url($job['permalink']); ?>"><?php echo esc_html($job['title']); ?></a></h3>

                        <?php if (!empty($job['post_time'])): ?>
                            <time
                                datetime="<?php echo esc_attr($job['post_time']); ?>"><?php echo esc_html(SkeletonForSEOUtils::timeAgo($job['post_time'])); ?></time>
                        <?php endif; ?>

                        <?php if (!empty($job[CustomFields::NAMA_PERUSAHAAN])): ?>
                            <div><?php echo esc_html($job[CustomFields::NAMA_PERUSAHAAN]); ?></div>
                        <?php endif; ?>

                        <?php
                        $rows = SkeletonForSEOUtils::summaryRows($job['ringkasanPekerjaan'] ?? []);
                        if (!empty($rows)) {
                            echo '<div><ul>';
                            foreach ($rows as $row) {
                                if ($row['label'] === 'Deadline')
                                    continue; // JobCard hides Deadline in the summary list
                                echo '<li><strong>' . esc_html($row['label']) . ':</strong> ' . esc_html($row['value']) . '</li>';
                            }
                            echo '</ul></div>';
                        }
                        ?>

                        <?php $status = SkeletonForSEOUtils::statusLabel($job[CustomFields::STATUS_PEKERJAAN] ?? null); ?>
                        <?php if ($status): ?>
                            <div><strong>Status:</strong> <?php echo esc_html($status); ?></div>
                        <?php endif; ?>

                        <?php $deadlineText = SkeletonForSEOUtils::deadlineText($job[CustomFields::DEADLINE] ?? ($job['deadline'] ?? null)); ?>
                        <?php if ($deadlineText): ?>
                            <div><strong>Deadline:</strong> <?php echo esc_html($deadlineText); ?></div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    public static function pasangIklanHTML(): string
    {
        ob_start();
        self::style();
        self::header();
        ?>
        <div class="seo-pasang-iklan">
            <h1>Pasang Iklan Lowongan Kerja</h1>
            <h2>Tingkatkan Peluang Mendapatkan Kandidat Terbaik</h2>
            <p>Sebarkan informasi lowongan kerja Anda ke ribuan pencari kerja di Kalimantan terutama
                Banjarmasin, Banjarbaru, Martapura dan sekitarnya melalui platform kami.
                Dengan jangkauan luas dan fitur pencarian yang efektif, Anda dapat
                menemukan kandidat yang tepat dengan cepat.</p>
            <p>Keuntungan: Iklan lowongan kerja Anda akan ditampilkan di website dan dipromosikan ke media sosial kami dengan
                jangkauan ribuan pencari kerja.</p>
            <h2>Tentang Kami</h2>
            <p>Loker Banjarmasin adalah platform lowongan kerja yang dibuat untuk mendukung komunitas bisnis kecil dan lokal di
                Kalimantan — khususnya Banjarmasin, Banjarbaru, Martapura, dan sekitarnya. Kami terbuka untuk semua pemberi
                kerja, dengan fokus pada
                UMKM, usaha kecil, dan perekrut independen yang ingin membagikan peluang kerja secara mudah dan efektif.</p>
            <p>Situs ini tidak menyediakan tombol "lamar" langsung; pelamar akan diarahkan ke pihak HR melalui media sosial atau
                kontak yang Anda cantumkan, sehingga prosesnya tetap fleksibel dan personal. Setiap lowongan akan ditayangkan
                selama 1 bulan, kecuali bila Anda menyertakan batas waktu (deadline) yang berbeda.</p>
            <h2>Cara Memasang Lowongan Kerja</h2>
            <h3>Hubungi Kami</h3>
            <p>Silakan hubungi admin kami melalui:</p>
            <ul>
                <li>Instagram: <a href="https://instagram.com/loker_banjarmasin">@loker_banjarmasin</a></li>
                <li>Threads: <a href="https://threads.com/@loker_banjarmasin">@loker_banjarmasin</a></li>
                <li>WhatsApp: <a href="https://wa.me/6283862447271">+62 838-6244-7271</a></li>
                <li>Facebook: <a href="https://facebook.com/loker.banjarmasin.2025/">facebook.com/loker.banjarmasin.2025/</a>
                </li>
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
        <?php self::footer(); ?>
        <?php
        return ob_get_clean();
    }

    public static function kebijakanPrivacyHTML(): string
    {
        ob_start();
        self::style();
        self::header();
        ?>
        <div class="seo-kebijakan-privacy">
            <h1>Kebijakan Privasi</h1>

            <section>
                <h2>Penggunaan Data dan Privasi</h2>
                <p>Situs ini menggunakan Google Analytics (GA4) dan Google Tag Manager (GTM)
                    untuk menganalisis lalu lintas pengunjung. Kami tidak mengumpulkan data
                    pribadi pengguna secara langsung, dan data yang dikumpulkan hanya
                    digunakan untuk meningkatkan pengalaman situs. Di masa depan, kami mungkin
                    menyisipkan sedikit iklan tanpa merusak pengalaman pengguna. Data tidak dijual atau dibagikan dengan pihak
                    ketiga.</p>
                <p>Kami berkomitmen untuk melindungi privasi pengunjung dan mematuhi peraturan perlindungan data yang berlaku.
                    Untuk pertanyaan lebih lanjut, hubungi admin kami melalui media sosial seperti Instagram atau WhatsApp.</p>
            </section>

            <section>
                <h2>Kebijakan Cookies dan Penyimpanan Browser</h2>
                <p>Situs ini menggunakan cookies dan penyimpanan alami browser seperti (localStorage, sessionStorage, IndexedDB)
                    untuk meningkatkan pengalaman pengguna. Cookies adalah file kecil yang disimpan di perangkat Anda untuk
                    membantu situs berfungsi dengan baik. Kami menggunakan cookies untuk menganalisis lalu lintas, mengingat
                    preferensi Anda, dan menyediakan konten yang relevan (termasuk iklan).</p>
                <p>Penyimpanan alami browser digunakan untuk menyimpan data sementara seperti posisi scroll, status pencarian,
                    bookmark lowongan, dan preferensi navigasi & tema, untuk memberikan pengalaman yang lebih lancar. Data ini
                    tidak berisi informasi pribadi dan hanya digunakan untuk fungsionalitas situs.</p>
                <p>Anda dapat mengelola pengaturan cookies melalui browser Anda. Namun, menonaktifkan cookies atau penyimpanan
                    alami browser mungkin mempengaruhi fungsionalitas situs.</p>
            </section>

            <section>
                <h2>Peringatan Penipuan Lowongan Kerja</h2>
                <p>Kami sangat prihatin dengan maraknya penipuan lowongan kerja di Indonesia. Sebagai situs papan informasi
                    lowongan kerja, terkadang admin kami bisa khilaf, mohon selalu teliti dan verifikasi terhadap informasi
                    lowongan kerja yang tercantum. Jangan pernah memberikan uang atau data pribadi ke pihak yang tidak
                    terpercaya.</p>
                <p>Jika Anda menjadi korban penipuan lowongan kerja, segera laporkan ke pihak yang berwenang seperti Kepolisian
                    Republik Indonesia atau Kementerian Ketenagakerjaan. Kami juga siap membantu dengan melaporkan lowongan
                    mencurigakan yang terdeteksi di platform kami.</p>
            </section>

            <section>
                <h2>Perubahan Kebijakan</h2>
                <p>Apabila suatu saat nanti kami harus mengubah Kebijakan Privasi kami, maka kami akan mencantumkannya di sini
                    agar para pengguna dapat mengetahui informasi apa saja yang kami kumpulkan dan bagaimana kami menggunakan
                    informasi tersebut. Data pribadi Anda akan digunakan sesuai dengan kebijakan privasi kami.</p>
                <p>Apabila, sewaktu-waktu Anda ingin mengajukan pertanyaan ataupun memberikan komentar tentang Kebijakan Privasi
                    kami, maka Anda dapat menghubungi kami lewat tombol "Kontak Admin".</p>
                <p>Terakhir diubah 12 Februari 2026.</p>
            </section>
        </div>
        <?php self::footer(); ?>
        <?php
        return ob_get_clean();
    }

    /** Stall FOUC till JS boot */
    private static function style(): void
    {
        ?>
        <style>
            .seo-job-detail,
            .seo-job-listings,
            .seo-pasang-iklan,
            .seo-kebijakan-privacy {
                display: block;
                width: 100%;
                position: relative;

                opacity: 0.4;
                pointer-events: none;

                transition: opacity 2s ease-in-out;
                transition-delay: 2s;

                animation: stay-visible 0.5s linear 3s forwards;
            }

            @keyframes stay-visible {
                to {
                    opacity: 1;
                    pointer-events: auto;
                }
            }
        </style>
        <?php
    }

    private static function header(): void
    {
        $homeUrl = home_url() . '/pasang-iklan-loker/';
        ?>
        <header><a href="<?= $homeUrl; ?>"><strong>Pasang Iklan Loker</strong></a></header>
        <?php
    }

    private static function footer(): void
    {
        $homeUrl = home_url() . '/kebijakan-privasi/';
        ?>
        <footer>
            <p><a href="<?= $homeUrl; ?>"><strong>Kebijakan Privasi</strong></a></p>
        </footer>
        <?php
    }
}

class SkeletonForSEOUtils
{
    public static function timeAgo(?string $iso): string
    {
        if (!$iso)
            return '';
        $ts = strtotime($iso);
        if ($ts === false)
            return '';
        $diff = time() - $ts;
        if ($diff < 60)
            return 'Baru saja diposting';
        if ($diff < 3600)
            return floor($diff / 60) . ' menit lalu';
        if ($diff < 86400)
            return floor($diff / 3600) . ' jam lalu';
        if ($diff < 604800)
            return floor($diff / 86400) . ' hari lalu';
        if ($diff < 2592000)
            return floor($diff / 604800) . ' minggu lalu';
        if ($diff < 31536000)
            return floor($diff / 2592000) . ' bulan lalu';
        return floor($diff / 31536000) . ' tahun lalu';
    }

    public static function formatCurrency($value): string
    {
        if ($value === null || $value === '')
            return '';
        return 'Rp ' . number_format((int) $value, 0, ',', '.');
    }

    public static function formatSalary($min = null, $max = null): ?string
    {
        $hasMin = $min !== null && $min !== '';
        $hasMax = $max !== null && $max !== '';
        if (!$hasMin && !$hasMax)
            return null;
        if ($hasMin && $hasMax) {
            return self::formatCurrency($min) . ' - ' . self::formatCurrency($max);
        }
        if ($hasMin)
            return 'Sekitar ' . self::formatCurrency($min);
        return 'Maksimal ' . self::formatCurrency($max);
    }

    public static function formatAge($min = null, $max = null): ?string
    {
        $hasMin = $min !== null && $min !== '';
        $hasMax = $max !== null && $max !== '';
        if (!$hasMin && !$hasMax)
            return null;
        if ($hasMin && $hasMax)
            return $min . ' - ' . $max . ' Tahun';
        if ($hasMin)
            return 'Minimal ' . $min . ' Tahun';
        return 'Maksimal ' . $max . ' Tahun';
    }

    public static function summaryRows(array $s): array
    {
        $rows = [];
        if (empty($s))
            return $rows;

        if (!empty($s['jenis_pekerjaan'])) {
            $rows[] = ['label' => 'Jenis Pekerjaan', 'value' => is_array($s['jenis_pekerjaan']) ? implode(', ', $s['jenis_pekerjaan']) : (string) $s['jenis_pekerjaan']];
        }
        if (!empty($s['pendidikan'])) {
            $rows[] = ['label' => 'Pendidikan', 'value' => is_array($s['pendidikan']) ? implode(', ', $s['pendidikan']) : (string) $s['pendidikan']];
        }
        if (!empty($s['pengalaman'])) {
            $rows[] = ['label' => 'Pengalaman', 'value' => 'Minimal ' . $s['pengalaman'] . ' Tahun Pengalaman'];
        }
        if (!empty($s['gender'])) {
            $rows[] = ['label' => 'Gender', 'value' => is_array($s['gender']) ? implode(', ', $s['gender']) : (string) $s['gender']];
        }

        $salary = self::formatSalary($s['gaji_minimal'] ?? null, $s['gaji_maksimal'] ?? null);
        if ($salary) {
            $rows[] = ['label' => 'Gaji', 'value' => $salary];
        }

        $age = self::formatAge($s['umur_min'] ?? null, $s['umur_max'] ?? null);
        if ($age) {
            $rows[] = ['label' => 'Usia', 'value' => $age];
        }

        if (!empty($s['lokasi_pekerjaan'])) {
            $rows[] = ['label' => 'Lokasi', 'value' => is_array($s['lokasi_pekerjaan']) ? implode(', ', $s['lokasi_pekerjaan']) : (string) $s['lokasi_pekerjaan']];
        }

        if (!empty($s['deadline'])) {
            $dl = $s['deadline'];
            $formatted = false !== strtotime($dl) ? date('j F Y', strtotime($dl)) : $dl;
            $rows[] = ['label' => 'Deadline', 'value' => $formatted];
        }

        return $rows;
    }

    public static function statusLabel($code): string
    {
        return match ((int) $code) {
            2 => 'Urgent',
            3 => 'Pinned',
            default => '',
        };
    }

    public static function deadlineText(?string $deadline): string
    {
        if (!$deadline)
            return '';
        $dlTs = strtotime($deadline);
        if ($dlTs === false)
            return $deadline;
        $today = strtotime(date('Y-m-d'));
        $dlDay = strtotime(date('Y-m-d', $dlTs));
        $days_left = (int) floor(($dlDay - $today) / 86400);

        if ($days_left > 1)
            return 'Sisa ' . $days_left . ' hari';
        if ($days_left === 1)
            return 'Sisa 1 hari';
        if ($days_left === 0)
            return 'Hari terakhir';
        if ($days_left === -1)
            return 'Berakhir kemarin';
        if ($days_left < -1)
            return 'Berakhir ' . abs($days_left) . ' hari lalu';
        return 'Berakhir hari ini';
    }
}