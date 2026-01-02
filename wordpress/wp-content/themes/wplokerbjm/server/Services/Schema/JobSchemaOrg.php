<?php

namespace WPLokerBJM\Services\Schema;
use WPLokerBJM\Factories\JobDataFactory;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Models\Schema\{Taxonomies, CustomFields};
use WPLokerBJM\Shared\Utilities\SharedUtils;

class JobSchemaOrg
{
    public function __construct(
        private JobDataFactory $jobDataFactory
    ) {
    }

    /**
     * Schema.org JobPosting JSON-LD generator
     * @param int $post_id
     * @return array
     */
    public function getJobPostingSchema(int $post_id): array
    {
        $cacheKey = CacheKey::JOB_SCHEMA_PREFIX . $post_id;
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $jobdata = $this->jobDataFactory->createJobData($post_id);

        $lokasi = $jobdata[Taxonomies::LOKASI_PEKERJAAN] ?? null;
        if (is_array($lokasi)) {
            $lokasi = array_filter(array_map('trim', $lokasi));
            $lokasi = implode(', ', $lokasi);
        } else {
            $lokasi = trim((string) $lokasi);
        }
        if (empty($lokasi)) {
            $lokasi = "To Be Confirmed";
        }

        $jenis_pekerjaan = $jobdata[Taxonomies::JENIS_PEKERJAAN] ?? null;
        if (is_array($jenis_pekerjaan)) {
            $jenis_pekerjaan = array_filter(array_map('trim', $jenis_pekerjaan));
            $jenis_pekerjaan = implode(', ', $jenis_pekerjaan);
        } else {
            $jenis_pekerjaan = trim((string) $jenis_pekerjaan);
        }

        $pendidikan = $jobdata[Taxonomies::PENDIDIKAN] ?? '';
        if (is_array($pendidikan)) {
            $pendidikan = array_filter(array_map('trim', $pendidikan), fn($v) => $v !== '');
            if (count($pendidikan) === 1) {
                $pendidikan = reset($pendidikan);
            } elseif (count($pendidikan) > 1) {
                $pendidikan = implode(', ', $pendidikan);
            } else {
                $pendidikan = "no requirements";
            }
        } elseif (is_string($pendidikan)) {
            $pendidikan = trim($pendidikan);
            if ($pendidikan === '') {
                $pendidikan = "no requirements";
            }
        }
        $pengalaman = $jobdata[CustomFields::PENGALAMAN] ?? '';
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
        $experienceRequirements = $experienceEnum ?? (!empty($pengalaman_str) ? $pengalaman_str : null);

        $sameAs = [];

        // Add company website(s)
        if (!empty($jobdata[CustomFields::SITUS_KONTAK])) {
            if (is_array($jobdata[CustomFields::SITUS_KONTAK])) {
                foreach ($jobdata[CustomFields::SITUS_KONTAK] as $url) {
                    if (filter_var($url, FILTER_VALIDATE_URL)) {
                        $sameAs[] = $url;
                    }
                }
            } elseif (filter_var($jobdata[CustomFields::SITUS_KONTAK], FILTER_VALIDATE_URL)) {
                $sameAs[] = $jobdata[CustomFields::SITUS_KONTAK];
            }
        }

        $schema = [
            "@context" => "https://schema.org",
            "@type" => "JobPosting",
            "title" => html_entity_decode(get_the_title($post_id), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            "url" => get_permalink($post_id),
            "@id" => get_permalink($post_id) . '#jobposting',
            "description" => !empty($jobdata[CustomFields::DESKRIPSI_PEKERJAAN]) ? wp_strip_all_tags($jobdata[CustomFields::DESKRIPSI_PEKERJAAN]) : "No description",
            "aboutCompany" => !empty($jobdata[CustomFields::TENTANG_PERUSAHAAN]) ? wp_strip_all_tags($jobdata[CustomFields::TENTANG_PERUSAHAAN]) : "No information about the company.",
            "requirements" => !empty($jobdata[CustomFields::PERSYARATAN]) ? wp_strip_all_tags($jobdata[CustomFields::PERSYARATAN]) : null,
            "howToApply" => !empty($jobdata[CustomFields::CARA_MELAMAR]) ? wp_strip_all_tags($jobdata[CustomFields::CARA_MELAMAR]) : null,
            "datePosted" => get_post_time('c', false, $post_id),
            "hiringOrganization" => [
                "@type" => "Organization",
                "name" => $jobdata[CustomFields::NAMA_PERUSAHAAN] ?? "Anonymous",
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
            "validThrough" => $jobdata[CustomFields::DEADLINE] ?? null,
            "identifier" => [
                "@type" => "PropertyValue",
                "name" => $jobdata[CustomFields::NAMA_PERUSAHAAN] ?? "Anonymous",
                "value" => $post_id,
            ],
            "educationRequirements" => $pendidikan,
            "experienceRequirements" => $experienceRequirements,
            "jobBenefits" => !empty($jobdata[CustomFields::BENEFIT]) ? wp_strip_all_tags($jobdata[CustomFields::BENEFIT]) : null,
        ];

        if (!empty($jobdata[CustomFields::GAJI_MINIMAL])) {
            $maxValue = $jobdata[CustomFields::GAJI_MAKSIMAL] ?? $jobdata[CustomFields::GAJI_MINIMAL];
            $schema['baseSalary'] = [
                "@type" => "MonetaryAmount",
                "currency" => "IDR",
                "value" => [
                    "@type" => "QuantitativeValue",
                    "minValue" => (int) $jobdata[CustomFields::GAJI_MINIMAL],
                    "maxValue" => (int) $maxValue,
                    "unitText" => "MONTH",
                ],
            ];
            $gaji_min = (int) $jobdata[CustomFields::GAJI_MINIMAL];
            $gaji_max = (int) $maxValue;
            $schema['gaji_minimal'] = $gaji_min;
            $schema['gaji_maksimal'] = $gaji_max;
        }

        $umur_min = !empty($jobdata[CustomFields::UMUR_MIN]) ? (int) $jobdata[CustomFields::UMUR_MIN] : null;
        $umur_max = !empty($jobdata[CustomFields::UMUR_MAX]) ? (int) $jobdata[CustomFields::UMUR_MAX] : null;
        $schema['umur_minimal'] = $umur_min;
        $schema['umur_maksimal'] = $umur_max;

        $schema['hiringOrganization'] = array_filter($schema['hiringOrganization'], fn($v) => !is_null($v));
        $schema = SharedUtils::filterEmptyValues($schema);

        Cache::set($cacheKey, $schema, 86400);

        return $schema;
    }
}