<?php
namespace AstraChild\Views\Jobs;

use AstraChild\Models\JobEntity;
use AstraChild\Helpers\JobHelpers;
use AstraChild\Helpers\SocialMedia;
use AstraChild\Views\Components\ShareButton;

class Single
{
    protected $shareButton;

    public function __construct() {
        $this->shareButton = new ShareButton();
    }

    /**
     * Render the single job view
     * 
     * @param JobEntity $job The job entity to display
     * @return void
     */
    public function render(JobEntity $job): void
    {
        // Make job and view available to the template
        set_query_var('job', $job);
        set_query_var('view', $this);  // Add this line to pass view to template
        
        // Add direct variable access as well (belt and suspenders approach)
        $view = $this; // Make $view directly available
        
        // Include template
        include get_stylesheet_directory() . '/template-parts/jobs/single-content.php';
    }
    
    /**
     * Get job summary items HTML
     * 
     * @param JobEntity $job The job entity
     * @return string The HTML content
     */
    public function getSummaryItemsHtml(JobEntity $job): string
    {
        ob_start();
        $this->renderSummaryItems($job);
        return ob_get_clean();
    }
    
    /**
     * Render job summary items
     * 
     * @param JobEntity $job The job entity
     * @return void
     */
    public function renderSummaryItems(JobEntity $job): void
    {
        // Job type
        if (!JobHelpers::isReallyEmpty($job->getAttribute('job_type'))) {
            $this->renderSummaryItem(
                'fas fa-briefcase',
                'Jenis Pekerjaan',
                $job->getAttribute('job_type')
            );
        }
        
        // Education
        if (!JobHelpers::isReallyEmpty($job->getAttribute('education'))) {
            $this->renderSummaryItem(
                'fas fa-graduation-cap',
                'Pendidikan',
                $job->getFormattedEducation()
            );
        }
        
        // Experience
        if (!JobHelpers::isReallyEmpty($job->getAttribute('experience'))) {
            $this->renderSummaryItem(
                'fas fa-history',
                'Pengalaman',
                $job->getFormattedExperience()
            );
        }
        
        // Gender
        if (!JobHelpers::isReallyEmpty($job->getAttribute('gender'))) {
            $this->renderSummaryItem(
                'fas fa-user',
                'Gender',
                $job->getAttribute('gender')
            );
        }
        
        // Age
        if (!JobHelpers::isReallyEmpty($job->getAttribute('min_age')) || 
            !JobHelpers::isReallyEmpty($job->getAttribute('max_age'))) {
            $this->renderSummaryItem(
                'fas fa-user-clock',
                'Umur',
                $job->getFormattedAgeRange()
            );
        }
        
        // Salary
        if (!JobHelpers::isReallyEmpty($job->getAttribute('min_salary')) || 
            !JobHelpers::isReallyEmpty($job->getAttribute('max_salary'))) {
            $this->renderSummaryItem(
                'fas fa-money-bill-wave',
                'Gaji',
                $job->getFormattedSalary()
            );
        }
        
        // Location
        if (!JobHelpers::isReallyEmpty($job->getAttribute('location'))) {
            $this->renderSummaryItem(
                'fas fa-map-marker-alt',
                'Lokasi',
                $job->getAttribute('location')
            );
        }
        
        // Deadline
        if (!JobHelpers::isReallyEmpty($job->getAttribute('deadline'))) {
            $this->renderSummaryItem(
                'fas fa-clock',
                'Deadline',
                $job->getFormattedDeadline()
            );
        }
    }
    
