<?php

use WPLokerBJM\Core\Container;

get_header();
Container::getContainer()->get(\WPLokerBJM\Views\Page\HomepageView::class)->render();
get_footer();