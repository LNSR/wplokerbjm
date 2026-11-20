<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Registry;

use Brick\VarExporter\VarExporter;
use WPLokerBJM\Core\Container\Support\WPHooks\Registrations\HookKey;
use WPLokerBJM\Core\Container\Support\WPHooks\Janitor\QueuedHooksCleaner;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\ModuleClassHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\{DeferredHooksTrait, HookScannerTrait};
use WPLokerBJM\Core\Container\Support\WPHooks\Indexers\EntriesIndexer;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\Resolver\InstanceRegistryRuntimeHookResolver;
use WPLokerBJM\Core\Container\Support\WPHooks\Registrations\InstanceHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager\{InstanceRegistryDeferredHookManager, Handlers\DeferredHookEntryHandler};

/**
 * File-backed metadata cache for runtime-registered hook sites.
 *
 * Anonymous hook classes extending ModuleClassHookMetadata carry a
 * stable (parentClass, parentProperty) pair that uniquely identifies the
 * property-hook site. The reflected metadata (resolved hook names, plans,
 * hook-arg names) for that site is accumulated in an in-memory buffer during
 * the request and flushed atomically to WPHooksInstanceObjectCache.php, so repeated
 * registerHooksOn() calls skip all reflection.
 *
 * Per-instance state (owner instance, WeakReference, remove callbacks) is
 * intentionally NOT cached — only scan-derived metadata.
 * 
 * @phpstan-import-type InstanceHookMetadataData from InstanceHookMetadata
 * @template TClass of class-string
 * @phpstan-type TCache array<TClass, array<property-string<TClass>&property-hook-string<TClass>, list<InstanceHookMetadata>&list<InstanceHookMetadataData>>>
 * @internal
 */
class WPHooksInstanceObjectCache
{

    public private(set) bool $isDirty = false;

    /**
     * @param string|null $file file path cache to configure
     */
    public function __construct(private ?string $file = null)
    {
        $file !== null && $this->cacheState->loadCache();
    }

    public function __destruct()
    {
        if (!$this->isDirty) return;
        $this->flush();
    }

    /** 
     * @var class@anonymous::class
     */
    protected ?object $cacheState {
        get {
            return $this->cacheState ??= new class($this->file) {
                public private(set) bool $alreadyLoaded = false;

                /** @var TCache */
                public array $bufferRuntime = [];
                /** @var TCache */
                public array $loadedCache = [];

                public function __construct(private readonly ?string $file) {}

                public function loadCache(): void
                {
                    if ($this->file === null || !is_readable($this->file)) {
                        return;
                    }
                    $this->loadedCache = $this->mapCache(require $this->file, 'fromArray');
                    $this->alreadyLoaded = true;
                }

                /**
                 * @param TCache $cache
                 * @return TCache
                 */
                public function extractCacheToArray(array $cache): array
                {
                    return $this->mapCache($cache, 'toArray');
                }

                /**
                 * @template T
                 * @param T $c
                 * @param 'toArray'|'fromArray' $operation
                 * @return TCache
                 */
                private function mapCache(array $c, string $operation): array
                {
                    return \array_map(
                        // lvl1: parentClass
                        static fn(array $sites): array => \array_map(
                            // lvl2: parentProperty
                            static fn(array $entries): array => \array_map(
                                // lvl3: InstanceHookMetadata
                                match ($operation) {
                                    'toArray' => static fn(
                                        /** @param InstanceHookMetadata|InstanceHookMetadataData $entry */
                                        InstanceHookMetadata|array $entry
                                    ): array => $entry instanceof InstanceHookMetadata ? $entry->toArray() : $entry,
                                    'fromArray' => static fn(
                                        /** @param InstanceHookMetadata|InstanceHookMetadataData $entry */
                                        InstanceHookMetadata|array $entry
                                    ): InstanceHookMetadata => $entry instanceof InstanceHookMetadata ? $entry : InstanceHookMetadata::fromArray($entry),
                                },
                                $entries,
                            ),
                            $sites,
                        ),
                        $c,
                    );
                }
            };
        }
    }

    /**
     * Clear all runtime hooks cache.
     */
    public function clearCacheFile(): void
    {
        if (!empty($this->file) && file_exists($this->file)) {
            try {
                unlink($this->file);
            } catch (\Throwable $th) {
                Logger::Error(__METHOD__, 'Failed to clear cache file: ' . $th->getMessage());
            }
        }
    }

    /**
     * @param class-string<TClass> $parentClass
     * @param property-string<TClass>&property-hook-string<TClass> $parentProperty
     *
     * @return list<InstanceHookMetadata>|null
     */
    public function get(string $parentClass, string $parentProperty): ?array
    {
        return $this->cacheState->loadedCache[$parentClass][$parentProperty]
            ?? $this->cacheState->bufferRuntime[$parentClass][$parentProperty]
            ?? null;
    }

    /**
     * @param class-string<TClass> $parentClass
     * @param property-string<TClass>&property-hook-string<TClass> $parentProperty
     * @param list<InstanceHookMetadata> $metadata
     */
    public function set(string $parentClass, string $parentProperty, array $metadata): void
    {
        $this->cacheState->bufferRuntime[$parentClass][$parentProperty] = $metadata;
        $this->isDirty = true;
    }

    /**
     * @return void
     */
    public function flush(): void
    {
        if ($this->file === null || $this->cacheState->bufferRuntime === []) {
            return;
        }

        $allCache = $this->cacheState->bufferRuntime;
        if (is_file($this->file)) {
            $allCache = array_replace_recursive($this->cacheState->loadedCache, $this->cacheState->bufferRuntime);
        }

        $directory = dirname($this->file);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $exported = VarExporter::export(
            $allCache,
            VarExporter::CLOSURE_SNAPSHOT_USES | VarExporter::ADD_RETURN | VarExporter::ADD_TYPE_HINTS
        );

        $content = "<?php\n\ndeclare(strict_types=1);\n\n/**\n * Auto-generated WP Hooks Runtime Cache\n * Generated at: " . date('Y-m-d H:i:s') . "\n */\n\n" . $exported;
        $content = \str_replace('__CLASS__', 'self::class', $content);
        $tmp = $this->file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $content, LOCK_EX) !== false) {
            rename($tmp, $this->file);
        }
    }
}
