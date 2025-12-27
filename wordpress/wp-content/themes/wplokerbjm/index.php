<?php

use WPLokerBJM\Core\Container;
use WPLokerBJM\Views\Page\{HomepageView, SingleView, PasangIklanLokerView};

$container = Container::getContainer();

switch (true) {
    case (is_page('pasang-iklan-loker') || is_page(184)):
        $viewClass = PasangIklanLokerView::class;
        break;
    case is_single() && get_post_type() === 'lowongan':
        $viewClass = SingleView::class;
        break;
    case is_front_page() || is_page(146):
        $viewClass = HomepageView::class;
        break;
}

$container->get($viewClass)->render();