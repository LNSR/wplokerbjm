<?php

namespace AstraChild\Core;

class Actions
{
	public function register(): void
	{
		add_action('wp_enqueue_scripts', [$this, 'disableJquery'], 1);
		add_action('wp_head', [$this, 'injectThemeScript'], 0);
		add_action('wp_head', [$this, 'suppressJqueryErrors'], 1);
		add_action('wp_head', [$this, 'injectNoScriptWarning']);

		// if (!is_admin() && !is_user_logged_in()) {
		//     add_action('wp_head', [$this, 'injectAdsenseScript'], 10);
		//     add_action('wp_head', [$this, 'injectGTMHead'], 10);
		//     add_action('wp_body_open', [$this, 'injectGTMBody'], 10);
		// }
	}

	public function disableJquery(): void
	{
		// Only disable jQuery for non-logged-in users on the frontend
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
						val: function () { return ''; }, // common in forms
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

	/**
	 * Injects a script to set the theme before CSS loads, preventing FOUC.
	 */
	public function injectThemeScript(): void
	{
		?>
		<script>
			(function () {
				try {
					let theme = localStorage.getItem('astra-theme');
					if (theme === 'dark' || theme === 'light') {
						document.documentElement.setAttribute('data-theme', theme);
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
			setTimeout(loadGTM, 3000); // fallback: load after 3s
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

	/**
	 * Injects a warning for users with JavaScript disabled.
	 */
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
}
