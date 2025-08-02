<?php

/**
 * Test script to debug the AutowireScanner
 * This file can be run from the command line to see what classes are being detected
 */

// Include the autoloader
require_once __DIR__ . '/vendor/autoload.php';

use AstraChild\Core\AutowireScanner;

// Create scanner instance
$scanner = new AutowireScanner(
    __DIR__ . '/inc', // Points to the inc/ directory
    'AstraChild'
);

echo "=== AutowireScanner Debug Results ===\n\n";

// Get debug results
$results = $scanner->debugScanResults();

$autowirableCount = 0;
$skippedCount = 0;

foreach ($results as $result) {
    $status = $result['autowirable'] ? '✓ AUTOWIRABLE' : '✗ SKIPPED';
    echo sprintf(
        "%s: %s\n   File: %s\n   Reason: %s\n\n",
        $status,
        $result['class'],
        basename($result['file']),
        $result['reason'] ?: 'N/A'
    );
    
    if ($result['autowirable']) {
        $autowirableCount++;
    } else {
        $skippedCount++;
    }
}

echo "=== Summary ===\n";
echo "Total files scanned: " . count($results) . "\n";
echo "Autowirable classes: $autowirableCount\n";
echo "Skipped classes: $skippedCount\n";

// Test the actual definitions
echo "\n=== Generated Definitions ===\n";
$definitions = $scanner->scanForAutowirableClasses();
foreach ($definitions as $className => $definition) {
    echo "- $className\n";
}
