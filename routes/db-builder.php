<?php
global $app;

// Example routes showing the enhanced db-builder plugin features.
// Copy into your app's routes/ folder, gated behind APP_DEBUG.
if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
    $app->get("/db-builder-demo", function($req) {
        try {
            $driver = strtolower($_ENV['DB_CONNECTION'] ?? 'mysql');
            $tableName = ($driver === 'sqlite') ? 'sqlite_master' : 'information_schema.tables';

            // Demonstrate toSql() and getBindings()
            $query = DB::table($tableName)->limit(1);
            $sql = $query->toSql();
            $results = $query->get();

            return Response::json([
                "status"  => "success",
                "message" => "Enhanced Laravel-compatible Query Builder is active!",
                "driver"  => $driver,
                "sql"     => $sql,
                "data"    => $results
            ]);
        } catch (\Exception $e) {
            return Response::json([
                "status"  => "error",
                "message" => $e->getMessage()
            ], 500);
        }
    });
}
