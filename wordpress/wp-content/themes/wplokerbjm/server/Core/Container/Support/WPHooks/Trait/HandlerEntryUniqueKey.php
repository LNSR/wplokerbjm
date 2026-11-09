<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Trait;

trait HandlerEntryUniqueKey
{
    public function toUniqueKey(): string
    {
        return $this->hook . ':' . $this->key->toString();
    }
}
