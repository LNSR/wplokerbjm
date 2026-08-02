<?php
declare(strict_types=1);
namespace WPLokerBJM\Core\Container\Support\WPHooks;
use Brick\VarExporter\VarExporter;

use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HookScannerTrait;
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
     */
    public function __construct(private string $namespace = 'WPLokerBJM', $cacheLocation = '')
    {
        $this->namespace = trim($namespace, '\\');
        $this->cacheLocation = $cacheLocation;
    }

    /**
     * Build the parameter resolution plan for a condition closure.
     *
     * Each entry describes one parameter: its name, the class type to
     * resolve from the container (null for builtin/untyped params),
     * whether a default value exists, and the default value itself.
     *
     * Returns an empty plan for null conditions or when a default value
     * cannot be safely exported to the cache (objects/resources) — in
     * that case the registry falls back to reflection at fire time.
     *
     * @return array<int, array{name: string, type: class-string|null, hasDefault: bool, default: mixed}>
     */
    public static function buildConditionPlan(?\Closure $condition): array
    {
        if ($condition === null) {
            return [];
        }

        try {
            $reflect = new ReflectionFunction($condition);
            $plan = [];

            foreach ($reflect->getParameters() as $param) {
                $type = $param->getType();
                $hasDefault = $param->isDefaultValueAvailable();
                $default = $hasDefault ? $param->getDefaultValue() : null;

                // Non-exportable defaults would break the cache — defer to reflection.
                if ($hasDefault && (is_object($default) || is_resource($default))) {
                    return [];
                }

                $plan[] = [
                    'name' => $param->getName(),
                    'type' => ($type instanceof ReflectionNamedType && !$type->isBuiltin()) ? $type->getName() : null,
                    'hasDefault' => $hasDefault,
                    'default' => $default,
                ];
            }

            return $plan;
        } catch (\ReflectionException) {
            return [];
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
                return $this->cachedHookRegistrations = $loaded;
            }
        }

        $registrations = $this->performHookRegistrationScan();
        $this->cachedHookRegistrations = $registrations;

        if (!empty($this->cacheLocation)) {
            $this->exportCache();
        }

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

        foreach (Bootstrap::getRobotLoader()->getIndexedClasses() as $className => $file) {
            if (!str_starts_with($className, $namespacePrefix) || !class_exists($className)) {
                continue;
            }
            try {
                $reflection = new ReflectionClass($className);
                $methodCb = static function (ReflectionMethod $method, Action|Filter $attr, string $visibility, string $type) use ($className, &$registrations): void {
                    $registrations[] = new HookRegistration(
                        class: $className,
                        method: $method->getName(),
                        type: $type,
                        hook: $attr->hook,
                        priority: $attr->priority,
                        acceptedArgs: $attr->acceptedArgs,
                        defer: $attr->defer,
                        target: 'method',
                        visibility: $visibility,
                        condition: $attr->condition,
                        conditionParams: self::buildConditionPlan($attr->condition),
                    );
                };
                $this->scanMethodHooks($reflection, $methodCb);

                $propertyCb = static function (ReflectionProperty $property, Action|Filter $attr, string $visibility, string $type, string $target) use ($className, &$registrations): void {
                    $registrations[] = new HookRegistration(
                        class: $className,
                        method: $property->getName(),
                        type: $type,
                        hook: $attr->hook,
                        priority: $attr->priority,
                        acceptedArgs: $attr->acceptedArgs,
                        defer: $attr->defer,
                        target: $target,
                        visibility: $visibility,
                        condition: $attr->condition,
                        conditionParams: self::buildConditionPlan($attr->condition),
                    );
                };

                $this->scanPropertyHooks($reflection, $propertyCb);
            } catch (\Exception $e) {
                Logger::error('WPhooksScanner', 'Error scanning hooks for class ' . $className . ': ' . $e->getMessage());
            }
        }

        return $registrations;
    }
    /**
     * Exports the hook registrations array to a PHP file returning the array.
     *
     * @return bool True on success, false on failure.
     */
    private function exportCache(): bool
    {
        $targetFile = $this->cacheLocation;

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

        $registrations = $this->getHookRegistrations();

        $registrationsArray = array_map(
            static fn(HookRegistration $reg) => $reg->toArray(),
            $registrations
        );

        $exportedArray = VarExporter::export(
            $registrationsArray,
            VarExporter::CLOSURE_SNAPSHOT_USES | VarExporter::ADD_RETURN | VarExporter::ADD_TYPE_HINTS
        );

        $phpContent = "<?php\n\ndeclare(strict_types=1);\n\n/**\n * Auto-generated WP Hooks Cache\n * Generated at: " . date('Y-m-d H:i:s') . "\n */\n\n" . $exportedArray;
        $result = file_put_contents($targetFile, $phpContent, LOCK_EX);

        return $result !== false;
    }
}