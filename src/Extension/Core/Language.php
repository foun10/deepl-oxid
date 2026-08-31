<?php
declare(strict_types=1);

namespace foun10\DeepL\Extension\Core;

use foun10\DeepL\Core\DeepL;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;

class Language extends Language_parent
{
    private array $deepLStringCache = [];

    /**
     * OXID's own $aLang lang-file convention: a handful of technical idents at the top of
     * lang.php (charset, date-format tokens) sit alongside the real, translatable UPPERCASE
     * content idents. Their resolved values (e.g. "UTF-8" for the <meta charset> tag) aren't
     * user-facing text and must never be sent to DeepL - hasTranslatableContent() lets "UTF-8"
     * through since "UTF" is a 2+ letter run, so it needs an explicit ident-based exclusion here.
     */
    protected const NON_TRANSLATABLE_IDENTS = ['charset'];

    public function detectLanguageByBrowser()
    {
        $browserLanguage = $this->getBrowserLanguage();

        /** @var DeepL $deepL */
        $deepL = Registry::get(DeepL::class);

        // Set shop lang to e.g. "en" if browser language matches one of the languages on demand
        if (in_array($browserLanguage, array_keys($deepL->getLanguagesOnDemand()))) {
            $deepLShopLang = $deepL->getShopLangForLanguageOnDemand();

            return (int) $deepLShopLang['langId'];
        }

        return parent::detectLanguageByBrowser();
    }

    public function translateString($stringToTranslate, $lang = null, $adminMode = null)
    {
        $ident = is_string($stringToTranslate) ? strtolower($stringToTranslate) : null;

        $stringToTranslate = parent::translateString($stringToTranslate, $lang, $adminMode);

        if (is_array($stringToTranslate)) {
            return $stringToTranslate;
        }

        if ($ident !== null && in_array($ident, self::NON_TRANSLATABLE_IDENTS, true)) {
            return $stringToTranslate;
        }

        if (!$adminMode && $this->isTranslated()) {
            /** @var DeepL $deepL */
            $deepL = Registry::get(DeepL::class);
            $langOnDemand = $deepL->getActiveLanguageOnDemand();

            if (!empty($langOnDemand) && !empty($stringToTranslate)) {
                if (!array_key_exists($stringToTranslate, $this->deepLStringCache)) {
                    $this->deepLStringCache[$stringToTranslate] = $deepL->translateText(
                        Registry::getLang()->getLanguageAbbr(),
                        $langOnDemand,
                        $stringToTranslate,
                        ['tag_handling' => 'html']
                    );
                }
                $stringToTranslate = $this->deepLStringCache[$stringToTranslate];
            }
        }

        return $stringToTranslate;
    }
}
