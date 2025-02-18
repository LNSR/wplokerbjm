<?php
/**
 * Template Name: Pasang Iklan Loker
 */
get_header();

$iklanView = \AstraChild\Core\Container::getContainer()->get(\AstraChild\Views\Page\PasangIklanView::class)->render();

get_footer();

?>