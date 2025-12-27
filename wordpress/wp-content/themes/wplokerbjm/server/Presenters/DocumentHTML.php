<?php
namespace WPLokerBJM\Presenters;
class DocumentHTML
{

    public static function renderDocument(?string $schema = null, ?array $props = null): void
    {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>

        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <?php wp_head();
            $schema ? print $schema : null;
            ?>
        </head>

        <body <?php body_class(); ?>>
            <?php
            self::renderApp($props);
            wp_footer();
            ?>
        </body>

        </html>
        <?php
    }

    /**
     *  Renders the main app container with optional props as JSON
     */
    private static function renderApp(?array $props = null): void
    {
        ?>
        <div id="app">
            <?php if ($props): ?>
                <script type="application/json"
                    data-props><?= wp_json_encode($props, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
            <?php endif; ?>
        </div>
        <?php
    }
}