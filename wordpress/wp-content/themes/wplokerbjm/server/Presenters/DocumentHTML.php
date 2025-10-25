<?php
namespace WPLokerBJM\Presenters;
class DocumentHTML
{
    public static function renderHead(string|null $schema = null)
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
    }

    public static function renderFooter()
    {
        ?>
        <?php wp_footer(); ?>
        </body>

        </html>
        <?php
    }
}