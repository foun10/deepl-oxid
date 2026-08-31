<?php

declare(strict_types=1);

namespace foun10\DeepL\Tests\Integration;

use OxidEsales\Eshop\Core\ViewConfig;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Templating\TemplateRendererBridgeInterface;
use PHPUnit\Framework\TestCase;

/**
 * Renders the language switcher and checks the module's languages actually reach the page.
 *
 * The rest of the suite proves the module is wired into the shop and that its logic is right,
 * but nothing rendered a storefront template - so a template hook that stops firing would go
 * unnoticed. That is not hypothetical: UtilsView::parseThroughSmarty() overrode a method OXID 7
 * does not have and silently did nothing, and the Twig extension in this module rendered
 * nothing at all for a while because the language list it reads was empty.
 *
 * The view config is substituted with one returning a fixed pair of languages, so the test is
 * about the template wiring rather than about configuration: if the block name changes, the
 * extension path stops matching, or the module template is dropped from the chain, this fails.
 */
class LanguageWidgetTest extends TestCase
{
    /** The theme block this module extends, and the template that declares it. */
    private const WIDGET_TEMPLATE = 'widget/header/languages.html.twig';

    /** The theme whose blocks this module extends. */
    private const THEME = 'apex';

    /**
     * A freshly set up shop does not necessarily have an active theme - the CI shops do not,
     * because nothing in the headless setup activates one. Rendering then fails inside OXID
     * rather than in the module ("Theme ID is not configured"), so the test establishes the
     * precondition instead of depending on shop state.
     */
    protected function setUp(): void
    {
        \OxidEsales\Eshop\Core\Registry::getConfig()->setConfigParam('sTheme', self::THEME);
    }

    /** Public because the anonymous view config below reads it - a private constant is
     *  out of reach there. */
    public const TEST_LANGUAGES = [
        ['langIso' => 'zz', 'langName' => 'Testsprache', 'langUrl' => 'http://localhost/?langOnDemand=zz'],
        ['langIso' => 'yy', 'langName' => 'Zweitsprache', 'langUrl' => 'http://localhost/?langOnDemand=yy'],
    ];

    private function render(): string
    {
        $renderer = ContainerFactory::getInstance()
            ->getContainer()
            ->get(TemplateRendererBridgeInterface::class)
            ->getTemplateRenderer();

        return $renderer->renderTemplate(self::WIDGET_TEMPLATE, [
            'oViewConf' => $this->viewConfigOfferingTestLanguages(),
            'oView' => oxNew(\OxidEsales\Eshop\Application\Controller\StartController::class),
            // The theme only renders the switcher when the shop has more than one language of
            // its own, so two are supplied here - they also prove the module appends rather
            // than replaces.
            'oxcmp_lang' => [
                $this->shopLanguage(0, 'de', 'Deutsch', true),
                $this->shopLanguage(1, 'en', 'English', false),
            ],
        ]);
    }

    private function viewConfigOfferingTestLanguages(): ViewConfig
    {
        return new class extends ViewConfig {
            public function getOnDemandLanguageUrls(): array
            {
                return LanguageWidgetTest::TEST_LANGUAGES;
            }

            public function getActiveLanguageOnDemand(): string
            {
                return 'zz';
            }
        };
    }

    private function shopLanguage(int $id, string $abbr, string $name, bool $selected): object
    {
        $language = new \stdClass();
        $language->id = $id;
        $language->abbr = $abbr;
        $language->name = $name;
        $language->selected = $selected;
        $language->link = 'http://localhost/?lang=' . $id;

        return $language;
    }

    public function testTheSwitcherOffersTheOnDemandLanguages(): void
    {
        $output = $this->render();

        foreach (self::TEST_LANGUAGES as $language) {
            $this->assertStringContainsString(
                $language['langName'],
                $output,
                $language['langName'] . ' is missing from the language switcher - the module template '
                . 'is no longer extending ' . self::WIDGET_TEMPLATE
            );
            $this->assertStringContainsString($language['langUrl'], $output);
        }
    }

    /**
     * The module appends to the theme's own output. If it ever replaced the block instead, the
     * shop's languages would vanish from the switcher and only the DeepL ones would remain.
     */
    public function testTheShopsOwnLanguagesSurvive(): void
    {
        $output = $this->render();

        $this->assertStringContainsString('Deutsch', $output);
        $this->assertStringContainsString('English', $output);
    }

    public function testTheActiveOnDemandLanguageIsMarked(): void
    {
        $output = $this->render();

        // Deliberately a window rather than a tight pattern: the themes mark the active entry
        // differently - one puts the class on the link, the other on the surrounding list item -
        // and the test should care that it is marked at all, not how.
        $this->assertMatchesRegularExpression(
            '/active.{0,160}"?zz|zz.{0,160}active/s',
            $output,
            'the language currently being read is not marked as active in the switcher'
        );
    }
}
