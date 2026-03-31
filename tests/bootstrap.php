<?php

declare(strict_types=1);

require_once __DIR__.'/../../../../vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'IvanBaric\\Status\\Tests\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__.DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, $relative).'.php';

    if (is_file($path)) {
        require_once $path;
    }
});
