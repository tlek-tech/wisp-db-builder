# wisp-db-builder

A small fluent query builder plugin for the [Wisp](../wisp) PHP micro-framework's
`Wisp\DB` class.

Adds `select()`, `where()`, `limit()`, `get()`, `first()`, `insert()`, `update()`, and
`delete()` on top of `DB::query()` / `DB::execute()`, accessible via `DB::table('name')`.

## Requirements

Your Wisp core must support the plugin extension point this relies on:
- `DB::registerPlugin()` (single-slot query builder registry in `src/DB.php`)
- The `plugins/` auto-loader in `public/index.php` (loads `plugins/<name>/<name>.php`)

## Install

1. Copy the `plugins/db-builder/` folder from this repo into your Wisp project's
   `plugins/` folder:
   ```
   your-wisp-project/
   └── plugins/
       └── db-builder/
           └── db-builder.php
   ```
2. That's it — Wisp's plugin loader picks up `plugins/db-builder/db-builder.php`
   automatically on boot, which calls `DB::registerPlugin(DbBuilder::class)`.

## Usage

```php
$rows = DB::table('users')->where('active', 1)->limit(10)->get();
$user = DB::table('users')->where('id', $id)->first();

DB::table('users')->insert(['name' => 'Alice', 'email' => 'alice@example.com']);
DB::table('users')->where('id', $id)->update(['name' => 'Bob']);
DB::table('users')->where('id', $id)->delete();
```

See `routes/db-builder.php` in this repo for a runnable example (copy it into your
project's `routes/` folder if you want the `/db-builder-demo` endpoint).

## License

MIT
