<?php
declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks;

use HookRegistration;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HookScannerTrait;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Bootstrap;
use WPLokerBJM\Shared\Log\Logger;
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
 * @see \WPLokerBJM\Bootstrap
 *
 * @phpstan-type HookRegistration array{class: class-string, method: string, type: 'action'|'filter', hook: string, priority: int, accepted_args: int, defer: bool, target: 'method'|'property'|'property-hook', visibility: 'public'|'protected'|'private'}
 */
class WPHooksScanner
{
    use HookScannerTrait;

    /** @var array<int, HookRegistration>|null */
    private ?array $cachedHookRegistrations = null;

    public function __construct(private string $namespace = 'WPLokerBJM')
    {
        $this->namespace = trim($namespace, '\\');
    }

    /**
     * Get hook registrations from #[Action] and #[Filter] attributes.
     *
     * Scans instance methods and properties for hook attributes across all PHP files
     * in the base directory. Results are cached in-memory for the request.
     *
     * @return HookRegistration[]
     */
    public function getHookRegistrations(): array
    {
        if ($this->cachedHookRegistrations !== null) {
            return $this->cachedHookRegistrations;
        }

        $registrations = $this->performHookRegistrationScan();
        $this->cachedHookRegistrations = $registrations;
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

        foreach (Bootstrap::getRobotLoader()->getIndexedClasses() as $className => $file) {
            if (!str_starts_with($className, $namespacePrefix) || !class_exists($className)) {
                continue;
            }
            try {
                $reflection = new ReflectionClass($className);
                $methodCb = static function (ReflectionMethod $method, Action|Filter $attr, string $visibility, string $type) use ($className, &$registrations): void {
                    $registrations[] = [
                        'class' => $className,
                        'method' => $method->getName(),
                        'type' => $type,
                        'hook' => $attr->hook,
                        'priority' => $attr->priority,
                        'accepted_args' => $attr->acceptedArgs,
                        'defer' => $attr->defer,
                        'target' => 'method',
                        'visibility' => $visibility,
                    ];
                };
                $this->scanMethodHooks($reflection, $methodCb);

                $propertyCb = static function (ReflectionProperty $property, Action|Filter $attr, string $visibility, string $type, string $target) use ($className, &$registrations): void {
                    $registrations[] = [
                        'class' => $className,
                        'method' => $property->getName(),
                        'type' => $type,
                        'hook' => $attr->hook,
                        'priority' => $attr->priority,
                        'accepted_args' => $attr->acceptedArgs,
                        'defer' => $attr->defer,
                        'target' => $target,
                        'visibility' => $visibility,
                    ];
                };

                $this->scanPropertyHooks($reflection, $propertyCb);
            } catch (\Exception $e) {
                Logger::error('WPhooksScanner', 'Error scanning hooks for class ' . $className . ': ' . $e->getMessage());
            }
        }

        return $registrations;
    }
}