<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    foreach (['auth-access', 'academic', 'learning', 'workspaces', 'tools', 'media'] as $domain) {
        $routes = __DIR__."/{$domain}.php";
        if (is_file($routes)) {
            require $routes;
        }
    }
});

// Independently packaged tools use their own versioned transport contract.
if (is_file(__DIR__.'/tool-runtime.php')) {
    Route::prefix('tool/v1')->group(__DIR__.'/tool-runtime.php');
}
