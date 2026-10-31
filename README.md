# wisp-db-builder

A lightweight, zero-dependency, Laravel-compatible fluent query builder plugin for the [Wisp](https://github.com/tlek-tech/wisp) PHP micro-framework.

Replicates the developer experience and method chaining of Laravel's Query Builder while preserving Wisp's micro-footprint and high performance.

---

## Features

- **Laravel-Compatible Fluent API**: Intuitive, expressive, and familiar method signatures.
- **Driver-Aware Grammar**: Safe identifier quoting (backticks for MySQL, double-quotes for SQLite and PostgreSQL).
- **Zero Dependencies**: Pure PHP, no bloated external packages.
- **100% Backward Compatible**: Seamlessly integrates with `wisp-mvc` Active Record models (`Product::where(...)`).
- **Comprehensive Querying**:
  - `select()`, `addSelect()`, `selectRaw()`, `distinct()`
  - `where()`, `orWhere()`, `whereIn()`, `whereNotIn()`, `whereBetween()`, `whereNotBetween()`, `whereNull()`, `whereNotNull()`, `whereLike()`, `whereRaw()`
  - Nested where closures: `where(function($q) { $q->where(...)->orWhere(...); })`
  - `join()`, `leftJoin()`, `rightJoin()`, `crossJoin()`
  - `orderBy()`, `orderByDesc()`, `latest()`, `oldest()`, `inRandomOrder()`
  - `groupBy()`, `having()`, `orHaving()`, `havingRaw()`
  - `limit()` / `take()`, `offset()` / `skip()`
  - `count()`, `sum()`, `avg()`, `min()`, `max()`, `exists()`, `doesntExist()`
  - `first()`, `get()`, `find($id)`, `value('column')`, `pluck('column', 'key')`, `chunk()`
  - Built-in pagination: `paginate($perPage, $page)` returning items with metadata
  - Mutations: `insert()`, `insertGetId()`, `update()`, `updateOrInsert()`, `increment()`, `decrement()`, `delete()`, `truncate()`
  - Debugging helpers: `toSql()`, `getBindings()`, `dump()`, `dd()`
  - Conditionals: `when($condition, $callback)`, `unless()`, `tap()`

---

## Directory Structure

```text
plugins/db-builder/
├── db-builder.php           # Plugin entry point & Wisp\DB plugin registration
└── src/
    ├── Builder.php          # Main Fluent Query Builder class (Wisp\Builder)
    ├── Grammar.php          # SQL Compilation & Driver Identifier Quoting
    ├── Expression.php       # Raw SQL Expression wrapper (raw() / DB::raw)
    └── Paginator.php        # JSON-serializable pagination result object
```

---

## Installation

1. Copy the `plugins/db-builder/` folder from this repository into your Wisp project's `plugins/` folder:
   ```text
   your-wisp-project/
   └── plugins/
       └── db-builder/
           ├── db-builder.php
           └── src/
               ├── Builder.php
               ├── Grammar.php
               ├── Expression.php
               └── Paginator.php
   ```
2. That's it! Wisp's plugin loader automatically includes `plugins/db-builder/db-builder.php` on boot.

---

## Usage Examples

### 1. Basic Queries & Where Clauses

```php
use Wisp\DB;

// Simple where
$user = DB::table('users')->where('email', 'alice@example.com')->first();
$activeUsers = DB::table('users')->where('active', 1)->get();

// Operators & OrWhere
$results = DB::table('users')
    ->where('votes', '>', 100)
    ->orWhere('name', 'John')
    ->get();

// Where In / Between / Null
$users = DB::table('users')->whereIn('id', [1, 2, 3])->get();
$range = DB::table('users')->whereBetween('age', [18, 30])->get();
$verified = DB::table('users')->whereNotNull('email_verified_at')->get();

// Nested Where Closures
$users = DB::table('users')
    ->where('active', 1)
    ->where(function ($query) {
        $query->where('role', 'admin')
              ->orWhere('role', 'editor');
    })
    ->get();
```

### 2. Joins

```php
$posts = DB::table('posts')
    ->join('users', 'posts.user_id', '=', 'users.id')
    ->select('posts.id', 'posts.title', 'users.name as author_name')
    ->get();
```

### 3. Ordering, Grouping & Limit

```php
$users = DB::table('users')
    ->orderBy('created_at', 'desc')
    ->skip(10)
    ->take(5)
    ->get();

// Shorthands:
$latestUser = DB::table('users')->latest()->first();
$randomItem = DB::table('products')->inRandomOrder()->first();
```

### 4. Aggregates & Retrieval Helpers

```php
$total = DB::table('orders')->count();
$revenue = DB::table('orders')->where('status', 'completed')->sum('amount');
$average = DB::table('reviews')->avg('rating');
$exists = DB::table('users')->where('email', $email)->exists();

// Fast single value
$name = DB::table('users')->where('id', $id)->value('name');

// Pluck
$titles = DB::table('roles')->pluck('title', 'name'); // ['admin' => 'Administrator', ...]
```

### 5. Pagination & Chunks

```php
// Paginate
$paginator = DB::table('products')->where('in_stock', 1)->paginate(15);
// Returns Paginator object:
// $paginator->items()
// $paginator->total()
// $paginator->currentPage()
// $paginator->lastPage()
// $paginator->hasMorePages()
// $paginator->toArray() / json_encode($paginator)

// Memory-friendly Chunking
DB::table('users')->orderBy('id')->chunk(100, function ($users, $page) {
    foreach ($users as $user) {
        // Process user
    }
});
```

### 6. Raw SQL Expressions

```php
use function Wisp\raw;

$users = DB::table('users')
    ->select('department', raw('COUNT(*) as total'))
    ->groupBy('department')
    ->havingRaw('COUNT(*) > ?', [5])
    ->get();
```

### 7. Mutations (Insert, Update, Delete)

```php
// Insert and get generated ID
$userId = DB::table('users')->insertGetId([
    'name'  => 'Alice',
    'email' => 'alice@example.com'
]);

// Upsert (update or insert)
DB::table('users')->updateOrInsert(
    ['email' => 'bob@example.com'],
    ['name' => 'Bob', 'updated_at' => date('Y-m-d H:i:s')]
);

// Increment / Decrement
DB::table('posts')->where('id', $postId)->increment('views');
DB::table('posts')->where('id', $postId)->decrement('stock', 2);

// Delete
DB::table('users')->where('active', 0)->delete();
```

### 8. Inspection & Debugging

```php
$sql = DB::table('users')->where('age', '>', 20)->toSql();
$bindings = DB::table('users')->where('age', '>', 20)->getBindings();

// Or dump right away:
DB::table('users')->where('id', 1)->dump(); // prints SQL and bindings
```

---

## License

MIT
