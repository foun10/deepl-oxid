# Changelog

All notable changes to this module are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the module uses
[semantic versioning](https://semver.org/spec/v2.0.0.html).

Each OXID line has its own release series: `7.x` on the `b-7.x` branch for OXID 7, `6.x` on the
`b-6.x` branch for OXID 6 - the major version tracks the OXID line it targets, not a generation
of the module. The two are developed in parallel, so a fix usually appears in both.

## [6.0.0] - 2026-08-28

First public release for OXID 6. The module existed internally before this; the public history
and the version numbering start here.

### Added

- Translation of shop content into languages the shop does not maintain, resolved per request
  and served through the DeepL API.
- A translation cache in `foun10deepltranslations`, so identical text is paid for once.
- DeepL glossary support, applied automatically for the language pairs a configured glossary
  covers, with an admin page to inspect dictionaries and add entries.
- An admin overview of the DeepL character quota and the local cache, including a per language
  pair breakdown and a targeted purge by translated text.
- `hreflang` entries and language switcher items for every offered language, integrated into
  the Flow theme through template blocks.
- Configurable source language (`foun10DeepLSourceLanguage`) - the language translations are
  generated from, previously fixed to English.
- Configurable list of offered languages (`foun10DeepLLanguagesOnDemand`). Clearing it switches
  the additional languages off without deactivating the module.
- Search terms are translated back into the shop's language before the search runs, so a
  query typed in an on-demand language matches the catalogue. Search suggestions are left
  untouched - a half-typed word has no meaningful translation - and input beyond 100 characters
  is searched as typed, so an open search box cannot be turned into an API bill.
- A per request time budget: once exceeded, the remainder of the page is served untranslated
  rather than waiting on the API.
- `blDeepLTestMode`, which suppresses every API call and cache write - for local work and for
  test suites that must not spend money.

### Fixed

- Translating a numeric multilang field aborted the surrounding widget on PHP 8, because a
  non-string value reached `strpos()`.
- The cache key depended on the order of the translate options, so the same request could miss
  the cache and be billed again.

### Known limitations

- Search suggestions are matched against the catalogue in the shop's language, so they come back
  empty or unrelated while an on-demand language is active. Switch suggest off for those
  languages.

- Fields containing template syntax are skipped at the model and translated after the template
  has been rendered instead.
- Translations are never reviewed - what DeepL returns is what customers see.
