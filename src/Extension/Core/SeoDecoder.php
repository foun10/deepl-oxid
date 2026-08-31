<?php
declare(strict_types=1);

namespace foun10\DeepL\Extension\Core;

use foun10\DeepL\Core\DeepL;
use OxidEsales\Eshop\Core\Registry;

class SeoDecoder extends SeoDecoder_parent
{
    public function decodeUrl($seoUrl)
    {
        /** @var DeepL $deepL */
        $deepL = Registry::get(DeepL::class);

        // Cast at the point of use: both callees declare string, and under strict_types a
        // non-string arriving from the shop would be a TypeError instead of a coercion.
        $langOnDemand = $this->detectLangOnDemandPrefix((string) $seoUrl, $deepL);

        if ($langOnDemand !== null) {
            // Modify seoUrl to set up for correct shop language (before translate)
            $shopLang = $deepL->getShopLangForLanguageOnDemand();

            $seoUrl = $this->swapLangOnDemandPrefix((string) $seoUrl, $langOnDemand, $shopLang);

            // Set up used lang on demand
            $deepL->setActiveLanguageOnDemand($langOnDemand);

            // Set up shop language
            Registry::getLang()->setBaseLanguage($shopLang['langId']);
            Registry::getLang()->setTplLanguage($shopLang['langId']);
            Registry::getSession()->setVariable('tpllanguage', $shopLang['langId']);
            $_GET['lang'] = $shopLang['langId'];
            $_POST['lang'] = $shopLang['langId'];
        } elseif (strpos((string) $seoUrl, 'index.php') === false && !empty($seoUrl) && $seoUrl !== '/') {
            $deepL->setActiveLanguageOnDemand('');
        }

        return parent::decodeUrl($seoUrl);
    }

    /**
     * Core's processSeoCall() calls decodeOldUrl() directly with the raw request params
     * whenever decodeUrl() doesn't find a live oxseo match — bypassing the prefix swap above.
     * Without this override, a stale URL requested under a langOnDemand prefix (e.g. "fr/...")
     * never matches oxseohistory, whose idents are keyed on the real shop language prefix
     * ("en/..."), so the old-URL redirect silently fails and the request 404s instead.
     */
    protected function decodeOldUrl($seoUrl)
    {
        /** @var DeepL $deepL */
        $deepL = Registry::get(DeepL::class);

        $langOnDemand = $this->detectLangOnDemandPrefix((string) $seoUrl, $deepL);

        if ($langOnDemand === null) {
            return parent::decodeOldUrl($seoUrl);
        }

        $shopLang = $deepL->getShopLangForLanguageOnDemand();
        $redirectUrl = parent::decodeOldUrl(
            $this->swapLangOnDemandPrefix((string) $seoUrl, $langOnDemand, $shopLang)
        );

        if (!$redirectUrl) {
            return $redirectUrl;
        }

        // Re-apply the langOnDemand prefix so the redirect keeps the visitor on their language
        if (!empty($shopLang['langSeoPrefix'])) {
            $redirectUrl = preg_replace(
                '/^' . preg_quote($shopLang['langSeoPrefix'], '/') . '\//',
                $langOnDemand . '/',
                $redirectUrl,
                1
            );
        } else {
            $redirectUrl = $langOnDemand . '/' . ltrim($redirectUrl, '/');
        }

        return $redirectUrl;
    }

    protected function detectLangOnDemandPrefix(string $seoUrl, DeepL $deepL): ?string
    {
        if (1 !== preg_match('/^([a-z]{2})\/(.*)/', $seoUrl, $matches)) {
            return null;
        }

        if (!in_array($matches[1], array_keys($deepL->getLanguagesOnDemand()), true)) {
            return null;
        }

        return $matches[1];
    }

    protected function swapLangOnDemandPrefix(string $seoUrl, string $langOnDemand, array $shopLang): string
    {
        // Fix for double language prefix e.g. ru/en/
        $doubledPrefix = $langOnDemand . '/' . $shopLang['langSeoPrefix'] . '/';
        if (strpos($seoUrl, $doubledPrefix) === 0) {
            $seoUrl = substr_replace($seoUrl, $langOnDemand . '/', 0, strlen($doubledPrefix));
        }

        return preg_replace(
            '/^' . $langOnDemand . '\//',
            (!empty($shopLang['langSeoPrefix'] ?? '') ? $shopLang['langSeoPrefix'] . '/' : ''),
            $seoUrl
        );
    }
}
