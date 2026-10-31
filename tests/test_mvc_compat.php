<?php
// Test compatibility with wisp-mvc Model

require_once __DIR__ . '/../../wisp-core/src/DB.php';
require_once __DIR__ . '/../plugins/db-builder/db-builder.php';
require_once __DIR__ . '/../../wisp-mvc/plugins/mvc/mvc.php';

use Wisp\DB;
use Wisp\Model;

$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = ':memory:';

DB::execute("CREATE TABLE products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100),
    price DECIMAL(10, 2),
    stock INTEGER
)");

class TestProduct extends Model {
    protected string $table = 'products';
}

// 1. Create via Active Record
$p1 = new TestProduct(['name' => 'Mechanical Keyboard', 'price' => 99.50, 'stock' => 10]);
$p1->save();

$p2 = new TestProduct(['name' => 'Gaming Mouse', 'price' => 49.99, 'stock' => 25]);
$p2->save();

// 2. Query via Active Record using new DbBuilder methods
$items = TestProduct::where('price', '>', 50)->get();
echo "MVC Model items count > 50: " . count($items) . " (Expected: 1)\n";
if (count($items) === 1 && $items[0]->name === 'Mechanical Keyboard') {
    echo "[PASS] MVC Compatibility: Model::where()->get()\n";
} else {
    echo "[FAIL] MVC Compatibility\n";
    exit(1);
}

// 3. Test find
$found = TestProduct::find(2);
if ($found && $found->name === 'Gaming Mouse') {
    echo "[PASS] MVC Compatibility: Model::find()\n";
} else {
    echo "[FAIL] Model::find()\n";
    exit(1);
}

// 4. Test first
$first = TestProduct::where('stock', '<=', 10)->first();
if ($first && $first->name === 'Mechanical Keyboard') {
    echo "[PASS] MVC Compatibility: Model::first()\n";
} else {
    echo "[FAIL] Model::first()\n";
    exit(1);
}

echo "=== MVC COMPATIBILITY TEST PASSED! ===\n";