    /**
     * Render a summary item
     * 
     * @param string $icon FontAwesome icon class
     * @param string $label Item label
     * @param string $value Item value
     * @return void
     */
    private function renderSummaryItem(string $icon, string $label, string $value): void
    {
        ?>
        <div class="group flex items-center gap-3 p-3 bg-white border border-gray-200 rounded-lg hover:border-blue-200 hover:shadow-md transition-all duration-300">
            <div class="shrink-0">
                <div class="w-8 h-8 flex items-center justify-center rounded-full bg-blue-100 group-hover:bg-blue-600 transition-colors duration-300">
                    <i class="<?php echo esc_attr($icon); ?> text-blue-600 group-hover:text-white text-xl"></i>
                </div>
            </div>
            <div class="min-w-0 flex-1 flex items-baseline gap-1 text-wrap">
                <span class="text-sm font-semibold text-gray-500 group-hover:text-blue-600 transition-colors duration-300"><?php echo esc_html($label); ?>:</span>
                <span class="text-sm font-semibold text-gray-900"><?php echo esc_html($value); ?></span>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render contact information
     * 
     * @param JobEntity $job The job entity
     * @return void
     */
    public function renderContactInfo(JobEntity $job): void
    {
        // Email
        if (!JobHelpers::isReallyEmpty($job->getAttribute('email'))) {
            $this->renderContactItem(
                'fas fa-envelope',
                'Email',
                $job->getAttribute('email'),
                'mailto:' . $job->getAttribute('email')
            );
        }
        
        // Phone
        if (!JobHelpers::isReallyEmpty($job->getAttribute('phone'))) {
            $this->renderContactItem(
                'fas fa-phone',
                'Telepon',
                $job->getAttribute('phone'),
                'tel:' . $job->getAttribute('phone')
            );
        }
        
        // Website
        if (!JobHelpers::isReallyEmpty($job->getAttribute('website'))) {
            $this->renderContactItem(
                'fas fa-globe',
                'Website',
                $job->getAttribute('website'),
                $job->getAttribute('website')
            );
        }
    }
    
    /**
     * Render a contact information item
     * 
     * @param string $icon FontAwesome icon class
     * @param string $label Item label
     * @param string $value Display text
     * @param string $url Link URL
     * @return void
     */
    private function renderContactItem(string $icon, string $label, string $value, string $url): void
    {
        ?>
        <div class="group flex items-center gap-3 p-3 bg-white border border-gray-200 rounded-lg hover:border-blue-200 hover:shadow-md transition-all duration-300">
            <div class="shrink-0">
                <div class="w-8 h-8 flex items-center justify-center rounded-full bg-blue-100 group-hover:bg-blue-600 transition-colors duration-300">
                    <i class="<?php echo esc_attr($icon); ?> text-blue-600 group-hover:text-white text-xl"></i>
                </div>
            </div>
            <div class="min-w-0 flex-1">
                <span class="block text-sm font-semibold text-gray-500 group-hover:text-blue-600 transition-colors duration-300"><?php echo esc_html($label); ?>:</span>
                <a href="<?php echo esc_url($url); ?>" <?php echo strpos($url, 'http') === 0 ? 'target="_blank"' : ''; ?> class="block text-sm font-semibold text-gray-900 hover:text-blue-600 transition-colors truncate">
                    <?php echo esc_html($value); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render social media links
     * 
     * @param JobEntity $job The job entity
     * @return void
     */
    public function renderSocialMedia(JobEntity $job): void
    {
        $socials = $job->getAttribute('socials');
        
        if (empty($socials) || !is_array($socials)) {
            return;
        }
        
        foreach ($socials as $platform => $username) {
            $link_data = SocialMedia::getLinkData($platform, $username);
            if ($link_data) {
                $this->renderSocialMediaItem(
                    $link_data['icon'],
                    ucfirst($platform),
                    $link_data['username'],
                    $link_data['url']
                );
            }
        }
    }
    
    /**
     * Render a social media item
     * 
     * @param string $icon FontAwesome icon class
     * @param string $platform Platform name
     * @param string $username Username to display
     * @param string $url Full URL to social profile
     * @return void
     */
    private function renderSocialMediaItem(string $icon, string $platform, string $username, string $url): void
    {
        ?>
        <div class="group flex items-center gap-3 p-3 bg-white border border-gray-200 rounded-lg hover:border-blue-200 hover:shadow-md transition-all duration-300">
            <div class="shrink-0">
                <div class="w-8 h-8 flex items-center justify-center rounded-full bg-blue-100 group-hover:bg-blue-600 transition-colors duration-300">
                    <i class="<?php echo esc_attr($icon); ?> text-blue-600 group-hover:text-white text-xl"></i>
                </div>
            </div>
            <div class="min-w-0 flex-1">
                <span class="block text-sm font-semibold text-gray-500 group-hover:text-blue-600 transition-colors duration-300"><?php echo esc_html($platform); ?>:</span>
                <a href="<?php echo esc_url($url); ?>" target="_blank" class="block text-sm font-semibold text-gray-900 hover:text-blue-600 transition-colors truncate">
                    <?php echo esc_html($username); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render share button
     * 
     * @param JobEntity $job The job entity
     * @return void
     */
    public function renderShareButton(JobEntity $job): void {
        $this->shareButton->render($job);
    }
}