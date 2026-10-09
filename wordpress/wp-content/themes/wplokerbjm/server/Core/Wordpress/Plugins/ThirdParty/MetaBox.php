<?php

namespace WPLokerBJM\Core\Wordpress\Plugins\ThirdParty;

use WPLokerBJM\Core\Container\Attributes\{Action, Filter, Inject};
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\ModuleClassHookMetadata;
use WPLokerBJM\Core\Wordpress\InstanceRuntimeRegistryEvent;
use WPLokerBJM\Core\Wordpress\Plugins\PluginConfigInterface;
use WPLokerBJM\Core\Wordpress\Plugins\PluginList;
use WPLokerBJM\Models\Schema\CustomFields;
use WPLokerBJM\Models\Schema\PostTypes;
use WPLokerBJM\Models\Schema\Taxonomies;

/**
 * MetaBox Plugin Hooks
 */
final class MetaBox implements PluginConfigInterface
{
    public static function isActive(): bool
    {
        return PluginList::MetaBox->isActive();
    }

    #[Action('plugins_loaded')]
    public function boot(): void
    {
        do_action(InstanceRuntimeRegistryEvent::REGISTER_HOOKS, $this->registerCustomModels);
        do_action(InstanceRuntimeRegistryEvent::REGISTER_HOOKS, $this->metaboxBugsPatch);
    }

    private ModuleClassHookMetadata $metaboxBugsPatch {
        get => $this->metaboxBugsPatch ??= new class(__CLASS__, __PROPERTY__) extends ModuleClassHookMetadata {
            /**
             * Fix blank WYSIWYG/TinyMCE editor on fresh post-editor load edit by viewing WYSIWYG/TinyMCE editor html code first
             */
            #[Filter('wp_default_editor', 8, registerIf: static function(): bool { return is_admin(); })]
            public function switch_tinymce_default_view(): string
            {
                return 'html';
            }
        };
    }

    private ModuleClassHookMetadata $registerCustomModels {
        get => $this->registerCustomModels ??= new class(__CLASS__, __PROPERTY__) extends ModuleClassHookMetadata {
            #[Inject]
            private CustomFields $customFields;
            #[Inject]
            private Taxonomies $taxonomies;
            #[Inject]
            private PostTypes $postTypes;

            #[Filter('rwmb_meta_boxes')]
            public function registerCustomFields($meta_boxes): void
            {
                $this->customFields->lowonganCustomFields($meta_boxes);
            }
            #[Action('init')]
            public function registerPostTypes(): void
            {
                $this->postTypes->registerLowonganPostType();
            }
            #[Action('init')]
            public function registerTaxonomies(): void
            {
                $this->taxonomies->registerAllTaxonomies();
            }
        };
    }
}
