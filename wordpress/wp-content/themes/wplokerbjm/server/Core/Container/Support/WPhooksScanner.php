<?php

namespace WPLokerBJM\Core\Container\Support;

use ReflectionClass;
use ReflectionMethod;
use WPLokerBJM\Core\Container\Support\Utilities\FileScannerTrait;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Core\Container\Container;
use WPLokerBJM\Shared\Log\Logger;

/**
 * Scans directories for WordPress hook attribute registrations.
 *
 * This scanner searches for public, non-static methods annotated with
 * #[Action] or #[Filter] attributes across all autowirable PHP classes.
 * The resulting registrations are used by the Init service to automatically
 * register WordPress hooks via the DI container.
 *
 * @see \WPLokerBJM\Core\Container\Init
 * @see \WPLokerBJM\Core\Container\Attributes\Action
 * @see \WPLokerBJM\Core\Container\Attributes\Filter
 * @see Utilities\FileScannerTrait
 */
class WPhooksScanner
{
    use FileScannerTrait;

    private ?array $cachedHookRegistrations = null;

    public function __construct(private string $baseDirectory, private string $namespace = 'WPLokerBJM')
    {
        $this->baseDirectory = rtrim($baseDirectory, '/');
        $this->namespace = trim($namespace, '\\');
    }

    /**
     * Get the cache key for hook registration results.
     * Uses a distinct suffix to avoid collision with the autowire scanner cache.
     */
    private function getCacheKey(): string
    {
        $cachePath = Container::$CACHE_DIR;
        $mtime = is_dir($cachePath) ? filemtime($cachePath) : 0;
        return CacheKey::AUTOWIRE_SCANNER_PREFIX
            . md5($this->baseDirectory . $this->namespace . $this->getDirectoryHash() . $mtime)
            . '_hook_registrations';
    }

    /**
     * Get hook registrations from attributes on methods of autowirable classes.
     *
     * Scans public, non-static methods for #[Action] or #[Filter] attributes
     * and returns an array of hook registration data. Static methods are
     * intentionally skipped — hooks are resolved through the DI container
     * and therefore must be instance methods.
     *
     * @return array Array of hook data: ['class' => FQCN, 'method' => methodName, 'type' => 'action'|'filter', 'hook' => hookName, 'priority' => int, 'accepted_args' => int]
     */
    public function getHookRegistrations(): array
    {
        // Check in-memory cache first
        if ($this->cachedHookRegistrations !== null) {
            return $this->cachedHookRegistrations;
        }

        $cacheKey = $this->getCacheKey();

        // Check APCu first
        if (function_exists('apcu_enabled') && apcu_enabled()) {
            $cached = apcu_fetch($cacheKey);
            if ($cached !== false) {
                $this->cachedHookRegistrations = $cached;
                return $cached;
            }
        }

        // Fallback to Redis cache
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            $this->cachedHookRegistrations = $cached;
            return $cached;
        }

        // Perform the scan
        $registrations = $this->performHookRegistrationScan();

        // Cache the result
        if (function_exists('apcu_enabled') && apcu_enabled()) {
            apcu_store($cacheKey, $registrations, 86400);
        } else {
            Cache::set($cacheKey, $registrations, 86400);
        }

        $this->cachedHookRegistrations = $registrations;
        return $registrations;
    }

    /**
     * Perform the actual hook registration scanning logic.
     *
     * Only public, **non-static** methods are considered: hooks are resolved
     * through the DI container so the owning service must be instantiable.
     * Static hook methods are intentionally ignored.
     *
     * @return array Array of hook registration data
     */
    private function performHookRegistrationScan(): array
    {
        $registrations = [];
        $phpFiles = $this->findPhpFiles();

        foreach ($phpFiles as $file) {
            $classNames = $this->getClassNamesFromFile($file);

            foreach ($classNames as $className) {
                if (class_exists($className)) {
                    try {
                        $reflection = new ReflectionClass($className);
                        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                            // Skip static methods — hooks must be instance methods
                            // because the container resolves the owning service lazily.
                            if ($method->isStatic()) {
                                continue;
                            }
                            foreach ($method->getAttributes(\WPLokerBJM\Core\Container\Attributes\Action::class) as $attribute) {
                                $action = $attribute->newInstance();
                                $registrations[] = [
                                    'class' => $className,
                                    'method' => $method->getName(),
                                    'type' => 'action',
                                    'hook' => $action->hook,
                                    'priority' => $action->priority,
                                    'accepted_args' => $action->acceptedArgs,
                                ];
                            }
                            foreach ($method->getAttributes(\WPLokerBJM\Core\Container\Attributes\Filter::class) as $attribute) {
                                $filter = $attribute->newInstance();
                                $registrations[] = [
                                    'class' => $className,
                                    'method' => $method->getName(),
                                    'type' => 'filter',
                                    'hook' => $filter->hook,
                                    'priority' => $filter->priority,
                                    'accepted_args' => $filter->acceptedArgs,
                                ];
                            }
                        }
                    } catch (\Exception $e) {
                        Logger::error('WPhooksScanner', 'Error scanning hooks for class ' . $className . ': ' . $e->getMessage());
                    }
                }
            }
        }

        return $registrations;
    }
}
