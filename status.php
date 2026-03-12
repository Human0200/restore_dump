<?php
require_once __DIR__ . '/config.php';

function statusFile(string $ns): string {
    return __DIR__ . '/status_' . $ns . '.json';
}

function updateStatus(string $ns, array $data): void {
    file_put_contents(statusFile($ns), json_encode($data));
}

function getStatus(string $ns): ?array {
    $file = statusFile($ns);
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    }
    return null;
}
