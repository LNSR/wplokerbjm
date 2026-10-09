<?php

namespace WPLokerBJM\Services\GraphQL;

use ThemeData;
use WPLokerBJM\Core\Wordpress\Theme\ThemeHooks;

/**
 * @phpstan-import-type ThemeData from ThemeHooks
 */
class GraphQLThemeData
{
    /**
     * @return ThemeData
     */
    public function createThemeData(): array
    {
        return \apply_filters(ThemeHooks::THEME_DATA_HOOK, []); //Cached Internally
    }
}
