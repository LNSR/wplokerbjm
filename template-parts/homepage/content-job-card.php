<?php
// filepath: /home/maulana/Project/Lowker-site/astra-child/template-parts/homepage/content-job-card.php
/**
 * Template part for displaying a job card
 */

use AstraChild\Views\Jobs\JobCard;

// Initialize view
$job_card_view = new JobCard();

// Let the view handle rendering with status filtering
$job_card_view->render(null, [
    'show_statuses' => [
        '0' => true,   // Show normal jobs
        '2' => true,   // Show urgent jobs
        '3' => false,  // Hide pinned jobs
        '4' => false   // Hide pinned & urgent jobs
    ]
]);