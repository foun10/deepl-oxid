<?php

declare(strict_types=1);

namespace foun10\DeepL\Tests\Unit\Double;

use foun10\DeepL\Core\DeepL;

/**
 * DeepL with the source-language setting and the shop's language list substituted, so the
 * resolution can be tested without a shop.
 */
class ConfigurableSourceDeepL extends DeepL
{
    /** Value the module setting returns. */
    public $sourceLanguage = '';

    /** @var object[] Languages the fake shop has configured. */
    public $languages = [];

    /** @var array<string, string> Value of the on-demand language setting. */
    public $languagesOnDemand = [];

    public $translateActive = true;

    /**
     * Mirrors what OXID's getLanguageArray() hands back: objects carrying id and abbr.
     */
    public static function language(int $id, string $abbr): object
    {
        $language = new \stdClass();
        $language->id = $id;
        $language->abbr = $abbr;

        return $language;
    }

    protected function getModuleSetting(string $name): string
    {
        return $name === self::SOURCE_LANGUAGE_SETTING ? $this->sourceLanguage : '';
    }

    protected function getShopLanguages(): array
    {
        return $this->languages;
    }

    protected function getModuleSettingCollection(string $name): array
    {
        return $name === self::LANGUAGES_ON_DEMAND_SETTING ? $this->languagesOnDemand : [];
    }

    public function isDeepLTranslateActive(): bool
    {
        return $this->translateActive;
    }

    public function exposedGetDeepLLang(string $lang): string
    {
        return $this->getDeepLLang($lang);
    }
}
