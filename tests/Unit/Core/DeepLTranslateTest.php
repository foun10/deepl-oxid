<?php

declare(strict_types=1);

namespace foun10\DeepL\Tests\Unit\Core;

use DeepL\TranslateTextOptions;
use foun10\DeepL\Core\DeepL;
use foun10\DeepL\Tests\Unit\Double\FakeTranslationModel;
use foun10\DeepL\Tests\Unit\Double\TranslatingDeepL;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the translation path - cache lookup, throttling, glossary handling and the
 * fallbacks when the DeepL API rejects a request.
 *
 * This is the code that costs money: every cache miss is a billed API call, and a changed
 * cache key orphans every entry already stored. The tests therefore pin the cache key and
 * assert that the API is *not* called whenever a cached value exists.
 *
 * DeepL keeps its runtime cache and throttle flag in static properties, so setUp() resets
 * them through reflection - otherwise state would leak from one test into the next.
 */
class DeepLTranslateTest extends TestCase
{
    private TranslatingDeepL $deepL;

    protected function setUp(): void
    {
        $this->resetStaticState();
        $this->deepL = new TranslatingDeepL();
    }

    protected function tearDown(): void
    {
        $this->resetStaticState();
    }

    private function resetStaticState(): void
    {
        $reflection = new \ReflectionClass(DeepL::class);

        foreach (['runtimeCache' => [], 'translatedOutputs' => [], 'throttled' => false, 'totalTranslationTime' => 0.0] as $name => $value) {
            $property = $reflection->getProperty($name);
            $property->setAccessible(true);
            $property->setValue(null, $value);
        }
    }

    // ---------------------------------------------------------------- cache behaviour

    public function testCallsTheApiOnceAndCachesTheResultForTheSecondCall(): void
    {
        $this->deepL->apiResponse = 'Hallo Welt';

        $first = $this->deepL->translateText('en', 'de', 'Hello World');
        $second = $this->deepL->translateText('en', 'de', 'Hello World');

        $this->assertSame('Hallo Welt', $first);
        $this->assertSame('Hallo Welt', $second, 'second call must come from the runtime cache');
        $this->assertSame(1, $this->deepL->apiCallCount, 'the API must not be called twice for the same text');
    }

    public function testUsesTheStoredTranslationWithoutCallingTheApi(): void
    {
        $this->deepL->model->loaded = true;
        $this->deepL->model->storedTranslation = 'Bereits uebersetzt';

        $result = $this->deepL->translateText('en', 'de', 'Hello World');

        $this->assertSame('Bereits uebersetzt', $result);
        $this->assertSame(0, $this->deepL->apiCallCount, 'a stored translation must not trigger a billed API call');
    }

    public function testStoresTheTranslationWithTheHashesItWasLookedUpBy(): void
    {
        $this->deepL->apiResponse = 'Hallo Welt';

        $this->deepL->translateText('en', 'de', 'Hello World');

        [, , $lookedUpTextHash, $lookedUpOptionHash] = $this->deepL->model->lastLookup;

        $this->assertSame($lookedUpTextHash, $this->deepL->model->assigned['FOUN10TEXTHASH']);
        $this->assertSame($lookedUpOptionHash, $this->deepL->model->assigned['FOUN10OPTIONHASH']);
        $this->assertSame('en', $this->deepL->model->assigned['FOUN10FROMLANG']);
        $this->assertSame('de', $this->deepL->model->assigned['FOUN10TOLANG']);
        $this->assertSame('Hallo Welt', $this->deepL->model->assigned['FOUN10TRANSLATEDTEXT']);
    }

    /**
     * The runtime cache key has to carry every dimension of the request. If one were dropped,
     * two different requests would collide - a text translated to French would be served to
     * Italian visitors, from cache, with nothing in the logs.
     *
     * @dataProvider collidingRequestProvider
     */
    public function testDoesNotServeOneRequestFromAnotherRequestsCacheEntry(
        array $first,
        array $second,
        string $why
    ): void {
        $this->deepL->apiResponse = 'first result';
        $firstResult = $this->deepL->translateText(...$first);

        $this->deepL->apiResponse = 'second result';
        $secondResult = $this->deepL->translateText(...$second);

        $this->assertSame('first result', $firstResult);
        $this->assertSame('second result', $secondResult, $why);
        $this->assertSame(2, $this->deepL->apiCallCount, $why);
    }

