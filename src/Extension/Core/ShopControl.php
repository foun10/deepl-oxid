<?php
declare(strict_types=1);

namespace foun10\DeepL\Extension\Core;

use foun10\DeepL\Core\DeepL;
use OxidEsales\Eshop\Core\Registry;

class ShopControl extends ShopControl_parent
{
    protected $deepLTranslateInit = false;

    protected function _runOnce()
    {
        parent::_runOnce();

        if (!$this->deepLTranslateInit && !$this->isAdmin()) {
            $this->deepLTranslateInit = true;

            /** @var DeepL $deepL */
            $deepL = Registry::get(DeepL::class);

            // Trigger cookie set and preload all cached translations into runtime cache
            $langOnDemand = $deepL->getActiveLanguageOnDemand();

            if (!empty($langOnDemand)) {
                $this->deepLForceShopLangForLanguageOnDemand($deepL);

                $deepL->preloadTranslations(Registry::getLang()->getLanguageAbbr(), $langOnDemand);
            }
        }
    }

    /**
     * Language-on-demand translations are always sourced from one fixed base language
     * (DeepL::getShopLangForLanguageOnDemand(), currently English) - see
     * foun10\DeepL\Extension\Core\SeoDecoder::decodeUrl(), which already forces this for the
     * "clean" entry path (a URL carrying an on-demand prefix, e.g. /fr/...) and clears
     * langOnDemand again on a plain SEO'd German page. That only runs for SEO-decoded requests
     * though: langOnDemand can still end up active (via its cookie, the langOnDemand URL param, or
     * browser detection) while the shop's base language is still German on the home page (which
     * decodeUrl() deliberately exempts from its reset). Left uncorrected, that fragments the
     * translation cache and glossary coverage (only configured for the fixed origin language)
     * across every base-language combination instead of just one.
     *
     * NOTE: this does NOT cover widget.php (WidgetControl) requests - confirmed via production
     * diagnostics that WidgetControl never reaches this _runOnce() override despite extending
     * ShopControl in plain PHP terms. See foun10\DeepL\Extension\Core\WidgetControl for the
     * equivalent fix on that path.
     */
    protected function deepLForceShopLangForLanguageOnDemand(DeepL $deepL): void
    {
        $shopLang = $deepL->getShopLangForLanguageOnDemand();
        $shopLangId = (int) $shopLang['langId'];

        if ((int) Registry::getLang()->getBaseLanguage() === $shopLangId) {
            return;
        }

        Registry::getLang()->setBaseLanguage($shopLangId);
        Registry::getLang()->setTplLanguage($shopLangId);
        Registry::getSession()->setVariable('tpllanguage', $shopLangId);
    }
}
