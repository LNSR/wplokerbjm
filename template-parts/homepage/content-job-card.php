<?php

/**
 * Template part for displaying a job card
 */
use AstraChild\Views\Jobs\JobCard;

// Initialize view
$job_card_view = new JobCard();

// Get options passed from featured jobs grid, if any
$job_card_options = get_query_var('job_card_options', [
    'show_statuses' => [
        '0' => true,   // Show normal jobs
        '2' => true,   // Show urgent jobs
        '3' => false,  // Hide pinned jobs
        '4' => false   // Hide pinned & urgent jobs
    ]
]);

// Let the view handle rendering with status filtering
$job_card_view->render(null, $job_card_options);