<?php
declare(strict_types=1);

namespace foun10\DeepL\Extension\Core;

use foun10\DeepL\Core\DeepL;
use OxidEsales\Eshop\Core\Registry;

class Output extends Output_parent
{
    public function process($html, $className)
    {
        /** @var DeepL $deepL */
        $deepL = Registry::get(DeepL::class);

        $langOnDemand = $deepL->getActiveLanguageOnDemand();

        if (!empty($langOnDemand) && !isAdmin()) {
            // strict_types applies to the calls this file makes, so the string functions below
            // would raise a TypeError rather than coerce if the shop ever handed over a
            // non-string. Cast once here; the pass-through above stays untouched.
            $html = (string) $html;

            $shopLang = $deepL->getShopLangForLanguageOnDemand();

            $needle = '<html lang="' . $shopLang['langIso'] . '"';
            $pos = strpos($html, $needle);
            if ($pos !== false) {
                $html = substr_replace($html, '<html lang="' . $langOnDemand . '"', $pos, strlen($needle));
            }

            if ($shopLang['langIso'] !== 'de') {
                $html = str_replace('href="/' . $shopLang['langSeoPrefix'] . '/', 'href="' . $langOnDemand . '/', $html);
                $html = str_replace('href="/./' . $shopLang['langSeoPrefix'] . '/', 'href="/./' . $langOnDemand . '/', $html);
                $html = str_replace('href="' . $shopLang['langSeoPrefix'] . '/', 'href="' . $langOnDemand . '/', $html);

                // Absolute URLs on the shop's own domain, e.g. the "." fallback links OXID's
                // language switcher emits when no SEO alias exists yet for the target language
                // (href="https://shop.tld/./en/..." or href="https://shop.tld/en/...").
                $shopBaseUrl = rtrim(Registry::getConfig()->getShopUrl(), '/');
                $html = str_replace(
                    'href="' . $shopBaseUrl . '/./' . $shopLang['langSeoPrefix'] . '/',
                    'href="' . $shopBaseUrl . '/./' . $langOnDemand . '/',
                    $html
                );
                $html = str_replace(
                    'href="' . $shopBaseUrl . '/' . $shopLang['langSeoPrefix'] . '/',
                    'href="' . $shopBaseUrl . '/' . $langOnDemand . '/',
                    $html
                );
            }

            // Inject langOnDemand into all index.php links — both relative (href="index.php?")
            // and absolute (href="https://…/index.php?"). Negative lookahead prevents double-injection.
            $html = preg_replace(
                '/href="([^"]*?)index\.php\?(?!langOnDemand=)/',
                'href="$1index.php?langOnDemand=' . $langOnDemand . '&',
                $html
            );
        }

        return parent::process($html, $className);
    }
}
