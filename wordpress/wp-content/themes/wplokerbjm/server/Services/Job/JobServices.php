<?php

namespace WPLokerBJM\Services\Job;
use WPLokerBJM\Factories\JobDataFactory;

class JobServices
{

    public function __construct(
        private JobDataFactory $jobDataFactory
    ) {
    }

    /**
     * Schema.org JobPosting JSON-LD generator
     * @param int $post_id
     * @return string
     */
    public function renderJobPostingJsonLd(int $post_id): string
    {
        $jobdata = $this->jobDataFactory->buatDataPekerjaan($post_id);

        $lokasi = $jobdata['lokasi_taxo'] ?? '';
        if (is_array($lokasi)) {
            $lokasi = array_filter(array_map('trim', $lokasi));
            $lokasi = implode(', ', $lokasi);
        } else {
            $lokasi = trim((string) $lokasi);
        }

        $jenis_pekerjaan = $jobdata['jenis_pekerjaan_taxo'] ?? '';
        if (is_array($jenis_pekerjaan)) {
            $jenis_pekerjaan = array_filter(array_map('trim', $jenis_pekerjaan));
            $jenis_pekerjaan = implode(', ', $jenis_pekerjaan);
        } else {
            $jenis_pekerjaan = trim((string) $jenis_pekerjaan);
        }

        $pendidikan = $jobdata['pendidikan_taxo'] ?? '';
        if (is_array($pendidikan)) {
            $pendidikan = array_filter(array_map('trim', $pendidikan), fn($v) => $v !== '');
            if (count($pendidikan) === 1) {
                $pendidikan = reset($pendidikan);
            } elseif (count($pendidikan) > 1) {
                $pendidikan = array_values($pendidikan);
            } else {
                $pendidikan = "no requirements";
            }
        } elseif (is_string($pendidikan)) {
            $pendidikan = trim($pendidikan);
            if ($pendidikan === '') {
                $pendidikan = "no requirements";
            }
        }
        $pengalaman = $jobdata['pengalaman'] ?? '';
        $pengalaman_str = '';
        if (!empty($pengalaman)) {
            // If numeric, append " tahun"
            $pengalaman_str = is_numeric($pengalaman) ? $pengalaman . ' tahun' : $pengalaman;
        }

        // Map pengalaman to Schema.org enum if possible, else use string
        $experienceEnum = null;
        if (is_numeric($pengalaman)) {
            if ($pengalaman <= 1) {
                $experienceEnum = "EntryLevel";
            } elseif ($pengalaman <= 3) {
                $experienceEnum = "MidLevel";
            } else {
                $experienceEnum = "SeniorLevel";
            }
        }
        $schema["experienceRequirements"] = $experienceEnum ?? (!empty($pengalaman_str) ? $pengalaman_str : null);

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

        $schema = [
            "@context" => "https://schema.org",
            "@type" => "JobPosting",
            "title" => html_entity_decode(get_the_title($post_id), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            "description" => !empty($jobdata['deskripsi_pekerjaan']) ? wp_strip_all_tags($jobdata['deskripsi_pekerjaan']) : '',
            "aboutCompany" => !empty($jobdata['tentang_perusahaan']) ? wp_strip_all_tags($jobdata['tentang_perusahaan']) : null,
            "requirements" => !empty($jobdata['persyaratan']) ? wp_strip_all_tags($jobdata['persyaratan']) : null,
            "howToApply" => !empty($jobdata['cara_melamar']) ? wp_strip_all_tags($jobdata['cara_melamar']) : null,
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
            "jobBenefits" => !empty($jobdata['benefit']) ? wp_strip_all_tags($jobdata['benefit']) : null,
        ];

        if (!empty($jobdata['gaji_minimal'])) {
            $schema['baseSalary'] = [
                "@type" => "MonetaryAmount",
                "currency" => "IDR",
                "value" => [
                    "@type" => "QuantitativeValue",
                    "minValue" => (int) $jobdata['gaji_minimal'],
                    "maxValue" => !empty($jobdata['gaji_maksimal']) ? (int) $jobdata['gaji_maksimal'] : (int) $jobdata['gaji_minimal'],
                    "unitText" => "MONTH"
                ]
            ];
            $gaji_min = (int) $jobdata['gaji_minimal'];
            $gaji_max = (int) ($jobdata['gaji_maksimal']);
            $schema['gaji_minimal'] = $gaji_min;
            $schema['gaji_maksimal'] = $gaji_max;
        }

        $umur_min = !empty($jobdata['umur_min']) ? (int) $jobdata['umur_min'] : null;
        $umur_max = !empty($jobdata['umur_max']) ? (int) $jobdata['umur_max'] : null;
        $schema['umur_minimal'] = $umur_min;
        $schema['umur_maksimal'] = $umur_max;

        $schema['hiringOrganization'] = array_filter($schema['hiringOrganization'], fn($v) => !is_null($v));
        $schema = array_filter($schema, fn($v) => !is_null($v));

        // Mark the script with data attributes so client-side code can target
        // this specific JobPosting JSON-LD (e.g. data-ld-id="jobposting-123").
        $jsonLd = '<script type="application/ld+json" data-ld-type="JobPosting" data-ld-id="jobposting-' . intval($post_id) . '">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';

        return $jsonLd;
    }

}
