<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests\Registration;

use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\ModuleHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\Resolver\ContainerRegistryHookTargetResolver;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Covers the opt-in anonymous-hook contract: ModuleHookMetadata
 * captures parent class + property at construction so ContainerRegistryHookTargetResolver can
 * resolve the hook target without walking the call stack.
 */
class ModuleHookMetadataTest extends WplokerbjmTestCase
{
    public function testConstructorCapturesParentClassAndProperty(): void
    {
        $hook = new class ('App\\ParentService', 'onFilter') extends ModuleHookMetadata {
            public function __invoke(): bool
            {
                return false;
            }
        };

        $this->assertSame('App\\ParentService', $hook->outerClassString);
        $this->assertSame('onFilter', $hook->identifier);
    }

    public function testConstructorAcceptsObjectParent(): void
    {
        $parent = new \stdClass();

        $hook = new class ($parent, 'onFilter') extends ModuleHookMetadata {
            public function __invoke(): bool
            {
                return false;
            }
        };

        // parentClass is encapsulated (private); getParentClassString() normalizes it.
        $this->assertSame(\stdClass::class, $hook->outerClassString);
    }

    public function testResolverNormalizesObjectParentViaGetClass(): void
    {
        $resolver = new ContainerRegistryHookTargetResolver();
        $parent = new \stdClass();

        $hook = new class ($parent, 'onFilter') extends ModuleHookMetadata {
            public function __invoke(): bool
            {
                return false;
            }
        };

        $this->assertSame([$hook::class, '__invoke'], $resolver->resolve($hook));
    }
}
