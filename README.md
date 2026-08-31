# foun10 DeepL

[![CI b-6.x](https://img.shields.io/github/actions/workflow/status/foun10/deepl-oxid/ci.yml?branch=b-6.x&label=CI%20b-6.x)](https://github.com/foun10/deepl-oxid/actions/workflows/ci.yml?query=branch%3Ab-6.x)
[![PHP](https://img.shields.io/badge/PHP-%5E7.4%20%7C%7C%20%5E8.0-777BB4?logo=php&logoColor=white)](#compatibility)
[![OXID eShop](https://img.shields.io/badge/OXID%20eShop-6.2%20%E2%80%93%206.5-e30613)](#compatibility)
[![License](https://img.shields.io/badge/license-GPL--3.0--only-blue)](LICENSE)

> Translates shop content into additional languages at runtime via the DeepL API, without
> maintaining a second set of shop languages.

The shop keeps the languages it always had. Everything else — product texts, categories,
manufacturers, content pages, attributes, interface strings — is translated on the fly when a
visitor asks for a language the shop does not maintain, and cached so each text is paid for
once.

## Compatibility

| Module version | Branch | OXID eShop | Template engine |
|---|---|---|---|
| 7.x | [`b-7.x`](https://github.com/foun10/deepl-oxid/tree/b-7.x) | 7.0 – 7.5 | Twig |
| 6.x | [`b-6.x`](https://github.com/foun10/deepl-oxid/tree/b-6.x) | 6.2 – 6.5 | Smarty |

Composer resolves the right line for your shop automatically.

### Tested combinations

Every row below is installed from scratch and exercised by the full test suite on every push.
This is not a statement of intent — if a combination is listed here, CI proves it.

<!-- ci-matrix:start -->

| OXID eShop | PHP |
|---|---|
| 6.2 | 7.4 |
| 6.3 | 7.4, 8.0 |
| 6.4 | 7.4, 8.0 |
| 6.5 | 7.4, 8.0, 8.1 |

<!-- ci-matrix:end -->

The table and the [CI matrix](.github/workflows/ci.yml) are compared by a test, so they cannot
drift apart unnoticed.

## Features

- **Languages beyond the ones you maintain.** A visitor can request a language the shop has no
  translations for; the module supplies it from DeepL.
- **Every translation is cached.** Identical text is sent to DeepL once and then served from
  the database, so the bill does not scale with your traffic.
- **Glossary support.** A DeepL glossary is applied automatically where it covers the language
  pair, and the module ships an admin page to inspect it and add entries.
- **Usage and cache overview** in the backend: remaining DeepL character quota, cache size, and
  a breakdown per language pair.
- **Targeted cache purge.** Search cached translations by their translated text and delete
  exactly those entries, for example after correcting a term.
- **Search works in the reading language.** A query typed while reading an on-demand
  language is translated back into the shop's language before the search runs, so it
  matches the catalogue instead of returning nothing.
- **A time budget per request.** Once translation exceeds it, the remaining text is served
  untranslated rather than letting a page hang on the API.

## Installation

```bash
composer require foun10/deepl
./vendor/bin/oe-console oe:module:activate foun10DeepL
```

## Configuration

Both settings live in the OXID backend under **Extensions → Modules → foun10 DeepL →
Settings**:

| Setting | Meaning |
|---|---|
| `foun10DeepLApiKey` | Your DeepL API key. Without it the module stays inert. |
| `foun10DeepLSourceLanguage` | Abbreviation of the shop language translations are generated from, e.g. `de` or `en`. Must be a language your shop has configured; an unknown or empty value falls back to `en`. |
| `foun10DeepLLanguagesOnDemand` | Languages offered on top of the ones the shop maintains, as `abbreviation => display name` pairs. Clearing the list switches the extra languages off without deactivating the module. |
| `foun10DeepLGlossaryId` | Optional. A DeepL multilingual glossary; applied automatically for the language pairs it covers. |

Set the source language to whichever language your shop content is actually written in. Every
on-demand translation starts from it, so pointing it at a language you do not maintain produces
translations of empty or stale text.

There is also a development switch, `blDeepLTestMode`, set in `config.local.inc.php` rather
than the backend. With it on, the module never calls the API and never writes to the cache —
useful for local work and for test suites that must not spend money.

## Honest opinion

We wrote this module for our own customer projects, so here is where we would reach for it and
where we would not.

**✅ Use it when**

- You want additional languages without the work of maintaining them. The shop keeps the
  languages it has; everything else is translated on the way to the visitor.
- You need SEO URLs per language. Each on-demand language gets its own URL prefix and its own
  `hreflang` entry, so the additional languages are indexable rather than a client-side gimmick.

**❌ Do not use it when**

- You need the translations *on the objects* - stored on the article, the category, the
  manufacturer. This module translates on the way out and caches the result; it never writes
  translated values back into the shop's own language fields. If you need to search, filter,
  export or edit translated content, maintain real shop languages instead.
- Your texts change often. The cache key is the text itself, so editing a description discards
  the translation for that whole block and pays for it again on the next view. A catalogue that
  is edited daily will keep re-translating.
- Your shop has only one language. The URL prefixing assumes a second language to hand off to,
  and the theme hides its language switcher entirely below two languages. It can be made to
  work, but expect friction.

## Theme integration

The module adds its languages to two places in the storefront: the `hreflang` link list in the
page head, and the language switcher in the header. Both hook into blocks the shipped theme
provides - `head_link_hreflang` and `dd_layout_page_header_icon_menu_languages_list`. A custom
theme that renames or drops those blocks needs its own equivalents; the module's versions are
small and can be copied as a starting point.

The switcher is only rendered by the theme when the shop has more than one language of its
own, so a single-language shop shows no switcher for the module to extend.

## Using the translation from another module

The translation service is a plain class, so any other module can use it - an export module
producing a feed in a language the shop does not maintain, for instance:

```php
use foun10\DeepL\Core\DeepL;
use OxidEsales\Eshop\Core\Registry;

/** @var DeepL $deepL */
$deepL = Registry::get(DeepL::class);

// The language translations are generated from, as configured in the module settings.
$sourceLang = $deepL->getShopLangForLanguageOnDemand()['langIso'];

$title = $deepL->translateText($sourceLang, 'es', $article->oxarticles__oxtitle->value);

// For a field that carries markup, let DeepL keep the tags intact:
$description = $deepL->translateText(
    $sourceLang,
    'es',
    $article->oxarticles__oxlongdesc->value,
    ['tag_handling' => 'html']
);
```

`translateText()` is the same entry point the module uses for the storefront, so an export shares
the storefront's cache: text already translated for a visitor costs nothing again, and text
translated for the feed is there when a visitor asks for it. It also inherits the rest of the
behaviour - a configured glossary is applied for the language pair, text with nothing
translatable in it (numbers, filenames) is returned unchanged, and `blDeepLTestMode` makes every
call a no-op that returns the input, which is what you want in a test suite.

Two things to know before running it over a large catalogue:

- **The time budget is per PHP process, not per page.** After ten seconds of accumulated API
  time the module stops calling DeepL and returns text untranslated - sensible for a storefront
  request, wrong for an export that runs for minutes and would silently emit a mostly
  untranslated feed. Check `isTranslationThrottled()` before trusting the output; the limit
  itself is a protected static, so batch work wants a small subclass that exposes a setter for
  it.
- **Every uncached string is a billed API call.** Exporting a full catalogue into several
  languages at once is a much larger bill than a storefront ever generates. Try it against a
  small subset first and watch the character quota on the admin page.

## Known limitations

- **Content containing template syntax is skipped at the model.** Fields whose text contains
  `[{ ... }]` (or Twig delimiters, in content migrated from an OXID 7 shop) are not sent to
  DeepL, because mangled delimiters would break the page. On this branch they are picked up
  later instead, after the template has been rendered.
- **The first request for a language is slow.** Nothing is cached yet, so the whole page is
  translated in one go. Subsequent requests are served from the database. A per-request time
  budget stops a page from hanging on the API - once it is exceeded, the rest of that page is
  returned untranslated.
- **Search suggestions are not translated.** OXID's search suggest fires while the visitor is
  still typing, and a half-typed word has no meaningful translation - "sof" is not a term DeepL
  can turn into anything useful. The module therefore leaves the suggest request alone and
  translates only the submitted search, which means suggestions are matched against the
  catalogue in the shop's language and come back empty or unrelated for a visitor typing in an
  on-demand language. If your theme offers suggest, switch it off while an on-demand language is
  active; the search itself keeps working.
- **Very long search input is searched as typed.** Terms beyond 100 characters are not sent to
  DeepL. The search box is open to anyone and every distinct term is a billed request, so this
  keeps an automated flood of junk queries from turning into an API bill.
- **Translations are never reviewed.** What DeepL returns is what customers see.

## Development & Testing

```bash
composer tests-unit          # no shop required
composer tests-integration   # needs an installed shop, see below
composer tests-mutation      # Infection, needs the phar on PATH
```

The integration suite bootstraps a real shop. Point it at yours:

```bash
OXID_SHOP_BOOTSTRAP=/path/to/shop/source/bootstrap.php composer tests-integration
```

There is deliberately no `composer.lock` in this repository: the dependency tree is resolved
per PHP version, and a lock file would tie the whole supported range to one of them. Use
`composer update`, not `composer install`.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

[GPL-3.0-only](LICENSE). You may use this module commercially, including in customer projects.
If you redistribute it, or a derivative of it, that has to happen under the GPL as well.
