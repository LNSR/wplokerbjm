<?php

namespace WPLokerBJM\Repositories;
use WPLokerBJM\Models\Schema\CustomFields;

class CustomFieldRepository
{
    public $metaBoxesCustomFields = [
        CustomFields::NAMA_PERUSAHAAN,
        CustomFields::TENTANG_PERUSAHAAN,
        CustomFields::DESKRIPSI_PEKERJAAN,
        CustomFields::UMUR_MIN,
        CustomFields::UMUR_MAX,
        CustomFields::PENGALAMAN,
        CustomFields::PERSYARATAN,
        CustomFields::CARA_MELAMAR,
        CustomFields::BENEFIT,
        CustomFields::GAJI_MINIMAL,
        CustomFields::GAJI_MAKSIMAL,
        CustomFields::DEADLINE,
        CustomFields::EMAIL_KONTAK,
        CustomFields::NOMOR_KONTAK,
        CustomFields::SITUS_KONTAK,
        CustomFields::SOCIAL_MEDIA,
        CustomFields::STATUS_PEKERJAAN,
    ];

    /**
     * Get job meta values as entity
     * 
     * Uses rwmb_meta() to retrieve custom field values. According to the docs:
     * - For simple fields: returns a single value
     * - For fields with multiple values: returns an array of values
     * - For cloneable fields: returns an array of values (each value is an array of clone values)
     * - For fields that save multiple values (checkbox_list, etc.): returns an array of values
     *
     * @link https://docs.metabox.io/functions/rwmb-meta/
     * 
     * @param int $post_id Post ID
     * @return array The data representing the custom field data
     */
    public function getMetaBoxCustomFields(int $post_id): array
    {
        $field = [];
        foreach ($this->metaBoxesCustomFields as $fieldId) {
            $field[$fieldId] = rwmb_meta($fieldId, [], $post_id);
        }
        return $field;
    }
}
