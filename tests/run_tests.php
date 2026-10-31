<?php
// Test runner for wisp-db-builder

require_once __DIR__ . '/../../wisp-core/src/DB.php';
require_once __DIR__ . '/../plugins/db-builder/db-builder.php';

use Wisp\DB;
use Wisp\Builder;
use Wisp\DbBuilder;
use Wisp\Grammar;
use Wisp\Expression;
use function Wisp\raw;

$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = ':memory:';

$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, ?string $detail = null): void {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] {$name}\n";
        $passed++;
    } else {
        echo " [FAIL] {$name}" . ($detail ? " -> {$detail}" : "") . "\n";
        $failed++;
    }
}

echo "=== RUNNING WISP DB BUILDER TESTS ===\n\n";

// 1. Setup in-memory sqlite tables
DB::execute("CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100),
    email VARCHAR(100),
    age INTEGER,
    status VARCHAR(20) DEFAULT 'active',
    created_at DATETIME
)");

DB::execute("CREATE TABLE posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    title VARCHAR(200),
    views INTEGER DEFAULT 0
)");

// Test Class Alias & Registration
assert_test("DbBuilder class exists and extends Builder", is_subclass_of(DbBuilder::class, Builder::class) || DbBuilder::class === Builder::class);
assert_test("DB::table returns Builder instance", DB::table('users') instanceof Builder);

// Test Insert Single & InsertGetId
$id1 = DB::table('users')->insertGetId([
    'name' => 'Alice',
    'email' => 'alice@example.com',
    'age' => 25,
    'status' => 'active',
    'created_at' => '2026-01-01 10:00:00'
]);
assert_test("insertGetId returns 1", $id1 === 1);

$id2 = DB::table('users')->insertGetId([
    'name' => 'Bob',
    'email' => 'bob@example.com',
    'age' => 30,
    'status' => 'active',
    'created_at' => '2026-01-02 10:00:00'
]);
assert_test("insertGetId returns 2", $id2 === 2);

// Test Batch Insert
$batchSuccess = DB::table('users')->insert([
    ['name' => 'Charlie', 'email' => 'charlie@example.com', 'age' => 35, 'status' => 'inactive', 'created_at' => '2026-01-03 10:00:00'],
    ['name' => 'Diana', 'email' => 'diana@example.com', 'age' => 20, 'status' => 'active', 'created_at' => '2026-01-04 10:00:00'],
]);
assert_test("Batch insert", $batchSuccess === true);

// Test Count
assert_test("Count users is 4", DB::table('users')->count() === 4);

// Test Where Basic & First
$alice = DB::table('users')->where('email', 'alice@example.com')->first();
assert_test("where basic find Alice", $alice !== null && $alice['name'] === 'Alice');

// Test Find by ID
$bob = DB::table('users')->find(2);
assert_test("find(2) returns Bob", $bob !== null && $bob['name'] === 'Bob');

// Test Where with Operator
$older = DB::table('users')->where('age', '>=', 30)->get();
assert_test("where with operator >= 30", count($older) === 2);

// Test WhereIn & WhereNotIn
$inUsers = DB::table('users')->whereIn('id', [1, 2])->get();
assert_test("whereIn [1, 2] returns 2 rows", count($inUsers) === 2);

$notInUsers = DB::table('users')->whereNotIn('id', [1, 2])->get();
assert_test("whereNotIn [1, 2] returns 2 rows", count($notInUsers) === 2);

// Test WhereBetween
$betweenUsers = DB::table('users')->whereBetween('age', [20, 30])->get();
assert_test("whereBetween [20, 30] returns 3 rows (Diana 20, Alice 25, Bob 30)", count($betweenUsers) === 3);

// Test WhereLike
$likeUsers = DB::table('users')->whereLike('email', '%@example.com')->get();
assert_test("whereLike %@example.com returns 4 rows", count($likeUsers) === 4);

// Test Nested Where: (status = 'active' AND (age < 25 OR age >= 30))
$nested = DB::table('users')
    ->where('status', 'active')
    ->where(function($q) {
        $q->where('age', '<', 25)
          ->orWhere('age', '>=', 30);
    })
    ->get();
// Alice(25, active) - no
// Bob(30, active) - yes
// Charlie(35, inactive) - no
// Diana(20, active) - yes
assert_test("Nested where with Closure", count($nested) === 2);

// Test Order By & Latest/Oldest
$latestUser = DB::table('users')->latest('id')->first();
assert_test("latest('id') returns Diana (id: 4)", $latestUser['id'] == 4);

$oldestUser = DB::table('users')->oldest('id')->first();
assert_test("oldest('id') returns Alice (id: 1)", $oldestUser['id'] == 1);

// Test Value & Pluck
$val = DB::table('users')->where('id', 1)->value('name');
assert_test("value('name') is Alice", $val === 'Alice');

