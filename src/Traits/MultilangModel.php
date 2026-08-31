<?php
declare(strict_types=1);

namespace foun10\DeepL\Traits;

use foun10\DeepL\Core\DeepL;
use OxidEsales\Eshop\Core\DbMetaDataHandler;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;

trait MultilangModel
{
    /**
     * @var array<string, array{original: string, hash: int}>
     * Keyed by field name. 'original' is the pre-translation value to restore on save();
     * 'hash' is crc32 of the translated value used to detect whether the field was changed after assign().
     */
    protected array $_deepLOriginalFields = [];

    public function assign($dbRecord)
    {
        parent::assign($dbRecord);

        if (!$this->isAdmin()) {
            /** @var DeepL $deepL */
            $deepL = Registry::get(DeepL::class);

            if ($deepL->isDeepLTranslateActive() && $deepL->getActiveLanguageOnDemand()) {
                $this->applyMultilangFieldTranslations();
            }
        }
    }

    public function save()
    {
        // Only restore original value when the field still holds the DeepL translation.
        // If the value was changed after assign(), the new value must reach the DB unchanged.
        if (!empty($this->_deepLOriginalFields)) {
            $table = $this->getCoreTableName();
            foreach ($this->_deepLOriginalFields as $fieldName => $entry) {
                // Same cast as in applyMultilangFieldTranslations(): a numeric multilang
                // column arrives as int, and crc32() with a non-string is a TypeError here.
                $currentValue = (string) $this->{$table . '__' . $fieldName}->rawValue;
                if (crc32($currentValue) === $entry['hash']) {
                    $this->{$table . '__' . $fieldName} = new Field($entry['original'], Field::T_RAW);
                }
            }
        }

        return parent::save();
    }

    protected static array $_deepLMultilangFieldsCache = [];

    /**
     * Multilang fields that are never rendered to customers and therefore gain nothing from
     * translation — e.g. oxsearchkeys only feeds internal search matching (article numbers,
     * EANs, etc. concatenated together) and is never displayed on the storefront.
     */
    protected function getUntranslatableFields(): array
    {
        return ['oxsearchkeys'];
    }

    protected function containsTemplateSyntax(string $value): bool
    {
        foreach (['{{', '{%', '[{'] as $delimiter) {
            if (strpos($value, $delimiter) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function applyMultilangFieldTranslations(): void
    {
        $table = $this->getCoreTableName();

        /** @var DeepL $deepL */
        $deepL = Registry::get(DeepL::class);

        if (!isset(static::$_deepLMultilangFieldsCache[$table])) {
            /** @var DbMetaDataHandler $dbHandler */
            $dbHandler = oxNew(DbMetaDataHandler::class);
            static::$_deepLMultilangFieldsCache[$table] = $dbHandler->getMultilangFields($table);
        }
        $multilangFields = static::$_deepLMultilangFieldsCache[$table];

        foreach ($multilangFields as $dbField) {
            $dbField = strtolower($dbField ?? '');

            if (in_array($dbField, $this->getUntranslatableFields(), true)) {
                continue;
            }

            // Cast: multilang columns are not always text. A numeric one arrives as int,
            // and strpos() with an int haystack is a TypeError on PHP 8 - it took down the
            // whole widget that rendered such a field.
            $fieldValue = (string) $this->{$table . '__' . $dbField}->rawValue;

            // Skip fields containing template syntax - DeepL mangles the delimiters before the
            // template engine gets to resolve them. OXID 7 renders CMS content with Twig, so the
            // Twig delimiters are what matter here; [{ is still checked because content migrated
            // from an OXID 6 shop keeps the old Smarty markup in the database.
            //
            // Note that on this branch such fields end up untranslated: the OXID 6 fallback that
            // translated them after template parsing hung off UtilsView::parseThroughSmarty(),
            // which OXID 7 does not have.
            if (!empty($fieldValue) && !$this->containsTemplateSyntax($fieldValue)) {
                $translatedField = $deepL->translateText(
                    Registry::getLang()->getLanguageAbbr(),
                    $deepL->getActiveLanguageOnDemand(),
                    $fieldValue,
                    [
                        'tag_handling' => 'html',
                    ]
                );

                $this->_deepLOriginalFields[$dbField] = [
                    'original' => $fieldValue,
                    'hash'     => crc32($translatedField),
                ];

                $this->{$table . '__' . $dbField} = new Field($translatedField, Field::T_RAW);
            }
        }
    }
}
