<?php

declare(strict_types=1);

namespace foun10\DeepL\Tests\Unit\Double;

use foun10\DeepL\Core\DeepL;

/**
 * Test double for the language-on-demand resolution.
 *
 * Substitutes the three seams that reach the shop - request parameters, cookie reads and
 * cookie writes - and records what would have been written, so the recursion guard around
 * setOxCookie() can be asserted without a session.
 */
class LanguageOnDemandDeepL extends DeepL
{
    /** @var array<string, string> Request parameters the fake request exposes. */
    public $requestParameters = [];

    /** @var array<string, string> Cookies the fake browser sends. */
    public $cookies = [];

    /** @var array<int, array{0: string, 1: string}> [name, value] of every cookie write. */
    public $cookiesWritten = [];

    /**
     * The offered languages come from a module setting now, and an empty one offers nothing -
     * so these tests configure the languages they exercise explicitly.
     */
    protected function getModuleSettingCollection(string $name): array
    {
        return DeepL::DEFAULT_LANGUAGES_ON_DEMAND;
    }

    protected function getRequestParameter(string $name)
    {
        return $this->requestParameters[$name] ?? null;
    }

    protected function getCookie(string $name)
    {
        return $this->cookies[$name] ?? null;
    }

    protected function setCookie(string $name, string $value): void
    {
        $this->cookiesWritten[] = [$name, $value];
    }
}
