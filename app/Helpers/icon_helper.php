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

        $safe = preg_replace('/[^a-z0-9-]/', '', $name) ?: 'mark';

        return str_replace('<svg', '<svg class="icon icon-' . $safe . '" aria-hidden="true"', $svg);
    }
}
