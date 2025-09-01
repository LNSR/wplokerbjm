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
    /**
     * Injects ad hiding CSS inline in <head> for faster application.
     */
    public static function injectAdHideInlineStyle(): void
    {
        ?>
        <style>
            .google-auto-placed[hidden],
            .adsbygoogle[hidden],
            [data-adsbygoogle-status="empty"],
            [data-ad-status="unfilled"] {
                display: none !important;
            }
        </style>

        <script>
            (function () {
                const selectors = [
                    '[data-adsbygoogle-status="empty"]',
                    '[data-ad-status="unfilled"]',
                    '.google-auto-placed[hidden]',
                    '.adsbygoogle[hidden]'
                ];

                function hideIfEmpty(el) {
                    try {
                        if (!el) return;

                        // Prefer hiding inner ad elements (ins.adsbygoogle, iframe) so the outer
                        // container and its layout remain intact.
                        var innerAds = [];
                        try {
                            innerAds = Array.prototype.slice.call(el.querySelectorAll('ins.adsbygoogle, iframe'));
                        } catch (e) { innerAds = []; }

                        if (innerAds.length) {
                            innerAds.forEach(function (child) {
                                try {
                                    var childHeight = (child.offsetHeight || child.clientHeight || 0);
                                    var childHiddenAttr = child.getAttribute && (child.getAttribute('data-adsbygoogle-status') === 'empty' || child.getAttribute('data-ad-status') === 'unfilled' || child.style.display === 'none');
                                    // Hide the inner ad node only when it is signaled empty/unfilled or has zero height.
                                    if (childHiddenAttr || childHeight === 0) {
                                        child.setAttribute && child.setAttribute('data-hidden-by-theme', '1');
                                        child.style && (child.style.display = 'none');
                                    }
                                } catch (e) { /* ignore per-node errors */ }
                            });
                            return;
                        }

                        // No inner ad nodes found. Only hide the element itself if it explicitly
                        // signals empty/unfilled or it has zero computed height — this prevents
                        // collapsing containers that are used for layout.
                        var isMarkedEmpty = (el.matches && (el.matches('[data-adsbygoogle-status="empty"]') || el.matches('[data-ad-status="unfilled"]'))) || el.hasAttribute('hidden');
                        var rect = (el.getBoundingClientRect && el.getBoundingClientRect()) || null;
                        var height = rect ? rect.height : (el.offsetHeight || el.clientHeight || 0);

                        if (isMarkedEmpty && height === 0) {
                            el.setAttribute('data-hidden-by-theme', '1');
                            el.style.display = 'none';
                        }
                    } catch (e) {
                        // ignore DOM exceptions
                    }
                }

                function scanAndHide() {
                    try {
                        document.querySelectorAll(selectors.join(",")).forEach(hideIfEmpty);
                    } catch (e) {
                        // ignore
                    }
                }

                function startObserver() {
                    try {
                        var observer = new MutationObserver(function (mutations) {
                            // For performance, only inspect added nodes and attribute changes
                            mutations.forEach(function (m) {
                                if (m.type === 'childList' && m.addedNodes && m.addedNodes.length) {
                                    m.addedNodes.forEach(function (n) {
                                        if (n.nodeType === 1) hideIfEmpty(n);
                                        // also scan children of the added node
                                        try { n.querySelectorAll && n.querySelectorAll(selectors.join(",")).forEach(hideIfEmpty); } catch (e) { }
                                    });
                                } else if (m.type === 'attributes') {
                                    hideIfEmpty(m.target);
                                }
                            });
                        });

                        var root = document.body || document.documentElement;
                        observer.observe(root, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-adsbygoogle-status', 'data-ad-status', 'hidden'] });

                        // After a short grace period, disconnect observer to avoid long-running
                        // observers that could interfere with page performance.
                        setTimeout(function () {
                            try { observer.disconnect(); } catch (e) { }
                        }, 10000);
                    } catch (e) {
                        // ignore observer errors
                    }
                }

                // Immediate, conservative hiding to reduce FOUC for slots already marked empty.
                scanAndHide();

                if (document.body) {
                    startObserver();
                } else {
                    document.addEventListener('DOMContentLoaded', function () {
                        scanAndHide();
                        startObserver();
                    }, { once: true });

                    var pollCount = 0;
                    var poll = setInterval(function () {
                        if (document.body) {
                            clearInterval(poll);
                            scanAndHide();
                            startObserver();
                        } else if (++pollCount > 50) { // ~2.5s timeout
                            clearInterval(poll);
                        }
                    }, 50);
                }
            })();
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