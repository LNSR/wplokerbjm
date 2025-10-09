<?php
namespace WPLokerBJM\Core\Hooks;

class Nonce
{
    /**
     * Inject a script into the page head that stores a WP REST API nonce
     * in sessionStorage for use in client-side requests.
     */
    public static function injectNonceScript(): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        $nonce = wp_create_nonce('wp_rest');
        ?>
        <script>
            sessionStorage.setItem('wp-rest-nonce', '<?php echo esc_js($nonce); ?>');
            document.currentScript.remove();
        </script>
        <?php
    }

    /**
     * Send X-WP-Nonce header when WordPress sends headers.
     *
     * The `send_headers` action may pass one argument (the WP object or headers)
     * depending on WP version; accept an optional parameter to avoid signature
     * mismatch warnings. We don't need the parameter here.
     *
     * @param mixed $maybeArg Optional argument passed by the action (ignored).
     */
    public static function SendNonceHeader($maybeArg = null): void {
        if (!is_user_logged_in()) {
            return;
        }

        $nonce = wp_create_nonce('wp_rest');
        header('X-WP-Nonce: ' . $nonce);
    }
}