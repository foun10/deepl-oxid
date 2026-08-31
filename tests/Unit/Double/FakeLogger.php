<?php

declare(strict_types=1);

namespace foun10\DeepL\Tests\Unit\Double;

/**
 * Records log calls instead of writing them. Not a full PSR-3 implementation on purpose - the
 * getLogger() seam has no declared return type, and only warning() and error() are used.
 */
class FakeLogger
{
    /** @var string[] */
    public $warnings = [];

    /** @var string[] */
    public $errors = [];

    public function warning($message, array $context = []): void
    {
        $this->warnings[] = (string) $message;
    }

    public function error($message, array $context = []): void
    {
        $this->errors[] = (string) $message;
    }
}
