<?php

declare(strict_types=1);

namespace foun10\DeepL\Tests\Integration;

use foun10\DeepL\Core\DeepL;
use OxidEsales\Eshop\Application\Model\Article;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;
use PHPUnit\Framework\TestCase;

/**
 * Covers the MultilangModel trait against real models and a real database.
 *
 * This is where the module is most dangerous. The trait rewrites model fields in memory after
 * assign(), so the storefront renders translated text - and then save() has to put the original
 * value back before anything is written. If that restore ever breaks, saving a product in the
 * backend while a language on demand is active writes the DeepL output over the shop's own
 * content, permanently and without a trace.
 *
 * None of it is reachable from a unit test: the trait needs a model, and the field list comes
 * from the database schema via DbMetaDataHandler.
 *
 * The DeepL service is replaced through the Registry, so no API call is made and no money is
 * spent - the fake marks translated text with a prefix, which makes the assertions readable.
 */
class MultilangModelTest extends TestCase
{
    private const ARTICLE_ID = '_deepl_integration_article';
    private const ORIGINAL_TITLE = 'Ledersofa';
    private const LANGUAGE_ON_DEMAND = 'fr';

    protected function setUp(): void
    {
        $this->removeTestArticle();

        $article = oxNew(Article::class);
        $article->setId(self::ARTICLE_ID);
        $article->oxarticles__oxtitle = new Field(self::ORIGINAL_TITLE, Field::T_RAW);
        $article->oxarticles__oxshortdesc = new Field('Bequem und gross', Field::T_RAW);
        $article->save();
    }

    protected function tearDown(): void
    {
        $this->removeTestArticle();
        Registry::set(DeepL::class, null);
    }

    private function removeTestArticle(): void
    {
        DatabaseProvider::getDb()->execute('DELETE FROM oxarticles WHERE OXID = ?', [self::ARTICLE_ID]);
    }

    /**
     * @param string $languageOnDemand empty string means "no language on demand active"
     */
    private function useFakeDeepL(string $languageOnDemand = self::LANGUAGE_ON_DEMAND, bool $active = true): void
    {
        $fake = new class($languageOnDemand, $active) extends DeepL {
            private $fakeLanguageOnDemand;
            private $fakeActive;

            public function __construct(string $languageOnDemand, bool $active)
            {
                $this->fakeLanguageOnDemand = $languageOnDemand;
                $this->fakeActive = $active;
            }

            public function isDeepLTranslateActive(): bool
            {
                return $this->fakeActive;
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
                return '[' . $toLang . '] ' . $text;
            }
        };

        Registry::set(DeepL::class, $fake);
    }

    private function loadArticle(): Article
    {
        /** @var Article $article */
        $article = oxNew(Article::class);
        $article->load(self::ARTICLE_ID);

        return $article;
    }

    /**
     * Reads the stored title back through a fresh model with translation switched off.
     *
     * Deliberately not a raw SELECT on OXTITLE: which multilang column a value lands in
     * depends on the shop's base language, so a hard-coded column name passes on a German
     * shop and fails on an English one. Going through the model uses the same resolution the
     * shop itself uses, and still proves what matters - that the database holds the original
     * text rather than the translation.
     */
    private function readStoredTitle(): string
    {
        $this->useFakeDeepL('');

        return (string) $this->loadArticle()->oxarticles__oxtitle->rawValue;
    }

    // ---------------------------------------------------------------- translating on load

    public function testTranslatesMultilangFieldsWhenALanguageOnDemandIsActive(): void
    {
        $this->useFakeDeepL();

        $article = $this->loadArticle();

        $this->assertSame('[fr] ' . self::ORIGINAL_TITLE, $article->oxarticles__oxtitle->rawValue);
        $this->assertSame('[fr] Bequem und gross', $article->oxarticles__oxshortdesc->rawValue);
    }

    public function testLeavesFieldsAloneWithoutALanguageOnDemand(): void
    {
        $this->useFakeDeepL('');

        $article = $this->loadArticle();

        $this->assertSame(self::ORIGINAL_TITLE, $article->oxarticles__oxtitle->rawValue);
    }

