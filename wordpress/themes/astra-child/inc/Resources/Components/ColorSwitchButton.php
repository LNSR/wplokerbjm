<?php

namespace AstraChild\Resources\Components;

class ColorSwitchButton
{
    public static function render(): string
    {
        ob_start();
?>
        <div id="astra-color-switch-wrapper" class="fixed z-50 right-3 top-2 lg:!top-8">
            <div class="backdrop-blur-md bg-white/60 dark:bg-slate-800/60 rounded-full shadow-lg p-2">
                <label class="flex cursor-pointer gap-2 items-center">
                    <!-- Sun icon -->
                    <svg class="swap-on fill-current w-6 h-6" style="color: var(--icon-color);" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <g stroke-linejoin="round" stroke-linecap="round" stroke-width="2" fill="none" stroke="currentColor">
                            <circle cx="12" cy="12" r="4"></circle>
                            <path d="M12 2v2"></path>
                            <path d="M12 20v2"></path>
                            <path d="m4.93 4.93 1.41 1.41"></path>
                            <path d="m17.66 17.66 1.41 1.41"></path>
                            <path d="M2 12h2"></path>
                            <path d="M20 12h2"></path>
                            <path d="m6.34 17.66-1.41 1.41"></path>
                            <path d="m19.07 4.93-1.41 1.41"></path>
                        </g>
                    </svg>
                    <input id="astra-color-switch" type="checkbox" value="dark" class="toggle theme-controller" aria-label="Theme Switch" />
                    <!-- Moon icon -->
                    <svg class="swap-off fill-current w-6 h-6" style="color: var(--icon-color);" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <g stroke-linejoin="round" stroke-linecap="round" stroke-width="2" fill="none" stroke="currentColor">
                            <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"></path>
                        </g>
                    </svg>
                </label>
            </div>
        </div>
<?php
        return ob_get_clean();
    }
}
