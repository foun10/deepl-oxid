<?php

declare(strict_types=1);

namespace foun10\DeepL\Tests\Unit\Double;

use DeepL\DeepLClient;

/**
 * DeepL client that answers from a fixture instead of calling the API.
 *
 * parent::__construct() is deliberately not called: it validates the auth key and builds an
 * HTTP client, neither of which belongs in a unit test. Only translateText() is used, and it
 * touches none of the parent's state.
 */
class FakeDeepLClient extends DeepLClient
{
    /** @var TranslatingDeepL */
    private $recorder;

    public function __construct(TranslatingDeepL $recorder)
    {
        $this->recorder = $recorder;
    }

    /**
     * @param string|string[] $texts
     * @return object an object exposing ->text, which is all the production code reads
     */
    public function translateText($texts, ?string $sourceLang, string $targetLang, array $options = [])
    {
        $translated = $this->recorder->recordApiCall(
            is_array($texts) ? (string) reset($texts) : (string) $texts,
            $options
        );

        return new class($translated) {
            /** @var string */
            public $text;

            public function __construct(string $text)
            {
                $this->text = $text;
            }
        };
    }
}
