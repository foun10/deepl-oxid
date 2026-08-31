<?php

declare(strict_types=1);

namespace foun10\DeepL\Tests\Unit\Core;

use foun10\DeepL\Core\DeepL;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the shop-independent core logic of DeepL.
 *
 * The methods under test are protected and touch neither Registry nor oxNew, so a
 * subclass exposing them is enough - no shop bootstrap required. Everything that
 * reaches configuration, logger, request or session through Registry belongs in
 * the integration tests instead.
 */
class DeepLTest extends TestCase
{
    private DeepL $deepL;

    protected function setUp(): void
    {
        $this->deepL = new class extends DeepL {
            public function exposedGetTextHash(string $text): string
            {
                return $this->getTextHash($text);
            }

            public function exposedGetOptionHash(array $options): string
            {
                return $this->getOptionHash($options);
            }

            public function exposedGetDeepLLang(string $lang): string
            {
                return $this->getDeepLLang($lang);
            }

            public function exposedHasTranslatableContent(string $text): bool
            {
                return $this->hasTranslatableContent($text);
            }

            public function exposedExtractUntranslatableBlocks(string $text): array
            {
                return $this->extractUntranslatableBlocks($text);
            }
        };
    }

    // ---------------------------------------------------------------- getDeepLLang

    /**
     * @dataProvider deepLLangProvider
     */
    public function testMapsShopLanguageToDeepLLanguage(string $shopLang, string $expected): void
    {
        $this->assertSame($expected, $this->deepL->exposedGetDeepLLang($shopLang));
    }

    public function deepLLangProvider(): array
    {
        return [
            'english needs a regional variant' => ['en', 'en-US'],
            'portuguese needs a regional variant' => ['pt', 'pt-PT'],
            'norwegian is called nb at DeepL' => ['no', 'nb'],
            'german passes through unchanged' => ['de', 'de'],
            'unknown code passes through unchanged' => ['xx', 'xx'],
        ];
    }

    // ---------------------------------------------------------------- getTextHash

    public function testTextHashIgnoresSurroundingAndRepeatedWhitespace(): void
    {
        $this->assertSame(
            $this->deepL->exposedGetTextHash('Hello World'),
            $this->deepL->exposedGetTextHash("  Hello   \n World  ")
        );
    }

    public function testTextHashDecodesHtmlEntitiesBeforeHashing(): void
    {
        $this->assertSame(
            $this->deepL->exposedGetTextHash('Tom & Jerry'),
            $this->deepL->exposedGetTextHash('Tom &amp; Jerry')
        );
    }

    /**
     * html_entity_decode() runs with ENT_QUOTES | ENT_HTML5. Without ENT_QUOTES the
     * &#039; would survive and the same text would produce two cache entries,
     * depending on where it came from.
     */
    public function testTextHashDecodesQuoteEntities(): void
    {
        $this->assertSame(
            $this->deepL->exposedGetTextHash("Tom's book"),
            $this->deepL->exposedGetTextHash('Tom&#039;s book')
        );
    }

    public function testTextHashDistinguishesDifferentText(): void
    {
        $this->assertNotSame(
            $this->deepL->exposedGetTextHash('Hello World'),
            $this->deepL->exposedGetTextHash('Hello Worlds')
        );
    }

    // ---------------------------------------------------------------- getOptionHash

    public function testOptionHashIsStableForSameOptions(): void
    {
        $options = ['tag_handling' => 'html', 'formality' => 'less'];

        $this->assertSame(
            $this->deepL->exposedGetOptionHash($options),
            $this->deepL->exposedGetOptionHash($options)
        );
    }

    public function testOptionHashDistinguishesDifferentOptions(): void
    {
        $this->assertNotSame(
            $this->deepL->exposedGetOptionHash(['tag_handling' => 'html']),
            $this->deepL->exposedGetOptionHash(['tag_handling' => 'xml'])
        );
    }

    /**
     * The hash is part of the cache key. If the same options in a different order
     * produced a different key, every reordering would cause an avoidable cache
     * miss - and each miss is a billed DeepL call. getOptionHash() sorts first.
     */
    public function testOptionHashIsIndependentOfKeyOrder(): void
    {
        $this->assertSame(
            $this->deepL->exposedGetOptionHash(['formality' => 'less', 'tag_handling' => 'html']),
            $this->deepL->exposedGetOptionHash(['tag_handling' => 'html', 'formality' => 'less'])
        );
    }

    public function testOptionHashStillDistinguishesDifferentValues(): void
    {
        $this->assertNotSame(
            $this->deepL->exposedGetOptionHash(['a' => 1, 'b' => 2]),
            $this->deepL->exposedGetOptionHash(['a' => 2, 'b' => 1])
        );
    }

