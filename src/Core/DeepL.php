<?php
declare(strict_types=1);

namespace foun10\DeepL\Core;

use DeepL\DeepLClient;
use DeepL\TranslateTextOptions;
use foun10\DeepL\Model\Translation;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingServiceInterface;

class DeepL
{
    /** Module id as declared in metadata.php - the key module settings are stored under. */
    public const MODULE_ID = 'foun10DeepL';

    /** Shop language the translations are generated from, stored as its abbreviation. */
    public const SOURCE_LANGUAGE_SETTING = 'foun10DeepLSourceLanguage';

    /** Used when the setting is empty or names a language the shop does not have. */
    public const SOURCE_LANGUAGE_FALLBACK = 'en';

    /** Languages offered on demand, stored as "abbreviation => label" pairs. */
    public const LANGUAGES_ON_DEMAND_SETTING = 'foun10DeepLLanguagesOnDemand';

    /** Every target language DeepL supports - what a fresh installation starts with. */
    public const DEFAULT_LANGUAGES_ON_DEMAND = [
        'bg' => 'Български',
        'cs' => 'Čeština',
        'da' => 'Dansk',
        'el' => 'Ελληνικά',
        'es' => 'Español',
        'et' => 'Eesti',
        'fi' => 'Suomi',
        'fr' => 'Français',
        'hr' => 'Hrvatski',
        'hu' => 'Magyar',
        'it' => 'Italiano',
        'lt' => 'Lietuvių',
        'lv' => 'Latviešu',
        'nl' => 'Nederlands',
        'pl' => 'Polski',
        'pt' => 'Português',
        'ro' => 'Română',
        'sk' => 'Slovenčina',
        'sl' => 'Slovenščina',
        'sv' => 'Svenska',
        'he' => 'עברית',
        'ja' => '日本語',
        'zh' => '中文',
        'uk' => 'Українська',
        'ru' => 'Русский',
        'no' => 'Norsk',
        'ar' => 'العربية',
        'ko' => '한국어',
    ];

    protected $cookiesSet = false;

    // Seams for the shop singletons.
    //
    // Every call this class makes into the shop goes through one of the methods below, so
    // unit tests can override them individually instead of bootstrapping a shop. Keep them
    // free of logic - anything implemented in here is untestable by definition.

    /**
     * @return mixed
     */
    protected function getConfigParam(string $name)
    {
        return Registry::getConfig()->getConfigParam($name);
    }

    /**
     * Module settings live in the module configuration in OXID 7, not in oxconfig, so
     * Config::getConfigParam() returns null for them - reading them that way silently yields
     * an empty API key. The b-6.x branch, where module settings do live in oxconfig, reads
     * them through getConfigParam() instead.
     */
    protected function getModuleSetting(string $name): string
    {
        $settingService = ContainerFactory::getInstance()
            ->getContainer()
            ->get(ModuleSettingServiceInterface::class);

        return (string) $settingService->getString($name, self::MODULE_ID);
    }

    /**
     * @return \Psr\Log\LoggerInterface
     */
    protected function getLogger()
    {
        return Registry::getLogger();
    }

    /**
     * Deliberately without a return type: Translation extends OXID's BaseModel, which cannot be
     * autoloaded outside a bootstrapped shop, so a declared type would make this seam
     * impossible to substitute in a unit test.
     *
     * @return Translation
     */
    protected function getTranslationModel()
    {
        return oxNew(Translation::class);
    }

    /**
     * @return mixed
     */
    protected function getRequestParameter(string $name)
    {
        return Registry::getRequest()->getRequestEscapedParameter($name);
    }

    /**
     * @return mixed
     */
    protected function getCookie(string $name)
    {
        return Registry::getUtilsServer()->getOxCookie($name);
    }

    protected function setCookie(string $name, string $value): void
    {
        Registry::getUtilsServer()->setOxCookie($name, $value, 0, '/', null, true, false, false);
    }

    /**
     * Array-valued module settings need their own accessor - the string one cannot carry them.
     *
     * @return array<string, string>
     */
    protected function getModuleSettingCollection(string $name): array
    {
        $settingService = ContainerFactory::getInstance()
            ->getContainer()
            ->get(ModuleSettingServiceInterface::class);

        return (array) $settingService->getCollection($name, self::MODULE_ID);
    }

    protected function getShopLanguageAbbr(): string
    {
        return (string) Registry::getLang()->getLanguageAbbr();
    }

