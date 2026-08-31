[{* Add the on-demand languages to the language switcher.

   The parent block renders the shop's own languages first, so the list reads as one menu with
   the maintained languages on top. The active on-demand language is marked so a visitor can
   see which one they are currently reading.

   No flag image is emitted: the shop only ships images for its own languages, and a missing
   file would render as a broken image for every language added here. *}]
[{$smarty.block.parent}]
[{assign var="deepLActive" value=$oViewConf->getActiveLanguageOnDemand()}]
[{foreach from=$oViewConf->getOnDemandLanguageUrls() item="deepLLanguage"}]
    <li[{if $deepLLanguage.langIso == $deepLActive}] class="active"[{/if}]>
        <a class="flag [{$deepLLanguage.langIso}]" title="[{$deepLLanguage.langName}]" href="[{$deepLLanguage.langUrl}]" hreflang="[{$deepLLanguage.langIso}]">
            [{$deepLLanguage.langName}]
        </a>
    </li>
[{/foreach}]
