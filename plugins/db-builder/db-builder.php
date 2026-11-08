<?php
namespace Wisp;

// Polyfills for PHP < 8.0 compatibility
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        return $needle === '' || $needle === substr($haystack, -strlen($needle));
    }
}

// 1. Require class files in modular src/ directory
require_once __DIR__ . '/src/Expression.php';
require_once __DIR__ . '/src/Paginator.php';
require_once __DIR__ . '/src/Grammar.php';
require_once __DIR__ . '/src/Builder.php';

// 2. Backward-compatibility alias for wisp-mvc and legacy code
if (!class_exists('Wisp\DbBuilder', false)) {
    class DbBuilder extends Builder {}
}

// 3. Register query builder plugin in Wisp\DB
if (class_exists('Wisp\DB')) {
    DB::registerPlugin(Builder::class);
}

// 4. Global helper function for creating raw SQL expressions
if (!function_exists('Wisp\raw')) {
    function raw(string $value): Expression {
        return new Expression($value);
    }
}
