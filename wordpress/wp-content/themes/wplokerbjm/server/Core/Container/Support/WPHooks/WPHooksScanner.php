<?php
declare(strict_types=1);
namespace WPLokerBJM\Core\Container\Support\WPHooks;
use Brick\VarExporter\VarExporter;

use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HookScannerTrait;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Bootstrap;
use WPLokerBJM\Shared\Log\Logger;
/**
 * Scans directories for WordPress hook attribute registrations.
 *
 * This scanner searches for instance methods and properties annotated with
 * #[Action] or #[Filter] attributes across all autowirable PHP classes.
 * The resulting registrations are used by the Init service to automatically
 * register WordPress hooks via the DI container.
 *
 * @see Action
 * @see Filter
 * @see \WPLokerBJM\Bootstrap
 */
class WPHooksScanner
{
    use HookScannerTrait;

    /** @var array<int, HookRegistration>|null */
    private ?array $cachedHookRegistrations = null;
    public private(set) string $cacheLocation {
        set(string $value) {
            $this->cacheLocation = is_dir($value) || str_ends_with($value, '/') || str_ends_with($value, '\\')
            ? rtrim($value, '/\\') . '/WPHooksCache.php'
            : $value;
        }
    }

    /**
     * @param string $namespace
     * @param string $cacheLocation directory where cache file will be stored
     * @param WPHookPlanProvider|null $hookPlanProvider plan builder for condition gates and dynamic hook names
     */
    public function __construct(private string $namespace = 'WPLokerBJM', $cacheLocation = '', private ?WPHookPlanProvider $hookPlanProvider = null)
    {
        $this->namespace = trim($namespace, '\\');
        $this->cacheLocation = $cacheLocation;
    }

    public function __destruct()
    {
        if (!empty($this->cacheLocation) && !file_exists($this->cacheLocation)) {
            $this->exportCache();
        }
    }

    /**
     * Get hook registrations from #[Action] and #[Filter] attributes.
     *
     * Scans instance methods and properties for hook attributes across all PHP files
     * in the base directory. Results are cached in-memory for the request.
     *
     * @return HookRegistration[]|array
     */
    public function getHookRegistrations(): array|HookRegistration
    {
        if ($this->cachedHookRegistrations !== null) {
            return $this->cachedHookRegistrations;
        }

        if (!empty($this->cacheLocation) && is_file($this->cacheLocation)) {
            $loaded = require $this->cacheLocation;
            if (is_array($loaded)) {
                return $this->cachedHookRegistrations = array_map(
                    static fn(HookRegistration|array $reg): HookRegistration => $reg instanceof HookRegistration ? $reg : HookRegistration::fromArray($reg),
                    $loaded,
                );
            }
        }

        $registrations = $this->performHookRegistrationScan();
        $this->cachedHookRegistrations = $registrations;

        return $registrations;
    }

