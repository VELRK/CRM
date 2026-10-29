<?php

/**
 * @param array<int, string> $designationByUser
 */
function person_label(string $name, int $userId, array $designationByUser = []): string
{
    $name  = trim($name);
    $extra = trim((string) ($designationByUser[$userId] ?? ''));

    if ($name === '') {
        return $extra !== '' ? $extra : '—';
    }

    return $extra === '' ? $name : $name . ' · ' . $extra;
}
