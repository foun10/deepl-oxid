<?php
declare(strict_types=1);

namespace foun10\DeepL\Extension\Core;

use foun10\DeepL\Core\DeepL;
use OxidEsales\Eshop\Core\Registry;

class UtilsUrl extends UtilsUrl_parent
{
    public function processSeoUrl($seoUrl)
    {
        $seoUrl = parent::processSeoUrl($seoUrl);

        // Cast for strpos() only - $seoUrl itself is returned unchanged when we do no work.
        if (!$this->isAdmin() && strpos((string) $seoUrl, 'index.php') === false) {
            /** @var DeepL $deepL */
            $deepL = Registry::get(DeepL::class);

            $langOnDemand = $deepL->getActiveLanguageOnDemand();

            if (!empty($langOnDemand)) {
                // Modify seoUrl to set up for correct shop language (before translate)
                $shopLang = $deepL->getShopLangForLanguageOnDemand();

                $shopBaseUrl = rtrim(Registry::getConfig()->getShopUrl(), '/') . '/';

                if (!empty($shopLang['langSeoPrefix'])) {
                    $seoUrl = preg_replace(
                        '#' . $shopBaseUrl . $shopLang['langSeoPrefix'] . '/#',
                        $shopBaseUrl . $langOnDemand . '/',
                        $seoUrl
                    );
                } else {
                    // We are in shop lang 0 with no language prefix
                    $seoUrl = str_replace('/' . $langOnDemand . '/', '/', $seoUrl);
                    $seoUrl = str_replace($shopBaseUrl, $shopBaseUrl . $langOnDemand . '/', $seoUrl);
                }
            }
        }

        return $seoUrl;
    }
}
