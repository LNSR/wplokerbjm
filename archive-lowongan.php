<?php
/**
 * The template for displaying Lowongan archives and search/filter
 *
 * @package Astra-Child
 */


namespace AstraChild;


get_header();

$archiveView = \AstraChild\Core\Container::getContainer()->get(\AstraChild\Views\Page\ArchiveView::class)->render();

get_footer();