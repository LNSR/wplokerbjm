<?php
namespace AstraChild\Core\Hooks;

class Google
{
    public static function injectGoogleScript(): void
    {
        ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=YOUR_TRACKING_ID"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag() { dataLayer.push(arguments); }
            gtag('js', new Date());
            gtag('config', 'YOUR_TRACKING_ID');
        </script>
        <?php
    }


    public static function injectAdsenseScript(): void
    {
        ?>
        <script>
            window.addEventListener('DOMContentLoaded', function () {
                var s = document.createElement('script');
                s.async = true;
                s.src = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-3206452872913415';
                s.crossOrigin = 'anonymous';
                document.head.appendChild(s);
            });
        </script>
        <?php
    }


    public static function injectGTMHead(): void
    {
        ?>
        <!-- Google Tag Manager (deferred) -->
        <script>
            function loadGTM() {
                if (window.gtmLoaded) return;
                window.gtmLoaded = true;
                var s = document.createElement('script');
                s.async = true;
                s.src = 'https://www.googletagmanager.com/gtm.js?id=GTM-PHZNSBWX';
                document.head.appendChild(s);
                window.dataLayer = window.dataLayer || [];
                window.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
            }
            window.addEventListener('scroll', loadGTM, { once: true });
            window.addEventListener('mousemove', loadGTM, { once: true });
            window.addEventListener('touchstart', loadGTM, { once: true });
            setTimeout(loadGTM, 3000);
        </script>
        <!-- End Google Tag Manager (deferred) -->
        <?php
    }


    public static function injectGTMBody(): void
    {
        ?>
        <!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-PHZNSBWX" height="0" width="0"
                style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->
        <?php
    }
}