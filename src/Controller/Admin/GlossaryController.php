<?php
declare(strict_types=1);

namespace foun10\DeepL\Controller\Admin;

use foun10\DeepL\Core\DeepL;
use foun10\DeepL\Core\DeepLStats;
use OxidEsales\Eshop\Application\Controller\Admin\AdminController;
use OxidEsales\Eshop\Core\Registry;

/**
 * Admin view for the configured DeepL glossary (foun10DeepLGlossaryId, set on the standard module
 * settings screen - deliberately not duplicated here): its dictionaries, adding new entries to it,
 * and a tool to purge cached translations by translated word. Room for further glossary management
 * actions to grow on this tab later.
 */
class GlossaryController extends AdminController
{
    protected $_sThisTemplate = '@foun10DeepL/admin/foun10_deepl_glossary.html.twig';

    /**
     * Below this, a search term matches too much of the cache to be a deliberate, targeted lookup -
     * refuses to preview/delete rather than risk wiping most of the table on a fat-fingered "a".
     */
    protected const MIN_SEARCH_LENGTH = 3;

    protected $deepLDeletedCount;

    /** @var string|null Raw error message from the DeepL API, if the add-entry call itself failed. */
    protected $deepLAddEntryError;

    /** @var string|null "no_glossary"|"invalid_input" - a validation failure caught before any API call. */
    protected $deepLAddEntryValidationError;

    protected $deepLAddEntrySuccess = false;

    public function render()
    {
        parent::render();

        $glossaryId = trim((string) Registry::getConfig()->getConfigParam('foun10DeepLGlossaryId'));
        $this->_aViewData['deepLGlossaryId'] = $glossaryId;
        $this->_aViewData['deepLGlossary'] = null;
        $this->_aViewData['deepLGlossaryError'] = null;

        if ($glossaryId !== '') {
            $glossaryResult = $this->getStats()->fetchGlossary($glossaryId);
            $glossary = $glossaryResult['glossary'];

            if ($glossary !== null) {
                $dictionaries = [];

                foreach ($glossary->dictionaries as $dictionary) {
                    $entries = [];
                    $entriesError = null;

                    // Skip the extra API round-trip for a dictionary we already know is empty.
                    if ($dictionary->entryCount > 0) {
                        $entriesResult = $this->getStats()->fetchGlossaryEntries($glossaryId, $dictionary->sourceLang, $dictionary->targetLang);
                        $entries = $entriesResult['entries'];
                        $entriesError = $entriesResult['error'];
                    }

                    $dictionaries[] = [
                        'sourceLang' => $dictionary->sourceLang,
                        'targetLang' => $dictionary->targetLang,
                        'entryCount' => $dictionary->entryCount,
                        'entries' => $entries,
                        'entriesError' => $entriesError,
                    ];
                }

                $this->_aViewData['deepLGlossary'] = [
                    'name' => $glossary->name,
                    'creationTime' => $glossary->creationTime->format('Y-m-d H:i'),
                    'dictionaries' => $dictionaries,
                ];
            }

            $this->_aViewData['deepLGlossaryError'] = $glossaryResult['error'];
        }

        $request = Registry::getRequest();
        $deepL = $this->getDeepL();

        $this->_aViewData['deepLSourceLangIso'] = $deepL->getShopLangForLanguageOnDemand()['langIso'];
        $this->_aViewData['deepLTargetLanguages'] = $deepL->getLanguagesOnDemand();
        $this->_aViewData['deepLAddEntryError'] = $this->deepLAddEntryError;
        $this->_aViewData['deepLAddEntryValidationError'] = $this->deepLAddEntryValidationError;
        $this->_aViewData['deepLAddEntrySuccess'] = $this->deepLAddEntrySuccess;

        // Sticky fields so a validation error (or the target language on a successful add) doesn't
        // lose what the admin typed - the term inputs are cleared after a successful add, since the
        // next likely action is adding another term rather than re-adding the same one.
        $this->_aViewData['deepLEntrySourceTerm'] = $this->deepLAddEntrySuccess
            ? ''
            : trim((string) $request->getRequestEscapedParameter('foun10DeepLEntrySourceTerm'));
        $this->_aViewData['deepLEntryTargetTerm'] = $this->deepLAddEntrySuccess
            ? ''
            : trim((string) $request->getRequestEscapedParameter('foun10DeepLEntryTargetTerm'));
        $this->_aViewData['deepLEntryTargetLang'] = (string) $request->getRequestEscapedParameter('foun10DeepLEntryTargetLang');

        $searchTerm = trim((string) Registry::getRequest()->getRequestEscapedParameter('foun10DeepLSearchTerm'));
        $this->_aViewData['deepLSearchTerm'] = $searchTerm;
        $this->_aViewData['deepLSearchTooShort'] = ($searchTerm !== '' && mb_strlen($searchTerm) < self::MIN_SEARCH_LENGTH);
        $this->_aViewData['deepLSearchPreview'] = null;
        $this->_aViewData['deepLDeletedCount'] = $this->deepLDeletedCount;
        $this->_aViewData['deepLMinSearchLength'] = self::MIN_SEARCH_LENGTH;

        if ($searchTerm !== '' && !$this->_aViewData['deepLSearchTooShort']) {
            $this->_aViewData['deepLSearchPreview'] = $this->getStats()->searchCacheByTranslatedText($searchTerm);
        }

        $this->_aViewData['deepLLastAction'] = (string) Registry::getRequest()->getRequestEscapedParameter('fnc');

        return $this->_sThisTemplate;
    }

