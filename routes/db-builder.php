<?php
global $app;

// Example route showing the db-builder plugin. Copy into your app's routes/
// folder (or use as reference), gated behind APP_DEBUG the same way as
// Wisp's other demo routes.
if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
    $app->get("/db-builder-demo", function($req) {
        try {
            $driver = strtolower($_ENV['DB_CONNECTION'] ?? 'mysql');
            $tableName = ($driver === 'sqlite') ? 'sqlite_master' : 'information_schema.tables';
            $results = DB::table($tableName)->limit(1)->get();
            return Response::json([
                "status" => "success",
                "message" => "Query builder works!",
                "data" => $results
            ]);
        } catch (\Exception $e) {
            return Response::json([
                "status" => "error",
                "message" => $e->getMessage()
            ], 500);
        }
    });
}
