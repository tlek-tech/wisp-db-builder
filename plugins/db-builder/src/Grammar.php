<?php
namespace Wisp;

/**
 * Class Grammar
 * 
 * Compiles fluent query components into standard SQL statements with driver-aware identifier quoting.
 */
class Grammar {
    protected string $driver;

    public function __construct(?string $driver = null) {
        $this->driver = strtolower($driver ?? $_ENV['DB_CONNECTION'] ?? 'mysql');
    }

    /**
     * Get the driver being used.
     */
    public function getDriver(): string {
        return $this->driver;
    }

    /**
     * Wrap a value in keyword identifiers (backtick for mysql, double quotes for sqlite/postgres).
     */
    public function wrap(string|Expression $value): string {
        if ($value instanceof Expression) {
            return $value->getValue();
        }

        // If the value has an alias (e.g., 'table as alias' or 'table alias')
        if (stripos($value, ' as ') !== false) {
            $segments = preg_split('/\s+as\s+/i', $value);
            return $this->wrap($segments[0]) . ' AS ' . $this->wrapValue($segments[1]);
        }

        // If the value contains a dot (e.g., 'table.column')
        if (str_contains($value, '.')) {
            $segments = explode('.', $value);
            return implode('.', array_map([$this, 'wrapValue'], $segments));
        }

        return $this->wrapValue($value);
    }

    /**
     * Wrap a single segment value.
     */
    protected function wrapValue(string $value): string {
        $value = trim($value);
        if ($value === '*' || $value === '') {
            return $value;
        }

        $quote = ($this->driver === 'mysql') ? '`' : '"';
        return $quote . str_replace($quote, $quote . $quote, $value) . $quote;
    }

    /**
     * Wrap an array of values.
     */
    public function columnize(array $columns): string {
        return implode(', ', array_map([$this, 'wrap'], $columns));
    }

    /**
     * Parameterize an array of values into placeholders (?, ?, ...).
     */
    public function parameterize(array $values): string {
        return implode(', ', array_fill(0, count($values), '?'));
    }

    /**
     * Compile a SELECT query.
     */
    public function compileSelect(Builder $builder, array &$bindings): string {
        $components = [];

        // 1. SELECT [DISTINCT] columns
        $select = $builder->distinct ? 'SELECT DISTINCT ' : 'SELECT ';
        $columns = [];
        foreach ($builder->columns as $column) {
            if ($column instanceof Expression) {
                $columns[] = $column->getValue();
            } else {
                $columns[] = $this->wrap($column);
            }
        }
        $components['select'] = $select . implode(', ', $columns ?: ['*']);

        // Bindings from selectRaw if any
        foreach ($builder->getSelectBindings() as $b) {
            $bindings[] = $b;
        }

        // 2. FROM table
        $components['from'] = 'FROM ' . $this->wrap($builder->table);

        // 3. JOINS
        if (!empty($builder->joins)) {
            $joinSql = [];
            foreach ($builder->joins as $join) {
                $type = strtoupper($join['type']);
                $table = $this->wrap($join['table']);
                if ($type === 'CROSS') {
                    $joinSql[] = "CROSS JOIN {$table}";
                } else {
                    $first = $this->wrap($join['first']);
                    $second = $this->wrap($join['second']);
                    $joinSql[] = "{$type} JOIN {$table} ON {$first} {$join['operator']} {$second}";
                }
            }
            $components['joins'] = implode(' ', $joinSql);
        }

        // 4. WHERE
        if (!empty($builder->wheres)) {
            $whereSql = $this->compileWheres($builder->wheres, $bindings);
            if ($whereSql !== '') {
                $components['wheres'] = 'WHERE ' . $whereSql;
            }
        }

        // 5. GROUP BY
        if (!empty($builder->groups)) {
            $groups = array_map([$this, 'wrap'], $builder->groups);
            $components['groups'] = 'GROUP BY ' . implode(', ', $groups);
        }

        // 6. HAVING
        if (!empty($builder->havings)) {
            $havingSql = $this->compileHavings($builder->havings, $bindings);
            if ($havingSql !== '') {
                $components['havings'] = 'HAVING ' . $havingSql;
            }
        }

        // 7. ORDER BY
        if (!empty($builder->orders)) {
            $orderSql = [];
            foreach ($builder->orders as $order) {
                if ($order['column'] instanceof Expression) {
                    $orderSql[] = $order['column']->getValue();
                } else {
                    $orderSql[] = $this->wrap($order['column']) . ' ' . strtoupper($order['direction']);
                }
            }
            $components['orders'] = 'ORDER BY ' . implode(', ', $orderSql);
        }

        // 8. LIMIT
        if ($builder->limit !== null) {
            $components['limit'] = 'LIMIT ' . (int)$builder->limit;
        }

        // 9. OFFSET
        if ($builder->offset !== null) {
            $components['offset'] = 'OFFSET ' . (int)$builder->offset;
        }

        return implode(' ', $components);
    }

