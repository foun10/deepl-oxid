<?php
declare(strict_types=1);

namespace foun10\DeepL\Core;

use DeepL\MultilingualGlossaryDictionaryEntries;
use DeepL\MultilingualGlossaryInfo;
use DeepL\Usage;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Registry;

/**
 * Admin-only DeepL data and actions: live account usage, the configured glossary's dictionaries
 * (plus adding entries to it), and local translation-cache totals/cleanup. Kept separate from
 * DeepL.php (the translation engine) so none of this clutters the request-critical
 * translateText() path.
 */
class DeepLStats
{
    // Seams for the shop singletons - same rationale as in DeepL.php: every call into the
    // shop goes through one of these, so unit tests can override them instead of
    // bootstrapping a shop. Keep them free of logic.

    protected function getDeepL(): DeepL
    {
        return Registry::get(DeepL::class);
    }

    /**
     * @return \OxidEsales\Eshop\Core\Database\Adapter\DatabaseInterface
     */
    protected function getDb()
    {
        return DatabaseProvider::getDb();
    }

    /**
     * A separate seam instead of a fetch-mode argument on getDb(): the mode is an OXID class
     * constant, and evaluating it at the call site would pull the shop into every unit test.
     * Keeping it inside the seam body means substituted implementations never touch it.
     *
     * @return \OxidEsales\Eshop\Core\Database\Adapter\DatabaseInterface
     */
    protected function getAssocDb()
    {
        return DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC);
    }

    /**
     * @return array{usage: ?Usage, error: ?string}
     */
    public function fetchUsage(): array
    {
        try {
            return ['usage' => $this->getDeepL()->getTranslator()->getUsage(), 'error' => null];
        } catch (\Throwable $e) {
            return ['usage' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{glossary: ?MultilingualGlossaryInfo, error: ?string}
     */
    public function fetchGlossary(string $glossaryId): array
    {
        try {
            return ['glossary' => $this->getDeepL()->getTranslator()->getMultilingualGlossary($glossaryId), 'error' => null];
        } catch (\Throwable $e) {
            return ['glossary' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{entries: int, characters: int, newest: ?string}
     */
    public function getCacheTotals(): array
    {
        $db = $this->getAssocDb();

        $row = $db->getRow('
            SELECT
                COUNT(*) AS entries,
                COALESCE(SUM(CHAR_LENGTH(FOUN10TRANSLATEDTEXT)), 0) AS characters,
                MAX(OXTIMESTAMP) AS newest
            FROM foun10deepltranslations
        ');

        return [
            'entries' => (int) ($row['entries'] ?? 0),
            'characters' => (int) ($row['characters'] ?? 0),
            'newest' => $row['newest'] ?? null,
        ];
    }

    /**
     * Adds (or, if the source term already exists in that dictionary, overwrites the translation
     * of) a single entry. Uses PATCH (Translator::updateMultilingualGlossary()), which merges into
     * the dictionary for the given language pair instead of replacing it wholesale - every other
     * entry already in that dictionary, and every other dictionary in the glossary, is left as-is.
     *
     * @return array{glossary: ?MultilingualGlossaryInfo, error: ?string}
     */
    public function addGlossaryEntry(
        string $glossaryId,
        string $sourceLang,
        string $targetLang,
        string $sourceTerm,
        string $targetTerm
    ): array {
        try {
            $entries = new MultilingualGlossaryDictionaryEntries($sourceLang, $targetLang, [$sourceTerm => $targetTerm]);
            $glossary = $this->getDeepL()->getTranslator()->updateMultilingualGlossary($glossaryId, null, [$entries]);

            return ['glossary' => $glossary, 'error' => null];
        } catch (\Throwable $e) {
            return ['glossary' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * The source->target term pairs stored in one dictionary of the glossary.
     *
     * @return array{entries: array<string, string>, error: ?string}
     */
    public function fetchGlossaryEntries(string $glossaryId, string $sourceLang, string $targetLang): array
    {
        try {
            $dictionaries = $this->getDeepL()->getTranslator()->getMultilingualGlossaryEntries($glossaryId, $sourceLang, $targetLang);

            return ['entries' => $dictionaries[0]->entries ?? [], 'error' => null];
        } catch (\Throwable $e) {
            return ['entries' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array<int, array{fromLang: string, toLang: string, entries: int, characters: int}>
     */
    public function getCacheByLanguage(): array
    {
        $db = $this->getAssocDb();

        $rows = $db->getAll('
            SELECT
                FOUN10FROMLANG AS fromLang,
                FOUN10TOLANG AS toLang,
                COUNT(*) AS entries,
                COALESCE(SUM(CHAR_LENGTH(FOUN10TRANSLATEDTEXT)), 0) AS characters
            FROM foun10deepltranslations
            GROUP BY FOUN10FROMLANG, FOUN10TOLANG
            ORDER BY entries DESC
        ');

        return array_map(static function (array $row): array {
            return [
                'fromLang' => (string) $row['fromLang'],
                'toLang' => (string) $row['toLang'],
                'entries' => (int) $row['entries'],
                'characters' => (int) $row['characters'],
            ];
        }, $rows);
    }

    /**
     * We only ever store the source text as a hash (FOUN10TEXTHASH), never the plain text itself
     * (see DeepL::getTextHash()) - so the translated output is the only field cached entries can be
     * searched by, e.g. to find every entry that picked up an outdated/wrong translation of a term
     * before it was added to the glossary.
     *
     * @return array{entries: int, characters: int}
     */
    public function searchCacheByTranslatedText(string $term): array
    {
        if ($term === '') {
            return ['entries' => 0, 'characters' => 0];
        }

        $db = $this->getAssocDb();

        $row = $db->getRow(
            "SELECT
                COUNT(*) AS entries,
                COALESCE(SUM(CHAR_LENGTH(FOUN10TRANSLATEDTEXT)), 0) AS characters
             FROM foun10deepltranslations
             WHERE FOUN10TRANSLATEDTEXT LIKE ? ESCAPE '\\\\'",
            [$this->buildContainsPattern($term)]
        );

        return [
            'entries' => (int) ($row['entries'] ?? 0),
            'characters' => (int) ($row['characters'] ?? 0),
        ];
    }

    /**
     * Deletes every cache entry whose translated output contains $term. Callers MUST have shown the
     * admin the searchCacheByTranslatedText() preview for the exact same term first - this performs
     * no confirmation of its own. Returns the number of rows actually deleted.
     */
    public function deleteCacheByTranslatedText(string $term): int
    {
        if ($term === '') {
            return 0;
        }

        $db = $this->getDb();

        return (int) $db->execute(
            "DELETE FROM foun10deepltranslations WHERE FOUN10TRANSLATEDTEXT LIKE ? ESCAPE '\\\\'",
            [$this->buildContainsPattern($term)]
        );
    }

    /**
     * Escapes LIKE wildcards already present in the admin-supplied search term, so e.g. a literal
     * "_" or "%" in a translated word is matched literally instead of acting as a wildcard.
     */
    protected function buildContainsPattern(string $term): string
    {
        return '%' . addcslashes($term, '%_\\') . '%';
    }
}
