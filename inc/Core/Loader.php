<?php
namespace AstraChild\Core;

/**
 * Autoloader class
 */
class Loader {
    /**
     * Register autoloader
     */
    public static function register() {
        spl_autoload_register([__CLASS__, 'autoload']);
    }
    
    /**
     * Autoload classes
     */
    public static function autoload($class) {
        // Only handle our namespace
        $prefix = 'AstraChild\\';
        if (strpos($class, $prefix) !== 0) {
            return;
        }
        
        // Convert namespace to file path
        $file = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
        $file = get_stylesheet_directory() . '/inc/' . $file . '.php';
        
        // Include file if it exists
        if (file_exists($file)) {
            require_once $file;
        }
    }
}