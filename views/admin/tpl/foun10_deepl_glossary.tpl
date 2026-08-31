[{include file="headitem.tpl" title="foun10 DeepL"}]

<style>
    .foun10dl-wrap { padding: 4px 14px 20px; }
    .foun10dl-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
    .foun10dl-header h1 { margin: 0; }
    .foun10dl-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 4px; padding: 14px 16px; margin-bottom: 16px; }
    .foun10dl-card h2 { font-size: 14px; margin: 0 0 10px; text-transform: uppercase; letter-spacing: .03em; color: #555; }
    .foun10dl-hint { color: #777; font-size: 12px; margin: 2px 0 0; }
    .foun10dl-error { color: #a94442; background: #f2dede; border: 1px solid #ebccd1; padding: 6px 8px; border-radius: 3px; margin-top: 6px; }
    .foun10dl-empty { color: #888; font-style: italic; padding: 6px 0; }
    .foun10dl-stats-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .foun10dl-stats-table th, .foun10dl-stats-table td { padding: 7px 10px; border-bottom: 1px solid #eee; text-align: right; }
    .foun10dl-stats-table th:first-child, .foun10dl-stats-table td:first-child { text-align: left; }
    .foun10dl-stats-table th { color: #555; font-size: 11px; text-transform: uppercase; letter-spacing: .03em; border-bottom: 2px solid #ddd; }
    .foun10dl-search-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .foun10dl-search-input { width: 320px; max-width: 100%; }
    .foun10dl-preview { margin-top: 12px; padding: 10px 12px; background: #f7f7f7; border: 1px solid #e5e5e5; border-radius: 3px; }
    .foun10dl-preview strong { font-size: 15px; }
    .foun10dl-actions { margin-top: 10px; }
    .foun10dl-msg { padding: 8px 12px; border-radius: 3px; margin-bottom: 14px; }
    .foun10dl-msg-success { background: #dff0d8; color: #3c763d; border: 1px solid #b2d8a8; }
    .btn-danger-outline { color: #a94442; border-color: #ebccd1; background: #fff; }
    .foun10dl-entry-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .foun10dl-entry-field { display: flex; flex-direction: column; gap: 3px; }
    .foun10dl-entry-field label { font-size: 11px; color: #777; text-transform: uppercase; letter-spacing: .03em; }
    .foun10dl-entry-input { width: 220px; max-width: 100%; }
    .foun10dl-entry-source-lang { width: 70px; text-align: center; background: #f2f2f2; }
    .foun10dl-dict-details { border: 1px solid #e5e5e5; border-radius: 3px; margin-bottom: 8px; }
    .foun10dl-dict-details + .foun10dl-dict-details { margin-top: 8px; }
    .foun10dl-dict-summary { cursor: pointer; padding: 8px 10px; display: flex; align-items: center; justify-content: space-between; font-size: 13px; background: #fafafa; }
    .foun10dl-dict-summary:hover { background: #f2f2f2; }
    .foun10dl-dict-pair { font-weight: bold; }
    .foun10dl-dict-count { color: #777; font-size: 12px; }
    .foun10dl-entries-table-wrap { max-height: 320px; overflow-y: auto; }
    .foun10dl-entries-table-wrap .foun10dl-stats-table th, .foun10dl-entries-table-wrap .foun10dl-stats-table td { text-align: left; }
</style>

<div class="foun10dl-wrap">
    <div class="foun10dl-header">
        <h1>[{oxmultilang ident="FOUN10_DEEPL"}] &ndash; [{oxmultilang ident="FOUN10_DEEPL_GLOSSARY"}]</h1>
    </div>

    [{if $deepLLastAction == "delete" && $deepLDeletedCount !== null}]
        <div class="foun10dl-msg foun10dl-msg-success">
            [{oxmultilang ident="DL_MSG_DELETED_PREFIX"}] [{$deepLDeletedCount|number_format:0:",":"."}] [{oxmultilang ident="DL_MSG_DELETED_SUFFIX"}] &raquo;[{$deepLSearchTerm|escape:"html"}]&laquo;
        </div>
    [{/if}]

    <div class="foun10dl-card">
        <h2>[{oxmultilang ident="DL_SECTION_GLOSSARY"}]</h2>

        [{if $deepLGlossaryId == ""}]
            <p class="foun10dl-empty">[{oxmultilang ident="DL_GLOSSARY_EMPTY_HINT"}]</p>
        [{else}]
            <p class="foun10dl-hint">[{oxmultilang ident="DL_GLOSSARY_ID_LABEL"}]: <a href="https://www.deepl.com/en/glossary/[{$deepLGlossaryId|escape:"url"}]?api=true" target="_blank" rel="noopener noreferrer">[{$deepLGlossaryId|escape:"html"}]</a></p>

            [{if $deepLGlossaryError}]
                <p class="foun10dl-error">[{oxmultilang ident="DL_GLOSSARY_ERROR_PREFIX"}]: [{$deepLGlossaryError}]</p>
            [{elseif $deepLGlossary}]
                <p>
                    <strong>[{$deepLGlossary.name}]</strong>
                    &nbsp;&middot;&nbsp;
                    [{oxmultilang ident="DL_GLOSSARY_CREATED_LABEL"}]: [{$deepLGlossary.creationTime}]
                </p>

                [{if $deepLGlossary.dictionaries|@count}]
                    [{foreach from=$deepLGlossary.dictionaries item="dictionary"}]
                        <details class="foun10dl-dict-details">
                            <summary class="foun10dl-dict-summary">
                                <span class="foun10dl-dict-pair">[{$dictionary.sourceLang}] &rarr; [{$dictionary.targetLang}]</span>
                                <span class="foun10dl-dict-count">[{$dictionary.entryCount}] [{oxmultilang ident="DL_TABLE_ENTRIES"}]</span>
                            </summary>

                            [{if $dictionary.entriesError}]
                                <p class="foun10dl-error">[{oxmultilang ident="DL_GLOSSARY_ERROR_PREFIX"}]: [{$dictionary.entriesError}]</p>
                            [{elseif $dictionary.entries|@count}]
                                <div class="foun10dl-entries-table-wrap">
                                    <table class="foun10dl-stats-table">
                                        <thead>
                                            <tr>
                                                <th>[{oxmultilang ident="DL_LABEL_SOURCE_TERM"}]</th>
                                                <th>[{oxmultilang ident="DL_LABEL_TARGET_TERM"}]</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            [{foreach from=$dictionary.entries key="sourceTerm" item="targetTerm"}]
                                                <tr>
                                                    <td>[{$sourceTerm}]</td>
                                                    <td>[{$targetTerm}]</td>
                                                </tr>
                                            [{/foreach}]
                                        </tbody>
                                    </table>
                                </div>
                            [{else}]
                                <p class="foun10dl-empty">[{oxmultilang ident="DL_DICT_NO_ENTRIES"}]</p>
                            [{/if}]
                        </details>
                    [{/foreach}]
                [{else}]
                    <p class="foun10dl-empty">[{oxmultilang ident="DL_GLOSSARY_NO_DICTIONARIES"}]</p>
                [{/if}]
            [{/if}]
        [{/if}]
    </div>

    [{if $deepLGlossaryId != ""}]
        <div class="foun10dl-card">
            <h2>[{oxmultilang ident="DL_SECTION_ADD_ENTRY"}]</h2>
            <p class="foun10dl-hint">[{oxmultilang ident="DL_HINT_ADD_ENTRY"}]</p>

            [{if $deepLAddEntrySuccess}]
                <div class="foun10dl-msg foun10dl-msg-success">[{oxmultilang ident="DL_MSG_ENTRY_ADDED"}]</div>
            [{elseif $deepLAddEntryValidationError == "no_glossary"}]
                <p class="foun10dl-error">[{oxmultilang ident="DL_ADDENTRY_ERR_NO_GLOSSARY"}]</p>
            [{elseif $deepLAddEntryValidationError == "invalid_input"}]
                <p class="foun10dl-error">[{oxmultilang ident="DL_ADDENTRY_ERR_INVALID"}]</p>
            [{elseif $deepLAddEntryError}]
                <p class="foun10dl-error">[{oxmultilang ident="DL_ADDENTRY_ERR_API_PREFIX"}]: [{$deepLAddEntryError}]</p>
            [{/if}]

            <form action="[{$oViewConf->getSelfLink()}]" method="post" class="foun10dl-entry-row">
                [{$oViewConf->getHiddenSid()}]
                <input type="hidden" name="cl" value="[{$oViewConf->getActiveClassName()}]">
                <input type="hidden" name="fnc" value="addentry">

                <div class="foun10dl-entry-field">
                    <label for="foun10DeepLEntrySourceLang">[{oxmultilang ident="DL_LABEL_SOURCE_LANG"}]</label>
                    <input type="text" id="foun10DeepLEntrySourceLang" value="[{$deepLSourceLangIso|escape:"html"}]" class="foun10dl-entry-source-lang" disabled>
                </div>

                <div class="foun10dl-entry-field">
                    <label for="foun10DeepLEntrySourceTerm">[{oxmultilang ident="DL_LABEL_SOURCE_TERM"}]</label>
                    <input type="text" id="foun10DeepLEntrySourceTerm" name="foun10DeepLEntrySourceTerm" value="[{$deepLEntrySourceTerm|escape:"html"}]" class="foun10dl-entry-input" required>
                </div>

                <div class="foun10dl-entry-field">
                    <label for="foun10DeepLEntryTargetLang">[{oxmultilang ident="DL_LABEL_TARGET_LANG"}]</label>
                    <select id="foun10DeepLEntryTargetLang" name="foun10DeepLEntryTargetLang" class="foun10dl-entry-input">
                        [{foreach from=$deepLTargetLanguages key="langIso" item="langName"}]
                            <option value="[{$langIso}]"[{if $langIso == $deepLEntryTargetLang}] selected[{/if}]>[{$langName}] ([{$langIso}])</option>
                        [{/foreach}]
                    </select>
                </div>

                <div class="foun10dl-entry-field">
                    <label for="foun10DeepLEntryTargetTerm">[{oxmultilang ident="DL_LABEL_TARGET_TERM"}]</label>
                    <input type="text" id="foun10DeepLEntryTargetTerm" name="foun10DeepLEntryTargetTerm" value="[{$deepLEntryTargetTerm|escape:"html"}]" class="foun10dl-entry-input" required>
                </div>

                <button type="submit" class="btn btn-default">[{oxmultilang ident="DL_BUTTON_ADD_ENTRY"}]</button>
            </form>
        </div>
    [{/if}]

    <div class="foun10dl-card">
        <h2>[{oxmultilang ident="DL_SECTION_CACHE_SEARCH"}]</h2>
        <p class="foun10dl-hint">[{oxmultilang ident="DL_HINT_CACHE_SEARCH"}]</p>

        <form action="[{$oViewConf->getSelfLink()}]" method="get" class="foun10dl-search-row">
            [{$oViewConf->getHiddenSid()}]
            <input type="hidden" name="cl" value="[{$oViewConf->getActiveClassName()}]">
            <input type="text" name="foun10DeepLSearchTerm" value="[{$deepLSearchTerm|escape:"html"}]" class="foun10dl-search-input" placeholder="[{oxmultilang ident="DL_SEARCH_PLACEHOLDER"}]">
            <button type="submit" class="btn btn-default">[{oxmultilang ident="DL_BUTTON_SEARCH"}]</button>
        </form>

        [{if $deepLSearchTooShort}]
            <p class="foun10dl-hint">[{oxmultilang ident="DL_HINT_SEARCH_TOO_SHORT"}] ([{$deepLMinSearchLength}])</p>
        [{elseif $deepLSearchPreview}]
            [{if $deepLSearchPreview.entries == 0}]
                <p class="foun10dl-empty">[{oxmultilang ident="DL_SEARCH_NO_MATCHES"}]</p>
            [{else}]
                <div class="foun10dl-preview">
                    <strong>[{$deepLSearchPreview.entries|number_format:0:",":"."}]</strong> [{oxmultilang ident="DL_TABLE_CACHE_ENTRIES"}],
                    <strong>[{$deepLSearchPreview.characters|number_format:0:",":"."}]</strong> [{oxmultilang ident="DL_USAGE_CHARACTERS_LABEL"}]
                    [{oxmultilang ident="DL_SEARCH_MATCH_SUFFIX"}] &raquo;[{$deepLSearchTerm|escape:"html"}]&laquo;
                </div>

                <form action="[{$oViewConf->getSelfLink()}]" method="post" class="foun10dl-actions" onsubmit="return confirm('[{oxmultilang ident="DL_CONFIRM_DELETE_PREFIX"}] [{$deepLSearchPreview.entries}] [{oxmultilang ident="DL_TABLE_CACHE_ENTRIES"}] ([{$deepLSearchPreview.characters}] [{oxmultilang ident="DL_USAGE_CHARACTERS_LABEL"}]) [{oxmultilang ident="DL_CONFIRM_DELETE_SUFFIX"}] \'[{$deepLSearchTerm|escape:"javascript"|escape:"html"}]\'?');">
                    [{$oViewConf->getHiddenSid()}]
                    <input type="hidden" name="cl" value="[{$oViewConf->getActiveClassName()}]">
                    <input type="hidden" name="fnc" value="delete">
                    <input type="hidden" name="foun10DeepLSearchTerm" value="[{$deepLSearchTerm|escape:"html"}]">
                    <button type="submit" class="btn btn-danger-outline">[{oxmultilang ident="DL_BUTTON_DELETE"}]</button>
                </form>
            [{/if}]
        [{/if}]
    </div>
</div>

[{include file="bottomitem.tpl"}]
