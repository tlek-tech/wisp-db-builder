<?php
namespace Wisp;

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
