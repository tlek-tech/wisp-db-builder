<?php
namespace Wisp;

use Closure;

/**
 * Class Builder
 * 
 * Fluent Query Builder designed with Laravel-compatible API for the Wisp PHP framework.
 */
class Builder {
    public string $table;
    public array $columns = ['*'];
    public bool $distinct = false;
    public array $joins = [];
    public array $wheres = [];
    public array $groups = [];
    public array $havings = [];
    public array $orders = [];
    public ?int $limit = null;
    public ?int $offset = null;

    protected array $selectBindings = [];
    protected ?Grammar $grammar = null;

    public function __construct(string $table, ?Grammar $grammar = null) {
        $this->table = $table;
        $this->grammar = $grammar ?? new Grammar();
    }

    /**
     * Get the Grammar instance.
     */
    public function getGrammar(): Grammar {
        if ($this->grammar === null) {
            $this->grammar = new Grammar();
        }
        return $this->grammar;
    }

    /**
     * Set a custom Grammar instance.
     */
    public function setGrammar(Grammar $grammar): self {
        $this->grammar = $grammar;
        return $this;
    }

    /**
     * Set the columns to select.
     */
    public function select(...$columns): self {
        $this->columns = [];
        $this->selectBindings = [];

        if (empty($columns)) {
            $this->columns = ['*'];
            return $this;
        }

        if (count($columns) === 1 && is_array($columns[0])) {
            $columns = $columns[0];
        }

        $this->columns = $columns;
        return $this;
    }

    /**
     * Add new columns to the select statement.
     */
    public function addSelect(...$columns): self {
        if (count($columns) === 1 && is_array($columns[0])) {
            $columns = $columns[0];
        }

        if ($this->columns === ['*']) {
            $this->columns = $columns;
        } else {
            $this->columns = array_merge($this->columns, $columns);
        }

        return $this;
    }

    /**
     * Add a raw expression to the select clause.
     */
    public function selectRaw(string $expression, array $bindings = []): self {
        $this->addSelect(new Expression($expression));
        foreach ($bindings as $b) {
            $this->selectBindings[] = $b;
        }
        return $this;
    }

    /**
     * Get select bindings.
     */
    public function getSelectBindings(): array {
        return $this->selectBindings;
    }

    /**
     * Force the query to only return distinct results.
     */
    public function distinct(): self {
        $this->distinct = true;
        return $this;
    }

    // ==========================================
    // WHERE CLAUSES
    // ==========================================

    /**
     * Add a basic where clause.
     */
    public function where($column, $operator = null, $value = null, string $boolean = 'and'): self {
        // If column is Closure, handle nested where: $query->where(function($q) { ... })
        if ($column instanceof Closure) {
            return $this->whereNested($column, $boolean);
        }

        // Handle 2 arguments: where('active', 1) -> where('active', '=', 1)
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        } elseif ($value === null && $operator !== null && !in_array(strtoupper((string)$operator), ['IS', 'IS NOT', '=', '!=', '<>'])) {
            $value = $operator;
            $operator = '=';
        } elseif ($operator === null) {
            $operator = '=';
        }

        // If value is null, convert to IS NULL / IS NOT NULL
        if ($value === null) {
            $op = strtoupper((string)$operator);
            $not = in_array($op, ['!=', '<>', 'NOT', 'IS NOT']);
            return $this->whereNull($column, $boolean, $not);
        }

        $this->wheres[] = [
            'type'     => 'Basic',
            'column'   => $column,
            'operator' => $operator,
            'value'    => $value,
            'boolean'  => $boolean,
        ];

