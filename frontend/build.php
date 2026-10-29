<?php

/**
 * Copy the front-end source onto the paths CodeIgniter serves.
 * Edit files in frontend/, then run: php frontend/build.php
 */
$root = dirname(__DIR__);
$map  = [
    __DIR__ . DIRECTORY_SEPARATOR . 'desk'       => $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'desk',
    __DIR__ . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'js'  => $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'js',
    __DIR__ . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'css' => $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css',
    __DIR__ . DIRECTORY_SEPARATOR . 'icons'      => $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'icons',
];

$copied = 0;

foreach ($map as $from => $to) {
    if (! is_dir($from)) {
        fwrite(STDERR, "Missing source folder: {$from}\n");
        exit(1);
    }

    if (! is_dir($to) && ! mkdir($to, 0777, true) && ! is_dir($to)) {
        fwrite(STDERR, "Could not create {$to}\n");
        exit(1);
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($iterator as $item) {
        $target = $to . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

        if ($item->isDir()) {
            if (! is_dir($target) && ! mkdir($target, 0777, true) && ! is_dir($target)) {
                fwrite(STDERR, "Could not create {$target}\n");
                exit(1);
            }

            continue;
        }

        if (! copy($item->getPathname(), $target)) {
            fwrite(STDERR, "Could not copy {$item->getPathname()}\n");
            exit(1);
        }

        $copied++;
    }
}

echo "Copied {$copied} front-end files into public.\n";
