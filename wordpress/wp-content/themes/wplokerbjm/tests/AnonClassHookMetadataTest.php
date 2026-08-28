<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\AnonClassHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\HookTargetResolver;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Covers the opt-in anonymous-hook contract: AnonClassHookMetadata
 * captures parent class + property at construction so HookTargetResolver can
 * resolve the hook target without walking the call stack.
 */
class AnonClassHookMetadataTest extends WplokerbjmTestCase
{
    public function testConstructorCapturesParentClassAndProperty(): void
    {
        $hook = new class ('App\\ParentService', 'onFilter') extends AnonClassHookMetadata {
            public function __invoke(): bool
            {
                return false;
            }
        };

        $this->assertSame('App\\ParentService', $hook->getParentClass());
        $this->assertSame('onFilter', $hook->parentProperty);
    }

    public function testConstructorAcceptsObjectParent(): void
    {
        $parent = new \stdClass();

        $hook = new class ($parent, 'onFilter') extends AnonClassHookMetadata {
            public function __invoke(): bool
            {
                return false;
            }
        };

        // parentClass is encapsulated (private); getParentClass() normalizes it.
        $this->assertSame(\stdClass::class, $hook->getParentClass());
    }

    public function testResolverNormalizesObjectParentViaGetClass(): void
    {
        $resolver = new HookTargetResolver();
        $parent = new \stdClass();

        $hook = new class ($parent, 'onFilter') extends AnonClassHookMetadata {
            public function __invoke(): bool
            {
                return false;
            }
        };

        $this->assertSame([\stdClass::class, 'onFilter'], $resolver->resolve($hook));
    }

    public function testResolverUsesCapturedParentWithoutBacktrace(): void
    {
        $resolver = new HookTargetResolver();

        $hook = new class ('App\\ParentService', 'onFilter') extends AnonClassHookMetadata {
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
        $resolver = new HookTargetResolver();

        $hook = new class ('App\\ParentService', 'onFilter') extends AnonClassHookMetadata {
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
