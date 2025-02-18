<?php
/**
 * The template for displaying Lowongan archives
 *
 * @package Astra-Child
 */

use AstraChild\Controllers\JobController;
use AstraChild\Views\Jobs\Archive;
use AstraChild\Views\Sidebar\Sidebar;

// Initialize controller
$job_controller = new JobController();

get_header();
?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex flex-col lg:flex-row gap-8">
        <div class="w-full lg:w-3/4">
            <?php 
            // Initialize and render archive view
            $archive_view = new Archive();
            $archive_view->render();
            ?>
        </div>

        <div class="hidden lg:block w-full lg:w-1/4">
            <?php
            // Initialize and render sidebar
            $sidebar = new Sidebar();
            $sidebar->render([
                'show_recent_jobs' => true,
                'show_categories' => true,
                'recent_job_count' => 5
            ]);
            ?>
        </div>
    </div>
</div>

<?php
get_footer();