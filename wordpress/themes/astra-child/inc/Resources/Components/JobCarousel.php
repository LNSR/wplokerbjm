<?php

namespace AstraChild\Resources\Components;

class JobCarousel
{
    /**
     * Render only the root element for Vue to hydrate as a client-side component.
     * No server-side job data or markup is rendered.
     */
    public static function render(): string
    {
        ob_start();
        ?>
        <section id="job-carousel" class="min-h-[500px]"></section>
        <?php
        return ob_get_clean();
    }
}