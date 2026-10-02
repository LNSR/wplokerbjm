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
    public function __construct(private RobotLoader $robotLoader, public private(set) ?string $cacheDir = null)
    {
        $this->cacheDir ??= \sprintf("%s/cache", rtrim(get_stylesheet_directory()));
        $this->cacheFile ??= \sprintf("%s/CompiledContainer.php", $this->cacheDir);
    }

    private ?ContainerConfigurationState $containerConfigurationState {
        get => $this->containerConfigurationState ??= new ContainerConfigurationState();
    }
    public private(set) ?string $cacheFile = null;

    private ?WPLokerBJMContainerBuilderConfiguration $builderConfiguration {
        get => $this->builderConfiguration ??= new WPLokerBJMContainerBuilderConfiguration(
            $this,
            $this->robotLoader,
            $this->containerConfigurationState
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
            if ($forceRebuild) {
                $this->containerConfigurationState = null;
                @is_readable($this->cacheDir) && @unlink($this->cacheFile);
            }
            $this->containerConfigurationState->containerBuilder = new ContainerBuilder();
            return $this->builderConfiguration;
        } catch (\Exception $e) {
            Logger::error('Container', 'Container::getContainer error: ' . $e->getMessage());
            Logger::flush();
            throw $e;
        }
    }
}

/**
 * @internal
 */
#[Injectable(skip: true)]
final class WPLokerBJMContainerBuilderConfiguration
{

    public function __construct(
        private WPLokerBJMContainer $wpLokerContainer,
        private RobotLoader $robotLoader,
        private ContainerConfigurationState $containerConfigurationState
    ) {}

    public function setExtraDefinitionsSet(array $extraDefinitionsSet): static
    {
        $this->statusException();
        $this->containerConfigurationState->extraDefinitionsSet = $extraDefinitionsSet;
        return $this;
    }

    public function buildContainer(): Container
    {
        $builder = $this->containerConfigurationState->containerBuilder;
        $builder->useAutowiring(false);
        $builder->useAttributes(true);
        $this->setupCache();
        $c = $builder->build();
        if (!$c->has(WPLokerBJMContainer::class)) {
            $c->set(WPLokerBJMContainer::class, $this->wpLokerContainer);
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
        $hasCache = \is_readable($this->wpLokerContainer->cacheFile);

        if ($hasCache) {
            $builder->enableCompilation($this->wpLokerContainer->cacheDir);
            return;
        }

        if (!is_dir($this->wpLokerContainer->cacheDir) && !mkdir($this->wpLokerContainer->cacheDir, 0755, true)) {
            Logger::error('Container', "Failed to create cache directory: " . $this->wpLokerContainer->cacheDir);
            return;
        }

        if (!is_writable($this->wpLokerContainer->cacheDir)) {
            Logger::warning('Container', "Compilation directory not writable, skipping compilation: " . $this->wpLokerContainer->cacheDir);
            Logger::flush();
            return;
        }

        try {
            $this->persistDefinition($builder);
            $builder->enableCompilation($this->wpLokerContainer->cacheDir);
            $builder->writeProxiesToFile(true, $this->wpLokerContainer->cacheDir . '/');
        } catch (\Exception $e) {
            Logger::warning('Container', 'Failed to enable compilation: ' . $e->getMessage());
        }
    }
    /**
     * persist definitions
     */
    private function persistDefinition(ContainerBuilder $builder): void
    {
        $coreDefinition = new Core($this->robotLoader);
        $factoryDefinitions = new Factory();

        $builder->addDefinitions(
            // default definitions
            // Last position will overwrite previous definitions
            \array_merge(
                // core definitions
                $coreDefinition->getDefinitions(),
                // factory definitions
                $factoryDefinitions->getDefinitions(),
                $this->containerConfigurationState->extraDefinitionsSet
            )
        );
    }
}
/**
 * @internal
 */
#[Injectable(skip: true)]
final class ContainerConfigurationState
{
    /** @var array<string,object> */
    public array $extraDefinitionsSet = [];
    public bool $configured = false;
    public ?ContainerBuilder $containerBuilder = null;
}
