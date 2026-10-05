<?php

namespace WPLokerBJM\Core\Container;

use DI\ContainerBuilder;
use DI\Container;
use Nette\Loaders\RobotLoader;
use WPLokerBJM\Bootstrap;
use WPLokerBJM\Core\Container\Attributes\Injectable;
use WPLokerBJM\Core\Container\Definitions\{Core, Factory};
use WPLokerBJM\Shared\Log\Logger;

/**
 * WPLokerBJM Container
 * 
 * Builder for the PHP-DI container.
 */
#[Injectable(skip: true)]
class WPLokerBJMContainer
{
    public function __construct(public readonly RobotLoader $robotLoader, ?string $cacheDir = null)
    {
        $this->cacheLocation = new readonly class ($cacheDir ??= \sprintf("%s/cache", rtrim(\get_stylesheet_directory()))) {
            public string $cacheFile;

            public function __construct(public string $cacheDir)
            {
                $this->cacheFile = \sprintf("%s/CompiledContainer.php", $this->cacheDir);
            }
        };
    }

    /**
     * @phpstan-ignore-next-line
     * @var __CLASS__::class
     */
    public private(set) ?object $cacheLocation = null;

    private ?ContainerConfigurationState $containerConfigurationState {
        get => $this->containerConfigurationState ??= new ContainerConfigurationState();
    }

    private ?WPLokerBJMContainerBuilderConfiguration $builderConfiguration {
        get => $this->builderConfiguration ??= new WPLokerBJMContainerBuilderConfiguration(
        $this,
        $this->containerConfigurationState,
        );
    }

    /**
     * @param bool $forceRebuild
     */
    public function initContainerBuilder(bool $forceRebuild = false): WPLokerBJMContainerBuilderConfiguration
    {
        try {
            if ($this->containerConfigurationState->configured && !$forceRebuild) {
                throw new \LogicException('Container already configured, use forceRebuild to force rebuild');
            }
            $forceRebuild && $this->reset();
            $this->containerConfigurationState->containerBuilder = new ContainerBuilder();
            return $this->builderConfiguration;
        } catch (\Exception $e) {
            Logger::error('Container', 'Container::getContainer error: ' . $e->getMessage());
            Logger::flush();
            throw $e;
        }
    }

    private function reset(): void
    {
        $this->containerConfigurationState = null;
        $this->builderConfiguration = null;
        @is_readable($this->cacheLocation->cacheDir) && @unlink($this->cacheLocation->cacheFile);
    }
}

/**
 * @internal
 */
#[Injectable(skip: true)]
final class WPLokerBJMContainerBuilderConfiguration
{
    /** @var array<string,object> */
    private array $extraDefinitionsSet = [];
    public function __construct(
        private WPLokerBJMContainer $wpLokerContainer,
        private ContainerConfigurationState $containerConfigurationState,
    ) {}

    public function setExtraDefinitionsSet(array $extraDefinitionsSet): static
    {
        $this->statusException();
        $this->extraDefinitionsSet = $extraDefinitionsSet;
        return $this;
    }

    public function buildContainer(): Container
    {
        $builder = $this->containerConfigurationState->containerBuilder;
        $builder->useAutowiring(false);
        $builder->useAttributes(true);
        $this->setupCache();
        $c = $builder->build();
        $postRegisteration = [
            WPLokerBJMContainer::class => $this->wpLokerContainer,
            RobotLoader::class => $this->wpLokerContainer->robotLoader,
        ];
        foreach ($postRegisteration as $class => $instance) {
            $c->has($class) && $c->set($class, $instance);
        }
        $this->containerConfigurationState->configured = true;
        return $c;
    }

    private function statusException(): void
    {
        if ($this->containerConfigurationState->configured) {
            throw new \LogicException('Container already configured, use forceRebuild to force rebuild');
        }
    }

    /**
     * Configures container compilation using strict guard clauses to minimize disk I/O.
     */
    private function setupCache(): void
    {
        $builder = $this->containerConfigurationState->containerBuilder;
        $hasCache = \is_readable($this->wpLokerContainer->cacheLocation->cacheFile);

        if ($hasCache) {
            $builder->enableCompilation($this->wpLokerContainer->cacheLocation->cacheDir);
            return;
        }

        if (!is_dir($this->wpLokerContainer->cacheLocation->cacheDir) && !mkdir($this->wpLokerContainer->cacheLocation->cacheDir, 0755, true)) {
            Logger::error('Container', "Failed to create cache directory: " . $this->wpLokerContainer->cacheLocation->cacheDir);
            return;
        }

        if (!is_writable($this->wpLokerContainer->cacheLocation->cacheDir)) {
            Logger::warning('Container', "Compilation directory not writable, skipping compilation: " . $this->wpLokerContainer->cacheLocation->cacheDir);
            Logger::flush();
            return;
        }

        try {
            $this->persistDefinition($builder);
            $builder->enableCompilation($this->wpLokerContainer->cacheLocation->cacheDir);
            $builder->writeProxiesToFile(true, $this->wpLokerContainer->cacheLocation->cacheDir . '/');
        } catch (\Exception $e) {
            Logger::warning('Container', 'Failed to enable compilation: ' . $e->getMessage());
        }
    }
    /**
     * persist definitions
     */
    private function persistDefinition(ContainerBuilder $builder): void
    {
        $coreDefinition = new Core($this->wpLokerContainer->robotLoader);
        $factoryDefinitions = new Factory();

        $builder->addDefinitions(
            // default definitions
            // Last position will overwrite previous definitions
            \array_merge(
                // core definitions
                $coreDefinition->getDefinitions(),
                // factory definitions
                $factoryDefinitions->getDefinitions(),
                $this->extraDefinitionsSet,
            ),
        );
    }
}
/**
 * @internal
 */
#[Injectable(skip: true)]
final class ContainerConfigurationState
{
    public bool $configured = false;
    public ?ContainerBuilder $containerBuilder = null;
}
