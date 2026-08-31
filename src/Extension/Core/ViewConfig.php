<?php
declare(strict_types=1);

namespace foun10\DeepL\Extension\Core;

use foun10\DeepL\Core\DeepL;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Registry;

class ViewConfig extends ViewConfig_parent
{
    public function getLanguagesOnDemandHrefLangUrls(): array
    {
        $langOnDemandLanguageUrls = [];

        /** @var DeepL $deepL */
        $deepL = Registry::get(DeepL::class);

        $shopLangForTranslate = $deepL->getShopLangForLanguageOnDemand();
        $originLink = Registry::getConfig()->getTopActiveView()->getLink($shopLangForTranslate['langId']);

        if (!empty($originLink)) {
            $shopBaseUrl = Registry::getConfig()->getShopUrl($shopLangForTranslate['langId']);

            $isSeoUrl = strpos($originLink, 'index.php') === false;

            $activeLangOnDemand = $this->getActiveLanguageOnDemand();

            // The shop's own languages, whichever they are. This used to be a hardcoded German
            // and English pair at ids 0 and 1, which only held for the shop it was written for.
            $shopLanguages = Registry::getLang()->getLanguageArray();
            $shopLanguageLinks = [];

            foreach ($shopLanguages as $language) {
                $languageId = (int) $language->id;
                $link = Registry::getConfig()->getTopActiveView()->getLink($languageId);

                if (!empty($activeLangOnDemand)) {
                    // Drop the on-demand prefix. For the source language the shop's own SEO
                    // prefix takes its place, for the others the segment simply goes.
                    $replacement = ($languageId === $shopLangForTranslate['langId']
                        && $shopLangForTranslate['langSeoPrefix'] !== '')
                        ? '/' . $shopLangForTranslate['langSeoPrefix'] . '/'
                        : '/';
                    $link = str_replace('/' . $activeLangOnDemand . '/', $replacement, $link);
                }

                $shopLanguageLinks[$languageId] = $link;
            }

            if (!empty($activeLangOnDemand)) {
                $originLink = str_replace('/' . $activeLangOnDemand . '/', '/', $originLink);
            }

            foreach ($shopLanguages as $language) {
                $languageId = (int) $language->id;
                $link = $shopLanguageLinks[$languageId];

                $langOnDemandLanguageUrls[] = [
                    'langIso' => (string) $language->abbr,
                    'langName' => (string) $language->name,
                    'langUrl' => $link . (strpos($link, '?') !== false ? '&' : '?')
                        . 'langOnDemand=&lang=' . $languageId,
                ];
            }

            if ($isSeoUrl && !empty($shopLangForTranslate['langSeoPrefix'])) {
                $defaultLanguageLink = $shopLanguageLinks[0] ?? $originLink;

                if ($defaultLanguageLink === ($shopLanguageLinks[$shopLangForTranslate['langId']] ?? null)) {
                    // Source and default language resolve to the same SEO link, which means the
                    // source language has none of its own - look it up instead.
                    // Note: intentionally not using SeoDecoder::decodeUrl() here - this module's
                    // own SeoDecoder extension has side effects (it can reset the active
                    // language-on-demand state, see Extension/Core/SeoDecoder.php), which must not
                    // be triggered just to parse a URL while rendering the language switcher.
                    $database = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC);

                    $seoUrlForIdent = $defaultLanguageLink;
                    $baseUrl = Registry::getConfig()->getShopURL();
                    if (strpos($seoUrlForIdent, $baseUrl) === 0) {
                        $seoUrlForIdent = substr($seoUrlForIdent, strlen($baseUrl));
                    }
                    $seoUrlForIdent = rawurldecode($seoUrlForIdent);
                    $ident = md5(strtolower($seoUrlForIdent));

                    $stdUrl = $database->getOne("
                        SELECT OXSTDURL FROM oxseo WHERE OXIDENT = ? AND OXSHOPID = ?
                    ", [$ident, Registry::getConfig()->getShopId()]);

                    if ($stdUrl) {
                        $urlParameter = [];
                        $stdUrl = html_entity_decode($stdUrl);
                        if (($queryPos = strpos($stdUrl, '?')) !== false) {
                            parse_str(substr($stdUrl, $queryPos + 1), $urlParameter);
                        }
                        unset($urlParameter['lang']);

                        $stdUrl = 'index.php?' . http_build_query($urlParameter);
                        $stdUrl = htmlentities($stdUrl);

                        $originLink = $database->getOne("
                            SELECT OXSEOURL FROM oxseo WHERE OXSTDURL = ? AND OXLANG = ?
                        ", [$stdUrl, $shopLangForTranslate['langId']]) ?: $originLink;
                    }
                }

                $originLink = preg_replace('/^' . $shopLangForTranslate['langSeoPrefix'] . '\//iDx', '', $originLink);
            }

            foreach ($deepL->getLanguagesOnDemand() as $langIso => $langName) {
                $langOnDemandLanguageUrls[] = [
                    'langIso' => $langIso,
                    'langName' => $langName,
                    'langUrl' => ($isSeoUrl && !empty(str_replace($shopBaseUrl, '', $originLink)))
                        ? $shopBaseUrl . $langIso . '/' . str_replace($shopBaseUrl, '', $originLink)
                        : $originLink . (strpos($originLink, '?') !== false ? '&' : '?') . 'langOnDemand=' . $langIso,
                ];
            }
        }

        return $langOnDemandLanguageUrls;
    }

    /**
     * Only the on-demand languages, without the shop's own.
     *
     * getLanguagesOnDemandHrefLangUrls() returns both, which is what a template wants when it
     * replaces the shop's language output wholesale. The template blocks this module ships
     * append to what OXID already rendered instead, so they need the additions on their own -
     * otherwise every shop language would appear twice.
     *
     * @return array<int, array{langIso: string, langName: string, langUrl: string}>
     */
    public function getOnDemandLanguageUrls(): array
    {
        /** @var DeepL $deepL */
        $deepL = Registry::get(DeepL::class);
        $onDemand = $deepL->getLanguagesOnDemand();

        return array_values(array_filter(
            $this->getLanguagesOnDemandHrefLangUrls(),
            static function (array $language) use ($onDemand): bool {
                return isset($onDemand[$language['langIso']]);
            }
        ));
    }

    public function getActiveLanguageOnDemand(): string
    {
        /** @var DeepL $deepL */
        $deepL = Registry::get(DeepL::class);

        return $deepL->getActiveLanguageOnDemand();
    }

    public function deepLTranslate(string $text): string
    {
        /** @var DeepL $deepL */
        $deepL = Registry::get(DeepL::class);
        $langOnDemand = $deepL->getActiveLanguageOnDemand();

        if (!empty($langOnDemand)) {
            return $deepL->translateText(
                Registry::getLang()->getLanguageAbbr(),
                $langOnDemand,
                $text
            );
        }

        return $text;
    }

    public function getHiddenSid()
    {
        $hiddenSid = parent::getHiddenSid();

        $langOnDemand = $this->getActiveLanguageOnDemand();
        if (!empty($langOnDemand)) {
            $hiddenSid .= "\n<input type=\"hidden\" name=\"langOnDemand\" value=\"" . htmlspecialchars($langOnDemand) . "\" />";
        }

        return $hiddenSid;
    }

    public function getHomeLink()
    {
        $homeLink = parent::getHomeLink();
        $activeLangOnDemand = $this->getActiveLanguageOnDemand();

        if ($activeLangOnDemand && strpos($homeLink, 'index.php') !== false) {
            $homeLink = $homeLink . (strpos($homeLink, '?') !== false ? '&' : '?') . 'langOnDemand=' . $activeLangOnDemand;
        }

        return $homeLink;
    }
}
