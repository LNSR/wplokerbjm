<?php

namespace WPLokerBJM\Core\Container;

use WPLokerBJM\Shared\Log\Logger;
use Psr\Container\ContainerInterface;

/**
 * Initializes core services in the wplokerbjm theme by registering WordPress hooks.
 *
 * This class is responsible for bootstrapping the theme's services.
 * It automatically discovers and initializes these services,
 * registering hooks from #[Action] and #[Filter] attributes on their **instance**
 * methods. (Static-method hooks are not supported — the container resolves
 * the owning service, which requires an instance.)
 *
 * ## Lazy hook resolution
 *
 * Each hook is registered as a closure that defers container resolution to
 * the moment WordPress actually fires the hook. The underlying service is
 * therefore NOT instantiated during `initialize()` — only when the hook
 * runs. Combined with the container's `->lazy()` autowire definitions, this
 * means a service is constructed at most once per request, the first time
 * any of its hooks fire.
 *
 * ## Usage
 * Call the `initialize()` method (typically in functions.php or a bootstrap file)
 * to register hooks from attributes. This centralizes hook registration,
 * making it easier to manage and debug WordPress integrations across the theme.
 *
 * @see \WPLokerBJM\Core\Container\Definitions\Core
 * @see \WPLokerBJM\Core\Container\Attributes\Action
 * @see \WPLokerBJM\Core\Container\Attributes\Filter
 */
class Init
{
    /**
     * @var array<int,array<string,mixed>> $hookRegistrations
     * @var ContainerInterface $container 
     * Array of hook registration data produced by the AutowireScanner.
     */
    public function __construct(
        private readonly array $hookRegistrations = [],
        private readonly ?ContainerInterface $container = null
    ) {
    }

    private bool $initialized = false;

    /**
     * Register all WordPress hooks from attributes.
     *
     * Every hook is wrapped in a closure that defers container resolution
     * until WordPress fires the hook. The container is queried with `has()`
     * (cheap) to validate the service exists before registering — this
     * preserves the original "skip misconfigured hook" behavior without paying
     * the cost of instantiating the service.
     *
     * @return void
     */
    public function initialize(): void
    {
        // Prevent multiple initializations
        if ($this->initialized) {
            return;
        }

        foreach ($this->hookRegistrations as $reg) {
            $callable = $this->buildCallable($reg);
            if ($callable === null) {
                continue;
            }

            try {
                if ($reg['type'] === 'action') {
                    add_action($reg['hook'], $callable, $reg['priority'], $reg['accepted_args']);
                } elseif ($reg['type'] === 'filter') {
                    add_filter($reg['hook'], $callable, $reg['priority'], $reg['accepted_args']);
                }
            } catch (\Exception $e) {
                Logger::error(
                    'Init',
                    'Error registering hook ' . $reg['hook']
                    . ' for ' . $reg['class'] . '::' . $reg['method']
                    . ': ' . $e->getMessage()
                );
            }
        }

        $this->initialized = true;
    }

    /**
     * Build a closure that defers container resolution to hook-fire time.
     *
     * Returns `null` when the hook is invalid (e.g. the owning class is not
     * registered in the container); the caller is expected to skip registration
     * and emit a warning.
     *
     * @param array<string,mixed> $reg
     * @return callable|null
     */
    private function buildCallable(array $reg): callable|null
    {
        if (!$this->container || !$this->container->has($reg['class'])) {
            Logger::warning(
                'Init',
                'Skipping hook ' . $reg['hook'] . ' — class not in container: ' . $reg['class']
            );
            return null;
        }

        $class = $reg['class'];
        $method = $reg['method'];

        return fn(...$args) => $this->container->get($class)->{$method}(...$args);
    }
}
