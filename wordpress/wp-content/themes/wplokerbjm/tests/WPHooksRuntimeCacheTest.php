<?php
declare(strict_types=1);

namespace WPLokerBJM\Tests;

use WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksRuntimeCache;
use WPLokerBJM\Core\Container\Support\WPHooks\RuntimeHookMetadata;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

class WPHooksRuntimeCacheTest extends WplokerbjmTestCase
{
    private string $dirPath;
    private string $file;

    protected function setUp(): void
    {
        $this->dirPath = __DIR__ . '/cache-dir/';
        $this->file = $this->dirPath . 'WPHooksRuntimeCache.php';

        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_file($this->file)) {
            unlink($this->file);
        }

        if (is_dir($this->dirPath)) {
            rmdir($this->dirPath);
        }
        if (is_dir($this->dirPath)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->dirPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($files as $fileInfo) {
                $todo = $fileInfo->isDir() ? 'rmdir' : 'unlink';
                $todo($fileInfo->getRealPath());
            }

            rmdir($this->dirPath);
        }
    }

    private function sampleMetadata(): RuntimeHookMetadata
    {
        return new RuntimeHookMetadata(
            hook: 'my_hook',
            type: 'action',
            priority: 10,
            acceptedArgs: 1,
            once: false,
            executeIf: static fn(): bool => true,
            executeIfParams: [],
            registerIf: null,
            registerIfParams: [],
            deferRegisterUntilHook: null,
            deferRegisterUntilHookParams: [],
            hookArgNames: ['c'],
            target: 'method',
            targetName: 'onMyHook',
            visibility: 'public',
        );
    }

    private function writeStaleArrayFormatFile(): void
    {
        if (!is_dir($this->dirPath)) {
            mkdir($this->dirPath, 0755, true);
        }

        // Simulates a cache file written by the pre-DTO version: raw arrays.
        file_put_contents($this->file, <<<'PHP'
            <?php

            declare(strict_types=1);

            /**
             * Auto-generated WP Hooks Runtime Cache
             * Generated at: 2026-08-13 10:00:00
             */

            return [
                'Service' => [
                    'prop' => [
                        [
                            'hook' => 'my_hook',
                            'type' => 'action',
                            'priority' => 10,
                            'acceptedArgs' => 1,
                            'once' => false,
                            'executeIf' => static function (): bool {
                                return true;
                            },
                            'executeIfParams' => [],
                            'registerIf' => null,
                            'registerIfParams' => [],
                            'deferRegisterUntilHook' => null,
                            'deferRegisterUntilHookParams' => [],
                            'hookArgNames' => ['c'],
                            'target' => 'method',
                            'targetName' => 'onMyHook',
                            'visibility' => 'public',
                        ],
                    ],
                ],
            ];
            PHP);
    }

    public function testSetGetRoundTrip(): void
    {
        $cache = new WPHooksRuntimeCache($this->file);
        $meta = $this->sampleMetadata();

        $cache->set('Service', 'prop', [$meta]);

        $this->assertSame([$meta], $cache->get('Service', 'prop'));
        $this->assertNull($cache->get('Service', 'other'));
        $this->assertNull($cache->get('OtherService', 'prop'));
    }

    public function testFlushWritesFileAndLoadRoundTrips(): void
    {
        $cache = new WPHooksRuntimeCache($this->file);
        $cache->set('Service', 'prop', [$this->sampleMetadata()]);

        $cache->flush();

        $this->assertFileExists($this->file);
        $content = file_get_contents($this->file);
        $this->assertIsString($content);
        $this->assertStringContainsString('<?php', $content);
        $this->assertStringContainsString('return [', $content);

        // A fresh instance loads the flushed file from the configured directory and hydrates DTOs again.
        $fresh = new WPHooksRuntimeCache($this->file);
        $entries = $fresh->get('Service', 'prop');
        $this->assertIsArray($entries);
        $this->assertCount(1, $entries);
        $this->assertInstanceOf(RuntimeHookMetadata::class, $entries[0]);
        $this->assertSame('my_hook', $entries[0]->hook);
        $this->assertSame('action', $entries[0]->type);
        $this->assertSame('onMyHook', $entries[0]->targetName);
        $this->assertSame(['c'], $entries[0]->hookArgNames);
        $this->assertTrue(($entries[0]->executeIf)());
    }

    public function testGetPrecedenceLoadedOverBuffer(): void
    {
        $cache = new WPHooksRuntimeCache($this->file);
        $cache->set('Service', 'prop', [$this->sampleMetadata()]);
        $cache->flush();

        $second = new WPHooksRuntimeCache($this->file);
        $bufferMeta = new RuntimeHookMetadata(
            hook: 'buffered_hook',
            type: 'filter',
            priority: 20,
            acceptedArgs: 2,
            once: true,
            executeIf: null,
            executeIfParams: [],
            registerIf: null,
            registerIfParams: [],
            deferRegisterUntilHook: null,
            deferRegisterUntilHookParams: [],
            hookArgNames: [],
            target: 'property',
            targetName: 'prop',
            visibility: 'private',
        );
        $second->set('Service', 'prop', [$bufferMeta]);

        // loaded (file) wins over buffer (runtime) for the same site.
        $entries = $second->get('Service', 'prop');
        $this->assertIsArray($entries);
        $this->assertCount(1, $entries);
        $this->assertSame('my_hook', $entries[0]->hook);
    }

    public function testFlushSurvivesStaleArrayFormatCacheFile(): void
    {
        $this->writeStaleArrayFormatFile();

        $cache = new WPHooksRuntimeCache($this->file); // loads stale arrays from directory

        $cache->flush(); // must not throw TypeError

        $this->assertFileExists($this->file);

        // The rewritten file still round-trips into DTOs.
        $fresh = new WPHooksRuntimeCache($this->file);
        $entries = $fresh->get('Service', 'prop');
        $this->assertIsArray($entries);
        $this->assertCount(1, $entries);
        $this->assertInstanceOf(RuntimeHookMetadata::class, $entries[0]);
        $this->assertSame('my_hook', $entries[0]->hook);
        $this->assertSame('onMyHook', $entries[0]->targetName);
        $this->assertTrue(($entries[0]->executeIf)());
    }

    public function testClearCacheFile(): void
    {
        $cache = new WPHooksRuntimeCache($this->file);
        $cache->set('Service', 'prop', [$this->sampleMetadata()]);
        $cache->flush();
        $this->assertFileExists($this->file);

        $cache->clearCacheFile();

        $this->assertFileDoesNotExist($this->file);
    }

    public function testFlushIsNoOpWithoutFile(): void
    {
        $cache = new WPHooksRuntimeCache(null);
        $cache->set('Service', 'prop', [$this->sampleMetadata()]);

        $cache->flush(); // must not write anything
        $this->assertFileDoesNotExist($this->file);
    }

    public function testFlushCreatesMissingDirectory(): void
    {
        $cacheDir = __DIR__ . '/cache-dir';
        $nestedDir = $cacheDir . '/' . bin2hex(random_bytes(4)) . '/nested/';
        $file = $nestedDir . 'WPHooksRuntimeCache.php';
        $cache = new WPHooksRuntimeCache($file);
        $cache->set('Service', 'prop', [$this->sampleMetadata()]);

        try {
            $cache->flush();
            $this->assertFileExists($file);
        } finally {
            if (is_dir($cacheDir)) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($cacheDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST,
                );

                foreach ($files as $fileInfo) {
                    $todo = $fileInfo->isDir() ? 'rmdir' : 'unlink';
                    $todo($fileInfo->getRealPath());
                }

                rmdir($cacheDir);
            }
        }
    }
}