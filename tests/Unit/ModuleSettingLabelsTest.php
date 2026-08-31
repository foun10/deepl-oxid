<?php

declare(strict_types=1);

namespace foun10\DeepL\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Every module setting needs a label in every admin language.
 *
 * OXID renders the settings screen by looking up SHOP_MODULE_GROUP_<group> for the group
 * heading and SHOP_MODULE_<name> for each field. When one is missing the backend does not fail
 * or log anything - it simply prints "ERROR: Translation for SHOP_MODULE_... not found!" where
 * the label should be, which is easy to ship without noticing.
 *
 * metadata.php can be included here without a shop: its `use` statements are only aliases and
 * `Foo::class` resolves to a string without loading the class.
 */
class ModuleSettingLabelsTest extends TestCase
{
    /**
     * @dataProvider languageFileProvider
     */
    public function testEverySettingHasALabelInThisLanguage(string $languageFile): void
    {
        $translations = $this->readLanguageFile($languageFile);
        $module = $this->readMetadata();

        $missing = [];
        foreach ($module['settings'] ?? [] as $setting) {
            foreach (
                [
                    'SHOP_MODULE_GROUP_' . $setting['group'],
                    'SHOP_MODULE_' . $setting['name'],
                ] as $ident
            ) {
                if (!array_key_exists($ident, $translations)) {
                    $missing[$ident] = true;
                }
            }
        }

        $this->assertSame(
            [],
            array_keys($missing),
            'missing in ' . basename(dirname($languageFile)) . ': the settings screen will show '
            . '"ERROR: Translation for ... not found!" instead of a label'
        );
    }

    public function languageFileProvider(): array
    {
        // views/admin_twig/<lang>/ on the OXID 7 branch, views/admin/<lang>/ on the OXID 6 one.
        $files = glob(__DIR__ . '/../../views/*/*/*_lang.php') ?: [];

        $cases = [];
        foreach ($files as $file) {
            $cases[basename(dirname(dirname($file))) . '/' . basename(dirname($file))] = [$file];
        }

        return $cases;
    }

    public function testThereIsAtLeastOneLanguageFile(): void
    {
        $this->assertNotEmpty(
            $this->languageFileProvider(),
            'no admin language files found - the provider glob no longer matches the layout'
        );
    }

    /**
     * @return array<string, string>
     */
    private function readLanguageFile(string $file): array
    {
        $aLang = [];
        require $file;

        return $aLang;
    }

    /**
     * @return array<string, mixed>
     */
    private function readMetadata(): array
    {
        $aModule = [];
        require __DIR__ . '/../../metadata.php';

        return $aModule;
    }
}
