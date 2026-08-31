<?php

declare(strict_types=1);

namespace foun10\DeepL\Tests\Unit\Double;

use DeepL\DeepLClient;
use foun10\DeepL\Core\DeepL;

/**
 * Test double for DeepL that substitutes every seam into the shop and the DeepL API.
 *
 * Written by hand rather than as a PHPUnit mock because the collaborators cannot be mocked
 * generically: Translation extends OXID's BaseModel, which is not autoloadable without a
 * bootstrapped shop, and the DeepL client is a final-ish third-party class whose call
 * arguments we want to record rather than merely expect.
 *
 * Everything the production code reads is a public property here, so a test can arrange a
 * scenario in one line and afterwards inspect what was sent to the API.
 */
class TranslatingDeepL extends DeepL
{
    /** Text the fake API returns. */
    public string $apiResponse = 'translated';

    /** When set, the fake API throws this instead of answering. */
    public ?\Throwable $apiException = null;

    public int $apiCallCount = 0;

    /** Last text handed to the API - lets tests assert what was actually sent. */
    public string $lastApiText = '';

    /** @var array<string, mixed> Last options handed to the API. */
    public array $lastApiOptions = [];

    public FakeTranslationModel $model;

    public FakeLogger $logger;

    public bool $testMode = false;

    public ?string $glossaryId = null;

    /** @var string[] Language pairs the configured glossary covers, e.g. ['en_de']. */
    public array $glossaryPairs = [];

    public function __construct()
    {
        $this->model = new FakeTranslationModel();
        $this->logger = new FakeLogger();
    }

    public function isTestModeActive(): bool
    {
        return $this->testMode;
    }

    public function getTranslator(): DeepLClient
    {
        return new FakeDeepLClient($this);
    }

    protected function getTranslationModel()
    {
        return $this->model;
    }

    protected function getLogger()
    {
        return $this->logger;
    }

    protected function getGlossaryId(): ?string
    {
        return $this->glossaryId;
    }

    protected function getGlossaryDictionaryPairs(string $glossaryId): array
    {
        return $this->glossaryPairs;
    }

    /**
     * Called by FakeDeepLClient - keeps the recording logic on the double rather than in the
     * client stub, so tests only have to look in one place.
     */
    public function recordApiCall(string $text, array $options): string
    {
        $this->apiCallCount++;
        $this->lastApiText = $text;
        $this->lastApiOptions = $options;

        if ($this->apiException !== null) {
            throw $this->apiException;
        }

        return $this->apiResponse;
    }
}