    /**
     * Deletes every cache entry whose translated output contains the search term - the admin must
     * have already seen the matching entry/character count via render()'s preview for this exact
     * term (the confirm dialog on the delete button repeats that count client-side) before this runs.
     */
    public function delete()
    {
        $searchTerm = trim((string) Registry::getRequest()->getRequestEscapedParameter('foun10DeepLSearchTerm'));

        if ($searchTerm !== '' && mb_strlen($searchTerm) >= self::MIN_SEARCH_LENGTH) {
            $this->deepLDeletedCount = $this->getStats()->deleteCacheByTranslatedText($searchTerm);
        }
    }

    /**
     * Adds one glossary entry. Source language is always the shop's language-on-demand origin
     * (DeepL::getShopLangForLanguageOnDemand(), currently "en") - never picked by the admin - and
     * the target language is restricted to DeepL::getLanguagesOnDemand(), the set of languages this
     * module actually offers as translations, so an entry can't accidentally be added for a
     * language pair the shop never translates into.
     */
    public function addentry()
    {
        $request = Registry::getRequest();
        $deepL = $this->getDeepL();

        $glossaryId = trim((string) Registry::getConfig()->getConfigParam('foun10DeepLGlossaryId'));
        $sourceTerm = trim((string) $request->getRequestEscapedParameter('foun10DeepLEntrySourceTerm'));
        $targetTerm = trim((string) $request->getRequestEscapedParameter('foun10DeepLEntryTargetTerm'));
        $targetLang = (string) $request->getRequestEscapedParameter('foun10DeepLEntryTargetLang');

        if ($glossaryId === '') {
            $this->deepLAddEntryValidationError = 'no_glossary';
            return;
        }

        if ($sourceTerm === '' || $targetTerm === '' || !array_key_exists($targetLang, $deepL->getLanguagesOnDemand())) {
            $this->deepLAddEntryValidationError = 'invalid_input';
            return;
        }

        $sourceLang = $deepL->getShopLangForLanguageOnDemand()['langIso'];
        $result = $this->getStats()->addGlossaryEntry($glossaryId, $sourceLang, $targetLang, $sourceTerm, $targetTerm);

        if ($result['error'] !== null) {
            $this->deepLAddEntryError = $result['error'];
        } else {
            $this->deepLAddEntrySuccess = true;
        }
    }

    protected function getDeepL(): DeepL
    {
        return Registry::get(DeepL::class);
    }

    protected function getStats(): DeepLStats
    {
        return Registry::get(DeepLStats::class);
    }
}
