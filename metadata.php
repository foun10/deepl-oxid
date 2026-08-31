<?php

use foun10\DeepL\Controller\Admin\GlossaryController;
use foun10\DeepL\Controller\Admin\StatsController;

$sMetadataVersion = '2.1';

/**
 * Metadata file for module
 */
$aModule = [
    'id' => 'foun10DeepL',
    'title' => 'foun10 - DeepL',
    'description' => [
        'de' => 'Übersetzt Shop-Inhalte per DeepL zur Laufzeit.',
        'en' => 'Translates shop content at runtime via DeepL.',
    ],
    'version' => '7.0.0',
    'author' => 'foun10 GmbH',
    'email' => 'info@foun10.de',
    'extend' => [
        \OxidEsales\Eshop\Application\Controller\SearchController::class => \foun10\DeepL\Extension\Application\Controller\SearchController::class,
        \OxidEsales\Eshop\Core\Output::class => \foun10\DeepL\Extension\Core\Output::class,
        \OxidEsales\Eshop\Core\SeoDecoder::class => \foun10\DeepL\Extension\Core\SeoDecoder::class,
        \OxidEsales\Eshop\Core\ViewConfig::class => \foun10\DeepL\Extension\Core\ViewConfig::class,
        \OxidEsales\Eshop\Core\UtilsUrl::class => \foun10\DeepL\Extension\Core\UtilsUrl::class,
        \OxidEsales\Eshop\Core\ShopControl::class => \foun10\DeepL\Extension\Core\ShopControl::class,
        \OxidEsales\Eshop\Core\WidgetControl::class => \foun10\DeepL\Extension\Core\WidgetControl::class,
        \OxidEsales\Eshop\Core\Language::class => \foun10\DeepL\Extension\Core\Language::class,
        \OxidEsales\Eshop\Application\Model\Actions::class => \foun10\DeepL\Extension\Application\Model\Actions::class,
        \OxidEsales\Eshop\Application\Model\Article::class => \foun10\DeepL\Extension\Application\Model\Article::class,
        \OxidEsales\Eshop\Application\Model\Attribute::class => \foun10\DeepL\Extension\Application\Model\Attribute::class,
        \OxidEsales\Eshop\Application\Model\Category::class => \foun10\DeepL\Extension\Application\Model\Category::class,
        \OxidEsales\Eshop\Application\Model\Content::class => \foun10\DeepL\Extension\Application\Model\Content::class,
        \OxidEsales\Eshop\Application\Model\Country::class => \foun10\DeepL\Extension\Application\Model\Country::class,
        \OxidEsales\Eshop\Application\Model\Delivery::class => \foun10\DeepL\Extension\Application\Model\Delivery::class,
        \OxidEsales\Eshop\Application\Model\DeliverySet::class => \foun10\DeepL\Extension\Application\Model\DeliverySet::class,
        \OxidEsales\Eshop\Application\Model\Links::class => \foun10\DeepL\Extension\Application\Model\Links::class,
        \OxidEsales\Eshop\Application\Model\Manufacturer::class => \foun10\DeepL\Extension\Application\Model\Manufacturer::class,
        \OxidEsales\Eshop\Application\Model\Payment::class => \foun10\DeepL\Extension\Application\Model\Payment::class,
        \OxidEsales\Eshop\Application\Model\SelectList::class => \foun10\DeepL\Extension\Application\Model\SelectList::class,
        \OxidEsales\Eshop\Application\Model\State::class => \foun10\DeepL\Extension\Application\Model\State::class,
        \OxidEsales\Eshop\Application\Model\Vendor::class => \foun10\DeepL\Extension\Application\Model\Vendor::class,
        \OxidEsales\Eshop\Application\Model\Wrapping::class => \foun10\DeepL\Extension\Application\Model\Wrapping::class,
    ],
    'events' => [
        'onActivate'   => \foun10\DeepL\Events\ModuleEvent::class . '::onActivate',
    ],
    'controllers' => [
        'foun10_deepl_stats' => StatsController::class,
        'foun10_deepl_glossary' => GlossaryController::class,
    ],
    // OXID 7 registers views/twig/ automatically as a Twig namespace named after the
    // module ID (@foun10DeepL/...). On b-6.x this block holds the Smarty .tpl mapping.
    'templates' => [],
    'settings' => [
        [
            'group' => 'foun10DeepL',
            'name' => 'foun10DeepLApiKey',
            'type' => 'str',
            'value' => '',
        ],
        [
            'group' => 'foun10DeepLConfiguration',
            'name' => 'foun10DeepLSourceLanguage',
            'type' => 'str',
            'value' => 'en',
        ],
        [
            'group' => 'foun10DeepLConfiguration',
            'name' => 'foun10DeepLLanguagesOnDemand',
            'type' => 'aarr',
            'value' => [
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
            ],
        ],
        [
            'group' => 'foun10DeepLConfiguration',
            'name' => 'foun10DeepLGlossaryId',
            'type' => 'str',
            'value' => '',
        ],
    ],
];
