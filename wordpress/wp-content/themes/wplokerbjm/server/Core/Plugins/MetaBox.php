<?php
declare(strict_types=1);
namespace WPLokerBJM\Core\Plugins;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use DI\Attribute\Injectable;
use WPLokerBJM\Core\Container\Support\WPHooksRegistry;
use WPLokerBJM\Shared\Utilities\PluginList;

/**
 * MetaBox Plugin Hooks
 */
#[Injectable(lazy: true)]
class MetaBox
{

    public static function isActive()
    {
        return PluginList::MetaBox->isActive();
    }

    public function __construct(private WPHooksRegistry $hookRegistry)
    {
        if (self::isActive()) return;
        $hookRegistry->unregisterByClass(self::class);
    }

    /**
     * Fix blank WYSIWYG/TinyMCE editor on fresh post-editor load edit by viewing WYSIWYG/TinyMCE editor html code first
     */
    #[Filter('wp_default_editor', 8)]
    public function switch_tinymce_default_view(): string
    {
        return 'html';
    }
}