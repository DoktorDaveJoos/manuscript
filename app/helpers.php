<?php

if (! function_exists('cssEscape')) {
    /**
     * Escape a string for safe use inside CSS content: "..." properties.
     */
    function cssEscape(string $value): string
    {
        $value = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

        return preg_replace('/[\r\n]+/', '', $value);
    }
}
