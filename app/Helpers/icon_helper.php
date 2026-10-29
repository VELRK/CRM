<?php

if (! function_exists('icon')) {
    function icon(string $name): string
    {
        $path = FCPATH . 'icons' . DIRECTORY_SEPARATOR . $name . '.svg';

        if (! is_file($path)) {
            return '';
        }

        $svg = (string) file_get_contents($path);
        $svg = preg_replace('/\s(width|height)="[^"]*"/', '', $svg) ?? $svg;

        return str_replace('<svg', '<svg class="icon" aria-hidden="true"', $svg);
    }
}
