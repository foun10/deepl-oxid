<?php

declare(strict_types=1);

namespace foun10\DeepL\Tests\Unit\Core;

use foun10\DeepL\Tests\Unit\Double\StatsWithFakeDb;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the cache statistics and the cache purge.
 *
 * The database is substituted, so what is under test is the part that can silently go wrong
 * without anyone noticing: how rows are shaped into the admin view, and how a search term is
 * escaped before it reaches a LIKE clause. The purge deletes production data, so an
 * over-broad pattern is the worst outcome in this class.
 */
class DeepLStatsTest extends TestCase
{
    // ---------------------------------------------------------------- totals

    public function testShapesTheTotalsRow(): void
    {
        $stats = new StatsWithFakeDb();
        $stats->row = ['entries' => '42', 'characters' => '1337', 'newest' => '2026-08-27 10:00:00'];

        $this->assertSame(
            ['entries' => 42, 'characters' => 1337, 'newest' => '2026-08-27 10:00:00'],
            $stats->getCacheTotals()
        );
    }

    /**
     * On an empty table MySQL returns NULL for MAX(), and COUNT()/SUM() come back as strings.
     * The admin template does arithmetic on these, so the casting has to hold.
     */
    public function testTreatsAnEmptyCacheAsZero(): void
    {
        $stats = new StatsWithFakeDb();
        $stats->row = ['entries' => '0', 'characters' => '0', 'newest' => null];

        $totals = $stats->getCacheTotals();

        $this->assertSame(0, $totals['entries']);
        $this->assertSame(0, $totals['characters']);
        $this->assertNull($totals['newest']);
    }

    public function testSurvivesAMissingTotalsRow(): void
    {
        $stats = new StatsWithFakeDb();
        $stats->row = [];

        $this->assertSame(
            ['entries' => 0, 'characters' => 0, 'newest' => null],
            $stats->getCacheTotals()
        );
    }

    // ---------------------------------------------------------------- per language

    public function testShapesThePerLanguageRows(): void
    {
        $stats = new StatsWithFakeDb();
        $stats->rows = [
            ['fromLang' => 'de', 'toLang' => 'fr', 'entries' => '10', 'characters' => '100'],
            ['fromLang' => 'de', 'toLang' => 'it', 'entries' => '5', 'characters' => '50'],
        ];

        $this->assertSame(
            [
                ['fromLang' => 'de', 'toLang' => 'fr', 'entries' => 10, 'characters' => 100],
                ['fromLang' => 'de', 'toLang' => 'it', 'entries' => 5, 'characters' => 50],
            ],
            $stats->getCacheByLanguage()
        );
    }

    public function testReturnsAnEmptyListWhenNothingIsCached(): void
    {
        $stats = new StatsWithFakeDb();
        $stats->rows = [];

        $this->assertSame([], $stats->getCacheByLanguage());
    }

    // ---------------------------------------------------------------- search

    public function testDoesNotQueryTheDatabaseForAnEmptySearchTerm(): void
    {
        $stats = new StatsWithFakeDb();

        $this->assertSame(['entries' => 0, 'characters' => 0], $stats->searchCacheByTranslatedText(''));
        $this->assertSame([], $stats->queries, 'an empty term must not reach the database');
    }

    public function testShapesTheSearchResult(): void
    {
        $stats = new StatsWithFakeDb();
        $stats->row = ['entries' => '3', 'characters' => '30'];

        $this->assertSame(
            ['entries' => 3, 'characters' => 30],
            $stats->searchCacheByTranslatedText('Sofa')
        );
    }

    public function testWrapsTheSearchTermInWildcards(): void
    {
        $stats = new StatsWithFakeDb();
        $stats->row = ['entries' => '0', 'characters' => '0'];

        $stats->searchCacheByTranslatedText('Sofa');

        $this->assertSame(['%Sofa%'], $stats->lastParameters);
    }

    /**
     * A term containing LIKE wildcards must match literally. Unescaped, a search for "50%"
     * would match almost every row - and the purge below reuses this pattern to DELETE.
     *
     * @dataProvider wildcardProvider
     */
    public function testEscapesLikeWildcardsInTheSearchTerm(string $term, string $expected): void
    {
        $stats = new StatsWithFakeDb();
        $stats->row = ['entries' => '0', 'characters' => '0'];

        $stats->searchCacheByTranslatedText($term);

        $this->assertSame([$expected], $stats->lastParameters);
    }

    public function wildcardProvider(): array
    {
        return [
            'percent is escaped' => ['50%', '%50\\%%'],
            'underscore is escaped' => ['a_b', '%a\\_b%'],
            'backslash is escaped' => ['a\\b', '%a\\\\b%'],
            'plain term is untouched' => ['Sofa', '%Sofa%'],
        ];
    }

    // ---------------------------------------------------------------- purge

    public function testDoesNotDeleteAnythingForAnEmptyTerm(): void
    {
        $stats = new StatsWithFakeDb();

        $this->assertSame(0, $stats->deleteCacheByTranslatedText(''));
        $this->assertSame([], $stats->queries, 'an empty term must never reach a DELETE');
    }

    public function testDeletesUsingTheSameEscapedPatternAsTheSearch(): void
    {
        $stats = new StatsWithFakeDb();
        $stats->affectedRows = 7;

        $deleted = $stats->deleteCacheByTranslatedText('50%');

        $this->assertSame(7, $deleted);
        $this->assertSame(['%50\\%%'], $stats->lastParameters, 'the preview and the purge must use the same pattern');
        $this->assertStringContainsString('DELETE', $stats->queries[0]);
    }
}