    /**
     * @return object[] the shop's configured languages, each with ->id and ->abbr
     */
    protected function getShopLanguages(): array
    {
        return Registry::getLang()->getLanguageArray();
    }

    protected function getSessionChallengeToken(): string
    {
        return (string) Registry::getSession()->getSessionChallengeToken();
    }

    protected function getApiKey(): string
    {
        return $this->getModuleSetting('foun10DeepLApiKey');
    }

    /**
     * When set via config.local.inc.php ($this->blDeepLTestMode = true;), translateText() never calls
     * the DeepL API and never writes to foun10deepltranslations - it returns the text untouched.
     */
    public function isTestModeActive(): bool
    {
        return (bool) $this->getConfigParam('blDeepLTestMode');
    }

    /**
     * Public so the admin overview page (DeepLStats) can reuse it for usage/glossary lookups without
     * duplicating auth-key handling.
     */
    public function getTranslator(): DeepLClient
    {
        $authKey = $this->getApiKey();

        return new DeepLClient($authKey);
    }

    protected function getTextHash(string $text): string
    {
        $text = html_entity_decode(trim($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return md5($text);
    }

    /**
     * The hash is part of the cache key (see doTranslateText()), so the options are sorted
     * by key first: the same options in a different order must hit the same entry, otherwise
     * avoidable cache misses occur - and every miss is a billed DeepL call. All current
     * callers pass [] or exactly one key, so existing cache entries keep their hash.
     */
    protected function getOptionHash(array $options): string
    {
        ksort($options);

        return (string) crc32(\json_encode($options));
    }

    protected function getDeepLLang(string $lang): string
    {
        $mappingLanguages = [
            'en' => 'en-US',
            'pt' => 'pt-PT',
            'no' => 'nb',
        ];

        return $mappingLanguages[$lang] ?? $lang;
    }

    /**
     * Config param foun10DeepLGlossaryId - a single DeepL v3 "multilingual" glossary containing one
     * dictionary per source/target language pair. DeepL selects the matching dictionary at translate
     * time based on the request's source_lang/target_lang, so one glossary_id covers every pair.
     */
    protected function getGlossaryId(): ?string
    {
        $glossaryId = $this->getModuleSetting('foun10DeepLGlossaryId');

        return $glossaryId !== '' ? $glossaryId : null;
    }

    /**
     * "{sourceLang}_{targetLang}" pairs covered by the glossary's dictionaries, fetched once per
     * request. DeepL 400s a translate call whose language pair isn't in the glossary - and the
     * generic DeepLException handler below would then permanently cache the untranslated text for
     * that pair - so callers MUST check this before attaching glossary_id for a pair that isn't in it.
     */
    protected static ?array $glossaryDictionaryPairs = null;

    protected function getGlossaryDictionaryPairs(string $glossaryId): array
    {
        if (self::$glossaryDictionaryPairs !== null) {
            return self::$glossaryDictionaryPairs;
        }

        self::$glossaryDictionaryPairs = [];

        try {
            $glossary = $this->getTranslator()->getMultilingualGlossary($glossaryId);

            foreach ($glossary->dictionaries as $dictionary) {
                self::$glossaryDictionaryPairs[] = $dictionary->sourceLang . '_' . $dictionary->targetLang;
            }
        } catch (\Throwable $e) {
            $this->getLogger()->warning('DeepL glossary lookup failed, translating without glossary: ' . $e->getMessage());
        }

        return self::$glossaryDictionaryPairs;
    }

    protected static array $runtimeCache = [];

    /**
     * crc32() of texts already produced as a DeepL translation output during this request, keyed
     * by target language. Guards against re-translating already-translated content — e.g. a variant
     * without its own long description falls back to the parent article's (already translated) text,
     * which would otherwise be sent through DeepL a second time and create a duplicate cache row.
     */
    protected static array $translatedOutputs = [];

    protected static bool $throttled = false;

    protected static float $totalTranslationTime = 0.0;

    protected static float $translationTimeLimit = 10.0;

    /**
     * CSS classes whose HTML blocks are extracted before translation and restored afterwards.
     * Keeps dynamic/user-generated content (e.g. review quotes) out of DeepL and out of the cache key.
     */
    protected function getUntranslatableCssClasses(): array
    {
        return ['votingcomment'];
    }

    /**
     * Replaces untranslatable HTML blocks with stable placeholders.
     * Returns [text with placeholders, placeholder→original map].
     *
     * @return array{0: string, 1: array<string, string>}
     */
    protected function extractUntranslatableBlocks(string $text): array
    {
        $blocks = [];

        foreach ($this->getUntranslatableCssClasses() as $cssClass) {
            $text = preg_replace_callback(
                '/<(\w+)\b[^>]*\bclass="[^"]*\b' . preg_quote($cssClass, '/') . '\b[^"]*"[^>]*>.*?<\/\1>/s',
                function (array $match) use (&$blocks): string {
                    $placeholder = '__DEEPLSKIP' . count($blocks) . '__';
                    $blocks[$placeholder] = $match[0];
                    return $placeholder;
                },
                $text
            );
        }

        return [$text, $blocks];
    }

    protected function hasTranslatableContent(string $text): bool
    {
        $stripped = trim(strip_tags($text));

        // Single token with no whitespace ending in a file extension → filename or URL path
        if (!preg_match('/\s/', $stripped) && preg_match('/\.\w{2,5}$/', $stripped)) {
            return false;
        }

        // Overwhelmingly digits (e.g. concatenated article numbers/EANs) → nothing to translate,
        // even if a stray short letter code (unit, country suffix, ...) is mixed in.
        $alnum = preg_replace('/[^\p{L}\p{N}]/u', '', $stripped);
        if ($alnum !== null && $alnum !== '') {
            $digits = preg_replace('/[^\p{N}]/u', '', $alnum);
            if ($digits !== null && mb_strlen($digits) / mb_strlen($alnum) >= 0.9) {
                return false;
            }
        }

        // Requires at least one run of 2+ consecutive letters — excludes format strings
        // like "Y-m-d H:i:s" or "d.m.Y" where every letter stands alone.
        return preg_match('/\p{L}{2,}/u', $stripped) === 1;
    }

    /**
     * True once the per-request translation time budget has been exceeded
     * and translateText() has started returning untranslated fallback text
     * instead of calling DeepL - i.e. the page currently being rendered is
     * only partially translated.
     */
    public function isTranslationThrottled(): bool
    {
        return self::$throttled;
    }

    public function translateText(string $fromLang, string $toLang, string $text, ?array $translateOptions = []): string
    {
        if (!$this->hasTranslatableContent($text)) {
            return $text;
        }

        if ($this->isTestModeActive()) {
            return $text;
        }

        if (isset(self::$translatedOutputs[$toLang][crc32($text)])) {
            return $text;
        }

        $start = microtime(true);
        $result = $this->doTranslateText($fromLang, $toLang, $text, $translateOptions);
        self::$totalTranslationTime += microtime(true) - $start;

        if (!self::$throttled && self::$totalTranslationTime >= self::$translationTimeLimit) {
            self::$throttled = true;
            $this->getLogger()->warning(sprintf(
                'DeepL cumulative translation time exceeded %.1fs — throttling new API calls for remainder of request.',
                self::$translationTimeLimit
            ));
        }

        return $result;
    }

    protected function doTranslateText(string $fromLang, string $toLang, string $text, ?array $translateOptions = []): string
    {
        $translateOptions = $translateOptions ?? [];

        [$text, $skippedBlocks] = $this->extractUntranslatableBlocks($text);

        // A full HTML document (<!DOCTYPE ...><html>...</html>, sometimes with stray markup
        // trailing after </html>) pasted into a description field makes DeepL's tag_handling=html
        // parser reject the request with "multiple roots" — it only accepts a single fragment.
        // Extract just the <body> contents, which drops the doctype/head/title/meta scaffolding
        // and any trailing junk in one shot, leaving a single-root fragment to translate.
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $text, $bodyMatch)) {
            $text = trim($bodyMatch[1]);
        } else {
            $text = preg_replace('/^\s*<!DOCTYPE[^>]*>\s*/i', '', $text) ?? $text;
            $text = preg_replace('/<\/?html[^>]*>/i', '', $text) ?? $text;
            $text = preg_replace('/<head[^>]*>.*?<\/head>/is', '', $text) ?? $text;
        }

        $textHash = $this->getTextHash($text);
        $optionHash = $this->getOptionHash($translateOptions);

        $cacheKey = $fromLang . '|' . $toLang . '|' . $textHash . '|' . $optionHash;
        if (isset(self::$runtimeCache[$cacheKey])) {
            $result = strtr(self::$runtimeCache[$cacheKey], $skippedBlocks);
            self::$translatedOutputs[$toLang][crc32($result)] = true;

            return $result;
        }

        /** @var Translation $translationModel */
        $translationModel = $this->getTranslationModel();

        if ($translationModel->loadByParameter($fromLang, $toLang, $textHash, $optionHash)) {
            $cached = $translationModel->foun10deepltranslations__foun10translatedtext->rawValue;
            self::$runtimeCache[$cacheKey] = $cached;

            $result = strtr($cached, $skippedBlocks);
            self::$translatedOutputs[$toLang][crc32($result)] = true;

            return $result;
        }

        if (self::$throttled) {
            return strtr($text, $skippedBlocks);
        }

        try {
            $translator = $this->getTranslator();

            // Glossary is deliberately excluded from $optionHash above: whether a glossary happens to
            // be configured must not change the cache key, or every already-cached text would look
            // like a cache miss and get re-translated (and re-billed) the moment a glossary is added.
            $apiOptions = $translateOptions;
            if ($fromLang !== '' && !array_key_exists(TranslateTextOptions::GLOSSARY, $apiOptions)) {
                $glossaryId = $this->getGlossaryId();

                if ($glossaryId !== null && in_array($fromLang . '_' . $toLang, $this->getGlossaryDictionaryPairs($glossaryId), true)) {
                    $apiOptions[TranslateTextOptions::GLOSSARY] = $glossaryId;
                }
            }

            $translationResult = $translator->translateText(
                $text,
                $fromLang ?: null,
                $this->getDeepLLang($toLang),
                $apiOptions
            );

            $translation = $translationResult->text;

            $translationModel->assign([
                'FOUN10TEXTHASH' => $textHash,
                'FOUN10OPTIONHASH' => $optionHash,
                'FOUN10FROMLANG' => $fromLang,
                'FOUN10TOLANG' => $toLang,
                'FOUN10TRANSLATEDTEXT' => $translation,
            ]);

            try {
                $translationModel->save();
            } catch (\OxidEsales\Eshop\Core\Exception\DatabaseErrorException $e) {
                // Another concurrent request already cached this exact text/lang/option combination
                // (unique key TEXT_LANG_ID) between our loadByParameter() check and this save() — benign
                // race, not an error. Keep the translation we already got back from the API.
                if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                    throw $e;
                }
            }

            $result = $translation ?: $text;
            self::$runtimeCache[$cacheKey] = $result;

            $result = strtr($result, $skippedBlocks);
            self::$translatedOutputs[$toLang][crc32($result)] = true;

            return $result;
        } catch (\DeepL\TooManyRequestsException $e) {
            self::$throttled = true;
            $this->getLogger()->error('DeepL throttled — skipping translation for remainder of request.', [$e]);
        } catch (\DeepL\DeepLException $e) {
            // DeepL rejected the content itself (e.g. "Tag handling parsing failed ... multiple
            // roots" from a full HTML document pasted into a description field) — this is a data
            // problem, not a transient failure, and will fail identically on every retry. Cache the
            // untranslated text so we don't keep hitting the API and re-logging this on every view.
            $this->getLogger()->warning('DeepL rejected content, caching untranslated fallback: ' . $e->getMessage() . ' | text: ' . $text);

            $translationModel->assign([
                'FOUN10TEXTHASH' => $textHash,
                'FOUN10OPTIONHASH' => $optionHash,
                'FOUN10FROMLANG' => $fromLang,
                'FOUN10TOLANG' => $toLang,
                'FOUN10TRANSLATEDTEXT' => $text,
            ]);

            try {
                $translationModel->save();
            } catch (\OxidEsales\Eshop\Core\Exception\DatabaseErrorException $dbError) {
                if (strpos($dbError->getMessage(), 'Duplicate entry') === false) {
                    throw $dbError;
                }
            }

            self::$runtimeCache[$cacheKey] = $text;
        } catch (\Throwable $error) {
            $this->getLogger()->error($error->getMessage(), [$error]);
        }

