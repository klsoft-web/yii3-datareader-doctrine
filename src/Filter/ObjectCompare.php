<?php

declare(strict_types=1);

namespace Klsoft\Yii3DataReaderDoctrine\Filter;

use Yiisoft\Data\Reader\FilterInterface;

/**
 * `Compare` filter is a base class that defines a criteria for comparing field value with a given value.
 */
abstract class ObjectCompare implements FilterInterface
{
    /**
     * @param string $field Name of the field to compare.
     * @param bool|float|int|string|object $value Value to compare to.
     */
    final public function __construct(
        public readonly string                       $field,
        public readonly bool|float|int|string|object $value,
    )
    {
    }

    /**
     * @param bool|float|int|string|object $value Value to compare to.
     */
    final public function withValue(bool|float|int|string|object $value): static
    {
        return new static($this->field, $value);
    }
}
