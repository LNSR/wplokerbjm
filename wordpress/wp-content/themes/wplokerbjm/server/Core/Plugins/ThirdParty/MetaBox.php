<?php
namespace WPLokerBJM\Core\Plugins\ThirdParty;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use DI\Attribute\Injectable;
use WPLokerBJM\Core\Plugins\PluginConfigInterface;
use WPLokerBJM\Shared\Utilities\PluginList;

/**
 * MetaBox Plugin Hooks
 */
final class MetaBox implements PluginConfigInterface
{

    public static function isActive(): bool
    {
        return PluginList::MetaBox->isActive();
    }

    /**
     * Fix blank WYSIWYG/TinyMCE editor on fresh post-editor load edit by viewing WYSIWYG/TinyMCE editor html code first
     */
    #[Filter('wp_default_editor', 8, registerIf: static function(): bool { return is_admin(); })]
    public function switch_tinymce_default_view(): string
    {
        return 'html';
    }
}