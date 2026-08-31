<?php

$sLangName = 'English';

$aLang = [
    'charset' => 'UTF-8',

    // Labels for the settings screen. OXID looks them up as SHOP_MODULE_GROUP_<group>
    // and SHOP_MODULE_<name>; without them the screen reads
    // "ERROR: Translation for ... not found!".
    'SHOP_MODULE_GROUP_foun10DeepL' => 'DeepL access',
    'SHOP_MODULE_GROUP_foun10DeepLConfiguration' => 'Configuration',
    'SHOP_MODULE_foun10DeepLSourceLanguage' => 'Source language',
    'HELP_SHOP_MODULE_foun10DeepLSourceLanguage' => 'Abbreviation of the shop language translations are generated from (for example de or en). Must be a language the shop has configured; an unknown value falls back to en.',
    'SHOP_MODULE_foun10DeepLApiKey' => 'DeepL API key',
    'HELP_SHOP_MODULE_foun10DeepLApiKey' => 'Without a key the module does not translate. Find it in your DeepL account under "Account &raquo; Authentication Key".',
    'SHOP_MODULE_foun10DeepLLanguagesOnDemand' => 'Offered languages',
    'HELP_SHOP_MODULE_foun10DeepLLanguagesOnDemand' => 'Languages offered in addition to the ones the shop maintains, as abbreviation and display name pairs (for example es => Español). Clearing the list switches the extra languages off without deactivating the module.',
    'SHOP_MODULE_foun10DeepLGlossaryId' => 'DeepL glossary ID',
    'HELP_SHOP_MODULE_foun10DeepLGlossaryId' => 'Optional. ID of a multilingual DeepL glossary; applied automatically for the language pairs it covers.',

    'FOUN10_MODULES' => 'foun10 Modules',
    'FOUN10_DEEPL' => 'DeepL',
    'FOUN10_DEEPL_STATS' => 'Stats',
    'FOUN10_DEEPL_GLOSSARY' => 'Glossary',

    'DL_STATUS_LIVE' => 'Live',
    'DL_STATUS_TEST' => 'Test mode',

    'DL_SECTION_GLOSSARY' => 'Glossary',
    'DL_GLOSSARY_ID_LABEL' => 'Glossary ID',
    'DL_GLOSSARY_EMPTY_HINT' => 'No glossary ID configured yet. Set "foun10DeepLGlossaryId" under Module settings.',
    'DL_GLOSSARY_ERROR_PREFIX' => 'Could not load glossary',
    'DL_GLOSSARY_CREATED_LABEL' => 'Created',
    'DL_GLOSSARY_NO_DICTIONARIES' => 'This glossary has no dictionaries yet.',
    'DL_DICT_NO_ENTRIES' => 'This dictionary has no entries yet.',
    'DL_TABLE_SOURCE_LANG' => 'From',
    'DL_TABLE_TARGET_LANG' => 'To',
    'DL_TABLE_ENTRIES' => 'Entries',

    'DL_SECTION_ADD_ENTRY' => 'Add glossary entry',
    'DL_HINT_ADD_ENTRY' => 'Source language is always the shop\'s base "language on demand" origin, target language is limited to the languages configured in the module. Adding a term that already exists overwrites its translation; every other entry in the glossary is left untouched.',
    'DL_LABEL_SOURCE_LANG' => 'Source language',
    'DL_LABEL_SOURCE_TERM' => 'Source term',
    'DL_LABEL_TARGET_LANG' => 'Target language',
    'DL_LABEL_TARGET_TERM' => 'Translation',
    'DL_BUTTON_ADD_ENTRY' => 'Add entry',
    'DL_MSG_ENTRY_ADDED' => 'Entry added.',
    'DL_ADDENTRY_ERR_NO_GLOSSARY' => 'No glossary ID configured.',
    'DL_ADDENTRY_ERR_INVALID' => 'Please fill in the source term, target language and translation.',
    'DL_ADDENTRY_ERR_API_PREFIX' => 'Could not save entry',

    'DL_SECTION_CACHE_SEARCH' => 'Delete cache entries by translated word',
    'DL_HINT_CACHE_SEARCH' => 'Only a hash of the source text is stored (never the text itself), so entries can only be searched by their translated output. Useful to purge outdated translations of a term from the cache after adding it to the glossary - the text is then translated again on its next request.',
    'DL_SEARCH_PLACEHOLDER' => 'Translated word or text fragment...',
    'DL_BUTTON_SEARCH' => 'Search',
    'DL_HINT_SEARCH_TOO_SHORT' => 'Please enter at least this many characters',
    'DL_SEARCH_NO_MATCHES' => 'No cache entries found.',
    'DL_SEARCH_MATCH_SUFFIX' => 'contain',
    'DL_CONFIRM_DELETE_PREFIX' => 'Really delete',
    'DL_CONFIRM_DELETE_SUFFIX' => 'permanently? Affected texts will be re-translated on their next request. Search term:',
    'DL_BUTTON_DELETE' => 'Delete matching entries',
    'DL_MSG_DELETED_PREFIX' => 'Deleted',
    'DL_MSG_DELETED_SUFFIX' => 'cache entries for search term',

    'DL_SECTION_USAGE' => 'DeepL usage (current billing period)',
    'DL_USAGE_ERROR_PREFIX' => 'Could not load usage data',
    'DL_USAGE_NO_DATA' => 'No usage data available.',
    'DL_USAGE_CHARACTERS_LABEL' => 'characters',
    'DL_USAGE_NO_LIMIT' => 'no limit set',

    'DL_SECTION_CACHE' => 'Local translation cache',
    'DL_CACHE_TOTAL_ENTRIES' => 'Total entries',
    'DL_CACHE_TOTAL_CHARACTERS' => 'Total characters',
    'DL_CACHE_NEWEST_ENTRY' => 'Newest entry',
    'DL_CACHE_EMPTY' => 'The translation cache is still empty.',
    'DL_TABLE_FROM_LANG' => 'Source lang',
    'DL_TABLE_TO_LANG' => 'Target lang',
    'DL_TABLE_CACHE_ENTRIES' => 'Entries',
    'DL_TABLE_CACHE_CHARACTERS' => 'Characters',
    'DL_TABLE_TOTAL' => 'Total',
];
