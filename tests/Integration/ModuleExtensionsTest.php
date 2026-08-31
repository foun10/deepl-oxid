<?php

declare(strict_types=1);

namespace foun10\DeepL\Tests\Integration;

use foun10\DeepL\Core\DeepL;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingServiceInterface;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that the module is actually wired into the running shop.
 *
 * None of this is reachable from a unit test: the *_parent classes only exist once OXID has
 * built the extension chain for an activated module. These tests exist because of two failure
 * modes that produce no error at all:
 *
 * 1. An extend entry pointing at a shop class that no longer exists - OXID 7 dropped
 *    Application\Model\News, and activation aborts.
 * 2. An override whose method name no longer matches the parent. OXID 7 removed the
 *    underscore prefix from protected methods, so an override still called _runOnce() is
 *    simply never invoked. No exception, no log line, the feature just stops working.
 */
class ModuleExtensionsTest extends TestCase
{
    /**
     * @dataProvider extendedClassProvider
     */
    public function testShopClassIsExtendedByTheModule(string $shopClass, string $moduleClass): void
    {
        $instance = oxNew($shopClass);

        $this->assertInstanceOf(
            $moduleClass,
            $instance,
            $shopClass . ' is not extended - check the extend map in metadata.php'
        );
    }

    public function extendedClassProvider(): array
    {
        $cases = [
            \OxidEsales\Eshop\Core\Language::class => \foun10\DeepL\Extension\Core\Language::class,
            \OxidEsales\Eshop\Core\Output::class => \foun10\DeepL\Extension\Core\Output::class,
            \OxidEsales\Eshop\Application\Controller\SearchController::class => \foun10\DeepL\Extension\Application\Controller\SearchController::class,
            \OxidEsales\Eshop\Core\SeoDecoder::class => \foun10\DeepL\Extension\Core\SeoDecoder::class,
            \OxidEsales\Eshop\Core\UtilsUrl::class => \foun10\DeepL\Extension\Core\UtilsUrl::class,
            \OxidEsales\Eshop\Core\ViewConfig::class => \foun10\DeepL\Extension\Core\ViewConfig::class,
            \OxidEsales\Eshop\Core\ShopControl::class => \foun10\DeepL\Extension\Core\ShopControl::class,
            \OxidEsales\Eshop\Core\WidgetControl::class => \foun10\DeepL\Extension\Core\WidgetControl::class,
            \OxidEsales\Eshop\Application\Model\Article::class => \foun10\DeepL\Extension\Application\Model\Article::class,
            \OxidEsales\Eshop\Application\Model\Category::class => \foun10\DeepL\Extension\Application\Model\Category::class,
            \OxidEsales\Eshop\Application\Model\Manufacturer::class => \foun10\DeepL\Extension\Application\Model\Manufacturer::class,
            \OxidEsales\Eshop\Application\Model\Content::class => \foun10\DeepL\Extension\Application\Model\Content::class,
        ];

        $data = [];
        foreach ($cases as $shopClass => $moduleClass) {
            $data[$shopClass] = [$shopClass, $moduleClass];
        }

        return $data;
    }

    /**
     * The override guard, derived by reflection rather than from a list.
     *
     * Every method a module extension declares is either an override - it hooks into the shop
     * by replacing a parent method - or a helper the module added itself. The dangerous case is
     * a method that was an override and quietly stopped being one, because the shop then never
     * calls it: no error, no log entry, the feature simply does nothing.
     *
     * An earlier version of this test checked a hand-maintained list and therefore missed
     * exactly that. UtilsView::parseThroughSmarty() overrode nothing on OXID 7 - the method does
     * not exist there - so the fallback that translated template-bearing content never ran.
     *
     * Anything genuinely new must be named below, which makes adding one a deliberate act.
     *
     * @dataProvider extensionClassProvider
     */
    public function testEveryMethodEitherOverridesSomethingOrIsDeclaredNew(string $moduleClass): void
    {
        $parent = get_parent_class($moduleClass);
        $this->assertNotFalse($parent, $moduleClass . ' has no parent - is the module activated?');

        $reflection = new \ReflectionClass($moduleClass);
        $unexpected = [];

        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $moduleClass) {
                continue;
            }
            if (method_exists($parent, $method->getName())) {
                continue;
            }
            if (in_array($method->getName(), self::METHODS_THE_MODULE_ADDS, true)) {
                continue;
            }
            $unexpected[] = $method->getName();
        }

        $this->assertSame(
            [],
            $unexpected,
            sprintf(
                '%s declares method(s) that override nothing in %s. Either the shop renamed them '
                . '(then the hook is dead and the feature silently stopped working), or they are '
                . 'new helpers and belong in METHODS_THE_MODULE_ADDS.',
                $moduleClass,
                $parent
            )
        );
    }

    /**
     * Methods the module adds on purpose - template helpers and the shared trait.
     */
    private const METHODS_THE_MODULE_ADDS = [
        // called from the module's own templates
        'getLanguagesOnDemandHrefLangUrls',
        'getActiveLanguageOnDemand',
        'deepLTranslate',
        'getOnDemandLanguageUrls',
        // MultilangModel trait
        'getUntranslatableFields',
        'applyMultilangFieldTranslations',
        'containsTemplateSyntax',
        // helpers on the control classes and the seo decoder
        'deepLForceShopLangForLanguageOnDemand',
        'detectLangOnDemandPrefix',
        'translateSearchParameterToShopLanguage',
        'swapLangOnDemandPrefix',
    ];

    public function extensionClassProvider(): array
    {
        $cases = [];
        foreach (glob(__DIR__ . '/../../src/Extension/Core/*.php') as $file) {
            $class = 'foun10\\DeepL\\Extension\\Core\\' . basename($file, '.php');
            $cases[$class] = [$class];
        }
        foreach (glob(__DIR__ . '/../../src/Extension/Application/*/*.php') as $file) {
            $class = 'foun10\\DeepL\\Extension\\Application\\'
                . basename(dirname($file)) . '\\' . basename($file, '.php');
            $cases[$class] = [$class];
        }

        return $cases;
    }

    /**
     * Regression guard. The module reads its API key through getModuleSetting(); reading it
     * through Config::getConfigParam() - as it did until this test was written - returns null
     * on OXID 7, because module settings live in the module configuration and never reach
     * oxconfig. The symptom is an empty API key and no error anywhere.
     */
    public function testApiKeyIsReadBackThroughThePathTheModuleUses(): void
    {
        $settingService = ContainerFactory::getInstance()
            ->getContainer()
            ->get(ModuleSettingServiceInterface::class);

        $settingService->saveString('foun10DeepLApiKey', 'integration-test-key', DeepL::MODULE_ID);

        try {
            $deepL = new class extends DeepL {
                public function readApiKey(): string
                {
                    return $this->getApiKey();
                }
            };

            $this->assertSame('integration-test-key', $deepL->readApiKey());
        } finally {
            $settingService->saveString('foun10DeepLApiKey', '', DeepL::MODULE_ID);
        }
    }
}