    /**
     * The option sets used so far must keep the hash they had before ksort() was
     * introduced - otherwise every existing cache entry would be orphaned.
     */
    public function testOptionHashUnchangedForExistingCallSites(): void
    {
        $this->assertSame(
            (string) crc32('[]'),
            $this->deepL->exposedGetOptionHash([])
        );
        $this->assertSame(
            (string) crc32('{"tag_handling":"html"}'),
            $this->deepL->exposedGetOptionHash(['tag_handling' => 'html'])
        );
    }

    // ---------------------------------------------------------------- hasTranslatableContent

    /**
     * @dataProvider translatableContentProvider
     */
    public function testDecidesWhetherTextIsWorthTranslating(string $text, bool $expected, string $why): void
    {
        $this->assertSame($expected, $this->deepL->exposedHasTranslatableContent($text), $why);
    }

    public function translatableContentProvider(): array
    {
        return [
            ['Hello World', true, 'ordinary text'],
            ['<p>Hello <b>World</b></p>', true, 'tags are stripped before the check'],
            ['', false, 'empty text'],
            ['   ', false, 'whitespace only'],
            ['image.jpg', false, 'single token ending in a file extension'],
            ['products/image-large.png', false, 'path without whitespace ending in a file extension'],
            ['The image image.jpg shows the product', true, 'a file name inside a sentence stays translatable'],
            ['1234567890', false, 'digits only'],
            ['12345678901234567890 ST', false, 'mostly digits with a short unit code mixed in'],
            ['Y-m-d H:i:s', false, 'date format, every letter stands alone'],
            ['d.m.Y', false, 'date format without whitespace'],
            ['UTF-8', true, 'technical value the heuristic deliberately lets through'],
            ['A', false, 'a single letter is not a letter run'],

            // Edge cases that only surfaced through mutation testing
            ['  image.jpg  ', false, 'file name with surrounding whitespace - it is trimmed first'],
            ['Terms.and.Conditions', true, 'dots inside the word, but no file extension at the end'],
            ['123456789' . "\u{00e4}", false, 'digit share counts characters, not bytes'],
            ['123456789012345678AB', false, 'digit share sits exactly on the 0.9 threshold'],
            ["\u{00c4}\u{00d6}", true, 'letter run made up entirely of multibyte characters'],
            ['123456789012345678' . "\u{00e4}\u{00f6}", false, 'denominator in characters: byte-based the share would fall below the threshold'],
            ["\u{0660}\u{0661}\u{0662}\u{0663}\u{0664}" . 'abcde', true, 'numerator in characters: Arabic-Indic digits are multibyte'],
        ];
    }

    // ---------------------------------------------------------------- extractUntranslatableBlocks

    public function testExtractsBlockWithUntranslatableCssClass(): void
    {
        $text = 'Before <div class="votingcomment">Customer review</div> After';

        [$stripped, $blocks] = $this->deepL->exposedExtractUntranslatableBlocks($text);

        $this->assertSame('Before __DEEPLSKIP0__ After', $stripped);
        $this->assertSame(
            ['__DEEPLSKIP0__' => '<div class="votingcomment">Customer review</div>'],
            $blocks
        );
    }

    public function testExtractsSeveralBlocksWithDistinctPlaceholders(): void
    {
        $text = '<div class="votingcomment">First</div> and <div class="votingcomment">Second</div>';

        [$stripped, $blocks] = $this->deepL->exposedExtractUntranslatableBlocks($text);

        $this->assertSame('__DEEPLSKIP0__ and __DEEPLSKIP1__', $stripped);
        $this->assertCount(2, $blocks);
        $this->assertArrayHasKey('__DEEPLSKIP0__', $blocks);
        $this->assertArrayHasKey('__DEEPLSKIP1__', $blocks);
    }

    public function testExtractsBlockWhenCssClassAppearsAmongOthers(): void
    {
        $text = '<div class="review votingcomment highlighted">Customer review</div>';

        [$stripped, $blocks] = $this->deepL->exposedExtractUntranslatableBlocks($text);

        $this->assertSame('__DEEPLSKIP0__', $stripped);
        $this->assertCount(1, $blocks);
    }

    public function testLeavesUnrelatedMarkupUntouched(): void
    {
        $text = '<div class="description">Product description</div>';

        [$stripped, $blocks] = $this->deepL->exposedExtractUntranslatableBlocks($text);

        $this->assertSame($text, $stripped);
        $this->assertSame([], $blocks);
    }

    /**
     * The CSS class is matched on word boundaries; a mere prefix must not trigger.
     * Otherwise arbitrary third-party content would silently disappear from the
     * translation without anyone noticing.
     */
    public function testDoesNotMatchCssClassAsSubstring(): void
    {
        $text = '<div class="votingcommentary">Editorial text</div>';

        [$stripped, $blocks] = $this->deepL->exposedExtractUntranslatableBlocks($text);

        $this->assertSame($text, $stripped);
        $this->assertSame([], $blocks);
    }
}
