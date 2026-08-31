<?php

declare(strict_types=1);

namespace foun10\DeepL\Tests\Integration;

use foun10\DeepL\Core\DeepL;
use OxidEsales\Eshop\Application\Controller\SearchController;
use OxidEsales\Eshop\Core\Registry;
use PHPUnit\Framework\TestCase;

/**
 * Checks that a search made while reading an on-demand language reaches the shop in the shop's
 * own language.
 *
 * The unit tests cover the translation itself; this one covers the wiring, which is where it
 * would break silently. The controller rewrites $_GET so that the controller, the search model
 * and the view all see the same term - if the extension stopped being called, the search would
 * simply return nothing and look like an empty catalogue.
 */
class SearchTranslationTest extends TestCase
{
    /** @var array */
    private $originalGet = [];

    /** @var array */
    private $originalServer = [];

    /** @var DeepL */
    private $fakeDeepL;

    protected function setUp(): void
    {
        $this->originalGet = $_GET;
        $this->originalServer = $_SERVER;

        // A frontend controller expects to be handling an actual request, and parent::init()
        // walks into processRequest() -> Request::getRequestUrl(). Nothing sets these three keys
        // on the CLI, and the OXID builds that read them without a guard emit a warning for the
        // missing key, which PHPUnit turns into a failure on PHP 8. They are the only $_SERVER
        // keys that method touches.
        //
        // The two URLs stay empty on purpose: getRequestUrl() then returns an empty string and
        // processRequest() skips its whole body, so no test can wander into an SEO redirect or
        // a write to oxseologs.
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '';
        $_SERVER['SCRIPT_URI'] = '';
    }

    protected function tearDown(): void
    {
        $_GET = $this->originalGet;
        $_SERVER = $this->originalServer;
        Registry::set(DeepL::class, null);
    }

    /**
     * @param string $languageOnDemand empty means the visitor reads a language the shop maintains
     */
    private function useFakeDeepL(string $languageOnDemand): void
    {
        $fake = new class($languageOnDemand) extends DeepL {
            /** @var string the text the controller handed over, for inspection by the test */
            public $receivedText = '';

            /** @var int */
            public $translateCalls = 0;

            private $fakeLanguageOnDemand;

            public function __construct(string $languageOnDemand)
            {
                $this->fakeLanguageOnDemand = $languageOnDemand;
            }

            public function isDeepLTranslateActive(): bool
            {
                return true;
            }

            public function getActiveLanguageOnDemand(): string
            {
                return $this->fakeLanguageOnDemand;
            }

            public function translateText(
                string $fromLang,
                string $toLang,
                string $text,
                ?array $translateOptions = []
            ): string {
                $this->translateCalls++;
                $this->receivedText = $text;

                return 'Sofa';
            }
        };

        Registry::set(DeepL::class, $fake);

        $this->fakeDeepL = $fake;
    }

    private function initSearchControllerWith(string $searchTerm): void
    {
        $_GET['searchparam'] = $searchTerm;

        $controller = oxNew(SearchController::class);
        $controller->init();
    }

    public function testTheSearchTermReachesTheShopInTheShopLanguage(): void
    {
        $this->useFakeDeepL('es');

        $this->initSearchControllerWith('sofá');

        $this->assertSame(
            'Sofa',
            $_GET['searchparam'],
            'the search term was not translated - the search would run against the catalogue '
            . 'in the wrong language and find nothing'
        );
    }

    public function testTheTermIsLeftAloneWithoutALanguageOnDemand(): void
    {
        $this->useFakeDeepL('');

        $this->initSearchControllerWith('Sofa');

        $this->assertSame('Sofa', $_GET['searchparam']);
    }

    public function testAnEmptySearchIsLeftAlone(): void
    {
        $this->useFakeDeepL('es');

        $this->initSearchControllerWith('');

        $this->assertSame('', $_GET['searchparam']);
    }

    /**
     * The term must be read raw, not through getRequestEscapedParameter(): that turns & < > " '
     * into entities, which would be sent to DeepL as text to translate and pay for.
     */
    public function testTheTermReachesTheApiWithoutHtmlEntities(): void
    {
        $this->useFakeDeepL('es');

        $this->initSearchControllerWith('Sofa & Sessel');

        $this->assertSame(
            'Sofa & Sessel',
            $this->fakeDeepL->receivedText,
            'the term was escaped before translation - DeepL would be billed for entity noise'
        );
    }

    /**
     * What is written back must be raw as well. OXID escapes on read, so writing an escaped value
     * would have it escaped a second time and "&" would reach the search box as "&amp;amp;".
     */
    public function testTheTermIsWrittenBackUnescapedSoOxidEscapesItOnlyOnce(): void
    {
        $this->useFakeDeepL('es');
        $this->initSearchControllerWith('Sofa & Sessel');

        $this->assertSame(
            'Sofa',
            Registry::getRequest()->getRequestEscapedParameter('searchparam'),
            'the value in $_GET is no longer what OXID expects to escape itself'
        );
    }

    /**
     * The search box is open to anyone, and every distinct term is a billed API call, so long
     * input is passed through rather than translated.
     */
    public function testOverlyLongInputIsNotSentToTheApi(): void
    {
        $this->useFakeDeepL('es');

        $longTerm = str_repeat('a', 101);
        $this->initSearchControllerWith($longTerm);

        $this->assertSame(0, $this->fakeDeepL->translateCalls, 'an unbounded term was billed');
        $this->assertSame($longTerm, $_GET['searchparam']);
    }

    public function testATermAtTheLengthLimitIsStillTranslated(): void
    {
        $this->useFakeDeepL('es');

        $this->initSearchControllerWith(str_repeat('a', 100));

        $this->assertSame(1, $this->fakeDeepL->translateCalls);
    }
}
