<?php

namespace WPLokerBJM\Presenters\Pages;
use WPLokerBJM\Presenters\SEO\SkeletonHTML\SkeletonForSEO;

class KebijakanPrivacyPresenter
{
    public static function getData(): array
    {
        return [
            'seoHtml' => SkeletonForSEO::kebijakanPrivacyHTML(),
        ];
    }
}