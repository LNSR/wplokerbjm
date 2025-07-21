<?php

namespace AstraChild\Components\Partial;

class JobsContactsRows
{
    public static function getJobContactsRows(array $jobdata): array
    {
        $contacts = [];

        foreach (($jobdata['email_kontak'] ?? []) as $email) {
            if (!empty($email)) {
                $contacts[] = [
                    'type' => 'email',
                    'icon' => 'fas fa-envelope',
                    'label' => 'Email',
                    'value' => $email,
                    'href'  => 'mailto:' . $email,
                ];
            }
        }

        foreach (($jobdata['nomor_kontak'] ?? []) as $phone) {
            if (!empty($phone)) {
                $contacts[] = [
                    'type' => 'phone',
                    'icon' => 'fas fa-phone',
                    'label' => 'Telepon',
                    'value' => $phone,
                    'href'  => 'tel:' . $phone,
                ];
            }
        }

        foreach (($jobdata['situs_kontak'] ?? []) as $site) {
            if (!empty($site)) {
                $contacts[] = [
                    'type' => 'website',
                    'icon' => 'fas fa-globe',
                    'label' => 'Website',
                    'value' => preg_replace('#^https?://#', '', $site),
                    'href'  => $site,
                ];
            }
        }

        return $contacts;
    }
}
