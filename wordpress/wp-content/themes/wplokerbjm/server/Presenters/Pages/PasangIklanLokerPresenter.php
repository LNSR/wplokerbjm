<?php

namespace WPLokerBJM\Presenters\Pages;
use WPLokerBJM\Presenters\SEO\SkeletonHTML\SkeletonForSEO;

class PasangIklanLokerPresenter
{
    public static function getData(): array
    {
        return [
            'seoHtml' => SkeletonForSEO::pasangIklanHTML(),
        ];
    }
}