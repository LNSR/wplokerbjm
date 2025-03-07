<?php
/**
 * Template part for displaying the search section
 * 
 * @package Astra-Child
 */
use AstraChild\Views\Search\SearchForm;

// Initialize the search form view
$search_view = new SearchForm();

// Render the search form
$search_view->render([
    'title' => 'Temukan Lowongan Kerja Terbaik',
    'subtitle' => 'Temukan ribuan lowongan kerja di Banjarmasin dan sekitarnya',
    // Using a different title on search results page
    'show_title' => !strpos($_SERVER['REQUEST_URI'], 'search-jobs')
]);
?>