<?php

namespace WPLokerBJM\Core\Container\Definitions;

use WPLokerBJM\Core\Container\Support\AutowireScanner;

/**
 * Auto-scanned definitions for autowiring.
 * 
 * This class automatically scans the server/ directory recursively and registers
 * all suitable classes for autowiring, excluding interfaces, abstract classes,
 * and static-only classes.
 * 
 * Note: This only adds definitions for classes that don't already have manual
 * definitions in other definition files.
 */
class AutoScanned implements DefinitionProviderInterface
{
    public static function getDefinitions(): array
    {
        // Create scanner instance directly (not from container)
        $scanner = new AutowireScanner(
            get_stylesheet_directory() . '/server', // Points to the server/ directory
            'WPLokerBJM'
        );

        $autoDefinitions = $scanner->scanForAutowirableClasses();

        // Dynamically scan all definition files except this one
        $definitionsDir = __DIR__;
        $existingDefinitions = [];
        foreach (glob($definitionsDir . '/*.php') as $file) {
            if (basename($file) === 'AutoScanned.php') {
                continue;
            }
            $className = __NAMESPACE__ . '\\' . basename($file, '.php');
            if (class_exists($className) && is_subclass_of($className, DefinitionProviderInterface::class)) {
                $defs = $className::getDefinitions();
                if (is_array($defs)) {
                    $existingDefinitions = array_merge($existingDefinitions, $defs);
                }
            }
        }

        // Only return auto-definitions for classes that don't have manual definitions
        $filteredDefinitions = [];
        foreach ($autoDefinitions as $className => $definition) {
            if (!array_key_exists($className, $existingDefinitions)) {
                $filteredDefinitions[$className] = $definition;
            }
        }

        return $filteredDefinitions;
    }
}