        return $this;
    }

    /**
     * Add an "or where" clause to the query.
     */
    public function orWhere($column, $operator = null, $value = null): self {
        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * Add a nested where clause.
     */
    protected function whereNested(Closure $callback, string $boolean = 'and'): self {
        $query = new self($this->table, $this->getGrammar());
        $callback($query);

        if (!empty($query->wheres)) {
            $this->wheres[] = [
                'type'    => 'Nested',
                'query'   => $query,
                'boolean' => $boolean,
            ];
        }

        return $this;
    }

    /**
     * Add a "where in" clause.
     */
    public function whereIn($column, array $values, string $boolean = 'and', bool $not = false): self {
        $this->wheres[] = [
            'type'    => 'In',
            'column'  => $column,
            'values'  => array_values($values),
            'boolean' => $boolean,
            'not'     => $not,
        ];
        return $this;
    }

    /**
     * Add an "or where in" clause.
     */
    public function orWhereIn($column, array $values): self {
        return $this->whereIn($column, $values, 'or');
    }

    /**
     * Add a "where not in" clause.
     */
    public function whereNotIn($column, array $values, string $boolean = 'and'): self {
        return $this->whereIn($column, $values, $boolean, true);
    }

    /**
     * Add an "or where not in" clause.
     */
    public function orWhereNotIn($column, array $values): self {
        return $this->whereNotIn($column, $values, 'or');
    }

    /**
     * Add a "where null" clause.
     */
    public function whereNull($column, string $boolean = 'and', bool $not = false): self {
        $this->wheres[] = [
            'type'    => 'Null',
            'column'  => $column,
            'boolean' => $boolean,
            'not'     => $not,
        ];
        return $this;
    }

    /**
     * Add an "or where null" clause.
     */
    public function orWhereNull($column): self {
        return $this->whereNull($column, 'or');
    }

    /**
     * Add a "where not null" clause.
     */
    public function whereNotNull($column, string $boolean = 'and'): self {
        return $this->whereNull($column, $boolean, true);
    }

    /**
     * Add an "or where not null" clause.
     */
    public function orWhereNotNull($column): self {
        return $this->whereNotNull($column, 'or');
    }

    /**
     * Add a "where between" clause.
     */
    public function whereBetween($column, array $values, string $boolean = 'and', bool $not = false): self {
        if (count($values) < 2) {
            throw new \InvalidArgumentException("whereBetween requires an array with at least 2 values.");
        }
        $this->wheres[] = [
            'type'    => 'Between',
            'column'  => $column,
            'values'  => array_values($values),
            'boolean' => $boolean,
            'not'     => $not,
        ];
        return $this;
    }

    /**
     * Add an "or where between" clause.
     */
    public function orWhereBetween($column, array $values): self {
        return $this->whereBetween($column, $values, 'or');
    }

    /**
     * Add a "where not between" clause.
     */
    public function whereNotBetween($column, array $values, string $boolean = 'and'): self {
        return $this->whereBetween($column, $values, $boolean, true);
    }

    /**
     * Add an "or where not between" clause.
     */
    public function orWhereNotBetween($column, array $values): self {
        return $this->whereNotBetween($column, $values, 'or');
    }

    /**
     * Add a "where like" clause.
     */
    public function whereLike($column, string $value, string $boolean = 'and'): self {
        return $this->where($column, 'LIKE', $value, $boolean);
    }

    /**
     * Add an "or where like" clause.
     */
    public function orWhereLike($column, string $value): self {
        return $this->whereLike($column, $value, 'or');
    }

    /**
     * Add a raw where clause.
     */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'and'): self {
        $this->wheres[] = [
            'type'     => 'Raw',
            'sql'      => $sql,
            'bindings' => $bindings,
            'boolean'  => $boolean,
        ];
        return $this;
    }

    /**
     * Add a raw or where clause.
     */
    public function orWhereRaw(string $sql, array $bindings = []): self {
        return $this->whereRaw($sql, $bindings, 'or');
    }

    // ==========================================
    // JOINS
    // ==========================================

    /**
     * Add a join clause to the query.
     */
    public function join(string $table, string $first, ?string $operator = null, ?string $second = null, string $type = 'inner'): self {
        if ($operator !== null && $second === null) {
            $second = $operator;
            $operator = '=';
        }

        $this->joins[] = [
            'type'     => $type,
            'table'    => $table,
            'first'    => $first,
            'operator' => $operator ?? '=',
            'second'   => $second,
        ];
        return $this;
    }

    /**
     * Add a left join to the query.
     */
    public function leftJoin(string $table, string $first, ?string $operator = null, ?string $second = null): self {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    /**
     * Add a right join to the query.
     */
    public function rightJoin(string $table, string $first, ?string $operator = null, ?string $second = null): self {
        return $this->join($table, $first, $operator, $second, 'right');
    }

    /**
     * Add a cross join to the query.
     */
    public function crossJoin(string $table): self {
        $this->joins[] = [
            'type'  => 'cross',
            'table' => $table,
        ];
        return $this;
    }

    // ==========================================
    // ORDERING, GROUPING, LIMIT & OFFSET
    // ==========================================

    /**
     * Add an "order by" clause.
     */
    public function orderBy($column, string $direction = 'asc'): self {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $this->orders[] = [
            'column'    => $column,
            'direction' => $direction,
        ];
        return $this;
    }

    /**
     * Add a descending "order by" clause.
     */
    public function orderByDesc($column): self {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Add an "order by" clause for a timestamp to get newest items first.
     */
    public function latest(string $column = 'created_at'): self {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Add an "order by" clause for a timestamp to get oldest items first.
     */
    public function oldest(string $column = 'created_at'): self {
        return $this->orderBy($column, 'asc');
    }

    /**
     * Put the query results in random order.
     */
    public function inRandomOrder(): self {
        $driver = $this->getGrammar()->getDriver();
        $random = ($driver === 'mysql') ? 'RAND()' : 'RANDOM()';
        $this->orders[] = [
            'column'    => new Expression($random),
            'direction' => '',
        ];
        return $this;
    }

    /**
     * Add a "group by" clause.
     */
    public function groupBy(...$columns): self {
        if (count($columns) === 1 && is_array($columns[0])) {
            $columns = $columns[0];
        }
        foreach ($columns as $c) {
            $this->groups[] = $c;
        }
        return $this;
    }

    /**
     * Add a "having" clause.
     */
    public function having(string|Expression $column, ?string $operator = null, $value = null, string $boolean = 'and'): self {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->havings[] = [
            'type'     => 'Basic',
            'column'   => $column,
            'operator' => $operator ?? '=',
            'value'    => $value,
            'boolean'  => $boolean,
        ];
        return $this;
    }

    /**
     * Add an "or having" clause.
     */
    public function orHaving(string|Expression $column, ?string $operator = null, $value = null): self {
        return $this->having($column, $operator, $value, 'or');
    }

    /**
     * Add a raw having clause.
     */
    public function havingRaw(string $sql, array $bindings = [], string $boolean = 'and'): self {
        $this->havings[] = [
            'type'     => 'Raw',
            'sql'      => $sql,
            'bindings' => $bindings,
            'boolean'  => $boolean,
        ];
        return $this;
    }

    /**
     * Add a raw or having clause.
     */
    public function orHavingRaw(string $sql, array $bindings = []): self {
        return $this->havingRaw($sql, $bindings, 'or');
    }

    /**
     * Set the "limit" value for the query.
     */
    public function limit(int $limit): self {
        $this->limit = max(0, $limit);
        return $this;
    }

    /**
     * Alias to set the "limit" value (Laravel compatible).
     */
    public function take(int $limit): self {
        return $this->limit($limit);
    }

    /**
     * Set the "offset" value for the query.
     */
    public function offset(int $offset): self {
        $this->offset = max(0, $offset);
        return $this;
    }

    /**
     * Alias to set the "offset" value (Laravel compatible).
     */
    public function skip(int $offset): self {
        return $this->offset($offset);
    }

    // ==========================================
    // CONDITIONALS & PIPELINES
    // ==========================================

    /**
     * Apply the callback if the given "value" is truthy.
     */
    public function when($value, callable $callback, ?callable $default = null): self {
        if ($value) {
            $callback($this, $value);
        } elseif ($default) {
            $default($this, $value);
        }
        return $this;
    }

    /**
     * Apply the callback if the given "value" is falsy.
     */
    public function unless($value, callable $callback, ?callable $default = null): self {
        if (!$value) {
            $callback($this, $value);
        } elseif ($default) {
            $default($this, $value);
        }
        return $this;
    }

    /**
     * Pass the query to a given closure and return the result.
     */
    public function tap(callable $callback): self {
        $callback($this);
        return $this;
    }

    // ==========================================
    // INSPECTION & COMPILATION
    // ==========================================

    /**
     * Get the SQL representation of the query.
     */
    public function toSql(): string {
        $bindings = [];
        return $this->getGrammar()->compileSelect($this, $bindings);
    }

    /**
     * Get the current query value bindings.
     */
    public function getBindings(): array {
        $bindings = [];
        $this->getGrammar()->compileSelect($this, $bindings);
        return $bindings;
    }

    /**
     * Dump the current SQL and bindings.
     */
    public function dump(): self {
        echo "SQL: " . $this->toSql() . "\n";
        echo "Bindings: " . json_encode($this->getBindings()) . "\n";
        return $this;
    }

    /**
     * Die and dump the current SQL and bindings.
     */
    public function dd(): void {
        $this->dump();
        exit(1);
    }

    // ==========================================
    // AGGREGATES & RETRIEVAL
    // ==========================================

    /**
     * Run the query as a SELECT statement and return results.
     * Optional column arguments can be passed: get('id', 'name') or get(['id', 'name']).
     */
    public function get(): array {
        if (func_num_args() > 0) {
            $args = func_get_args();
            $columns = (count($args) === 1 && is_array($args[0])) ? $args[0] : $args;
            $this->select($columns);
        }

        $bindings = [];
        $sql = $this->getGrammar()->compileSelect($this, $bindings);
        return DB::query($sql, $bindings);
    }

    /**
     * Execute the query and get the first result.
     * Optional column arguments can be passed: first('id', 'name') or first(['id', 'name']).
     */
    public function first() {
        if (func_num_args() > 0) {
            $args = func_get_args();
            $columns = (count($args) === 1 && is_array($args[0])) ? $args[0] : $args;
            $this->select($columns);
        }

        $this->limit(1);
        $results = $this->get();
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Find a record by its primary key ID.
     */
    public function find($id, array $columns = ['*'], string $primaryKey = 'id') {
        return $this->where($primaryKey, '=', $id)->first($columns);
    }

    /**
     * Get a single column's value from the first record.
     */
    public function value(string $column) {
        $result = $this->first([$column]);
        if ($result && isset($result[$column])) {
            return $result[$column];
        }
        return null;
    }

    /**
     * Get an array with the values of a given column.
     */
    public function pluck(string $column, ?string $key = null): array {
        $results = $this->get($key ? [$column, $key] : [$column]);
        $out = [];
        foreach ($results as $row) {
            if ($key !== null && isset($row[$key])) {
                $out[$row[$key]] = $row[$column] ?? null;
            } else {
                $out[] = $row[$column] ?? null;
            }
        }
        return $out;
    }

    /**
     * Retrieve the count result of the query.
     */
    public function count(string $columns = '*'): int {
        return (int)$this->aggregate('COUNT', $columns);
    }

    /**
     * Retrieve the minimum value of a given column.
     */
    public function min(string $column) {
        return $this->aggregate('MIN', $column);
    }

    /**
     * Retrieve the maximum value of a given column.
     */
    public function max(string $column) {
        return $this->aggregate('MAX', $column);
    }

    /**
     * Retrieve the sum of the values of a given column.
     */
    public function sum(string $column) {
        $val = $this->aggregate('SUM', $column);
        return $val !== null ? (is_numeric($val) ? 0 + $val : $val) : 0;
    }

    /**
     * Retrieve the average of the values of a given column.
     */
    public function avg(string $column) {
        $val = $this->aggregate('AVG', $column);
        return $val !== null ? (float)$val : null;
    }

    /**
     * Execute an aggregate function on the database.
     */
    protected function aggregate(string $function, string $column) {
        $previousColumns = $this->columns;
        $previousOrders = $this->orders;

        $wrapped = ($column === '*') ? '*' : $this->getGrammar()->wrap($column);
        $this->columns = [new Expression("{$function}({$wrapped}) AS aggregate")];
        $this->orders = [];

        $bindings = [];
        $sql = $this->getGrammar()->compileSelect($this, $bindings);

        // Restore state
        $this->columns = $previousColumns;
        $this->orders = $previousOrders;

        return DB::value($sql, $bindings, 0);
    }

    /**
     * Determine if any rows exist for the current query.
     */
    public function exists(): bool {
        $clone = clone $this;
        $clone->columns = [new Expression('1')];
        $clone->limit(1);

        $bindings = [];
        $sql = $clone->getGrammar()->compileSelect($clone, $bindings);
        $result = DB::first($sql, $bindings);
        return !empty($result);
    }

    /**
     * Determine if no rows exist for the current query.
     */
    public function doesntExist(): bool {
        return !$this->exists();
    }

    /**
     * Chunk the results of the query into smaller pieces for memory efficiency.
     */
    public function chunk(int $count, callable $callback): bool {
        $page = 1;
        do {
            $clone = clone $this;
            $results = $clone->offset(($page - 1) * $count)->limit($count)->get();
            $countResults = count($results);

            if ($countResults === 0) {
                break;
            }

            if ($callback($results, $page) === false) {
                return false;
            }

            unset($results);
            $page++;
        } while ($countResults === $count);

        return true;
    }

    /**
     * Paginate the given query into a Paginator instance.
     */
    public function paginate(int $perPage = 15, ?int $page = null): Paginator {
        $page = $page ?? (isset($_GET['page']) ? (int)$_GET['page'] : 1);
        $page = max(1, $page);

        // Calculate total count without limit and offset
        $total = (clone $this)->count();

        // Slice items
        $results = (clone $this)->offset(($page - 1) * $perPage)->limit($perPage)->get();

        return new Paginator($results, $total, $perPage, $page);
    }

    // ==========================================
    // MUTATIONS: INSERT, UPDATE, DELETE
    // ==========================================

    /**
     * Insert a new record or multiple records.
     */
    public function insert(array $values): bool {
        if (empty($values)) {
            return false;
        }

        $bindings = [];
        $sql = $this->getGrammar()->compileInsert($this, $values, $bindings);
        return DB::execute($sql, $bindings) > 0;
    }

    /**
     * Insert a new record and get the ID.
     */
    public function insertGetId(array $values, ?string $sequence = null): string|int {
        $this->insert($values);
        $id = DB::lastInsertId($sequence);
        return is_numeric($id) ? (int)$id : $id;
    }

    /**
     * Update records matching the where conditions.
     */
    public function update(array $values): int {
        if (empty($values)) {
            return 0;
        }

        $bindings = [];
        $sql = $this->getGrammar()->compileUpdate($this, $values, $bindings);
        return DB::execute($sql, $bindings);
    }

    /**
     * Insert or update a record matching the attributes, and fill it with values.
     */
    public function updateOrInsert(array $attributes, array $values = []): bool {
        $query = clone $this;
        foreach ($attributes as $key => $val) {
            $query->where($key, $val);
        }

        if (!$query->exists()) {
            return $this->insert(array_merge($attributes, $values));
        }

        if (empty($values)) {
            return true;
        }

        return (bool)$query->update($values);
    }

    /**
     * Increment a column's value by a given amount.
     */
    public function increment(string $column, int|float $amount = 1, array $extra = []): int {
        $wrapped = $this->getGrammar()->wrap($column);
        $extra[$column] = new Expression("{$wrapped} + " . (float)$amount);
        return $this->update($extra);
    }

    /**
     * Decrement a column's value by a given amount.
     */
    public function decrement(string $column, int|float $amount = 1, array $extra = []): int {
        $wrapped = $this->getGrammar()->wrap($column);
        $extra[$column] = new Expression("{$wrapped} - " . (float)$amount);
        return $this->update($extra);
    }

    /**
     * Delete records matching the where conditions.
     */
    public function delete($id = null): int {
        if ($id !== null) {
            $this->where('id', '=', $id);
        }

        $bindings = [];
        $sql = $this->getGrammar()->compileDelete($this, $bindings);
        return DB::execute($sql, $bindings);
    }

    /**
     * Truncate the table.
     */
    public function truncate(): void {
        $statements = $this->getGrammar()->compileTruncate($this);
        foreach ($statements as $sql) {
            DB::execute($sql);
        }
    }
}
