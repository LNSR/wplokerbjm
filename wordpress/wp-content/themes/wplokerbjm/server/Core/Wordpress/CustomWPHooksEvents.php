<?php

namespace WPLokerBJM\Core\Wordpress;

use WPLokerBJM\Core\Container\Attributes\Action;
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\Abstract\AsChildClass;
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\DependencyInjector;

/**
 * @suppress PHP7104
 */
class DependencyInjectorEvent
{
    public const INJECT_ON = 'wplokerbjm_inject_on';
    public function __construct(private readonly DependencyInjector $injector) {}

    #[Action(self::INJECT_ON, 10, 1)]
    protected function injectOn(AsChildClass $target): void
    {
        $this->injector->injectOn($target);
    }
}
