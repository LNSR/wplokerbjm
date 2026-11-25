<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks;

use Brick\VarExporter\VarExporter;

use Nette\Loaders\RobotLoader;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\ModuleClassHookService;
use WPLokerBJM\Core\Container\Support\WPHooks\Registrations\HookRegistration;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HookScannerTrait;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Core\Container\Support\WPHooks\Logger\{HookLoggerInterface, NullHookLogger};
use WPLokerBJM\Core\Container\Support\WPHooks\Attributes\WPHooksScannerPolicy;

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
 */
class WPHooksScanner
{
    use HookScannerTrait;

    /** @var list<HookRegistration>|null */
    private ?array $cachedHookRegistrations = null;

    /**
     * @param \Closure(): list<class-string> $containerEntries candidate classes from the RobotLoader index, filtered to container entries
     * @param string $namespace namespace to scan
     * @param string $cacheLocation directory where cache file will be stored
     * @param WPHookPlanProvider $hookPlanProvider plan builder for condition gates and dynamic hook names
     */
    public function __construct(
        private \Closure $containerEntries,
        private WPHookPlanProvider $hookPlanProvider,
        private string $namespace = 'WPLokerBJM',
        private string $cacheLocation = '',
        private HookLoggerInterface $logger = new NullHookLogger(),
        public private(set) bool $forceNoCache = false
    ) {
        $this->namespace = trim($namespace, '\\');
        $this->cacheLocation = is_dir($cacheLocation) || str_ends_with($cacheLocation, '/') || str_ends_with($cacheLocation, '\\')
            ? rtrim($cacheLocation, '/\\') . '/WPhooksRegistryContainerCache.php'
            : $cacheLocation;
    }

