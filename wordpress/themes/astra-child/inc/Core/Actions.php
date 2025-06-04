<?php

namespace AstraChild\Core;

class Actions
{
    public function register(): void
    {
        add_action('wp_head', [$this, 'injectThemeScript'], 1);
        add_action('wp_head', [$this, 'injectAdsenseScript'], 2);
        add_action('wp_head', [$this, 'injectGTMHead'], 3);
        add_action('wp_body_open', [$this, 'injectGTMBody']);
    }

    /**
     * Injects a script to set the theme before CSS loads, preventing FOUC.
     */
    public function injectThemeScript(): void
    {
?>
        <script>
            (function() {
                try {
                    let theme = localStorage.getItem('astra-theme');
                    if (theme === 'dark' || theme === 'light') {
                        document.documentElement.setAttribute('data-theme', theme);
                    }
                } catch (e) {}
            })();
        </script>
<?php
    }

    public function injectAdsenseScript(): void
    {
        ?>
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-3206452872913415"
             crossorigin="anonymous"></script>
        <?php
    }

    public function injectGTMHead(): void
    {
        ?>
        <!-- Google Tag Manager -->
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','GTM-PHZNSBWX');</script>
        <!-- End Google Tag Manager -->
        <?php
    }

    public function injectGTMBody(): void
    {
        ?>
        <!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-PHZNSBWX"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->
        <?php
    }
}
