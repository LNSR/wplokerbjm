<?php
/**
 * Single Job Template
 *
 * This is the MVC implementation of the single job template
 */

use AstraChild\Controllers\JobController;
use AstraChild\Views\Jobs\Single;

get_header();

while (have_posts()) :
    the_post();
    
    // Use job_entity that was set up by JobController::setupSingleJob()
    $job_entity = get_query_var('job_entity');
    
    // If job_entity isn't available, get it
    if (empty($job_entity)) {
        global $post;
        $job_controller = new JobController();
        $job_entity = $job_controller->getJobEntity($post->ID);
    }
    
    // Initialize the view
    $view = new Single();
?>
    <div class="max-w-7xl mx-auto px-4 py-12">
        <div class="flex flex-col lg:flex-row gap-8">
            <div class="w-full">
                <!-- <div class="w-full lg:w-3/4"> Replace above if using Sidebar -->
                <?php $view->render($job_entity); ?>
            </div>

            <!-- Sidebar
            <div class="hidden lg:block w-full lg:w-1/4">
                <?php // get_template_part('template-parts/jobs/sidebar'); ?>
            </div> -->
        </div>
    </div>
<?php
endwhile;

get_footer();
?>