$plucked = DB::table('users')->pluck('name', 'id');
assert_test("pluck('name', 'id') is keyed array", isset($plucked[1]) && $plucked[1] === 'Alice' && $plucked[4] === 'Diana');

// Test Aggregates: sum, avg, min, max
assert_test("sum('age') = 110", DB::table('users')->sum('age') == 110);
assert_test("min('age') = 20", DB::table('users')->min('age') == 20);
assert_test("max('age') = 35", DB::table('users')->max('age') == 35);
assert_test("avg('age') = 27.5", abs(DB::table('users')->avg('age') - 27.5) < 0.01);
assert_test("exists() = true", DB::table('users')->where('age', '>', 50)->doesntExist() === true);

// Test Joins
DB::table('posts')->insert([
    ['user_id' => 1, 'title' => 'Alice Post 1', 'views' => 10],
    ['user_id' => 1, 'title' => 'Alice Post 2', 'views' => 20],
    ['user_id' => 2, 'title' => 'Bob Post 1', 'views' => 5],
]);

$postsWithAuthors = DB::table('posts')
    ->join('users', 'posts.user_id', '=', 'users.id')
    ->select('posts.title', 'users.name as author_name')
    ->get();
assert_test("Join posts and users", count($postsWithAuthors) === 3 && $postsWithAuthors[0]['author_name'] === 'Alice');

// Test GroupBy & Having
$grouped = DB::table('posts')
    ->select('user_id', raw('COUNT(*) as total_posts'))
    ->groupBy('user_id')
    ->having('user_id', '>=', 1)
    ->havingRaw('COUNT(*) >= 2')
    ->get();
assert_test("groupBy and having", count($grouped) === 1 && $grouped[0]['user_id'] == 1);

// Test Conditionals: when & unless
$search = 'Bob';
$filtered = DB::table('users')
    ->when($search, function($q, $val) {
        $q->where('name', $val);
    })
    ->get();
assert_test("when() applied when truthy", count($filtered) === 1 && $filtered[0]['name'] === 'Bob');

// Test Pagination
$page1 = DB::table('users')->orderBy('id', 'asc')->paginate(2, 1);
assert_test("Paginate page 1 has 2 items", count($page1->items()) === 2);
assert_test("Paginate total is 4", $page1->total() === 4);
assert_test("Paginate lastPage is 2", $page1->lastPage() === 2);
assert_test("Paginate hasMorePages is true", $page1->hasMorePages() === true);
$page2 = DB::table('users')->orderBy('id', 'asc')->paginate(2, 2);
assert_test("Paginate page 2 hasMorePages is false", $page2->hasMorePages() === false);

// Test Chunk
$chunkCount = 0;
DB::table('users')->orderBy('id')->chunk(2, function($chunk) use (&$chunkCount) {
    $chunkCount += count($chunk);
});
assert_test("chunk(2) processed all 4 records", $chunkCount === 4);

// Test Increment & Decrement
DB::table('posts')->where('id', 1)->increment('views', 5);
$post1 = DB::table('posts')->find(1);
assert_test("increment('views', 5) from 10 to 15", $post1['views'] == 15);

DB::table('posts')->where('id', 1)->decrement('views', 2);
$post1 = DB::table('posts')->find(1);
assert_test("decrement('views', 2) from 15 to 13", $post1['views'] == 13);

// Test UpdateOrInsert
DB::table('users')->updateOrInsert(['email' => 'alice@example.com'], ['age' => 26]);
assert_test("updateOrInsert updates existing record", DB::table('users')->where('email', 'alice@example.com')->value('age') == 26);

DB::table('users')->updateOrInsert(['email' => 'new@example.com'], ['name' => 'New User', 'age' => 40]);
assert_test("updateOrInsert inserts when not found", DB::table('users')->where('email', 'new@example.com')->value('name') === 'New User');

// Test toSql & getBindings
$sampleQuery = DB::table('users')->where('status', 'active')->whereIn('age', [20, 25])->orderBy('id', 'desc')->limit(5);
assert_test("toSql() generates valid SQL string", str_contains($sampleQuery->toSql(), 'SELECT * FROM "users" WHERE "status" = ? AND "age" IN (?, ?) ORDER BY "id" DESC LIMIT 5'));
assert_test("getBindings() returns correct bindings", $sampleQuery->getBindings() === ['active', 20, 25]);

// Test Delete
$deletedCount = DB::table('users')->delete($id2);
assert_test("delete(id) removed 1 record", $deletedCount === 1 && DB::table('users')->find(2) === null);

// Test Truncate
DB::table('posts')->truncate();
assert_test("truncate() empties the table", DB::table('posts')->count() === 0);

echo "\n=== TEST RESULTS: {$passed} passed, {$failed} failed ===\n";

if ($failed > 0) {
    exit(1);
}
