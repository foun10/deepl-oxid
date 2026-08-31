<?php

declare(strict_types=1);

namespace foun10\DeepL\Tests\Unit\Double;

/**
 * Stands in for OXID's Field object, of which the production code only reads ->rawValue.
 */
class FakeField
{
    /** @var string */
    public $rawValue;

    public function __construct(string $rawValue)
    {
        $this->rawValue = $rawValue;
    }
}
