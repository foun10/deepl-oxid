<?php

declare(strict_types=1);

namespace foun10\DeepL\Tests\Unit\Double;

/**
 * Stands in for the Translation model. Not a PHPUnit mock because Translation extends OXID's
 * BaseModel, which cannot be autoloaded without a bootstrapped shop.
 *
 * Records what it was looked up by, so tests can assert on the cache key without reaching
 * into protected hashing methods.
 */
class FakeTranslationModel
{
    /** Whether loadByParameter() should report a cache hit. */
    public $loaded = false;

    /** Translation handed back on a cache hit. */
    public $storedTranslation = '';

    /** @var array [fromLang, toLang, textHash, optionHash] of the last lookup. */
    public $lastLookup = [];

    /** @var array Fields passed to assign(). */
    public $assigned = [];

    public $saveCount = 0;

    /** @var FakeField|null Read by the production code on a cache hit. */
    public $foun10deepltranslations__foun10translatedtext;

    public function loadByParameter(string $fromLang, string $toLang, string $textHash, string $optionHash): bool
    {
        $this->lastLookup = [$fromLang, $toLang, $textHash, $optionHash];

        if ($this->loaded) {
            $this->foun10deepltranslations__foun10translatedtext = new FakeField($this->storedTranslation);
        }

        return $this->loaded;
    }

    public function assign(array $data): void
    {
        $this->assigned = $data;
    }

    public function save(): bool
    {
        $this->saveCount++;

        return true;
    }
}
