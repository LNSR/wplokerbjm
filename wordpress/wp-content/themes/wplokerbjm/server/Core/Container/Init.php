<?php

namespace WPLokerBJM\Core\Container;

use WPLokerBJM\Shared\Log\Logger;
use Psr\Container\ContainerInterface;

/**
 * Initializes core services in the wplokerbjm theme by registering WordPress hooks.
 *
 * This class is responsible for bootstrapping the theme's services.
 * It automatically discovers and initializes these services,
 * registering hooks from #[Action] and #[Filter] attributes on their methods.
 * Supports both static and instance methods.
 *
 * ## Constructor
 * Accepts an array of service objects and an array of hook registrations from attributes.
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
     * @var array<int,object> $services
     * Array of service objects to initialize.
     * Injected via constructor and stored as readonly for immutability.
     */
    public function __construct(private readonly array $services = [], private readonly array $hookRegistrations = [], private readonly ?ContainerInterface $container = null)
    {
    }

    /**
     * Initialize all services by registering WordPress hooks from attributes.
     *
     * Creates a map of services by class name, then loops through hook registrations
     * from attributes, finds the corresponding service instance for instance methods,
     * and registers the hook. For static methods, uses the class name directly.
     *
     * @return void
     */
    public function initialize(): void
    {
        // Create a map of services by class name for quick lookup
        $serviceMap = [];
        foreach ($this->services as $listService) {
            $serviceMap[get_class($listService)] = $listService;
        }

        $classMethodType = function ($reg) use ($serviceMap): ?array {
            if ($reg['is_static']) {
                // For static methods, use the class name directly
                $callable = [$reg['class'], $reg['method']];
            } else {
                // For instance methods, find the service instance
                $service = $serviceMap[$reg['class']] ?? null;
                if (!$service && $this->container && $this->container->has($reg['class'])) {
                    try {
                        $service = $this->container->get($reg['class']);
                    } catch (\Exception $e) {
                        Logger::warning('Init', 'Failed to get service from container for class ' . $reg['class'] . ': ' . $e->getMessage());
                    }
                }
                if (!$service) {
                    Logger::warning('Init', 'Service not found for class ' . $reg['class']);
                    return null;
                }

                $callable = [$service, $reg['method']];
            }

            return $callable;
        };

        // Register hooks from attributes
        foreach ($this->hookRegistrations as $reg) {
            $callable = $classMethodType($reg);
            try {
                if ($reg['type'] === 'action') {
                    add_action($reg['hook'], $callable, $reg['priority'], $reg['accepted_args']);
                } elseif ($reg['type'] === 'filter') {
                    add_filter($reg['hook'], $callable, $reg['priority'], $reg['accepted_args']);
                }
            } catch (\Exception $e) {
                Logger::error('Init', 'Error registering hook ' . $reg['hook'] . ' for ' . $reg['class'] . '::' . $reg['method'] . ': ' . $e->getMessage());
            }
        }
    }
}