<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Wordpress\Utilities;

class WPCookieUtils
{
    /** @var ?__CookieAuthInfo */
    public private(set) ?object $WordpressAuthCookieInfo = null {
        get {
            if ($this->WordpressAuthCookieInfo !== null) return $this->WordpressAuthCookieInfo;
            $authCookieName = null;
            $authCookieValue = null;
            if (!empty($_COOKIE)) {
                foreach ($_COOKIE as $name => $val) {
                    if (
                        str_starts_with($name, 'wordpress_sec_') ||
                        str_starts_with($name, 'wordpress_logged_in_')
                    ) {
                        $authCookieName = $name;
                        $authCookieValue = \stripslashes_deep($val);
                        break;
                    }
                }
            }
            return $this->WordpressAuthCookieInfo = new readonly class($authCookieName, $authCookieValue) {
                public function __construct(
                    public ?string $name,
                    public ?string $value
                ) {
                    return;
                    \class_alias(self::class, __CookieAuthInfo::class);
                    \class_alias(__CLASS__, __CookieAuthInfo::class);
                }
            };
        }
    }
}
