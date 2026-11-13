<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests;

use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\ModuleClassHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\Resolver\ContainerRegistryHookTargetResolver;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Covers the opt-in anonymous-hook contract: ModuleClassHookMetadata
 * captures parent class + property at construction so ContainerRegistryHookTargetResolver can
 * resolve the hook target without walking the call stack.
 */
class ModuleClassHookMetadataTest extends WplokerbjmTestCase
{
    public function testConstructorCapturesParentClassAndProperty(): void
    {
        $hook = new class ('App\\ParentService', 'onFilter') extends ModuleClassHookMetadata {
            public function __invoke(): bool
            {
                return false;
            }
        };

        $this->assertSame('App\\ParentService', $hook->parentClassString);
        $this->assertSame('onFilter', $hook->parentProperty);
    }

    public function testConstructorAcceptsObjectParent(): void
    {
        $parent = new \stdClass();

        $hook = new class ($parent, 'onFilter') extends ModuleClassHookMetadata {
            public function __invoke(): bool
            {
                return false;
            }
        };

        // parentClass is encapsulated (private); getParentClassString() normalizes it.
        $this->assertSame(\stdClass::class, $hook->parentClassString);
    }

    public function testResolverNormalizesObjectParentViaGetClass(): void
    {
        $resolver = new ContainerRegistryHookTargetResolver();
        $parent = new \stdClass();

        $hook = new class ($parent, 'onFilter') extends ModuleClassHookMetadata {
            public function __invoke(): bool
            {
                return false;
            }
        };

        $this->assertSame([\stdClass::class, 'onFilter'], $resolver->resolve($hook));
    }

    public function testResolverUsesCapturedParentWithoutBacktrace(): void
    {
        $resolver = new ContainerRegistryHookTargetResolver();

        $hook = new class ('App\\ParentService', 'onFilter') extends ModuleClassHookMetadata {
            public function __invoke(): bool
            {
                return false;
            }
        };

        // The captured metadata lets the resolver return the parent target
        // directly — no stack walking, no property reflection.
        $this->assertSame(['App\\ParentService', 'onFilter'], $resolver->resolve($hook));
    }

    public function testResolverCachesAnonHookResolution(): void
    {
        $resolver = new ContainerRegistryHookTargetResolver();

        $hook = new class ('App\\ParentService', 'onFilter') extends ModuleClassHookMetadata {
            public function __invoke(): bool
            {
                return false;
            }
        };

        $first = $resolver->resolve($hook);
        $second = $resolver->resolve($hook);

        $this->assertSame(['App\\ParentService', 'onFilter'], $first);
        $this->assertSame($first, $second);
    }
}