    public function collidingRequestProvider(): array
    {
        return [
            'target language differs' => [
                ['en', 'de', 'Hello World'],
                ['en', 'fr', 'Hello World'],
                'the target language must be part of the cache key',
            ],
            'source language differs' => [
                ['en', 'de', 'Hello World'],
                ['nl', 'de', 'Hello World'],
                'the source language must be part of the cache key',
            ],
            'text differs' => [
                ['en', 'de', 'Hello World'],
                ['en', 'de', 'Goodbye World'],
                'the text hash must be part of the cache key',
            ],
            'options differ' => [
                ['en', 'de', 'Hello World', ['tag_handling' => 'html']],
                ['en', 'de', 'Hello World', ['tag_handling' => 'xml']],
                'the option hash must be part of the cache key',
            ],
        ];
    }

    /**
     * DeepL occasionally answers with an empty string. Caching that would blank the text on
     * every following page view, so the original is kept instead.
     */
    public function testKeepsTheOriginalTextWhenTheApiReturnsNothing(): void
    {
        $this->deepL->apiResponse = '';

        $result = $this->deepL->translateText('en', 'de', 'Hello World');

        $this->assertSame('Hello World', $result);
    }

    /**
     * Text that is already the output of an earlier translation in this request must not be
     * sent back to the API - that would translate French into French and bill for it.
     */
    public function testDoesNotRetranslateItsOwnOutput(): void
    {
        $this->deepL->apiResponse = 'Bonjour';
        $this->deepL->translateText('en', 'fr', 'Hello');

        $callsSoFar = $this->deepL->apiCallCount;
        $result = $this->deepL->translateText('en', 'fr', 'Bonjour');

        $this->assertSame('Bonjour', $result);
        $this->assertSame($callsSoFar, $this->deepL->apiCallCount);
    }

    /**
     * The separators in the cache key are not decoration. Without them "en"+"de" and "e"+"nde"
     * produce the same key and one language pair is served from the other's cache. The codes
     * are contrived on purpose - the point is that concatenation alone is not a safe key.
     */
    public function testSeparatesTheCacheKeyComponents(): void
    {
        $this->deepL->apiResponse = 'first result';
        $first = $this->deepL->translateText('en', 'de', 'Hello World');

        $this->deepL->apiResponse = 'second result';
        $second = $this->deepL->translateText('e', 'nde', 'Hello World');

        $this->assertSame('first result', $first);
        $this->assertSame('second result', $second);
        $this->assertSame(2, $this->deepL->apiCallCount);
    }

    // ---------------------------------------------------------------- glossary

    /**
     * The glossary must never enter the cache key. If it did, configuring a glossary would
     * make every text already in the cache look like a miss and get re-translated - and
     * re-billed - in one go.
     */
    public function testGlossaryDoesNotChangeTheCacheKey(): void
    {
        $withoutGlossary = new TranslatingDeepL();
        $withoutGlossary->apiResponse = 'Hallo Welt';
        $withoutGlossary->translateText('en', 'de', 'Hello World');

        $this->resetStaticState();

        $withGlossary = new TranslatingDeepL();
        $withGlossary->apiResponse = 'Hallo Welt';
        $withGlossary->glossaryId = 'glossary-123';
        $withGlossary->glossaryPairs = ['en_de'];
        $withGlossary->translateText('en', 'de', 'Hello World');

        $this->assertSame(
            $withoutGlossary->model->lastLookup,
            $withGlossary->model->lastLookup,
            'configuring a glossary must not change the lookup hashes'
        );
    }

    public function testPassesTheGlossaryToTheApiWhenThePairIsSupported(): void
    {
        $this->deepL->apiResponse = 'Hallo Welt';
        $this->deepL->glossaryId = 'glossary-123';
        $this->deepL->glossaryPairs = ['en_de'];

        $this->deepL->translateText('en', 'de', 'Hello World');

        $this->assertSame('glossary-123', $this->deepL->lastApiOptions[TranslateTextOptions::GLOSSARY] ?? null);
    }