    /**
     * Perform the actual hook registration scanning logic.
     *
     * Scans instance methods and properties across all visibility levels.
     * Static members are intentionally ignored — hooks are resolved through
     * the DI container so the owning service must be instantiable.
     *
     * @return HookRegistration[]
     */
    private function performHookRegistrationScan(): array
    {
        $registrations = [];

        $namespacePrefix = $this->namespace . '\\';

        $hookPlanProvider = $this->hookPlanProvider;
        foreach (Bootstrap::$robotLoader->getIndexedClasses() as $className => $file) {
            if (!str_starts_with($className, $namespacePrefix) || !class_exists($className)) {
                continue;
            }
            try {
                $reflection = new ReflectionClass($className);
                $methodCb = static function (ReflectionMethod $method, Action|Filter $attr, string $visibility, string $type) use ($className, &$registrations, $hookPlanProvider): void {
                    $registrations[] = new HookRegistration(
                        class: $className,
                        method: $method->getName(),
                        type: $type,
                        hook: $attr->hook,
                        priority: $attr->priority,
                        acceptedArgs: $attr->acceptedArgs,
                        deferRegister: $attr->deferRegister,
                        target: 'method',
                        visibility: $visibility,
                        executeIf: $attr->executeIf,
                        executeIfParams: $hookPlanProvider->buildCallablePlan($attr->executeIf),
                        registerIf: $attr->registerIf,
                        registerIfParams: $hookPlanProvider->buildCallablePlan($attr->registerIf),
                        hookParams: $hookPlanProvider->buildCallablePlan($attr->hook instanceof \Closure ? $attr->hook : null),
                        hookArgs: array_map(static fn($p) => $p->getName(), $method->getParameters()),
                        tags: $attr->tag instanceof \Closure ? [] : $attr->tag,
                        tagCallable: $attr->tag instanceof \Closure ? $attr->tag : null,
                        tagCallableParams: $hookPlanProvider->buildCallablePlan($attr->tag instanceof \Closure ? $attr->tag : null),
                        deferRegisterUntilHook: $attr->deferRegisterUntilHook,
                        deferRegisterUntilHookParams: $hookPlanProvider->buildCallablePlan($attr->deferRegisterUntilHook instanceof \Closure ? $attr->deferRegisterUntilHook : null),
                        once: $attr->once,
                    );
                };
                $this->scanMethodHooks($reflection, $methodCb);

                $propertyCb = static function (ReflectionProperty $property, Action|Filter $attr, string $visibility, string $type, string $target) use ($className, &$registrations, $hookPlanProvider): void {
                    $registrations[] = new HookRegistration(
                        class: $className,
                        method: $property->getName(),
                        type: $type,
                        hook: $attr->hook,
                        priority: $attr->priority,
                        acceptedArgs: $attr->acceptedArgs,
                        deferRegister: $attr->deferRegister,
                        target: $target,
                        visibility: $visibility,
                        executeIf: $attr->executeIf,
                        executeIfParams: $hookPlanProvider->buildCallablePlan($attr->executeIf),
                        registerIf: $attr->registerIf,
                        registerIfParams: $hookPlanProvider->buildCallablePlan($attr->registerIf),
                        hookParams: $hookPlanProvider->buildCallablePlan($attr->hook instanceof \Closure ? $attr->hook : null),
                        hookArgs: $hookPlanProvider->extractPropertyCallableParamNames($property),
                        tags: $attr->tag instanceof \Closure ? [] : $attr->tag,
                        tagCallable: $attr->tag instanceof \Closure ? $attr->tag : null,
                        tagCallableParams: $hookPlanProvider->buildCallablePlan($attr->tag instanceof \Closure ? $attr->tag : null),
                        deferRegisterUntilHook: $attr->deferRegisterUntilHook,
                        deferRegisterUntilHookParams: $hookPlanProvider->buildCallablePlan($attr->deferRegisterUntilHook instanceof \Closure ? $attr->deferRegisterUntilHook : null),
                        once: $attr->once,
                    );
                };
                $this->scanPropertyHooks($reflection, $propertyCb);
            } catch (\RuntimeException $e) {
                Logger::error('WPhooksScanner', 'Error scanning hooks for class ' . $className . ': ' . $e->getMessage());
                throw $e;
            }
        }

        return $registrations;
    }

    public function deleteCache(): bool
    {
        $targetFile = $this->cacheLocation;

        if (empty($targetFile)) {
            Logger::error('WPHooksScanner', 'Delete failed: No target file path provided.');
            return false;
        }

        if (!\file_exists($targetFile)) {
            return true;
        }

        return unlink($targetFile);
    }

    /**
     * Exports the hook registrations array to a PHP file returning the array.
     *
     * @return bool True on success, false on failure.
     */
    public function exportCache(): bool
    {
        $targetFile = $this->cacheLocation;
        $registrations = $this->getHookRegistrations();

        if (empty($targetFile)) {
            Logger::error('WPHooksScanner', 'Export failed: No target file path provided.');
            return false;
        }

        $directory = dirname($targetFile);

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                Logger::error('WPHooksScanner', "Failed to create directory: {$directory}");
                return false;
            }
        }

        if (!is_writable($directory)) {
            Logger::error('WPHooksScanner', "Directory is not writable: {$directory}");
            return false;
        }

        $exportedArray = VarExporter::export(
            $registrations,
            VarExporter::CLOSURE_SNAPSHOT_USES | VarExporter::ADD_RETURN | VarExporter::ADD_TYPE_HINTS
        );

        $phpContent = "<?php\n\ndeclare(strict_types=1);\n\n/**\n * Auto-generated WP Hooks Cache\n * Generated at: " . date('Y-m-d H:i:s') . "\n */\n\n" . $exportedArray;
        $result = file_put_contents($targetFile, $phpContent, LOCK_EX);
        return $result !== false;
    }
}