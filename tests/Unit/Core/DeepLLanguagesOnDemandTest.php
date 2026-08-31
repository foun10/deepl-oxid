<?php

declare(strict_types=1);

namespace foun10\DeepL\Tests\Unit\Core;

use foun10\DeepL\Core\DeepL;
use foun10\DeepL\Tests\Unit\Double\ConfigurableSourceDeepL;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the list of languages offered on demand.
 *
 * The list used to be hardcoded to the 28 languages the shop this module was built for wanted.
 * Which languages to offer is a per-shop decision, so it is a setting now - with the full DeepL
 * target list as the default, so an existing installation that never touches it keeps behaving
 * as before.
 */
class DeepLLanguagesOnDemandTest extends TestCase
{
    private function deepL(array $configured): ConfigurableSourceDeepL
    {
        $deepL = new ConfigurableSourceDeepL();
        $deepL->languagesOnDemand = $configured;

        return $deepL;
    }

    public function testUsesTheConfiguredLanguages(): void
    {
        $this->assertSame(
            ['es' => 'Español', 'fr' => 'Français'],
            $this->deepL(['es' => 'Español', 'fr' => 'Français'])->getLanguagesOnDemand()
        );
    }

    /**
     * Clearing the setting is how a shop switches the feature off without deactivating the
     * module. It must not quietly fall back to offering everything.
     */
    public function testOffersNothingWhenTheSettingIsCleared(): void
    {
        $this->assertSame([], $this->deepL([])->getLanguagesOnDemand());
    }

    /**
     * A fresh installation still starts with the full list - that lives in metadata.php as the
     * setting's initial value, not as a fallback in the code.
     */
    public function testTheShippedDefaultCoversTheDeepLTargetLanguages(): void
    {
        $this->assertArrayHasKey('es', DeepL::DEFAULT_LANGUAGES_ON_DEMAND);
        $this->assertArrayHasKey('ja', DeepL::DEFAULT_LANGUAGES_ON_DEMAND);
        $this->assertGreaterThan(20, count(DeepL::DEFAULT_LANGUAGES_ON_DEMAND));
    }

    public function testNormalisesAbbreviations(): void
    {
        $this->assertSame(
            ['es' => 'Español'],
            $this->deepL([' ES ' => ' Español '])->getLanguagesOnDemand()
        );
    }

    /**
     * A label is a convenience, not a requirement - without one the abbreviation stands in, so a
     * half-filled setting still yields a usable switcher rather than blank entries.
     */
    public function testUsesTheAbbreviationWhenNoLabelIsGiven(): void
    {
        $this->assertSame(['es' => 'es'], $this->deepL(['es' => '  '])->getLanguagesOnDemand());
    }

    public function testIgnoresEntriesWithoutAnAbbreviation(): void
    {
        $this->assertSame(
            ['fr' => 'Français'],
            $this->deepL(['' => 'Nonsense', 'fr' => 'Français'])->getLanguagesOnDemand()
        );
    }

    public function testOffersNothingWhileTranslationIsSwitchedOff(): void
    {
        $deepL = $this->deepL(['es' => 'Español']);
        $deepL->translateActive = false;

        $this->assertSame([], $deepL->getLanguagesOnDemand());
    }

    /**
     * The shop-side abbreviations are mapped to DeepL's own codes on the way out, so adding a
     * language to the setting needs no further wiring.
     */
    public function testConfiguredLanguagesStillGetTheirDeepLCode(): void
    {
        $deepL = $this->deepL(['no' => 'Norsk', 'pt' => 'Português']);

        $this->assertSame('nb', $deepL->exposedGetDeepLLang('no'));
        $this->assertSame('pt-PT', $deepL->exposedGetDeepLLang('pt'));
        $this->assertSame('es', $deepL->exposedGetDeepLLang('es'));
    }
}
