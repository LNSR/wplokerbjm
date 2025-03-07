<?php
/**
 * Template part for displaying single job content
 *
 * @var $job JobEntity The job entity from the controller/view
 */

use AstraChild\Helpers\JobHelpers;

// Access job from query vars
$job = get_query_var('job');

// If it's not available, exit early
if (!$job) return;
?>

<article class="bg-white rounded-lg shadow-lg p-8 divide-y divide-gray-200">
    <!-- Title Section -->
    <section class="sticky top-0 z-10 bg-white/95 backdrop-blur mb-4 -mx-8 px-8 py-4">
        <h1 class="text-3xl font-bold text-gray-900 mb-4 text-center"><?php echo esc_html($job->getAttribute('title')); ?></h1>
    </section>

    <!-- Company Section -->
    <section class="pt-8 group">
        <div class="flex items-start gap-3 mb-6 transform transition-transform group-hover:translate-x-2">
            <div class="shrink-0 mt-2">
                <i class="fas fa-building text-blue-600 text-2xl"></i>
            </div>
            <div class="flex-1 ps-2">
                <h2 class="text-2xl font-semibold text-gray-800">
                    <span class=""><?php echo esc_html($job->getAttribute('company')); ?></span>
                </h2>
            </div>
        </div>

        <?php if (!JobHelpers::isReallyEmpty($job->getAttribute('company_desc'))) : ?>
            <div class="mt-6">
                <h3 class="flex items-center gap-1 text-xl font-semibold text-gray-800 mb-4">
                    <i class="fas fa-info-circle text-blue-600"></i>
                    <span class="ps-4">Tentang Perusahaan</span>
                </h3>
                <div class="prose max-w-none text-gray-600 mt-4 [&>p]:text-justify [&>ul]:text-left [&>ol]:text-left sm:[&>p]:indent-11 [&>p]:indent-10">
                    <?php echo wpautop($job->getAttribute('company_desc')); ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <!-- Job Summary Section -->
    <?php if ($job->hasSummary()) : ?>
        <section class="pt-8">
            <h3 class="flex items-center gap-2 text-xl font-semibold text-gray-800 mb-6">
                <i class="fas fa-clipboard-check text-blue-600"></i>
                <span class="ps-4">Ringkasan Pekerjaan</span>
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 animate-fade-in mt-4 mb-6">
                <?php $view->renderSummaryItems($job); ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Job Description Section -->
    <?php if (!JobHelpers::isReallyEmpty($job->getAttribute('job_desc'))) : ?>
        <section class="pt-8 content-visibility-auto">
            <h3 class="flex items-center gap-1 text-xl font-semibold text-gray-800 mb-6">
                <i class="fas fa-tasks text-blue-600"></i>
                <span class="ps-4">Deskripsi Pekerjaan</span>
            </h3>
            <div class="prose max-w-none text-gray-600 mt-4 [&>p]:text-justify [&>ul]:text-left [&>ol]:text-left sm:[&>p]:indent-11 [&>p]:indent-10">
                <?php echo wpautop($job->getAttribute('job_desc')); ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Requirements Section -->
    <?php if (!JobHelpers::isReallyEmpty($job->getAttribute('requirements'))) : ?>
        <section class="pt-8 content-visibility-auto">
            <h3 class="flex items-center gap-1 text-xl font-semibold text-gray-800 mb-6">
                <i class="fas fa-clipboard-list text-blue-600"></i>
                <span class="ps-5">Persyaratan</span>
            </h3>
            <div class="prose max-w-none text-gray-600 mt-4 [&>p]:text-justify [&>ul]:text-left [&>ol]:text-left sm:[&>p]:indent-11 [&>p]:indent-10">
                <?php echo wpautop($job->getAttribute('requirements')); ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Contact Section -->
    <?php if ($job->hasContactInfo()) : ?>
        <section class="pt-8">
            <h3 class="flex items-center justify-between gap-2 text-xl font-semibold text-gray-800 mb-6">
                <span class="flex items-center gap-2">
                    <i class="fas fa-address-card text-blue-600"></i>
                    <span class="ps-2">Kontak</span>
                </span>
                <!-- share button -->
                <button
                    class="share-button inline-flex items-center px-4 py-2 text-sm font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                    data-post-id="<?php echo $job->getAttribute('ID'); ?>">
                    <i class="fas fa-share-alt mr-2"></i>
                    Bagikan
                </button>
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-4 mb-6">
                <?php $view->renderContactInfo($job); ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Social Media Section -->
    <?php if (!JobHelpers::isReallyEmpty($job->getAttribute('socials')) && is_array($job->getAttribute('socials'))) : ?>
        <section class="pt-8">
            <h3 class="flex items-center gap-2 text-xl font-semibold text-gray-800 group">
                <i class="fas fa-share-alt text-blue-600"></i>
                <span class="ps-4">Sosial Media</span>
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-4 mb-6">
                <?php $view->renderSocialMedia($job); ?>
            </div>
        </section>
    <?php endif; ?>
</article>