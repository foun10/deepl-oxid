<?php

$sLangName = 'Deutsch';

$aLang = [
    'charset' => 'UTF-8',

    // Beschriftungen des Einstellungs-Bildschirms. OXID sucht sie unter
    // SHOP_MODULE_GROUP_<gruppe> bzw. SHOP_MODULE_<name>; fehlen sie, steht dort
    // "ERROR: Translation for ... not found!".
    'SHOP_MODULE_GROUP_foun10DeepL' => 'DeepL-Zugang',
    'SHOP_MODULE_GROUP_foun10DeepLConfiguration' => 'Konfiguration',
    'SHOP_MODULE_foun10DeepLSourceLanguage' => 'Ausgangssprache',
    'HELP_SHOP_MODULE_foun10DeepLSourceLanguage' => 'Kürzel der Shop-Sprache, aus der übersetzt wird (z. B. de oder en). Muss eine im Shop eingerichtete Sprache sein; ist sie unbekannt, wird auf en zurückgefallen.',
    'SHOP_MODULE_foun10DeepLApiKey' => 'DeepL API-Key',
    'HELP_SHOP_MODULE_foun10DeepLApiKey' => 'Ohne Key übersetzt das Modul nicht. Zu finden im DeepL-Konto unter "Account &raquo; Authentication Key".',
    'SHOP_MODULE_foun10DeepLLanguagesOnDemand' => 'Angebotene Sprachen',
    'HELP_SHOP_MODULE_foun10DeepLLanguagesOnDemand' => 'Sprachen, die zusätzlich angeboten werden, als Paare aus Kürzel und Anzeigename (z. B. es => Español). Die Liste leeren schaltet die Zusatzsprachen ab, ohne das Modul zu deaktivieren.',
    'SHOP_MODULE_foun10DeepLGlossaryId' => 'DeepL Glossar-ID',
    'HELP_SHOP_MODULE_foun10DeepLGlossaryId' => 'Optional. ID eines mehrsprachigen DeepL-Glossars; wird automatisch für die Sprachpaare angewendet, die es abdeckt.',

    'FOUN10_MODULES' => 'foun10 Module',
    'FOUN10_DEEPL' => 'DeepL',
    'FOUN10_DEEPL_STATS' => 'Statistik',
    'FOUN10_DEEPL_GLOSSARY' => 'Glossar',

    'DL_STATUS_LIVE' => 'Live',
    'DL_STATUS_TEST' => 'Testmodus',

    'DL_SECTION_GLOSSARY' => 'Glossar',
    'DL_GLOSSARY_ID_LABEL' => 'Glossar-ID',
    'DL_GLOSSARY_EMPTY_HINT' => 'Es ist noch keine Glossar-ID hinterlegt. Unter Modul-Einstellungen &raquo;foun10DeepLGlossaryId&laquo; setzen.',
    'DL_GLOSSARY_ERROR_PREFIX' => 'Glossar konnte nicht geladen werden',
    'DL_GLOSSARY_CREATED_LABEL' => 'Erstellt am',
    'DL_GLOSSARY_NO_DICTIONARIES' => 'Dieses Glossar enthält noch keine Wörterbücher.',
    'DL_DICT_NO_ENTRIES' => 'Dieses Wörterbuch enthält noch keine Einträge.',
    'DL_TABLE_SOURCE_LANG' => 'Von',
    'DL_TABLE_TARGET_LANG' => 'Nach',
    'DL_TABLE_ENTRIES' => 'Einträge',

    'DL_SECTION_ADD_ENTRY' => 'Glossar-Eintrag hinzufügen',
    'DL_HINT_ADD_ENTRY' => 'Ausgangssprache ist immer die Basissprache des Shops für Übersetzungen ("Language on demand"), Zielsprache ist auf die im Modul hinterlegten Sprachen beschränkt. Bereits vorhandene Übersetzungen des gleichen Ausgangsbegriffs werden überschrieben, alle anderen Einträge im Glossar bleiben unverändert.',
    'DL_LABEL_SOURCE_LANG' => 'Ausgangssprache',
    'DL_LABEL_SOURCE_TERM' => 'Ausgangsbegriff',
    'DL_LABEL_TARGET_LANG' => 'Zielsprache',
    'DL_LABEL_TARGET_TERM' => 'Übersetzung',
    'DL_BUTTON_ADD_ENTRY' => 'Eintrag hinzufügen',
    'DL_MSG_ENTRY_ADDED' => 'Eintrag wurde hinzugefügt.',
    'DL_ADDENTRY_ERR_NO_GLOSSARY' => 'Es ist keine Glossar-ID hinterlegt.',
    'DL_ADDENTRY_ERR_INVALID' => 'Bitte Ausgangsbegriff, Zielsprache und Übersetzung ausfüllen.',
    'DL_ADDENTRY_ERR_API_PREFIX' => 'Eintrag konnte nicht gespeichert werden',

    'DL_SECTION_CACHE_SEARCH' => 'Cache-Einträge nach übersetztem Wort löschen',
    'DL_HINT_CACHE_SEARCH' => 'Da nur der Hash des Ausgangstexts gespeichert wird (nicht der Text selbst), lässt sich nur nach dem übersetzten Wort/Text suchen. Nützlich, um veraltete Übersetzungen eines Begriffs gezielt aus dem Cache zu entfernen, nachdem er ins Glossar aufgenommen wurde - beim nächsten Aufruf wird der Text dann neu übersetzt.',
    'DL_SEARCH_PLACEHOLDER' => 'Übersetztes Wort oder Textteil...',
    'DL_BUTTON_SEARCH' => 'Suchen',
    'DL_HINT_SEARCH_TOO_SHORT' => 'Bitte mindestens so viele Zeichen eingeben',
    'DL_SEARCH_NO_MATCHES' => 'Keine Cache-Einträge gefunden.',
    'DL_SEARCH_MATCH_SUFFIX' => 'enthalten',
    'DL_CONFIRM_DELETE_PREFIX' => 'Wirklich',
    'DL_CONFIRM_DELETE_SUFFIX' => 'unwiderruflich löschen? Betroffene Texte werden beim nächsten Aufruf neu übersetzt. Suchbegriff:',
    'DL_BUTTON_DELETE' => 'Gefundene Einträge löschen',
    'DL_MSG_DELETED_PREFIX' => 'Es wurden',
    'DL_MSG_DELETED_SUFFIX' => 'Cache-Einträge gelöscht für den Suchbegriff',

    'DL_SECTION_USAGE' => 'DeepL-Nutzung (aktueller Abrechnungszeitraum)',
    'DL_USAGE_ERROR_PREFIX' => 'Nutzungsdaten konnten nicht geladen werden',
    'DL_USAGE_NO_DATA' => 'Keine Nutzungsdaten verfügbar.',
    'DL_USAGE_CHARACTERS_LABEL' => 'Zeichen',
    'DL_USAGE_NO_LIMIT' => 'kein Limit gesetzt',

    'DL_SECTION_CACHE' => 'Lokaler Übersetzungs-Cache',
    'DL_CACHE_TOTAL_ENTRIES' => 'Einträge gesamt',
    'DL_CACHE_TOTAL_CHARACTERS' => 'Zeichen gesamt',
    'DL_CACHE_NEWEST_ENTRY' => 'Neuester Eintrag',
    'DL_CACHE_EMPTY' => 'Der Übersetzungs-Cache ist noch leer.',
    'DL_TABLE_FROM_LANG' => 'Quellsprache',
    'DL_TABLE_TO_LANG' => 'Zielsprache',
    'DL_TABLE_CACHE_ENTRIES' => 'Einträge',
    'DL_TABLE_CACHE_CHARACTERS' => 'Zeichen',
    'DL_TABLE_TOTAL' => 'Gesamt',
];
