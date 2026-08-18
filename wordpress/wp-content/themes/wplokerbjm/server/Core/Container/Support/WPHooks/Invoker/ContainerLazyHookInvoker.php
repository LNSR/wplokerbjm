<?php
namespace WPLokerBJM\Core\Container\Support\WPHooks\Invoker;

use Psr\Container\ContainerInterface;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\{WPHookPlanProvider};
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HookInvokerTrait;

/**
 * Common lifecycle & gate evaluation logic for lazy container hook handlers.
 */
trait ContainerLazyHookInvokerTrait
{
    use HookInvokerTrait;

    public readonly string $label;

    /**
     * Common __invoke execution pipeline wrapping gate checks, invocation, and error handling.
     * 
     * @param \Closure(object $instance, mixed ...$args): mixed $executor
     */
    private function executeHook(\Closure $executor, array $args): mixed
    {
        $hookArgs = $this->buildHookArgs($args);

        if ($this->once) {
            if ($this->consumed) {
                return $this->filterPassthrough($args);
            }
            $this->consumed = true;
        }

        try {
            try {
                $gatePassed = $this->planProvider->evaluateExecuteIf(
                    $this->executeIf,
                    $this->executeIfParams,
                    $this->container,
                    $this->label,
                    $this->class,
                    $hookArgs
                );
            } catch (\Throwable $e) {
                if (!$this->once) {
                    throw $e;
                }
                Logger::warning(
                    static::class,
                    'executeIf gate unresolvable for once-hook ' . $this->label . ' — treated as pass.'
                );
                $gatePassed = true;
            }

            if (!$gatePassed) {
                Logger::warning(
                    static::class,
                    'Skipping hook ' . $this->label . ' — executeIf gate returned false.'
                );
                $this->consumeOnce();
                return $this->filterPassthrough($args);
            }

            $instance = $this->container->get($this->class);
            $result = $executor($instance, ...$args);

            SharedUtils::isDevelopment() && Logger::debug(static::class, "Hook invoke {$this->label}");
            $this->consumeOnce();
            return $result;

        } catch (\Throwable $e) {
            $this->consumeOnce();
            Logger::error(static::class, "Error invoking hook {$this->label}: " . $e->getMessage());
            return $this->filterPassthrough($args);
        }
    }
}

/**
 * Lazy hook handler — invocable object that defers container resolution to hook-fire time.
 *
 * Unlike closures, this is a named class that appears in debugging tools
 * WordPress can match it by instance identity (spl_object_hash) for
 * remove_action()/remove_filter().
 */
final class ContainerLazyHookHandler
{
    use ContainerLazyHookInvokerTrait;

    /** @var \Closure|null */
    private ?\Closure $invoker = null;

    /** Plan provider used for condition gates and hook-name resolution. */
    private readonly WPHookPlanProvider $planProvider;
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly string $class,
        private readonly string $method,
        private readonly string $visibility = 'public',
        private readonly string $type = 'action',
        private readonly ?\Closure $executeIf = null,
        private readonly array $executeIfParams = [],
        private readonly ?WPHookPlanProvider $hookPlanProvider = null,
        private readonly array $hookArgNames = [],
        private readonly bool $once = false,
    ) {
        $this->label = $this->class . '::' . $this->method;
        $this->planProvider = $hookPlanProvider ?? new WPHookPlanProvider();

        if ($this->visibility !== 'public') {
            $this->invoker = \Closure::bind(
                self::$templateClosure ??= static fn(object $instance, string $methodName, mixed ...$args): mixed => $instance->{$methodName}(...$args),
                null,
                $this->class,
            );
        }

    }

    public function __invoke(mixed ...$args): mixed
    {
        return $this->executeHook(function (object $instance, mixed ...$args) {
            return $this->visibility === 'public'
                ? $instance->{$this->method}(...$args)
                : ($this->invoker)($instance, $this->method, ...$args);
        }, $args);
    }
}

/**
 * Lazy hook handler for property closures.
 *
 * Reads the closure from a property at hook-fire time, using a pre-bound
 * Closure when the property is protected or private.
 *
 * Unlike raw closures, this is a named class that appears in debugging
 * tools and WordPress can match it by instance identity (spl_object_hash)
 * for remove_action()/remove_filter().
 */
final class ContainerLazyPropertyHookHandler
{
    use ContainerLazyHookInvokerTrait;

    /** @var \Closure(object, string):mixed|null */
    private ?\Closure $reader = null;

    /** Plan provider used for condition gates and hook-name resolution. */
    private readonly WPHookPlanProvider $planProvider;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly string $class,
        private readonly string $property,
        private readonly string $visibility = 'public',
        private readonly string $type = 'action',
        private readonly ?\Closure $executeIf = null,
        private readonly array $executeIfParams = [],
        private readonly ?WPHookPlanProvider $hookPlanProvider = null,
        private readonly array $hookArgNames = [],
        private readonly bool $once = false,
    ) {
        $this->label = $this->class . '::$' . $this->property;
        $this->planProvider = $hookPlanProvider ?? new WPHookPlanProvider();

        if ($this->visibility !== 'public') {
            $this->reader = \Closure::bind(
                self::$templateClosure ??= static fn(object $instance, string $propertyName): mixed => $instance->{$propertyName},
                null,
                $this->class,
            );
        }
    }

    public function __invoke(mixed ...$args): mixed
    {
        return $this->executeHook(function (object $instance, mixed ...$args) {
            $callable = $this->visibility === 'public'
                ? $instance->{$this->property}
                : ($this->reader)($instance, $this->property);

            $isInvokable = $callable instanceof \Closure || (is_object($callable) && method_exists($callable, '__invoke'));

            if (!$isInvokable || !is_callable($callable)) {
                throw new \RuntimeException("Property {$this->label} is not a valid callable or invokable object.");
            }

            return $callable(...$args);
        }, $args);
    }
}