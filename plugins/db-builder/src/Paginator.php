<?php
namespace Wisp;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * Class Paginator
 * 
 * Provides Laravel-style pagination metadata and allows direct iteration and JSON serialization.
 */
class Paginator implements IteratorAggregate, Countable, JsonSerializable {
    protected array $items;
    protected int $total;
    protected int $perPage;
    protected int $currentPage;
    protected int $lastPage;

    public function __construct(array $items, int $total, int $perPage, int $currentPage = 1) {
        $this->items = $items;
        $this->total = max(0, $total);
        $this->perPage = max(1, $perPage);
        $this->lastPage = max(1, (int)ceil($this->total / $this->perPage));
        $this->currentPage = max(1, min($currentPage, $this->lastPage));
    }

    /**
     * Get the slice of items for the current page.
     */
    public function items(): array {
        return $this->items;
    }

    /**
     * Get total number of items across all pages.
     */
    public function total(): int {
        return $this->total;
    }

    /**
     * Get items per page.
     */
    public function perPage(): int {
        return $this->perPage;
    }

    /**
     * Get the current page number.
     */
    public function currentPage(): int {
        return $this->currentPage;
    }

    /**
     * Get the last page number.
     */
    public function lastPage(): int {
        return $this->lastPage;
    }

    /**
     * Determine if there are more pages.
     */
    public function hasMorePages(): bool {
        return $this->currentPage < $this->lastPage;
    }

    /**
     * Determine if currently on the first page.
     */
    public function onFirstPage(): bool {
        return $this->currentPage <= 1;
    }

    /**
     * Get the index of the first item in current page.
     */
    public function firstItem(): ?int {
        return count($this->items) > 0 ? ($this->currentPage - 1) * $this->perPage + 1 : null;
    }

    /**
     * Get the index of the last item in current page.
     */
    public function lastItem(): ?int {
        return count($this->items) > 0 ? $this->firstItem() + count($this->items) - 1 : null;
    }

    /**
     * Convert paginator instance to array.
     */
    public function toArray(): array {
        return [
            'current_page' => $this->currentPage,
            'data'         => $this->items,
            'first_page'   => 1,
            'from'         => $this->firstItem(),
            'last_page'    => $this->lastPage,
            'per_page'     => $this->perPage,
            'to'           => $this->lastItem(),
            'total'        => $this->total,
            'has_more'     => $this->hasMorePages(),
        ];
    }

    public function jsonSerialize(): mixed {
        return $this->toArray();
    }

    public function getIterator(): Traversable {
        return new ArrayIterator($this->items);
    }

    public function count(): int {
        return count($this->items);
    }
}
