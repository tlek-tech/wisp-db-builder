<?php
namespace Wisp;

/**
 * Class Expression
 * 
 * Represents a raw SQL expression that should not be escaped or wrapped by the Grammar.
 */
class Expression {
    protected string $value;

    public function __construct(string $value) {
        $this->value = $value;
    }

    /**
     * Get the raw value of the expression.
     */
    public function getValue(): string {
        return $this->value;
    }

    /**
     * String representation of the expression.
     */
    public function __toString(): string {
        return $this->getValue();
    }
}
