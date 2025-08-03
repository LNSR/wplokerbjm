<?php

namespace AstraChild\Core;

use AstraChild\Contracts\HooksInterface;


class Hooks implements HooksInterface
{
    /*======================================================================
     | REGISTER HOOKS
     ======================================================================*/

    public function registerActions(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'disableJquery']);
        add_action('litespeed_purged_all', [$this, 'deleteCompiledContainer']);
        add_action('wp_head', [$this, 'injectThemeScript']);
        add_action('wp_head', [$this, 'suppressJqueryErrors']);
        add_action('wp_head', [$this, 'injectNoScriptWarning']);
        add_action('wp_head', [$this, 'injectWpUserLoggedInFlag']); // login status


        // if (!is_admin() && !is_user_logged_in()) {
        //     add_action('wp_head', [$this, 'injectAdsenseScript'], 10);
        //     add_action('wp_head', [$this, 'injectGTMHead'], 10);
        //     add_action('wp_body_open', [$this, 'injectGTMBody'], 10);
        // }
    }

    /*======================================================================
     | REGISTER FILTERS
     ======================================================================*/

    public function registerFilters(): void
    {
        add_filter('posts_search', [$this, 'jobPostsSearchFilter'], 10, 2);
        add_filter('litespeed_optimize_js_excludes', [$this, 'lscJsExcludes']);
        add_filter('litespeed_optimize_css_excludes', [$this, 'lscCssExcludes']);
    }


    /*======================================================================
     | ACTIONS
     ======================================================================*/


    /**
     * Injects a JS variable indicating if the user is logged in to WordPress.
     */
    public function injectWpUserLoggedInFlag(): void
    {
        ?>
        <script>window.wpUserLoggedIn = <?php echo is_user_logged_in() ? 'true' : 'false'; ?>;</script>
        <?php
    }

    public function disableJquery(): void
    {
        if (!is_admin() && !is_user_logged_in()) {
            global $wp_scripts;
            if ($wp_scripts instanceof \WP_Scripts) {
                foreach ($wp_scripts->registered as $handle => $script) {
                    if (strpos($handle, 'jquery') === 0) {
                        wp_dequeue_script($handle);
                        wp_deregister_script($handle);
                    }
                }
            }
        }
    }


    public function suppressJqueryErrors(): void
    {
        ?>
        <script>
            if (!window.jQuery) {
                console.warn('jQuery is not loaded. A minimal stub is provided to suppress errors.');
                window.jQuery = window.$ = function () {
                    return {
                        ready: function (fn) { if (typeof fn === 'function') fn(); return this; },
                        on: function () { return this; },
                        off: function () { return this; },
                        trigger: function () { return this; },
                        click: function () { return this; },
                        addClass: function () { return this; },
                        removeClass: function () { return this; },
                        hasClass: function () { return false; },
                        css: function () { return this; },
                        each: function () { return this; },
                        find: function () { return this; },
                        parent: function () { return this; },
                        children: function () { return this; },
                        attr: function () { return this; },
                        data: function () { return this; },
                        append: function () { return this; },
                        prepend: function () { return this; },
                        remove: function () { return this; },
                        hide: function () { return this; },
                        show: function () { return this; },
                        val: function () { return ''; },
                        html: function () { return this; },
                        text: function () { return this; },
                        fadeIn: function () { return this; },
                        fadeOut: function () { return this; }
                    };
                };
                window.jQuery.fn = window.jQuery.prototype = {};
            }
        </script>
        <?php
    }


    public function injectThemeScript(): void
    {
        ?>
        <script>
            (function () {
                try {
                    let theme = localStorage.getItem('astra-theme');
                    if (theme === 'dark' || theme === 'light') {
                        document.documentElement.setAttribute('data-theme', theme);
                        if (theme === 'dark') {
                            document.documentElement.classList.add('astra-dark-mode-enable');
                        } else {
                            document.documentElement.classList.remove('astra-dark-mode-enable');
                        }
                    }
                } catch (e) { }
            })();
        </script>
        <?php
    }


    public function injectAdsenseScript(): void
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


    public function injectGTMHead(): void
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


    public function injectGTMBody(): void
    {
        ?>
        <!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-PHZNSBWX" height="0" width="0"
                style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->
        <?php
    }


    public function injectNoScriptWarning(): void
    {
        ?>
        <noscript>
            <div class="fixed top-0 left-0 w-full z-[9999] bg-yellow-300 text-black text-center font-bold py-4 px-2 mt-12">
                Tolong aktifkan JavaScript di browser Anda.
            </div>
        </noscript>
        <?php
    }


    public function deleteCompiledContainer(): void
    {
        $file = get_stylesheet_directory() . '/cache/CompiledContainer.php';
        if (file_exists($file)) {
            unlink($file);
        }
    }


    /*======================================================================
     | FILTERS
     ======================================================================*/

    /**
     * Customizes the SQL WHERE clause for WordPress search queries on job posts.
     */
    public function jobPostsSearchFilter($search, $wp_query)
    {
        global $wpdb;
        if (!empty($wp_query->query_vars['s'])) {
            $search = '';
            $q = $wp_query->query_vars['s'];
            $q_esc = esc_sql($wpdb->esc_like($q));
            $q_html = esc_sql($wpdb->esc_like(htmlentities($q, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

            $search .= " AND (";
            $search .= "{$wpdb->posts}.post_title LIKE '%{$q_esc}%' OR ";
            $search .= "{$wpdb->posts}.post_title LIKE '%{$q_html}%' OR ";
            $search .= "{$wpdb->posts}.ID IN (
            SELECT post_id FROM {$wpdb->postmeta}
            WHERE meta_key = 'nama_perusahaan' AND (meta_value LIKE '%{$q_esc}%' OR meta_value LIKE '%{$q_html}%')
        ) OR ";
            $search .= "{$wpdb->posts}.ID IN (
            SELECT object_id FROM {$wpdb->term_relationships}
            INNER JOIN {$wpdb->term_taxonomy} ON {$wpdb->term_taxonomy}.term_taxonomy_id = {$wpdb->term_relationships}.term_taxonomy_id
            INNER JOIN {$wpdb->terms} ON {$wpdb->terms}.term_id = {$wpdb->term_taxonomy}.term_id
            WHERE {$wpdb->term_taxonomy}.taxonomy = 'perusahaan'
            AND {$wpdb->terms}.name LIKE '%{$q_esc}%'
        )";
            $search .= ")";
        }
        return $search;
    }

    /**
     * Exclude specific JS files from LiteSpeed Cache JS optimization.
     */
    public function lscJsExcludes($excludes)
    {
        $excludes[] = '/wp-content/themes/astra-child/assets/';
        return $excludes;
    }

    /**
     * Exclude specific CSS files from LiteSpeed Cache CSS optimization.
     */
    public function lscCssExcludes($excludes)
    {
        $excludes[] = '/wp-content/themes/astra-child/assets/';
        return $excludes;
    }
}