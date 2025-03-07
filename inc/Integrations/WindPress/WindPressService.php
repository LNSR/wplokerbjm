<?php
namespace AstraChild\Integrations\WindPress;

/**
 * WindPress Integration Service
 * 
 * Handles integration with WindPress performance optimization plugin
 */
class WindPressService
{
    /**
     * Initialize the WindPress integration
     */
    public function __construct()
    {
        // Initialize WindPress header injection
        add_action('wp_head', [$this, 'injectHeader'], 1);
        
        // Register theme scanner provider
        add_filter('f!windpress/core/cache:compile.providers', [$this, 'registerThemeProvider']);
    }

    /**
     * Inject WindPress header
     */
    public function injectHeader(): void
    {
        $is_cache_enabled = apply_filters(
            'f!windpress/core/runtime:append_header.cache_enabled',
            \WindPress\WindPress\Utils\Config::get('performance.cache.enabled', false)
        );

        $is_exclude_admin = apply_filters(
            'f!windpress/core/runtime:append_header.exclude_admin',
            \WindPress\WindPress\Utils\Config::get('performance.cache.exclude_admin', false)
                && current_user_can('manage_options')
        );

        $runtime = \WindPress\WindPress\Core\Runtime::get_instance();
        $runtime->print_windpress_metadata();

        if (!$is_exclude_admin && $is_cache_enabled && $runtime->is_cache_exists()) {
            $runtime->enqueue_css_cache();
        } else {
            $runtime->enqueue_play_cdn();
        }
    }
    
    /**
     * Register theme provider for WindPress scanner
     * 
     * @param array $providers Existing providers
     * @return array Updated providers
     */
    public function registerThemeProvider(array $providers): array
    {
        $providers[] = [
            'id' => 'astra-child',
            'name' => 'Astra Child Scanner',
            'description' => 'Scans the current active theme and child theme',
            'callback' => [$this, 'scannerCallback'],
            'enabled' => \WindPress\WindPress\Utils\Config::get(sprintf(
                'integration.%s.enabled',
                'astra-child'
            ), true),
        ];

        return $providers;
    }
    
    /**
     * Scanner callback to provide theme files
     * 
     * @return array Array of file contents
     */
    public function scannerCallback(): array
    {
        $file_extensions = ['php', 'js', 'html'];
        $contents = [];
        
        $finder = new \WindPressDeps\Symfony\Component\Finder\Finder();

        // The current active theme
        $wpTheme = wp_get_theme();
        $themeDir = $wpTheme->get_stylesheet_directory();

        // Check if the current theme is a child theme and get the parent theme directory
        $has_parent = $wpTheme->parent() ? true : false;
        $parentThemeDir = $has_parent ? $wpTheme->parent()->get_stylesheet_directory() : null;

        // Scan the theme directory according to the file extensions
        foreach ($file_extensions as $extension) {
            $finder->files()->in($themeDir)->name('*.' . $extension);
            if ($has_parent) {
                $finder->files()->in($parentThemeDir)->name('*.' . $extension);
            }
        }

        // Get the file contents and send to the compiler
        foreach ($finder as $file) {
            $contents[] = [
                'name' => $file->getRelativePathname(),
                'content' => $file->getContents(),
            ];
        }

        return $contents;
    }
}