<?php
/**
 * Helper functions for social media handling
 */

class Social_Media {
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
            'base_url' => 'https://wa.me/'
        ],
        'Tiktok' => [
            'icon' => 'fab fa-tiktok',
            'base_url' => 'https://tiktok.com/@'
        ],
        'Threads' => [
            'icon' => 'fab fa-threads',
            'base_url' => 'https://threads.net/@'
        ]
    ];

    /**
     * Get social media link data
     * 
     * @param string $platform Social media platform name
     * @param string $username Username/handle
     * @return array|null Array containing icon and full URL
     */
    public static function get_link_data($platform, $username) {
        if (!isset(self::$platforms[$platform]) || empty($username)) {
            return null;
        }

        $config = self::$platforms[$platform];
        
        // Clean username for WhatsApp
        if ($platform === 'Whatsapp') {
            $username = preg_replace('/[^0-9]/', '', $username);
        }

        return [
            'icon' => $config['icon'],
            'url' => $config['base_url'] . $username,
            'username' => $username
        ];
    }
}