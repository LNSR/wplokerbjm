<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use Closure;
use DI\Container;
use DI\ContainerBuilder;
use InvalidArgumentException;
use WPLokerBJM\Tests\Support\Fixtures\OnceActionService;
use WPLokerBJM\Tests\Support\Fixtures\OnceFilterService;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Test suite for wildcard (pattern) activate/unregister of hook registrations.
 *
 * Covers the settled semantics:
 * - Hook-name patterns are trailing-asterisk prefixes ('mail_*'), validated by
 *   a shared matcher (literal prefix >= 2 chars, exactly one trailing '*').
 * - Tag patterns match when ANY resolved tag matches ANY pattern (union of
 *   families) — "each entry in the pattern array is its own family to wipe".
 * - Unregistration only ever touches our own registered handlers.
 */
class PatternHooksTest extends WplokerbjmTestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        // Fresh, isolated container per test — only the once fixtures.
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->useAttributes(false);
        $builder->addDefinitions([
            OnceActionService::class => \DI\autowire(),
            OnceFilterService::class => \DI\autowire(),
        ]);
        $this->container = $builder->build();

        OnceActionService::reset();
        OnceFilterService::reset();
    }

    public function testActivateDeferredByHookPatternActivatesOnlyMatchingHooks(): void
    {
        $registrations = [
            $this->action(OnceActionService::class, 'onOnceAction', 'mail_welcome', deferRegister: true),
            $this->action(OnceActionService::class, 'onOnceAction', 'mail_digest', deferRegister: true),
            $this->action(OnceActionService::class, 'onOnceAction', 'other_hook', deferRegister: true),
        ];

        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        $this->assertNull($this->findRegisteredHook('action', 'mail_welcome'));
        $this->assertNull($this->findRegisteredHook('action', 'mail_digest'));
        $this->assertNull($this->findRegisteredHook('action', 'other_hook'));

        $activated = $registry->activateDeferredByHookPattern('mail_*');

        $this->assertSame(2, $activated);
        $this->assertNotNull($this->findRegisteredHook('action', 'mail_welcome'));
        $this->assertNotNull($this->findRegisteredHook('action', 'mail_digest'));
        $this->assertNull($this->findRegisteredHook('action', 'other_hook'));

        do_action('mail_welcome', 'hello');
        $this->assertSame(['hello'], OnceActionService::$capturedValues);
    }

    public function testActivateDeferredByTagPatternMatchesAnyFamily(): void
    {
        $registrations = [
            $this->action(OnceActionService::class, 'onOnceAction', 'a_hook', tags: ['email_alerts'], deferRegister: true),
            $this->action(OnceActionService::class, 'onOnceAction', 'b_hook', tags: ['digest_daily'], deferRegister: true),
            $this->action(OnceActionService::class, 'onOnceAction', 'c_hook', tags: ['other'], deferRegister: true),
        ];

        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        $activated = $registry->activateDeferredByTagPattern(['email_*', 'digest_*']);

        $this->assertSame(2, $activated);
        $this->assertNotNull($this->findRegisteredHook('action', 'a_hook'));
        $this->assertNotNull($this->findRegisteredHook('action', 'b_hook'));
        $this->assertNull($this->findRegisteredHook('action', 'c_hook'));

        do_action('b_hook', 'digest');
        $this->assertSame(['digest'], OnceActionService::$capturedValues);
    }

    public function testUnregisterByHookPatternRemovesActiveHandlers(): void
    {
        $registrations = [
            $this->action(OnceActionService::class, 'onOnceAction', 'mail_a'),
            $this->action(OnceActionService::class, 'onOnceAction', 'mail_b'),
            $this->action(OnceActionService::class, 'onOnceAction', 'keep_hook'),
        ];

        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        $this->assertNotNull($this->findRegisteredHook('action', 'mail_a'));
        $this->assertNotNull($this->findRegisteredHook('action', 'keep_hook'));

        $registry->unregisterByHookPattern('mail_*');

        $this->assertNull($this->findRegisteredHook('action', 'mail_a'));
        $this->assertNull($this->findRegisteredHook('action', 'mail_b'));
        $this->assertNotNull($this->findRegisteredHook('action', 'keep_hook'));

        do_action('mail_a', 'x');
        do_action('keep_hook', 'k');
        $this->assertSame(['k'], OnceActionService::$capturedValues);
    }

    public function testUnregisterDeferredByHookPatternPreventsActivation(): void
    {
        $registrations = [
            $this->action(OnceActionService::class, 'onOnceAction', 'mail_a', deferRegister: true),
            $this->action(OnceActionService::class, 'onOnceAction', 'mail_b', deferRegister: true),
        ];

        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        $registry->unregisterDeferredByHookPattern('mail_*');

        // Exact-name activation can no longer surface a wiped registration.
        $registry->activateDeferredByHook('mail_a');
        $this->assertNull($this->findRegisteredHook('action', 'mail_a'));

        // Pattern activation reports zero matches.
        $this->assertSame(0, $registry->activateDeferredByHookPattern('mail_*'));

        do_action('mail_a', 'x');
        $this->assertSame([], OnceActionService::$capturedValues);
    }

    public function testUnregisterByTagPatternRemovesMatchingActiveHandlers(): void
    {
        $registrations = [
            $this->action(OnceActionService::class, 'onOnceAction', 'a_hook', tags: ['email_alerts']),
            $this->action(OnceActionService::class, 'onOnceAction', 'b_hook', tags: ['digest_daily']),
            $this->action(OnceActionService::class, 'onOnceAction', 'c_hook', tags: ['other']),
        ];

        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        $registry->unregisterByTagPattern(['email_*']);

        $this->assertNull($this->findRegisteredHook('action', 'a_hook'));
        $this->assertNotNull($this->findRegisteredHook('action', 'b_hook'));
        $this->assertNotNull($this->findRegisteredHook('action', 'c_hook'));

        do_action('a_hook', 'x');
        do_action('b_hook', 'y');
        $this->assertSame(['y'], OnceActionService::$capturedValues);
    }

    public function testUnregisterDeferredByTagPatternPreventsMatchingActivation(): void
    {
        $registrations = [
            $this->action(OnceActionService::class, 'onOnceAction', 'a_hook', tags: ['email_alerts'], deferRegister: true),
            $this->action(OnceActionService::class, 'onOnceAction', 'b_hook', tags: ['digest_daily'], deferRegister: true),
        ];

        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        $registry->unregisterDeferredByTagPattern(['email_*']);

        $registry->activateDeferredByHook('a_hook');
        $this->assertNull($this->findRegisteredHook('action', 'a_hook'));

        // The other family is untouched and still activates.
        $registry->activateDeferredByHook('b_hook');
        $this->assertNotNull($this->findRegisteredHook('action', 'b_hook'));

        do_action('b_hook', 'y');
        $this->assertSame(['y'], OnceActionService::$capturedValues);
    }

    public function testEmptyTagPatternListIsNoOp(): void
    {
        $registrations = [
            $this->action(OnceActionService::class, 'onOnceAction', 'a_hook', tags: ['email_alerts'], deferRegister: true),
        ];

        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        $this->assertSame(0, $registry->activateDeferredByTagPattern([]));
        $this->assertNull($this->findRegisteredHook('action', 'a_hook'));

        // Unregister with an empty pattern list is a no-op, not an error.
        $registry->unregisterDeferredByTagPattern([]);
        $this->assertSame(1, $registry->activateDeferredByTagPattern(['email_*']));
    }

    public function testInvalidPatternsAreRejected(): void
    {
        $registry = $this->createRegistry([], $this->container);

        // [method, args]
        $cases = [
            ['activateDeferredByHookPattern', ['*']],
            ['activateDeferredByHookPattern', ['x*']],
            ['activateDeferredByHookPattern', ['mail']],
            ['activateDeferredByHookPattern', ['ma*il']],
            ['unregisterByHookPattern', ['']],
            ['unregisterByHookPattern', ['a*']],
            ['unregisterDeferredByHookPattern', ['*']],
            ['activateDeferredByTagPattern', [['x*']]],
            ['activateDeferredByTagPattern', [['*']]],
            ['unregisterByTagPattern', [['']]],
            ['unregisterDeferredByTagPattern', [['mail']]],
        ];

        $rejected = 0;

        foreach ($cases as [$method, $args]) {
            try {
                $registry->{$method}(...$args);
            } catch (InvalidArgumentException $e) {
                $rejected++;
            }
        }

        $this->assertSame(count($cases), $rejected, 'Every invalid pattern must be rejected.');
    }

    /**
     * @param array<int, string> $tags
     *
     * @return array<string,mixed>
     */
    private function action(
        string $class,
        string $method,
        string $hook,
        int $priority = 10,
        int $acceptedArgs = 1,
        array $tags = [],
        bool $deferRegister = false,
        ?Closure $executeIf = null,
        array $executeIfParams = []
    ): array {
        return [
            'class' => $class,
            'method' => $method,
            'type' => 'action',
            'hook' => $hook,
            'priority' => $priority,
            'accepted_args' => $acceptedArgs,
            'tags' => $tags,
            'defer_register' => $deferRegister,
            'execute_if' => $executeIf,
            'execute_if_params' => $executeIfParams,
        ];
    }

    /**
     * @param array<int, string> $tags
     *
     * @return array<string,mixed>
     */
    private function filter(
        string $class,
        string $method,
        string $hook,
        int $priority = 10,
        int $acceptedArgs = 1,
        array $tags = [],
        bool $deferRegister = false,
        ?Closure $executeIf = null,
        array $executeIfParams = []
    ): array {
        return [
            'class' => $class,
            'method' => $method,
            'type' => 'filter',
            'hook' => $hook,
            'priority' => $priority,
            'accepted_args' => $acceptedArgs,
            'tags' => $tags,
            'defer_register' => $deferRegister,
            'execute_if' => $executeIf,
            'execute_if_params' => $executeIfParams,
        ];
    }
}
