<?php

declare(strict_types=1);

namespace foun10\DeepL\Tests\Integration;

use foun10\DeepL\Core\DeepLStats;
use foun10\DeepL\Model\Translation;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Registry;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the translation cache against the real database.
 *
 * The unit tests substitute the database, so they prove the shaping and escaping logic but
 * say nothing about whether the table, the column names or the SQL are right. That is what
 * this suite is for - and it is not hypothetical: the model looks rows up through
 * getViewName(), which resolves differently depending on whether a database view exists for
 * the table.
 *
 * Every test cleans up after itself so the suite can run repeatedly against a shop that is
 * also used for manual testing.
 */
class TranslationCacheTest extends TestCase
{
    private const FROM_LANG = 'en';
    private const TO_LANG = 'zz';

    protected function setUp(): void
    {
        $this->removeTestRows();
    }

    protected function tearDown(): void
    {
        $this->removeTestRows();
    }

    private function removeTestRows(): void
    {
        DatabaseProvider::getDb()->execute(
            'DELETE FROM foun10deepltranslations WHERE FOUN10TOLANG = ?',
            [self::TO_LANG]
        );
    }

    private function insertRow(string $textHash, string $translated, string $optionHash = ''): void
    {
        DatabaseProvider::getDb()->execute(
            'INSERT INTO foun10deepltranslations
                (OXID, FOUN10TEXTHASH, FOUN10FROMLANG, FOUN10TOLANG, FOUN10TRANSLATEDTEXT, FOUN10OPTIONHASH)
             VALUES (?, ?, ?, ?, ?, ?)',
            [substr(md5($textHash . $optionHash), 0, 32), $textHash, self::FROM_LANG, self::TO_LANG, $translated, $optionHash]
        );
    }

    // ---------------------------------------------------------------- model round trip

    /**
     * Writing through the model and reading it back through loadByParameter() is the round
     * trip the translation path depends on. A wrong column name or a getViewName() that
     * resolves to a non-existent view would break exactly here.
     */
    public function testStoresAndFindsATranslation(): void
    {
        /** @var Translation $model */
        $model = oxNew(Translation::class);
        $model->assign([
            'FOUN10TEXTHASH' => 'hash-round-trip',
            'FOUN10OPTIONHASH' => 'opt-1',
            'FOUN10FROMLANG' => self::FROM_LANG,
            'FOUN10TOLANG' => self::TO_LANG,
            'FOUN10TRANSLATEDTEXT' => 'stored translation',
        ]);
        $model->save();

        /** @var Translation $loaded */
        $loaded = oxNew(Translation::class);
        $found = $loaded->loadByParameter(self::FROM_LANG, self::TO_LANG, 'hash-round-trip', 'opt-1');

        $this->assertTrue($found, 'the stored translation was not found again');
        $this->assertSame(
            'stored translation',
            $loaded->foun10deepltranslations__foun10translatedtext->rawValue
        );
    }

    public function testDoesNotFindATranslationStoredUnderDifferentOptions(): void
    {
        $this->insertRow('hash-options', 'stored translation', 'opt-1');

        /** @var Translation $loaded */
        $loaded = oxNew(Translation::class);

        $this->assertFalse(
            $loaded->loadByParameter(self::FROM_LANG, self::TO_LANG, 'hash-options', 'opt-2'),
            'the option hash must be part of the lookup'
        );
    }

    // ---------------------------------------------------------------- statistics

    public function testCountsCachedEntriesAndCharacters(): void
    {
        $this->insertRow('hash-a', 'abcde');
        $this->insertRow('hash-b', 'fghij');

        $stats = oxNew(DeepLStats::class);
        $byLanguage = $stats->getCacheByLanguage();

        $ourPair = null;
        foreach ($byLanguage as $row) {
            if ($row['toLang'] === self::TO_LANG) {
                $ourPair = $row;
            }
        }

        $this->assertNotNull($ourPair, 'the inserted language pair is missing from the statistics');
        $this->assertSame(self::FROM_LANG, $ourPair['fromLang']);
        $this->assertSame(2, $ourPair['entries']);
        $this->assertSame(10, $ourPair['characters'], 'characters are counted in characters, not bytes');
    }

