<?php
declare(strict_types=1);

namespace foun10\DeepL\Extension\Core;

use foun10\DeepL\Core\DeepL;
use OxidEsales\Eshop\Core\Registry;

/**
 * WidgetControl extends ShopControl in plain PHP terms, and its own _runOnce() is not overridden,
 * so on paper foun10\DeepL\Extension\Core\ShopControl::_runOnce() should already run for widget.php
 * requests too. Production diagnostics proved otherwise: zero hits for widget.php-dispatched
 * requests (e.g. cl=oxwarticledetails via the AJAX-loaded related-products carousel) despite plenty
 * of ShopControl hits for regular index.php requests in the same window. WidgetControl was never
 * itself a key in any module's `extend` map though - only ShopControl was - so extending it
 * explicitly here, with its own _runOnce() override, guarantees the fix runs on this class's own
 * chain instead of relying on inheritance through ShopControl to carry it.
 */
class WidgetControl extends WidgetControl_parent
{
    protected $deepLTranslateInit = false;

    protected function _runOnce()
    {
        parent::_runOnce();

        if (!$this->deepLTranslateInit && !$this->isAdmin()) {
            $this->deepLTranslateInit = true;

            /** @var DeepL $deepL */
            $deepL = Registry::get(DeepL::class);
            $langOnDemand = $deepL->getActiveLanguageOnDemand();

            if (!empty($langOnDemand)) {
                $this->deepLForceShopLangForLanguageOnDemand($deepL);

                $deepL->preloadTranslations(Registry::getLang()->getLanguageAbbr(), $langOnDemand);
            }
        }
    }

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