    /**
     * Get hook registrations from #[Action] and #[Filter] attributes.
     *
     * Scans instance methods and properties for hook attributes across all PHP files
     * in the base directory. Results are cached in-memory for the request.
     *
     * @return list<HookRegistration>
     */
    public function getHookRegistrations(): array
    {
        if ($this->cachedHookRegistrations !== null) return $this->cachedHookRegistrations;

        if (!$this->forceNoCache && !empty($this->cacheLocation) && \is_readable($this->cacheLocation)) {
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
        if (!$this->forceNoCache) $this->exportCache();
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

        foreach (array_values(array_unique(($this->containerEntries)() ?? [])) as $className) {
            if (!str_starts_with($className, $namespacePrefix) || !class_exists($className)) {
                continue;
            }
            try {
                $reflection = new ReflectionClass($className);
                if (!$reflection->isInstantiable() || $reflection->isEnum()) continue;
                $policy = $reflection->getAttributes(WPHooksScannerPolicy::class);
                $attribute = null;
                if (!empty($policy)) $attribute = $policy[0]->newInstance();
                if ($attribute instanceof WPHooksScannerPolicy && $attribute->skipContainerRegistryScan) continue;

                /** @var array<string, int> $attributeOrdinals */
                $attributeOrdinals = [];
                $this->scanMethodHooks($reflection, function (ReflectionMethod $method, Action|Filter $attr, string $visibility, string $type) use ($className, &$registrations, &$attributeOrdinals, $reflection): void {
                    $ordinalKey = 'method:' . $method->getName();
                    $attributeIndex = $attributeOrdinals[$ordinalKey] = ($attributeOrdinals[$ordinalKey] ?? -1) + 1;
                    $registrations[] = new HookRegistration(
                        class: $className,
                        method: $method->getName(),
                        type: $type,
                        hook: $attr->hook,
                        attributeIndex: $attributeIndex,
                        hookParams: $this->hookPlanProvider->buildCallablePlan($attr->hook instanceof \Closure ? $attr->hook : null),
                        priority: $attr->priority,
                        acceptedArgs: $attr->acceptedArgs,
                        deferRegister: $attr->deferRegister,
                        target: 'method',
                        visibility: $visibility,
                        executeIf: $attr->executeIf,
                        executeIfParams: $this->hookPlanProvider->buildCallablePlan($attr->executeIf instanceof \Closure ? $attr->executeIf : null),
                        hookArgs: $this->hookPlanProvider->buildCallablePlanFromReflector($method)['params'],
                        registerIf: $attr->registerIf,
                        registerIfParams: $this->hookPlanProvider->buildCallablePlan($attr->registerIf instanceof \Closure ? $attr->registerIf : null),
                        tags: $attr->tag instanceof \Closure ? [] : $attr->tag,
                        tagCallable: $attr->tag instanceof \Closure ? $attr->tag : null,
                        tagCallableParams: $this->hookPlanProvider->buildCallablePlan($attr->tag instanceof \Closure ? $attr->tag : null),
                        deferRegisterUntilHook: $attr->deferRegisterUntilHook,
                        deferRegisterUntilHookParams: $this->hookPlanProvider->buildCallablePlan($attr->deferRegisterUntilHook instanceof \Closure ? $attr->deferRegisterUntilHook : null),
                        once: $attr->once,
                        onUnregisterDo: $attr->onUnregisterDo,
                        onUnregisterDoParams: $this->hookPlanProvider->buildCallablePlan($attr->onUnregisterDo instanceof \Closure ? $attr->onUnregisterDo : null),
                        debugDeferredRegistration: $attr->debugDeferredRegistration,
                        debugDeferredRegistrationParams: $this->hookPlanProvider->buildCallablePlan($attr->debugDeferredRegistration),
                        debugLiveRegistration: $attr->debugLiveRegistration,
                        debugLiveRegistrationParams: $this->hookPlanProvider->buildCallablePlan($attr->debugLiveRegistration),
                        debugLiveUnregistration: $attr->debugLiveUnregistration,
                        debugLiveUnregistrationParams: $this->hookPlanProvider->buildCallablePlan($attr->debugLiveUnregistration),
                    );
                });

                $this->scanPropertyHooks($reflection,  function (ReflectionProperty $property, Action|Filter $attr, string $visibility, string $type, string $target) use ($className, &$registrations, &$attributeOrdinals, $reflection): void {
                    $ordinalKey = 'property:' . $property->getName();
                    $attributeIndex = $attributeOrdinals[$ordinalKey] = ($attributeOrdinals[$ordinalKey] ?? -1) + 1;
                    $registrations[] = new HookRegistration(
                        class: $className,
                        method: $property->getName(),
                        type: $type,
                        hook: $attr->hook,
                        attributeIndex: $attributeIndex,
                        hookParams: $this->hookPlanProvider->buildCallablePlan($attr->hook instanceof \Closure ? $attr->hook : null),
                        priority: $attr->priority,
                        acceptedArgs: $attr->acceptedArgs,
                        deferRegister: $attr->deferRegister,
                        target: $target,
                        visibility: $visibility,
                        executeIf: $attr->executeIf,
                        executeIfParams: $this->hookPlanProvider->buildCallablePlan($attr->executeIf instanceof \Closure ? $attr->executeIf : null),
                        hookArgs: $this->hookPlanProvider->extractPropertyCallablePlan($property)['params'],
                        registerIf: $attr->registerIf,
                        registerIfParams: $this->hookPlanProvider->buildCallablePlan($attr->registerIf instanceof \Closure ? $attr->registerIf : null),
                        tags: $attr->tag instanceof \Closure ? [] : $attr->tag,
                        tagCallable: $attr->tag instanceof \Closure ? $attr->tag : null,
                        tagCallableParams: $this->hookPlanProvider->buildCallablePlan($attr->tag instanceof \Closure ? $attr->tag : null),
                        deferRegisterUntilHook: $attr->deferRegisterUntilHook,
                        deferRegisterUntilHookParams: $this->hookPlanProvider->buildCallablePlan($attr->deferRegisterUntilHook instanceof \Closure ? $attr->deferRegisterUntilHook : null),
                        once: $attr->once,
                        onUnregisterDo: $attr->onUnregisterDo,
                        onUnregisterDoParams: $this->hookPlanProvider->buildCallablePlan($attr->onUnregisterDo instanceof \Closure ? $attr->onUnregisterDo : null),
                        debugDeferredRegistration: $attr->debugDeferredRegistration,
                        debugDeferredRegistrationParams: $this->hookPlanProvider->buildCallablePlan($attr->debugDeferredRegistration),
                        debugLiveRegistration: $attr->debugLiveRegistration,
                        debugLiveRegistrationParams: $this->hookPlanProvider->buildCallablePlan($attr->debugLiveRegistration),
                        debugLiveUnregistration: $attr->debugLiveUnregistration,
                        debugLiveUnregistrationParams: $this->hookPlanProvider->buildCallablePlan($attr->debugLiveUnregistration),
                    );
                });
            } catch (\RuntimeException $e) {
                $this->logger->error(__METHOD__, 'Error scanning hooks for class ' . $className . ': ', [
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->logger->flush();
                throw $e;
            }
        }

        return $registrations;
    }

    public function deleteCache(): bool
    {
        $targetFile = $this->cacheLocation;

        if (empty($targetFile)) {
            $this->logger->error(__METHOD__, 'Delete failed: No target file path provided.');
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
        $registrations = $this->cachedHookRegistrations ?? $this->cachedHookRegistrations = $this->performHookRegistrationScan();

        if (empty($targetFile)) {
            $this->logger->error(__METHOD__, 'Export failed: No target file path provided.');
            return false;
        }

        $directory = dirname($targetFile);

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                $this->logger->error(__METHOD__, "Failed to create directory: {$directory}");
                return false;
            }
        }

        if (!is_writable($directory)) {
            $this->logger->error(__METHOD__, "Directory is not writable: {$directory}");
            return false;
        }

        $exportedArray = VarExporter::export(
            $registrations,
            VarExporter::CLOSURE_SNAPSHOT_USES | VarExporter::ADD_RETURN | VarExporter::ADD_TYPE_HINTS
        );

        $phpContent = "<?php\n\ndeclare(strict_types=1);\n\n/**\n * Auto-generated WP Hooks Cache\n * Generated at: " . date('Y-m-d H:i:s') . "\n */\n\n" . $exportedArray;
        $phpContent = \str_replace('__CLASS__', 'self::class', $phpContent);
        $result = file_put_contents($targetFile, $phpContent, LOCK_EX);
        return $result !== false;
    }
}
