<?php

declare(strict_types=1);

namespace foun10\DeepL\Tests\Unit\Double;

use foun10\DeepL\Core\DeepL;

/**
 * DeepL with the translation call and the language state substituted, so the direction and the
 * arguments of a search-term translation can be inspected without a shop or an API key.
 */
class SearchingDeepL extends DeepL
{
    public $languageOnDemand = '';

    public $shopLanguageAbbr = 'de';

    public $translateActive = true;

    public $translateCalls = 0;

    public $lastFromLang = '';

    public $lastToLang = '';

    /** @var array */
    public $lastOptions = [];

    public function isDeepLTranslateActive(): bool
    {
        return $this->translateActive;
    }

    public function getActiveLanguageOnDemand(): string
    {
        return $this->languageOnDemand;
    }

    protected function getShopLanguageAbbr(): string
    {
        return $this->shopLanguageAbbr;
    }

    public function translateText(
        string $fromLang,
        string $toLang,
        string $text,
        ?array $translateOptions = []
    ): string {
        $this->translateCalls++;
        $this->lastFromLang = $fromLang;
        $this->lastToLang = $toLang;
        $this->lastOptions = $translateOptions ?? [];

        return '[' . $fromLang . '>' . $toLang . '] ' . $text;
    }
}