    public function testOmitsTheGlossaryWhenThePairIsNotInTheGlossary(): void
    {
        $this->deepL->apiResponse = 'Bonjour';
        $this->deepL->glossaryId = 'glossary-123';
        $this->deepL->glossaryPairs = ['en_de'];

        $this->deepL->translateText('en', 'fr', 'Hello');

        $this->assertArrayNotHasKey(TranslateTextOptions::GLOSSARY, $this->deepL->lastApiOptions);
    }

    public function testOmitsTheGlossaryWhenTheSourceLanguageIsUnknown(): void
    {
        $this->deepL->apiResponse = 'Hallo Welt';
        $this->deepL->glossaryId = 'glossary-123';
        $this->deepL->glossaryPairs = ['en_de'];

        $this->deepL->translateText('', 'de', 'Hello World');

        $this->assertArrayNotHasKey(TranslateTextOptions::GLOSSARY, $this->deepL->lastApiOptions);
    }

    // ---------------------------------------------------------------- throttling

    public function testStopsCallingTheApiOnceThrottled(): void
    {
        $this->deepL->apiException = new \DeepL\TooManyRequestsException('rate limited');

        $firstResult = $this->deepL->translateText('en', 'de', 'Hello World');

        $this->assertSame('Hello World', $firstResult, 'the untranslated text is returned as fallback');
        $this->assertTrue($this->deepL->isTranslationThrottled());

        $this->deepL->apiException = null;
        $this->deepL->apiResponse = 'Hallo';
        $callsSoFar = $this->deepL->apiCallCount;

        $secondResult = $this->deepL->translateText('en', 'de', 'Another sentence');

        $this->assertSame('Another sentence', $secondResult);
        $this->assertSame($callsSoFar, $this->deepL->apiCallCount, 'no further API calls once throttled');
    }

    // ---------------------------------------------------------------- rejected content

    /**
     * A rejected request is a data problem, not a transient one - it would fail identically on
     * every page view. The untranslated text is cached so the shop stops re-asking (and
     * re-paying) for it.
     */
    public function testCachesTheUntranslatedTextWhenDeepLRejectsTheContent(): void
    {
        $this->deepL->apiException = new \DeepL\DeepLException('tag handling parsing failed');

        $result = $this->deepL->translateText('en', 'de', 'Hello World');

        $this->assertSame('Hello World', $result);
        $this->assertSame('Hello World', $this->deepL->model->assigned['FOUN10TRANSLATEDTEXT'] ?? null);
        $this->assertSame(1, $this->deepL->model->saveCount, 'the fallback must be stored');
        $this->assertNotEmpty($this->deepL->logger->warnings);
    }

    // ---------------------------------------------------------------- surrounding behaviour

    public function testReturnsTextUnchangedInTestModeWithoutCallingTheApi(): void
    {
        $this->deepL->testMode = true;

        $result = $this->deepL->translateText('en', 'de', 'Hello World');

        $this->assertSame('Hello World', $result);
        $this->assertSame(0, $this->deepL->apiCallCount);
    }

    public function testSkipsTextThatIsNotWorthTranslating(): void
    {
        $result = $this->deepL->translateText('en', 'de', 'image.jpg');

        $this->assertSame('image.jpg', $result);
        $this->assertSame(0, $this->deepL->apiCallCount);
    }

    public function testRestoresUntranslatableBlocksAfterTranslating(): void
    {
        $this->deepL->apiResponse = 'Hallo __DEEPLSKIP0__ Welt';

        $result = $this->deepL->translateText(
            'en',
            'de',
            'Hello <div class="votingcomment">Customer review</div> World'
        );

        $this->assertSame('Hallo <div class="votingcomment">Customer review</div> Welt', $result);
        $this->assertStringNotContainsString(
            'Customer review',
            $this->deepL->lastApiText,
            'the untranslatable block must not be sent to the API'
        );
    }

    /**
     * A full HTML document pasted into a description field makes DeepL's html tag handling fail
     * with "multiple roots", so only the body contents are sent.
     */
    public function testSendsOnlyTheBodyOfAFullHtmlDocument(): void
    {
        $this->deepL->apiResponse = 'Hallo Welt';

        $this->deepL->translateText(
            'en',
            'de',
            '<!DOCTYPE html><html><head><title>x</title></head><body><p>Hello World</p></body></html>'
        );

        $this->assertSame('<p>Hello World</p>', $this->deepL->lastApiText);
    }
}
