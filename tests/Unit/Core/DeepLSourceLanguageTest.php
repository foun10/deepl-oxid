<?php

declare(strict_types=1);

namespace foun10\DeepL\Tests\Unit\Core;

use foun10\DeepL\Tests\Unit\Double\ConfigurableSourceDeepL;
use PHPUnit\Framework\TestCase;

/**
 * Tests for resolving the shop language translations are generated from.
 *
 * This used to be hardcoded to English at language id 1, which silently assumed every shop has
 * English configured as its second language. A German-only shop translating into French has to
 * start from German, and picking the wrong source language means every translation is generated
 * from text that may not even exist.
 *
 * The SEO prefix is part of the answer too: OXID builds URLs without a prefix for the shop's
 * default language, and several extensions branch on that being empty.
 */
class DeepLSourceLanguageTest extends TestCase
{
    private function deepL(string $configured, array $languages = null): ConfigurableSourceDeepL
    {
        $deepL = new ConfigurableSourceDeepL();
        $deepL->sourceLanguage = $configured;
        $deepL->languages = $languages ?? [
            ConfigurableSourceDeepL::language(0, 'de'),
            ConfigurableSourceDeepL::language(1, 'en'),
            ConfigurableSourceDeepL::language(2, 'fr'),
        ];

        return $deepL;
    }

    public function testResolvesTheConfiguredLanguage(): void
    {
        $this->assertSame(
            ['langId' => 2, 'langIso' => 'fr', 'langSeoPrefix' => 'fr'],
            $this->deepL('fr')->getShopLangForLanguageOnDemand()
        );
    }

    /**
     * OXID serves its default language without a URL prefix, so the prefix has to come back
     * empty - the extensions that rewrite links check exactly that.
     */
    public function testDefaultLanguageHasNoSeoPrefix(): void
    {
        $this->assertSame(
            ['langId' => 0, 'langIso' => 'de', 'langSeoPrefix' => ''],
            $this->deepL('de')->getShopLangForLanguageOnDemand()
        );
    }

    public function testIsNotConfusedByCasingOrSpaces(): void
    {
        $this->assertSame(2, $this->deepL('  FR ')->getShopLangForLanguageOnDemand()['langId']);
    }

    /**
     * Empty setting keeps the behaviour this method had before it was configurable, so an
     * existing installation that never sets it carries on unchanged.
     */
    public function testFallsBackToEnglishWhenUnset(): void
    {
        $this->assertSame(
            ['langId' => 1, 'langIso' => 'en', 'langSeoPrefix' => 'en'],
            $this->deepL('')->getShopLangForLanguageOnDemand()
        );
    }

    public function testFallsBackToEnglishForALanguageTheShopDoesNotHave(): void
    {
        $this->assertSame(
            ['langId' => 1, 'langIso' => 'en', 'langSeoPrefix' => 'en'],
            $this->deepL('xx')->getShopLangForLanguageOnDemand()
        );
    }

    /**
     * A shop without English at all still has to get a usable answer rather than an exception -
     * the previous hardcoded values are the last resort.
     */
    public function testKeepsTheOldHardcodedValuesWhenNothingMatches(): void
    {
        $deepL = $this->deepL('xx', [
            ConfigurableSourceDeepL::language(0, 'de'),
            ConfigurableSourceDeepL::language(1, 'fr'),
        ]);

        $this->assertSame(
            ['langId' => 1, 'langIso' => 'en', 'langSeoPrefix' => 'en'],
            $deepL->getShopLangForLanguageOnDemand()
        );
    }

    public function testPrefersTheConfiguredLanguageOverEnglish(): void
    {
        $this->assertSame('de', $this->deepL('de')->getShopLangForLanguageOnDemand()['langIso']);
    }
}
