<?php
/**
 * Template Name: Pasang Iklan Loker
 * 
 * Template for displaying the job posting information page
 */

use AstraChild\Views\PasangLoker\PasangLowongan;

get_header();

// Initialize the view
$pasang_view = new PasangLowongan();

// Render the view with custom options if needed
$pasang_view->render([
    // You can customize options here
    'instagram' => '@loker_banjarmasin',
    'whatsapp' => '+62 838-6244-7271',
    'email' => 'muhammadindra003@gmail.com',
]);

get_footer();
?>