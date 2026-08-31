<?php

declare(strict_types=1);

namespace foun10\DeepL\Tests\Unit\Core;

use foun10\DeepL\Tests\Unit\Double\SearchingDeepL;
use PHPUnit\Framework\TestCase;

/**
 * Tests for translating a search term back into the shop's language.
 *
 * A visitor reading the shop in an on-demand language types their query in that language, while
 * the catalogue is stored in the shop's own - so the term has to travel the opposite way to
 * everything else the module translates. Getting the direction wrong produces a search that
 * silently returns nothing, which looks like an empty catalogue rather than a bug.
 */
class DeepLSearchTermTest extends TestCase
{
    private function deepL(string $langOnDemand = 'es', bool $active = true): SearchingDeepL
    {
        $deepL = new SearchingDeepL();
        $deepL->languageOnDemand = $langOnDemand;
        $deepL->translateActive = $active;
        $deepL->shopLanguageAbbr = 'de';

        return $deepL;
    }

    public function testTranslatesFromTheReadingLanguageIntoTheShopLanguage(): void
    {
        $deepL = $this->deepL();

        $this->assertSame('[es>de] sofá', $deepL->translateSearchTerm('sofá'));
    }

    /**
     * The direction is the whole point: everything else in this module translates shop language
     * to visitor language, this one goes the other way.
     */
    public function testTranslatesInTheOppositeDirectionToPageContent(): void
    {
        $deepL = $this->deepL();
        $deepL->translateSearchTerm('sofá');

        $this->assertSame('es', $deepL->lastFromLang, 'source must be the language the visitor is reading');
        $this->assertSame('de', $deepL->lastToLang, 'target must be the language the catalogue is stored in');
    }

    public function testLeavesTheTermAloneWithoutALanguageOnDemand(): void
    {
        $deepL = $this->deepL('');

        $this->assertSame('Sofa', $deepL->translateSearchTerm('Sofa'));
        $this->assertSame(0, $deepL->translateCalls, 'no API call for a shop-language search');
    }

    public function testLeavesTheTermAloneWhenTranslationIsSwitchedOff(): void
    {
        $deepL = $this->deepL('es', false);

        $this->assertSame('sofá', $deepL->translateSearchTerm('sofá'));
        $this->assertSame(0, $deepL->translateCalls);
    }

    /**
     * An empty search must not be sent to the API - a visitor submitting the empty form would
     * otherwise cost a request for nothing.
     */
    public function testDoesNotTranslateAnEmptyTerm(): void
    {
        $deepL = $this->deepL();

        $this->assertSame('', $deepL->translateSearchTerm(''));
        $this->assertSame(0, $deepL->translateCalls);
    }

    /**
     * No translate options are passed on purpose: a search term is plain text, and asking for
     * html tag handling would make DeepL treat stray characters as markup.
     */
    public function testSendsNoTagHandlingOptions(): void
    {
        $deepL = $this->deepL();
        $deepL->translateSearchTerm('sofá');

        $this->assertSame([], $deepL->lastOptions);
    }
}
