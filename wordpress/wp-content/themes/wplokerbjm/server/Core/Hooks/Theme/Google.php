<?php
namespace WPLokerBJM\Core\Hooks\Theme;

class Google
{

    /**
     * Add Google Tag Manager script to the head section if tracking is not disabled
     */
    public static function addGTM(): void
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $isSSGBot = in_array($userAgent, \WPLokerBJM\Services\Utilities\SSG\BotDetection::isSsgBotGeneration(), true);

        if ($isSSGBot || is_user_logged_in()) {
            return;
        }

        ?>
        <!-- Google Tag Manager -->
        <script>(function (w, d, s, l, i) {
                w[l] = w[l] || []; w[l].push({
                    'gtm.start':
                        new Date().getTime(), event: 'gtm.js'
                }); var f = d.getElementsByTagName(s)[0],
                    j = d.createElement(s), dl = l != 'dataLayer' ? '&l=' + l : ''; j.async = true; j.src =
                        'https://www.googletagmanager.com/gtm.js?id=' + i + dl; f.parentNode.insertBefore(j, f);
            })(window, document, 'script', 'dataLayer', 'GTM-PHZNSBWX');</script>
        <!-- End Google Tag Manager -->
        <?php
    }
}