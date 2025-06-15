<?php

namespace AstraChild\Services\Job;
use AstraChild\Repositories\JobRepository;
use AstraChild\Factories\JobDataFactory;
use AstraChild\Services\CustomField\SocialMediaService;

class JobServices {

    public function __construct(
        protected JobRepository $jobRepository,
        protected ?JobDataFactory $jobDataFactory,
        protected ?SocialMediaService $socialMediaService
    ) {}


    public function renderJobPostingJsonLd(int $post_id): string
    {
        $jobdata = $this->jobRepository->getJobData($post_id);

        $lokasi = $jobdata['lokasi_taxo'] ?? '';
        if (is_array($lokasi)) {
            $lokasi = array_filter(array_map('trim', $lokasi));
            $lokasi = implode(', ', $lokasi);
        } else {
            $lokasi = trim((string)$lokasi);
        }

        $jenis_pekerjaan = $jobdata['jenis_pekerjaan_taxo'] ?? '';
        if (is_array($jenis_pekerjaan)) {
            $jenis_pekerjaan = array_filter(array_map('trim', $jenis_pekerjaan));
            $jenis_pekerjaan = implode(', ', $jenis_pekerjaan);
        } else {
            $jenis_pekerjaan = trim((string)$jenis_pekerjaan);
        }

        $pendidikan = $jobdata['pendidikan_taxo'] ?? '';
        if (is_array($pendidikan)) {
            // Remove empty values and trim
            $pendidikan = array_filter(array_map('trim', $pendidikan));
            // If only one, use string, else array
            if (count($pendidikan) === 1) {
                $pendidikan = reset($pendidikan);
            } elseif (count($pendidikan) > 1) {
                $pendidikan = array_values($pendidikan);
            } else {
                $pendidikan = null;
            }
        } elseif (is_string($pendidikan)) {
            $pendidikan = trim($pendidikan);
            if ($pendidikan === '') {
                $pendidikan = null;
            }
        }
        $pengalaman = $jobdata['pengalaman'] ?? '';
        $pengalaman_str = '';
        if (!empty($pengalaman)) {
            // If numeric, append " tahun"
            $pengalaman_str = is_numeric($pengalaman) ? $pengalaman . ' tahun' : $pengalaman;
        }

        $sameAs = [];

        // Add company website(s)
        if (!empty($jobdata['situs_kontak'])) {
            if (is_array($jobdata['situs_kontak'])) {
                foreach ($jobdata['situs_kontak'] as $url) {
                    if (filter_var($url, FILTER_VALIDATE_URL)) {
                        $sameAs[] = $url;
                    }
                }
            } elseif (filter_var($jobdata['situs_kontak'], FILTER_VALIDATE_URL)) {
                $sameAs[] = $jobdata['situs_kontak'];
            }
        }

        if (!empty($jobdata['social_media']) && is_object($this->jobDataFactory)) {
            $socialItems = $this->jobDataFactory->createSocialMediaItems($jobdata['social_media']);
            foreach ($socialItems as $item) {
                if (!empty($item['url'])) {
                    $sameAs[] = $item['url'];
                }
            }
        }

        $schema = [
            "@context" => "https://schema.org",
            "@type" => "JobPosting",
            "title" => html_entity_decode(get_the_title($post_id), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            "description" => !empty($jobdata['deskripsi_pekerjaan']) ? wp_strip_all_tags($jobdata['deskripsi_pekerjaan']) : '',
            "datePosted" => get_post_time('c', false, $post_id),
            "hiringOrganization" => [
                "@type" => "Organization",
                "name" => $jobdata['nama_perusahaan'] ?? '',
                "sameAs" => !empty($sameAs) ? array_values(array_unique($sameAs)) : null,
            ],
            "jobLocation" => [
                "@type" => "Place",
                "address" => [
                    "@type" => "PostalAddress",
                    "addressLocality" => $lokasi,
                    "addressCountry" => "ID",
                ],
            ],
            "employmentType" => $jenis_pekerjaan,
            "validThrough" => $jobdata['deadline'] ?? '',
            "identifier" => [
                "@type" => "PropertyValue",
                "name" => $jobdata['nama_perusahaan'] ?? '',
                "value" => $post_id,
            ],
            "educationRequirements" => $pendidikan,
            "experienceRequirements" => !empty($pengalaman_str) ? $pengalaman_str : null,
        ];

        if (!empty($jobdata['gaji_minimal'])) {
            $schema['baseSalary'] = [
                "@type" => "MonetaryAmount",
                "currency" => "IDR",
                "value" => [
                    "@type" => "QuantitativeValue",
                    "minValue" => (int)$jobdata['gaji_minimal'],
                    "maxValue" => !empty($jobdata['gaji_maksimal']) ? (int)$jobdata['gaji_maksimal'] : (int)$jobdata['gaji_minimal'],
                    "unitText" => "MONTH"
                ]
            ];
            $gaji_min = (int)$jobdata['gaji_minimal'];
            $gaji_max = !empty($jobdata['gaji_maksimal']) ? (int)$jobdata['gaji_maksimal'] : null;
            $schema['salaryDisplay'] = \AstraChild\Services\Job\FormatterServices::formatSalary($gaji_min, $gaji_max);
        }

        $umur_min = !empty($jobdata['umur_min']) ? (int)$jobdata['umur_min'] : null;
        $umur_max = !empty($jobdata['umur_max']) ? (int)$jobdata['umur_max'] : null;
        $umur_display = \AstraChild\Services\Job\FormatterServices::formatAge($umur_min, $umur_max);
        if ($umur_display) {
            $schema['ageDisplay'] = $umur_display;
        }

        $schema['hiringOrganization'] = array_filter($schema['hiringOrganization'], fn($v) => !is_null($v));
        $schema = array_filter($schema, fn($v) => !is_null($v));

        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
    }

}
