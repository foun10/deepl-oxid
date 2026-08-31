[{* Announce the on-demand languages to search engines.

   The parent block keeps the links the shop renders for its own languages, including
   x-default; this only appends the ones served through DeepL. The list is filtered to the
   on-demand languages on purpose - the view config also knows the shop's own, and emitting
   those again would duplicate every hreflang tag. *}]
[{$smarty.block.parent}]
[{foreach from=$oViewConf->getOnDemandLanguageUrls() item="deepLLanguage"}]
    <link rel="alternate" hreflang="[{$deepLLanguage.langIso}]" href="[{$deepLLanguage.langUrl}]"/>
[{/foreach}]