        return strtr($text, $skippedBlocks);
    }

    protected static int $preloadMaxLength = 2000;

    protected function getPreloadCacheFile(string $fromLang, string $toLang): string
    {
        $tmpDir = rtrim((string) $this->getConfigParam('sCompileDir'), '/\\');
        return $tmpDir . '/deepl_preload_' . $fromLang . '_' . $toLang . '.php';
    }

    public function preloadTranslations(string $fromLang, string $toLang): void
    {
        if ($this->isTestModeActive()) {
            return;
        }

        $file = $this->getPreloadCacheFile($fromLang, $toLang);

        if (file_exists($file)) {
            $preloaded = include $file;
        } else {
            $db = \OxidEsales\Eshop\Core\DatabaseProvider::getDb(
                \OxidEsales\Eshop\Core\DatabaseProvider::FETCH_MODE_ASSOC
            );

            $rows = $db->getAll(
                'SELECT FOUN10TEXTHASH, FOUN10OPTIONHASH, FOUN10TRANSLATEDTEXT
                 FROM foun10deepltranslations
                 WHERE FOUN10FROMLANG = ? AND FOUN10TOLANG = ?
                   AND LENGTH(FOUN10TRANSLATEDTEXT) < ?',
                [$fromLang, $toLang, self::$preloadMaxLength]
            );

            $preloaded = [];
            foreach ($rows as $row) {
                $cacheKey = $fromLang . '|' . $toLang . '|' . $row['FOUN10TEXTHASH'] . '|' . $row['FOUN10OPTIONHASH'];
                $preloaded[$cacheKey] = $row['FOUN10TRANSLATEDTEXT'];
            }

            file_put_contents($file, '<?php return ' . var_export($preloaded, true) . ';', LOCK_EX);
        }

        self::$runtimeCache += $preloaded;
    }

    protected $languageOnDemand = null;

    const LANGUAGE_ON_DEMAND_URL_PARAMETER = 'langOnDemand';
    const LANGUAGE_ON_DEMAND_COOKIE_VARIABLE = 'langOnDemand';

    public function isDeepLTranslateActive(): bool
    {
        return true;
    }

    /**
     * Languages a visitor can request that the shop does not maintain itself.
     *
     * Configurable: which languages a shop wants to offer is its own decision, and the list
     * this shipped with was the one the module was originally built for. The setting holds
     * "abbreviation => label" pairs. Clearing it offers nothing, which is the intended way to
     * switch the feature off without deactivating the module - DEFAULT_LANGUAGES_ON_DEMAND is
     * only the value a fresh installation starts with.
     *
     * The abbreviations here are shop-side. Where DeepL expects a different code - en-US for
     * en, pt-PT for pt, nb for no - getDeepLLang() maps them on the way out, so a language
     * added to this setting is handled automatically.
     *
     * @return array<string, string>
     */
    public function getLanguagesOnDemand(): array
    {
        if (!$this->isDeepLTranslateActive()) {
            return [];
        }

        $configured = [];

        foreach ($this->getModuleSettingCollection(self::LANGUAGES_ON_DEMAND_SETTING) as $abbr => $name) {
            $abbr = strtolower(trim((string) $abbr));
            $name = trim((string) $name);

            if ($abbr !== '') {
                $configured[$abbr] = $name !== '' ? $name : $abbr;
            }
        }

        return $configured;
    }

    /**
     * The shop language every translation is generated from.
     *
     * Configurable, because assuming English is wrong for plenty of shops - a German-only shop
     * translating into French has to start from German. The setting holds the language
     * abbreviation as configured in the shop ('de', 'en', ...); an unknown or empty value falls
     * back to English, which is what this method used to return unconditionally.
     *
     * langSeoPrefix is empty for the shop's default language: OXID builds SEO URLs without a
     * prefix for it, and the callers check for the empty case.
     *
     * @return array{langId: int, langIso: string, langSeoPrefix: string}
     */
    public function getShopLangForLanguageOnDemand(): array
    {
        $configured = strtolower(trim($this->getModuleSetting(self::SOURCE_LANGUAGE_SETTING)));

        if ($configured === '') {
            $configured = self::SOURCE_LANGUAGE_FALLBACK;
        }

        $languages = $this->getShopLanguages();
        $match = null;

        foreach ($languages as $language) {
            if (strtolower((string) $language->abbr) === $configured) {
                $match = $language;
                break;
            }
        }

        if ($match === null) {
            foreach ($languages as $language) {
                if (strtolower((string) $language->abbr) === self::SOURCE_LANGUAGE_FALLBACK) {
                    $match = $language;
                    break;
                }
            }
        }

        if ($match === null) {
            return [
                'langId' => 1,
                'langIso' => self::SOURCE_LANGUAGE_FALLBACK,
                'langSeoPrefix' => self::SOURCE_LANGUAGE_FALLBACK,
            ];
        }

        $langId = (int) $match->id;

        return [
            'langId' => $langId,
            'langIso' => (string) $match->abbr,
            'langSeoPrefix' => $langId === 0 ? '' : (string) $match->abbr,
        ];
    }

    public function getActiveLanguageOnDemand(): string
    {
        if ($this->languageOnDemand === null) {
            $this->setActiveLanguageOnDemand();
        }

        return $this->languageOnDemand;
    }

    public function setActiveLanguageOnDemand(?string $langOnDemand = null)
    {
        $langOnDemand = $langOnDemand ?? $this->languageOnDemand;

        if ($langOnDemand === null) {
            $availableLanguagesOnDemand = array_keys($this->getLanguagesOnDemand());

            // Check url parameter
            $urlParameter = $this->getRequestParameter(self::LANGUAGE_ON_DEMAND_URL_PARAMETER);

            if ($langOnDemand === null && !empty($urlParameter) && in_array($urlParameter, $availableLanguagesOnDemand)) {
                $langOnDemand = $urlParameter;
            } elseif ((isset($_POST[self::LANGUAGE_ON_DEMAND_URL_PARAMETER]) || isset($_GET[self::LANGUAGE_ON_DEMAND_URL_PARAMETER])) && empty($urlParameter)) {
                $langOnDemand = '';
            }

            // Check cookie
            $cookieValue = $this->getCookie(self::LANGUAGE_ON_DEMAND_COOKIE_VARIABLE);

            if ($langOnDemand === null && !empty($cookieValue) && in_array($cookieValue, $availableLanguagesOnDemand)) {
                $langOnDemand = $cookieValue;
            }

            // Check browser
            if ($langOnDemand === null && isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && $_SERVER['HTTP_ACCEPT_LANGUAGE']) {
                $browserLanguage = strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2));

                if ($langOnDemand === null && !empty($browserLanguage) && in_array($browserLanguage, $availableLanguagesOnDemand)) {
                    $langOnDemand = $browserLanguage;
                }
            }

            $langOnDemand = $langOnDemand ?? '';
        }

        if ($langOnDemand !== null && !$this->cookiesSet) {
            // Flip the guard and cache the value BEFORE calling setOxCookie(): UtilsServer::setOxCookie()
            // resolves Config::isSsl()/getSslShopUrl()/getShopUrl(), which - when no explicit language id
            // is passed - falls back to Language::getBaseLanguage(). On a fresh session (no "language"
            // cookie yet) that reenters detectLanguageByBrowser() and lands right back here while the
            // outer call is still on the stack. If the guard isn't already tripped by then, this recurses
            // into setOxCookie() again on every reentry and blows the stack (observed as a 503).
            $this->cookiesSet = true;
            $this->languageOnDemand = $langOnDemand;

            // Save to cookie
            $this->setCookie(self::LANGUAGE_ON_DEMAND_COOKIE_VARIABLE, (string) $langOnDemand);

            return (string) $langOnDemand;
        }

        $this->languageOnDemand = $langOnDemand;

        return (string) $langOnDemand;
    }

    /**
     * Translates a search term back into the shop's language.
     *
     * A visitor reading the shop in an on-demand language types their query in that language,
     * but the catalogue is stored in the shop's own language - searching for "sofá" would find
     * nothing. The term is therefore translated back before the search runs.
     *
     * Returns the term unchanged when there is nothing to do, so callers can apply it
     * unconditionally.
     */
    public function translateSearchTerm(string $searchTerm): string
    {
        $langOnDemand = $this->getActiveLanguageOnDemand();

        if ($searchTerm === '' || $langOnDemand === '' || !$this->isDeepLTranslateActive()) {
            return $searchTerm;
        }

        return $this->translateText($langOnDemand, $this->getShopLanguageAbbr(), $searchTerm, []);
    }

    public function placeHtmlPlaceholders(string $html): string
    {
        $placeholders = $this->getHtmlPlaceholders();
        $html = str_replace(array_values($placeholders), array_keys($placeholders), $html);

        return $html;
    }

    public function replaceHtmlPlaceholders(string $html): string
    {
        $placeholders = $this->getHtmlPlaceholders();
        $html = str_replace(array_keys($placeholders), array_values($placeholders), $html);

        return $html;
    }

    protected function getHtmlPlaceholders(): array
    {
        return [
            '__STOKEN__' => $this->getSessionChallengeToken(),
        ];
    }
}
