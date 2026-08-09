<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\AnonClassHookPropertyAbstract;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\HookTargetResolver;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Covers the opt-in anonymous-hook contract: AnonClassHookPropertyAbstract
 * captures parent class + property at construction so HookTargetResolver can
 * resolve the hook target without walking the call stack.
 */
class AnonClassHookPropertyAbstractTest extends WplokerbjmTestCase
{
    public function testConstructorCapturesParentClassAndProperty(): void
    {
        $hook = new class ('App\\ParentService', 'onFilter') extends AnonClassHookPropertyAbstract {
            public function __invoke(): bool
            {
                return false;
            }
        };

        $this->assertSame('App\\ParentService', $hook->parentClass);
        $this->assertSame('onFilter', $hook->parentProperty);
    }

    public function testResolverUsesCapturedParentWithoutBacktrace(): void
    {
        $resolver = new HookTargetResolver();

        $hook = new class ('App\\ParentService', 'onFilter') extends AnonClassHookPropertyAbstract {
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

        $hook = new class ('App\\ParentService', 'onFilter') extends AnonClassHookPropertyAbstract {
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