    public function testLeavesFieldsAloneWhenTranslationIsSwitchedOff(): void
    {
        $this->useFakeDeepL(self::LANGUAGE_ON_DEMAND, false);

        $article = $this->loadArticle();

        $this->assertSame(self::ORIGINAL_TITLE, $article->oxarticles__oxtitle->rawValue);
    }

    // ---------------------------------------------------------------- protecting the database

    /**
     * The one that matters. Saving a model that was loaded while a language on demand was
     * active must write the original text back, never the translation.
     */
    public function testSavingATranslatedModelDoesNotOverwriteTheOriginalText(): void
    {
        $this->useFakeDeepL();

        $article = $this->loadArticle();
        $this->assertSame('[fr] ' . self::ORIGINAL_TITLE, $article->oxarticles__oxtitle->rawValue);

        $article->save();

        $this->assertSame(
            self::ORIGINAL_TITLE,
            $this->readStoredTitle(),
            'the DeepL translation was written to the database - source content is lost'
        );
    }

    /**
     * The counterpart: a value edited after loading is a real change and has to survive. If
     * the restore were unconditional, every backend edit made while a language on demand was
     * active would be silently discarded.
     */
    public function testAnEditedFieldIsStillSaved(): void
    {
        $this->useFakeDeepL();

        $article = $this->loadArticle();
        $article->oxarticles__oxtitle = new Field('Stoffsofa', Field::T_RAW);
        $article->save();

        $this->assertSame('Stoffsofa', $this->readStoredTitle());
    }

    // ---------------------------------------------------------------- fields that are skipped

    /**
     * Template markup must not reach DeepL: it mangles the delimiters before the engine can
     * resolve them, and a broken tag takes the page with it.
     *
     * Both syntaxes are checked. Twig is what OXID 7 renders; the Smarty form still turns up in
     * databases migrated from an OXID 6 shop.
     *
     * @dataProvider templateMarkupProvider
     */
    public function testSkipsFieldsContainingTemplateMarkup(string $withMarkup): void
    {
        $article = $this->loadArticle();
        $article->oxarticles__oxshortdesc = new Field($withMarkup, Field::T_RAW);
        $article->save();

        $this->useFakeDeepL();
        $reloaded = $this->loadArticle();

        $this->assertSame($withMarkup, $reloaded->oxarticles__oxshortdesc->rawValue);
    }

    public function templateMarkupProvider(): array
    {
        return [
            'twig output' => ['Sofa {{ oViewConf.getBaseDir() }}'],
            'twig statement' => ['Sofa {% if x %}gross{% endif %}'],
            'smarty, from a migrated shop' => ['Sofa [{ $oViewConf->getBaseDir() }]'],
        ];
    }

    /**
     * oxsearchkeys only feeds internal search matching and is never shown to customers, so
     * translating it would be paid for and thrown away.
     */
    public function testSkipsFieldsThatAreNeverShownToCustomers(): void
    {
        $article = $this->loadArticle();
        $article->oxarticles__oxsearchkeys = new Field('sofa couch 12345', Field::T_RAW);
        $article->save();

        $this->useFakeDeepL();
        $reloaded = $this->loadArticle();

        $this->assertSame('sofa couch 12345', $reloaded->oxarticles__oxsearchkeys->rawValue);
    }

    /**
     * Multilang columns are not all text. A numeric one arrives as int, and strpos() with an
     * int haystack is a TypeError on PHP 8 - which took down every widget rendering such a
     * field, with a stack trace large enough to exhaust a gigabyte of memory in the logger.
     */
    public function testSurvivesANumericMultilangField(): void
    {
        $article = $this->loadArticle();
        $article->oxarticles__oxvarselect = new Field(12345, Field::T_RAW);
        $article->save();

        $this->useFakeDeepL();
        $reloaded = $this->loadArticle();

        $this->assertSame('[fr] 12345', $reloaded->oxarticles__oxvarselect->rawValue);
    }

    public function testSkipsEmptyFields(): void
    {
        $this->useFakeDeepL();

        $article = $this->loadArticle();

        $this->assertSame(
            '',
            $article->oxarticles__oxvarselect->rawValue,
            'an empty field must not be sent to the API'
        );
    }
}
