<?php

namespace AstraChild\Resources\Components\Partial;

use AstraChild\Services\Job\FormatterServices;

class JobSummaryRows
{
    /**
     * Returns an array of summary rows for job attributes.
     */
    public static function getSummaryRows(array $jobdata): array
    {
        $rows = [];

        if (!empty($jobdata['jenis_pekerjaan_taxo'])) {
            $rows[] = [
                'icon' => 'fa-clock',
                'label' => 'Jenis Pekerjaan',
                'value' => $jobdata['jenis_pekerjaan_taxo'],
            ];
        }
        if (!empty($jobdata['pendidikan_taxo'])) {
            $rows[] = [
                'icon' => 'fa-graduation-cap',
                'label' => 'Pendidikan',
                'value' => $jobdata['pendidikan_taxo'],
            ];
        }
        if (!empty($jobdata['pengalaman'])) {
            $rows[] = [
                'icon' => 'fa-briefcase',
                'label' => 'Pengalaman',
                'value' => 'Minimal ' . $jobdata['pengalaman'] . ' Tahun Pengalaman',
            ];
        }
        if (!empty($jobdata['gender_taxo'])) {
            $rows[] = [
                'icon' => 'fa-venus-mars',
                'label' => 'Gender',
                'value' => $jobdata['gender_taxo'],
            ];
        }
        $gaji_min = !empty($jobdata['gaji_minimal']) ? (int)$jobdata['gaji_minimal'] : null;
        $gaji_max = !empty($jobdata['gaji_maksimal']) ? (int)$jobdata['gaji_maksimal'] : null;
        $gaji_display = FormatterServices::formatSalary($gaji_min, $gaji_max);
        if ($gaji_display) {
            $rows[] = [
                'icon' => 'fa-money-bill-wave',
                'label' => 'Gaji',
                'value' => $gaji_display,
            ];
        }
        $umur_min = !empty($jobdata['umur_min']) ? (int)$jobdata['umur_min'] : null;
        $umur_max = !empty($jobdata['umur_max']) ? (int)$jobdata['umur_max'] : null;
        $umur_display = FormatterServices::formatAge($umur_min, $umur_max);
        if ($umur_display) {
            $rows[] = [
                'icon' => 'fa-birthday-cake',
                'label' => 'Usia',
                'value' => $umur_display,
            ];
        }
        if (!empty($jobdata['lokasi_taxo'])) {
            $rows[] = [
                'icon' => 'fa-map-marker-alt',
                'label' => 'Lokasi',
                'value' => $jobdata['lokasi_taxo'],
            ];
        }
        if (!empty($jobdata['deadline'])) {
            $rows[] = [
                'icon' => 'fa-calendar-alt',
                'label' => 'Deadline',
                'value' => $jobdata['deadline'],
            ];
        }

        return $rows;
    }
}
