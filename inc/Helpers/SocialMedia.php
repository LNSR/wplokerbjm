<?php
namespace AstraChild\Helpers;

/**
 * Helper functions for social media handling
 */

class SocialMedia
{
    /** @var array Mapping of social media platforms to their configurations */
    private static $platforms = [
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
    public static function getLinkData(string $platform, string $username): ?array
    {
        if (!isset(self::$platforms[$platform]) || empty($username)) {
            return null;
        }

        $config = self::$platforms[$platform];
        
        if ($platform === 'Whatsapp') {
            // Check if input is already a WhatsApp URL
            if (preg_match('/^https?:\/\/wa\.me\/qr\/[A-Z0-9]+$/i', $username)) {
                // Handle QR code link format
                return [
                    'icon' => $config['icon'],
                    'url' => $username,
                    'username' => $username
                ];
            } elseif (preg_match('/^(?:https?:\/\/)?wa\.me\/(\d+)$/i', $username, $matches)) {
                // Extract number from wa.me link and format for display
                $number = $matches[1];
                return [
                    'icon' => $config['icon'],
                    'url' => 'https://wa.me/' . $number,
                    'username' => '+' . $number
                ];
            } elseif (preg_match('/^https?:\/\/(api\.whatsapp\.com|web\.whatsapp\.com)/', $username)) {
                // Handle other WhatsApp links
                return [
                    'icon' => $config['icon'],
                    'url' => $username,
                    'username' => $username
                ];
            }
            
            // Handle phone number
            $clean_number = preg_replace('/[^0-9]/', '', $username);
            
            return [
                'icon' => $config['icon'],
                'url' => $config['base_url'] . $clean_number,
                'username' => $username // Keep original format for manually entered numbers
            ];
        }

        // Handle other platforms
        // Check if input is already a full URL
        if (preg_match('/^https?:\/\//i', $username)) {
            return [
                'icon' => $config['icon'],
                'url' => $username,
                'username' => $username
            ];
        }

        // Handle username
        // Remove @ symbol if present at start
        $clean_username = ltrim($username, '@');
        $url = $config['base_url'] . $clean_username;
        
        return [
            'icon' => $config['icon'],
            'url' => $url,
            'username' => $username // Keep original format
        ];
    }
}
?>