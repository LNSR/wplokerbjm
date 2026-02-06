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

        // Map employment and location type via helper
        [$employmentType, $jobLocationType] = JobSchemaHelper::mapEmploymentAndLocationType($jenis_pekerjaan);

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
        // Map experience requirements via helper
        $experienceRequirements = JobSchemaHelper::mapExperienceRequirements($jobdata[CustomFields::PENGALAMAN] ?? null);

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

        // Normalize permalink and date to avoid boolean false leaking into JSON-LD
        $permalink = get_permalink($post_id);
        $permalink = $permalink ? esc_url($permalink) : null;
        $idurl = $permalink ? $permalink . '#jobposting' : null;
        $datePostedRaw = get_post_time('c', false, $post_id);
        $datePosted = $datePostedRaw ? $datePostedRaw : null;;

        // Build description via helper (combines DESKRIPSI_PEKERJAAN and PERSYARATAN when both present)
        $descriptionHtml = JobSchemaHelper::buildDescription($jobdata);

        $validThrough = $jobdata[CustomFields::DEADLINE] ?? null;
        if (empty($validThrough) && $datePostedRaw) {
            $postDate = new \DateTime($datePostedRaw);
            $postDate->modify('+1 month');
            $validThrough = $postDate->format('Y-m-d');
        }

        $schema = [
            "@context" => "https://schema.org",
            "@type" => "JobPosting",
            "title" => html_entity_decode(get_the_title($post_id), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            "url" => $permalink,
            "@id" => $idurl,
            "mainEntityOfPage" => $permalink ? [
                "@type" => "WebPage",
                "@id" => $permalink
            ] : null,
            "description" => $descriptionHtml,
            "aboutCompany" => !empty($jobdata[CustomFields::TENTANG_PERUSAHAAN]) ? wp_strip_all_tags($jobdata[CustomFields::TENTANG_PERUSAHAAN]) : "No information about the company.",
            "howToApply" => !empty($jobdata[CustomFields::CARA_MELAMAR]) ? wp_strip_all_tags($jobdata[CustomFields::CARA_MELAMAR]) : null,
            "datePosted" => $datePosted,
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
            "jobLocationType" => $jobLocationType,
            "employmentType" => $employmentType,
            "validThrough" => $validThrough,
            "identifier" => [
                "@type" => "PropertyValue",
                "name" => $jobdata[CustomFields::NAMA_PERUSAHAAN] ?? "Anonymous",
                "value" => $post_id,
            ],
            "educationRequirements" => $pendidikan,
            "experienceRequirements" => $experienceRequirements,
            "jobBenefits" => !empty($jobdata[CustomFields::BENEFIT]) ? wp_strip_all_tags($jobdata[CustomFields::BENEFIT]) : null,
        ];

        // Attach salary information via helper when available
        $salaryData = JobSchemaHelper::formatBaseSalary($jobdata);
        if (!empty($salaryData)) {
            $schema = array_merge($schema, $salaryData);
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

    /**
     * Schema.org ItemList JSON-LD generator for multiple JobPostings
     * @param array $post_ids
     * @return array
     */
    public function getItemListSchema(array $post_ids): array
    {
        // Use a cache key specific to this set of post IDs to avoid returning
        // the same ItemList for different ID sets.
        $cacheKey = CacheKey::GRAPHQL_JOB_SCHEMA_BATCH_PREFIX . md5(implode(',', $post_ids));
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $itemListElements = [];
        foreach ($post_ids as $index => $post_id) {
            $position = $index + 1;
            $jobSchema = $this->getJobPostingSchema($post_id);


            $itemListElements[] = [
                "@type" => "ListItem",
                "position" => $position,
                "name" => $jobSchema['title'],
                "url" => $jobSchema['url']
            ];
        }

        $itemListSchema = [
            "@context" => "https://schema.org",
            "@type" => "ItemList",
            "mainEntity" => [
                "@type" => "ItemList",
            ],
            "itemListElement" => $itemListElements,
            "itemListOrder" => "https://schema.org/ItemListOrderDescending",
            "numberOfItems" => count($itemListElements),
        ];

        Cache::set($cacheKey, $itemListSchema, 86400); // Cache for 1 day

        return $itemListSchema;
    }
}

class JobSchemaHelper {
    /**
     * Map taxonomy string to Google employmentType and detect remote jobLocationType
     * @param string|null $jenis
     * @return array [employmentType, jobLocationType]
     */
    public static function mapEmploymentAndLocationType(?string $jenis): array
    {
        $employmentType = null;
        $jobLocationType = null;

        $jenis = trim((string) ($jenis ?? ''));
        if ($jenis === '') {
            return ['FULL_TIME', null];
        }

        $parts = array_filter(array_map('trim', explode(',', $jenis)));
        $mapped = [];
        foreach ($parts as $p) {
            $l = mb_strtolower($p);

            if (str_contains($l, 'remote') || str_contains($l, 'telecommut') || str_contains($l, 'work from home') || $l === 'remote') {
                $jobLocationType = 'TELECOMMUTE';
                continue;
            }
            if (str_contains($l, 'full') || str_contains($l, 'penuh') || $l === 'fulltime') {
                $mapped[] = 'FULL_TIME';
                continue;
            }
            if (str_contains($l, 'part') || str_contains($l, 'paruh') || $l === 'parttime') {
                $mapped[] = 'PART_TIME';
                continue;
            }
            if (str_contains($l, 'kontrak') || str_contains($l, 'contract') || $l === 'kontrak') {
                $mapped[] = 'CONTRACTOR';
                continue;
            }
            if (str_contains($l, 'freelance')) {
                $mapped[] = 'CONTRACTOR';
                continue;
            }
            if (str_contains($l, 'temporary') || str_contains($l, 'sementara')) {
                $mapped[] = 'TEMPORARY';
                continue;
            }
            if (str_contains($l, 'intern') || str_contains($l, 'magang')) {
                $mapped[] = 'INTERN';
                continue;
            }
            if (str_contains($l, 'volunteer') || str_contains($l, 'relawan')) {
                $mapped[] = 'VOLUNTEER';
                continue;
            }
            if (str_contains($l, 'per') && str_contains($l, 'diem')) {
                $mapped[] = 'PER_DIEM';
                continue;
            }
            if ($l === 'other' || str_contains($l, 'lain')) {
                $mapped[] = 'OTHER';
                continue;
            }

            $normalized = preg_replace('/[^a-z0-9\s]+/i', '', $p);
            $normalized = strtoupper(str_replace(' ', '_', trim($normalized)));
            $mapped[] = $normalized ?: 'OTHER';
        }

        $mapped = array_unique(array_filter($mapped));
        if (empty($mapped)) {
            $employmentType = 'FULL_TIME';
        } elseif (count($mapped) === 1) {
            $employmentType = reset($mapped);
        } else {
            $employmentType = array_values($mapped);
        }

        return [$employmentType, $jobLocationType];
    }

    /**
     * Map pengalaman (years or text) to experienceRequirements
     * @param mixed $pengalaman
     * @return string|null
     */
    public static function mapExperienceRequirements($pengalaman): ?string
    {
        if ($pengalaman === null || $pengalaman === '') {
            return null;
        }

        if (is_numeric($pengalaman)) {
            $years = (int) $pengalaman;
            if ($years <= 1) {
                return 'EntryLevel';
            }
            if ($years <= 3) {
                return 'MidLevel';
            }
            return 'SeniorLevel';
        }

        // non-numeric text (already sanitized by factory)
        return (string) $pengalaman;
    }

    /**
     * Build description using DESKRIPSI_PEKERJAAN and fallback/appended PERSYARATAN
     * @param array $jobdata
     * @return string|null
     */
    public static function buildDescription(array $jobdata): ?string
    {
        $deskripsi_raw = $jobdata[CustomFields::DESKRIPSI_PEKERJAAN] ?? '';
        $persyaratan_raw = $jobdata[CustomFields::PERSYARATAN] ?? '';

        if (!empty($deskripsi_raw) && !empty($persyaratan_raw)) {
            return $deskripsi_raw . '<br>' . $persyaratan_raw;
        }
        if (!empty($deskripsi_raw)) {
            return $deskripsi_raw;
        }
        if (!empty($persyaratan_raw)) {
            return $persyaratan_raw;
        }

        return null;
    }

    /**
     * Format base salary block for schema and return additional keys
     * @param array $jobdata
     * @return array
     */
    public static function formatBaseSalary(array $jobdata): array
    {
        if (empty($jobdata[CustomFields::GAJI_MINIMAL])) {
            return [];
        }

        $min = (int) $jobdata[CustomFields::GAJI_MINIMAL];
        $max = isset($jobdata[CustomFields::GAJI_MAKSIMAL]) ? (int) $jobdata[CustomFields::GAJI_MAKSIMAL] : $min;

        return [
            'baseSalary' => [
                "@type" => "MonetaryAmount",
                "currency" => "IDR",
                "value" => [
                    "@type" => "QuantitativeValue",
                    "minValue" => $min,
                    "maxValue" => $max,
                    "unitText" => "MONTH",
                ],
            ],
            'gaji_minimal' => $min,
            'gaji_maksimal' => $max,
        ];
    }
}