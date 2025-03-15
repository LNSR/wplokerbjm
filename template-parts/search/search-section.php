<?php
/**
 * Template part for displaying the search section
 * 
 * @package Astra-Child
 */
use AstraChild\Views\Search\SearchForm;

// Initialize the search form view
$search_view = new SearchForm();

// Check if we're on the search results page
$is_search_page = strpos($_SERVER['REQUEST_URI'] ?? '', 'search-jobs') !== false;

// Render with appropriate page-specific settings
$search_view->render([
    'title' => 'Cari Lowongan Kerja Terbaru',
    'subtitle' => 'Temukan lowongan kerja di Banjarmasin dan sekitarnya',
    'show_title' => !$is_search_page, 
    // Different margins based on page type
    'desktop_margin' => $is_search_page ? 'lg:mx-50' : 'lg:mx-80'
]);
?>