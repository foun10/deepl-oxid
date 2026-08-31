<?php

declare(strict_types=1);

namespace foun10\DeepL\Extension\Application\Controller;

use foun10\DeepL\Core\DeepL;
use OxidEsales\Eshop\Core\Registry;

/**
 * Translates the search term back into the shop's language before the search runs.
 *
 * A visitor reading the shop in an on-demand language types their query in that language, but
 * the catalogue is stored in the shop's own language - so searching for "sofá" while reading
 * Spanish would return nothing at all.
 *
 * The translated term is written back into $_GET because that is where OXID reads it from, in
 * the controller, in the search model and in the view. Setting it in one place therefore keeps
 * the whole request consistent; there is no setter on the request object to do this more
 * politely.
 *
 * Two things matter about how that is done:
 *
 * The raw parameter is read and a raw value written back. getRequestEscapedParameter() turns
 * & < > " ' into entities, so reading escaped would send "&amp;" to DeepL to be translated and
 * paid for, and every later read would escape the result a second time - a search for
 * "Sofa & Sessel" would end up as "Sofa &amp;amp; Sessel" in the box and in the query. Writing
 * raw leaves OXID's own escaping to happen exactly once, where it always did.
 *
 * Overly long input is not translated. The search box is reachable without logging in and every
 * distinct term is a billed API call, so an automated flood of long unique strings would
 * translate straight into cost. A term beyond MAX_TRANSLATABLE_LENGTH is passed through
 * untouched; it is not a search phrase at that point.
 */
class SearchController extends SearchController_parent
{
    /** Longer input is passed through untranslated - see the note above about cost. */
    private const MAX_TRANSLATABLE_LENGTH = 100;

    public function init()
    {
        $this->translateSearchParameterToShopLanguage();

        parent::init();
    }

    private function translateSearchParameterToShopLanguage(): void
    {
        $searchTerm = (string) Registry::getRequest()->getRequestParameter('searchparam');

        if ($searchTerm === '' || mb_strlen($searchTerm) > self::MAX_TRANSLATABLE_LENGTH) {
            return;
        }

        /** @var DeepL $deepL */
        $deepL = Registry::get(DeepL::class);
        $translated = $deepL->translateSearchTerm($searchTerm);

        if ($translated !== $searchTerm) {
            $_GET['searchparam'] = $translated;
        }
    }
}
