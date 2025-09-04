<?php

/**
 * Test script to debug the AutowireScanner
 * This file can be run from the command line to see what classes are being detected
 */

// Include the autoloader
require_once __DIR__ . '/vendor/autoload.php';

use AstraChild\Core\AutowireScanner;

/**
 * Output colored text to terminal
 */
function colorize(string $text, string $color): string
{
    $colors = [
        'green' => "\033[32m",
        'red' => "\033[31m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'reset' => "\033[0m",
        'bold' => "\033[1m"
    ];
    
    return ($colors[$color] ?? '') . $text . $colors['reset'];
}

/**
 * Format a result row for display
 */
function formatResult(array $result): string
{
    $status = $result['autowirable'] 
        ? colorize('✓ AUTOWIRABLE', 'green') 
        : colorize('✗ SKIPPED', 'red');
    
    $className = colorize($result['class'], 'bold');
    $fileName = basename($result['file']);
    $reason = $result['reason'] ?: 'N/A';
    
    return sprintf(
        "%s: %s\n   File: %s\n   Reason: %s\n",
        $status,
        $className,
        $fileName,
        $reason
    );
}

// Create scanner instance
$scanner = new AutowireScanner(
    __DIR__ . '/inc', // Points to the inc/ directory
    'AstraChild'
);

echo colorize("=== AutowireScanner Debug Results ===\n", 'blue');
echo "Scanning directory: " . __DIR__ . "/inc\n";
echo "Namespace: AstraChild\n\n";

// Get debug results
$results = $scanner->debugScanResults();

$autowirableCount = 0;
$skippedCount = 0;

// Display results grouped by status
$autowirableResults = array_filter($results, fn($r) => $r['autowirable']);
$skippedResults = array_filter($results, fn($r) => !$r['autowirable']);

if (!empty($autowirableResults)) {
    echo colorize("--- Autowirable Classes ---\n", 'green');
    foreach ($autowirableResults as $result) {
        echo formatResult($result) . "\n";
        $autowirableCount++;
    }
}

if (!empty($skippedResults)) {
    echo colorize("--- Skipped Classes ---\n", 'red');
    foreach ($skippedResults as $result) {
        echo formatResult($result) . "\n";
        $skippedCount++;
    }
}

echo colorize("=== Summary ===\n", 'blue');
echo "Total files scanned: " . count($results) . "\n";
echo colorize("Autowirable classes: $autowirableCount", 'green') . "\n";
echo colorize("Skipped classes: $skippedCount", 'red') . "\n";

// Test the actual definitions
echo "\n" . colorize("=== Generated Definitions ===\n", 'blue');
$definitions = $scanner->scanForAutowirableClasses();

if (empty($definitions)) {
    echo colorize("No autowirable definitions found.\n", 'yellow');
} else {
    echo "Found " . count($definitions) . " autowirable definitions:\n";
    foreach ($definitions as $className => $definition) {
        echo colorize("- $className", 'green') . "\n";
    }
}

echo "\n" . colorize("Test completed successfully!", 'green') . "\n";