    /**
     * Compile where clauses into SQL and collect bindings.
     */
    public function compileWheres(array $wheres, array &$bindings): string {
        $sql = '';

        foreach ($wheres as $index => $where) {
            $boolean = ($index === 0) ? '' : strtoupper($where['boolean']) . ' ';
            $type = $where['type'];

            switch ($type) {
                case 'Basic':
                    $sql .= $boolean . $this->wrap($where['column']) . " {$where['operator']} ?";
                    $bindings[] = $where['value'];
                    break;

                case 'Null':
                    $clause = $where['not'] ? 'IS NOT NULL' : 'IS NULL';
                    $sql .= $boolean . $this->wrap($where['column']) . " {$clause}";
                    break;

                case 'In':
                    if (empty($where['values'])) {
                        // In an empty set is always FALSE (or TRUE if NOT IN)
                        $sql .= $boolean . ($where['not'] ? '1 = 1' : '0 = 1');
                    } else {
                        $clause = $where['not'] ? 'NOT IN' : 'IN';
                        $placeholders = $this->parameterize($where['values']);
                        $sql .= $boolean . $this->wrap($where['column']) . " {$clause} ({$placeholders})";
                        foreach ($where['values'] as $v) {
                            $bindings[] = $v;
                        }
                    }
                    break;

                case 'Between':
                    $clause = $where['not'] ? 'NOT BETWEEN' : 'BETWEEN';
                    $sql .= $boolean . $this->wrap($where['column']) . " {$clause} ? AND ?";
                    $bindings[] = $where['values'][0];
                    $bindings[] = $where['values'][1];
                    break;

                case 'Raw':
                    $sql .= $boolean . $where['sql'];
                    foreach ($where['bindings'] as $b) {
                        $bindings[] = $b;
                    }
                    break;

                case 'Nested':
                    $nestedBindings = [];
                    $nestedSql = $this->compileWheres($where['query']->wheres, $nestedBindings);
                    if ($nestedSql !== '') {
                        $sql .= $boolean . "({$nestedSql})";
                        foreach ($nestedBindings as $b) {
                            $bindings[] = $b;
                        }
                    }
                    break;
            }

            $sql .= ' ';
        }

        return trim($sql);
    }

    /**
     * Compile having clauses into SQL and collect bindings.
     */
    public function compileHavings(array $havings, array &$bindings): string {
        $sql = '';

        foreach ($havings as $index => $having) {
            $boolean = ($index === 0) ? '' : strtoupper($having['boolean']) . ' ';
            if ($having['type'] === 'Raw') {
                $sql .= $boolean . $having['sql'];
                foreach ($having['bindings'] as $b) {
                    $bindings[] = $b;
                }
            } else {
                $column = ($having['column'] instanceof Expression)
                    ? $having['column']->getValue()
                    : $this->wrap($having['column']);

                if ($having['value'] instanceof Expression) {
                    $sql .= $boolean . $column . " {$having['operator']} " . $having['value']->getValue();
                } else {
                    $sql .= $boolean . $column . " {$having['operator']} ?";
                    $bindings[] = $having['value'];
                }
            }
            $sql .= ' ';
        }

        return trim($sql);
    }

    /**
     * Compile an INSERT statement.
     */
    public function compileInsert(Builder $builder, array $values, array &$bindings): string {
        $table = $this->wrap($builder->table);

        if (empty($values)) {
            return "INSERT INTO {$table} DEFAULT VALUES";
        }

        // Check if multiple records
        $isMultiple = isset($values[0]) && is_array($values[0]);
        $columns = $isMultiple ? array_keys($values[0]) : array_keys($values);
        $columnList = $this->columnize($columns);

        $rowPlaceholders = [];
        if ($isMultiple) {
            foreach ($values as $row) {
                $rowPlaceholders[] = '(' . $this->parameterize($row) . ')';
                foreach ($columns as $col) {
                    $bindings[] = $row[$col] ?? null;
                }
            }
        } else {
            $rowPlaceholders[] = '(' . $this->parameterize($values) . ')';
            foreach ($values as $val) {
                $bindings[] = $val;
            }
        }

        return "INSERT INTO {$table} ({$columnList}) VALUES " . implode(', ', $rowPlaceholders);
    }

    /**
     * Compile an UPDATE statement.
     */
    public function compileUpdate(Builder $builder, array $values, array &$bindings): string {
        $table = $this->wrap($builder->table);

        $sets = [];
        foreach ($values as $column => $value) {
            if ($value instanceof Expression) {
                $sets[] = $this->wrap($column) . ' = ' . $value->getValue();
            } else {
                $sets[] = $this->wrap($column) . ' = ?';
                $bindings[] = $value;
            }
        }

        $sql = "UPDATE {$table} SET " . implode(', ', $sets);

        if (!empty($builder->wheres)) {
            $whereSql = $this->compileWheres($builder->wheres, $bindings);
            if ($whereSql !== '') {
                $sql .= ' WHERE ' . $whereSql;
            }
        }

        return $sql;
    }

    /**
     * Compile a DELETE statement.
     */
    public function compileDelete(Builder $builder, array &$bindings): string {
        $table = $this->wrap($builder->table);
        $sql = "DELETE FROM {$table}";

        if (!empty($builder->wheres)) {
            $whereSql = $this->compileWheres($builder->wheres, $bindings);
            if ($whereSql !== '') {
                $sql .= ' WHERE ' . $whereSql;
            }
        }

        return $sql;
    }

    /**
     * Compile a TRUNCATE statement.
     */
    public function compileTruncate(Builder $builder): array {
        $table = $this->wrap($builder->table);
        $rawTable = $builder->table;

        if ($this->driver === 'sqlite') {
            return [
                "DELETE FROM {$table}",
                "DELETE FROM sqlite_sequence WHERE name = '{$rawTable}'",
            ];
        }

        return [
            "TRUNCATE TABLE {$table}"
        ];
    }
}
