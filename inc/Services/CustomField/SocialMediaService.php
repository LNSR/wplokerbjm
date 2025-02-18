<?php

namespace AstraChild\Services\CustomField;

class SocialMediaService
{
    /** @var array Mapping of social media platforms to their configurations */
    protected array $platforms = [
        'X / Twitter' => [
            'icon' => 'fab fa-x-twitter',
            'base_url' => 'https://twitter.com/'
        ],
        'Facebook' => [
            'icon' => 'fab fa-facebook',
            'base_url' => 'https://facebook.com/'
        ],
        'Instagram' => [
            'icon' => 'fab fa-instagram',
            'base_url' => 'https://instagram.com/'
        ],
        'LinkedIn' => [
            'icon' => 'fab fa-linkedin',
            'base_url' => 'https://linkedin.com/in/'
        ],
        'Youtube' => [
            'icon' => 'fab fa-youtube',
            'base_url' => 'https://youtube.com/@'
        ],
        'Whatsapp' => [
            'icon' => 'fab fa-whatsapp',
            'base_url' => 'https://wa.me/',
        ],
        'Tiktok' => [
            'icon' => 'fab fa-tiktok',
            'base_url' => 'https://tiktok.com/@'
        ],
        'Threads' => [
            'icon' => 'fab fa-threads',
            'base_url' => 'https://threads.net/@'
        ],
        'Telegram' => [
            'icon' => 'fab fa-telegram',
            'base_url' => 'https://t.me/'
        ]
    ];

    /**
     * Get social media link data
     * 
     * @param string $platform Social media platform name
     * @param string $username Username/handle or phone number for WhatsApp
     * @return array|null Array containing icon and full URL
     */
    public function getLinkData(string $platform, string $username): ?array
    {
        if (!isset($this->platforms[$platform]) || empty($username)) {
            return null;
        }

        $config = $this->platforms[$platform];

        return match ($platform) {
            'Whatsapp' => $this->getWhatsappLinkData($config, $username),
            'LinkedIn' => $this->getLinkedInLinkData($config, $username),
            default => $this->getDefaultLinkData($config, $username),
        };
    }

    /**
     * Handle Whatsapp link logic.
     */
    private function getWhatsappLinkData(array $config, string $username): array
    {
        // Already a wa.me QR code link
        if (preg_match('/^https?:\/\/wa\.me\/qr\/[A-Z0-9]+$/i', $username)) {
            return [
                'icon' => $config['icon'],
                'url' => $username,
                'username' => $username
            ];
        }
        // wa.me/number link
        if (preg_match('/^(?:https?:\/\/)?wa\.me\/(\d+)$/i', $username, $matches)) {
            $number = $matches[1];
            return [
                'icon' => $config['icon'],
                'url' => 'https://wa.me/' . $number,
                'username' => '+' . $number
            ];
        }
        // Other WhatsApp links
        if (preg_match('/^https?:\/\/(api\.whatsapp\.com|web\.whatsapp\.com)/', $username)) {
            return [
                'icon' => $config['icon'],
                'url' => $username,
                'username' => $username
            ];
        }
        // Treat as phone number
        $clean_number = preg_replace('/[^0-9]/', '', $username);
        return [
            'icon' => $config['icon'],
            'url' => $config['base_url'] . $clean_number,
            'username' => $username
        ];
    }

    /**
     * Handle LinkedIn link logic.
     */
    private function getLinkedInLinkData(array $config, string $username): array
    {
        if (preg_match('/^https?:\/\//i', $username)) {
            return [
                'icon' => $config['icon'],
                'url' => $username,
                'username' => $username
            ];
        }
        $clean_username = ltrim($username, '@');
        if (preg_match('/^company[:\/](.+)$/i', $clean_username, $matches)) {
            $company_name = $matches[1];
            $url = 'https://linkedin.com/company/' . $company_name;
        } else {
            $url = $config['base_url'] . $clean_username;
        }
        return [
            'icon' => $config['icon'],
            'url' => $url,
            'username' => $username
        ];
    }

    /**
     * Handle default link logic for other platforms.
     */
    private function getDefaultLinkData(array $config, string $username): array
    {
        if (preg_match('/^https?:\/\//i', $username)) {
            return [
                'icon' => $config['icon'],
                'url' => $username,
                'username' => $username
            ];
        }
        $clean_username = ltrim($username, '@');
        $url = $config['base_url'] . $clean_username;

        return [
            'icon' => $config['icon'],
            'url' => $url,
            'username' => $username
        ];
    }
}
