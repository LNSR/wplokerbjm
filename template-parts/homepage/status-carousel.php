<?php

/**
 * Template part for displaying the status carousel
 * 
 *
 */

use AstraChild\Views\Homepage\StatusCarousel;

// Initialize the view
$carousel_view = new StatusCarousel();

// Render the carousel
$carousel_view->render([
    'title' => 'Direkomendasikan',
    'show_title' => true,
    'items_to_show' => 3,
    'auto_slide' => true
]);
?>