    public function testCountsMultibyteCharactersCorrectly(): void
    {
        $this->insertRow('hash-umlaut', 'Grösse');

        $stats = oxNew(DeepLStats::class);

        foreach ($stats->getCacheByLanguage() as $row) {
            if ($row['toLang'] === self::TO_LANG) {
                $this->assertSame(6, $row['characters'], 'CHAR_LENGTH must count characters, not bytes');

                return;
            }
        }

        $this->fail('the inserted language pair is missing from the statistics');
    }

    // ---------------------------------------------------------------- search and purge

    public function testFindsCachedEntriesByTranslatedText(): void
    {
        $this->insertRow('hash-sofa', 'Ein bequemes Sofa');
        $this->insertRow('hash-tisch', 'Ein runder Tisch');

        $stats = oxNew(DeepLStats::class);
        $result = $stats->searchCacheByTranslatedText('Sofa');

        $this->assertSame(1, $result['entries']);
        $this->assertSame(17, $result['characters']);
    }

    /**
     * The LIKE escaping is unit tested against a fake database; here it has to hold against
     * the real one, including the ESCAPE clause in the statement.
     */
    public function testTreatsWildcardsInTheSearchTermLiterally(): void
    {
        $this->insertRow('hash-discount', 'Rabatt 50% auf alles');
        $this->insertRow('hash-plain', 'Ein runder Tisch');

        $stats = oxNew(DeepLStats::class);

        $this->assertSame(1, $stats->searchCacheByTranslatedText('50%')['entries']);
        $this->assertSame(0, $stats->searchCacheByTranslatedText('%%%')['entries'], 'wildcards must not match everything');
    }

    public function testDeletesOnlyTheMatchingEntries(): void
    {
        $this->insertRow('hash-sofa', 'Ein bequemes Sofa');
        $this->insertRow('hash-tisch', 'Ein runder Tisch');

        $stats = oxNew(DeepLStats::class);
        $deleted = $stats->deleteCacheByTranslatedText('Sofa');

        $this->assertSame(1, $deleted);
        $this->assertSame(0, $stats->searchCacheByTranslatedText('Sofa')['entries']);
        $this->assertSame(1, $stats->searchCacheByTranslatedText('Tisch')['entries'], 'unrelated entries must survive');
    }

    public function testRefusesToPurgeOnAnEmptyTerm(): void
    {
        $this->insertRow('hash-sofa', 'Ein bequemes Sofa');

        $stats = oxNew(DeepLStats::class);

        $this->assertSame(0, $stats->deleteCacheByTranslatedText(''));
        $this->assertSame(1, $stats->searchCacheByTranslatedText('Sofa')['entries'], 'nothing may be deleted');
    }

    // ---------------------------------------------------------------- translation path

    /**
     * With test mode on, translateText() must return the text untouched and must not write to
     * the cache table - this is what keeps the integration suite from calling the paid API.
     */
    public function testTestModeLeavesTextAndCacheUntouched(): void
    {
        Registry::getConfig()->setConfigParam('blDeepLTestMode', true);

        try {
            $deepL = oxNew(\foun10\DeepL\Core\DeepL::class);
            $result = $deepL->translateText(self::FROM_LANG, self::TO_LANG, 'Hello World');

            $this->assertSame('Hello World', $result);
            $this->assertSame(
                0,
                (int) DatabaseProvider::getDb()->getOne(
                    'SELECT COUNT(*) FROM foun10deepltranslations WHERE FOUN10TOLANG = ?',
                    [self::TO_LANG]
                ),
                'test mode must not write to the translation cache'
            );
        } finally {
            Registry::getConfig()->setConfigParam('blDeepLTestMode', false);
        }
    }
}
