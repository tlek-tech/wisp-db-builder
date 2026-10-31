<?php
namespace Wisp;

class DbBuilder {
    protected string $table;
    protected array $columns = ['*'];
    protected array $wheres = [];
    protected ?int $limit = null;

    public function __construct(string $table) {
        $this->table = $table;
    }

    /**
     * Set the columns to select.
     */
    public function select(...$columns): self {
        if (empty($columns)) {
            $this->columns = ['*'];
            return $this;
        }
        if (is_array($columns[0])) {
            $this->columns = $columns[0];
        } else {
            $this->columns = $columns;
        }
        return $this;
    }

    /**
     * Add a basic where clause to the query.
     */
    public function where($column, $operator = null, $value = null): self {
        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        } elseif ($operator === null) {
            $operator = '=';
        }

        $this->wheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value
        ];
        return $this;
    }

    /**
     * Set the limit for the query.
     */
    public function limit(int $limit): self {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Compile the select SQL statement.
     */
    protected function compileSelect(&$params): string {
        $sql = "SELECT " . implode(', ', $this->columns) . " FROM {$this->table}";

        if (!empty($this->wheres)) {
            $sql .= " WHERE " . $this->compileWheres($params);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT " . (int)$this->limit;
        }

        return $sql;
    }

    /**
     * Compile the where clauses into SQL and populate bindings.
     */
    protected function compileWheres(&$params): string {
        $clauses = [];
        foreach ($this->wheres as $w) {
            if ($w['value'] === null) {
                $op = strtoupper($w['operator']);
                if ($op === '=' || $op === 'IS') {
                    $clauses[] = "{$w['column']} IS NULL";
                } else {
                    $clauses[] = "{$w['column']} IS NOT NULL";
                }
            } else {
                $clauses[] = "{$w['column']} {$w['operator']} ?";
                $params[] = $w['value'];
            }
        }
        return implode(' AND ', $clauses);
    }

    /**
     * Run the query as a SELECT statement and return results.
     */
    public function get(): array {
        $params = [];
        $sql = $this->compileSelect($params);
        return DB::query($sql, $params);
    }

    /**
     * Get the first result of the query.
     */
    public function first() {
        $this->limit(1);
        $results = $this->get();
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Insert a new record or multiple records.
     */
    public function insert(array $values): bool {
        if (empty($values)) {
            return false;
        }

        // Check if 2D array (list of multiple inserts)
        if (isset($values[0]) && is_array($values[0])) {
            $success = true;
            foreach ($values as $row) {
                if (!$this->insert($row)) {
                    $success = false;
                }
            }
            return $success;
        }

        $columns = array_keys($values);
        $placeholders = array_fill(0, count($columns), '?');
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $params = array_values($values);

        return DB::execute($sql, $params) > 0;
    }

    /**
     * Update records matching the where conditions.
     */
    public function update(array $values): int {
        if (empty($values)) {
            return 0;
        }

        $sets = [];
        $params = [];
        foreach ($values as $col => $val) {
            $sets[] = "{$col} = ?";
            $params[] = $val;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets);

        if (!empty($this->wheres)) {
            $sql .= " WHERE " . $this->compileWheres($params);
        }

        return DB::execute($sql, $params);
    }

    /**
     * Delete records matching the where conditions.
     */
    public function delete(): int {
        $sql = "DELETE FROM {$this->table}";
        $params = [];

        if (!empty($this->wheres)) {
            $sql .= " WHERE " . $this->compileWheres($params);
        }

        return DB::execute($sql, $params);
    }
}

// Register the builder in Wisp\DB
DB::registerPlugin(DbBuilder::class);
