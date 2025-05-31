<?php

namespace AstraChild\Repositories;

use AstraChild\Contracts\DataProviderInterface;
use AstraChild\Models\CustomFieldEntity;

class CustomFieldRepository implements DataProviderInterface
{

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
     */
    public function getMetaBoxData(int $post_id): CustomFieldEntity
    {
        return new CustomFieldEntity(
            nama_perusahaan: rwmb_meta('nama_perusahaan', '', $post_id),
            tentang_perusahaan: rwmb_meta('tentang_perusahaan', '', $post_id),
            deskripsi_pekerjaan: rwmb_meta('deskripsi_pekerjaan', '', $post_id),
            umur_min: rwmb_meta('umur_min', '', $post_id),
            umur_max: rwmb_meta('umur_max', '', $post_id),
            pengalaman: rwmb_meta('pengalaman', '', $post_id),
            persyaratan: rwmb_meta('persyaratan', '', $post_id),
            cara_melamar: rwmb_meta('cara_melamar', '', $post_id),
            benefit: rwmb_meta('benefit', '', $post_id),
            gaji_minimal: rwmb_meta('gaji_minimal', '', $post_id),
            gaji_maksimal: rwmb_meta('gaji_maksimal', '', $post_id),
            deadline: rwmb_meta('deadline', '', $post_id),
            email_kontak: rwmb_meta('email_kontak', '', $post_id),
            nomor_kontak: rwmb_meta('nomor_kontak', '', $post_id),
            situs_kontak: rwmb_meta('situs_kontak', '', $post_id),
            social_media: rwmb_meta('social_media', '', $post_id),
            status_pekerjaan: rwmb_meta('status_pekerjaan', '', $post_id)
        );
    }
}
