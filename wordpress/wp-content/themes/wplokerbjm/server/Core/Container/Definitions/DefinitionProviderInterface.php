<?php

namespace WPLokerBJM\Core\Container\Definitions;

interface DefinitionProviderInterface
{
    /**
     * Return PHP-DI container definitions.
     *
     * @return array<string|class-string, mixed>
     */
    public static function getDefinitions(): array;
}