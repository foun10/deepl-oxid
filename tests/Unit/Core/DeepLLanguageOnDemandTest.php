<?php

declare(strict_types=1);

namespace foun10\DeepL\Tests\Unit\Core;

use foun10\DeepL\Tests\Unit\Double\LanguageOnDemandDeepL;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the "language on demand" resolution - which language a visitor ends up being
 * served, and the guard that stopped it from recursing into itself.
 *
 * Precedence is URL parameter, then cookie, then browser header. Getting that order wrong is
 * the kind of bug nobody notices until a customer reports that switching languages "sometimes"
 * does not stick.
 */
class DeepLLanguageOnDemandTest extends TestCase
{
    /** @var array Backup of the superglobals the resolution reads directly. */
    private $globals = [];

    protected function setUp(): void
    {
        $this->globals = ['get' => $_GET, 'post' => $_POST, 'server' => $_SERVER];
        $_GET = [];
        $_POST = [];
        unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
    }

    protected function tearDown(): void
    {
        $_GET = $this->globals['get'];
        $_POST = $this->globals['post'];
        $_SERVER = $this->globals['server'];
    }

    // ---------------------------------------------------------------- precedence

    public function testUrlParameterWins(): void
    {
        $deepL = new LanguageOnDemandDeepL();
        $deepL->requestParameters['langOnDemand'] = 'fr';
        $deepL->cookies['langOnDemand'] = 'it';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'es-ES,es;q=0.9';

        $this->assertSame('fr', $deepL->setActiveLanguageOnDemand());
    }

    public function testCookieWinsOverBrowserHeader(): void
    {
        $deepL = new LanguageOnDemandDeepL();
        $deepL->cookies['langOnDemand'] = 'it';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'es-ES,es;q=0.9';

        $this->assertSame('it', $deepL->setActiveLanguageOnDemand());
    }

    public function testFallsBackToTheBrowserHeader(): void
    {
        $deepL = new LanguageOnDemandDeepL();
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'es-ES,es;q=0.9';

        $this->assertSame('es', $deepL->setActiveLanguageOnDemand());
    }

    public function testResolvesToEmptyWhenNothingMatches(): void
    {
        $deepL = new LanguageOnDemandDeepL();

        $this->assertSame('', $deepL->setActiveLanguageOnDemand());
    }

    // ---------------------------------------------------------------- validation

    public function testIgnoresALanguageTheShopDoesNotOffer(): void
    {
        $deepL = new LanguageOnDemandDeepL();
        $deepL->requestParameters['langOnDemand'] = 'xx';
        $deepL->cookies['langOnDemand'] = 'it';

        $this->assertSame(
            'it',
            $deepL->setActiveLanguageOnDemand(),
            'an unsupported code must not win over a valid cookie'
        );
    }

    public function testIgnoresAnUnsupportedBrowserLanguage(): void
    {
        $deepL = new LanguageOnDemandDeepL();
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'xx-XX';

        $this->assertSame('', $deepL->setActiveLanguageOnDemand());
    }

    /**
     * An explicitly empty langOnDemand parameter is how the visitor switches back to the shop's
     * own language, so it has to beat a still-present cookie.
     */
    public function testEmptyUrlParameterClearsAPreviousChoice(): void
    {
        $deepL = new LanguageOnDemandDeepL();
        $_GET['langOnDemand'] = '';
        $deepL->requestParameters['langOnDemand'] = '';
        $deepL->cookies['langOnDemand'] = 'it';

        $this->assertSame('', $deepL->setActiveLanguageOnDemand());
    }

    // ---------------------------------------------------------------- cookie writing

    public function testWritesTheResolvedLanguageToTheCookieOnce(): void
    {
        $deepL = new LanguageOnDemandDeepL();
        $deepL->requestParameters['langOnDemand'] = 'fr';

        $deepL->setActiveLanguageOnDemand();
        $deepL->setActiveLanguageOnDemand();

        $this->assertSame([['langOnDemand', 'fr']], $deepL->cookiesWritten);
    }

    /**
     * The cookie is written exactly once per request on purpose. UtilsServer::setOxCookie()
     * resolves the shop URL, which falls back to browser language detection, which re-enters
     * this method - the guard is what stops that from recursing until the request dies with a
     * 503. Pinning it here so nobody "simplifies" the guard away later.
     */
    public function testDoesNotWriteTheCookieASecondTime(): void
    {
        $deepL = new LanguageOnDemandDeepL();
        $deepL->requestParameters['langOnDemand'] = 'fr';

        $deepL->setActiveLanguageOnDemand();
        $deepL->setActiveLanguageOnDemand('it');

        $this->assertCount(1, $deepL->cookiesWritten);
    }

    // ---------------------------------------------------------------- getActiveLanguageOnDemand

    public function testGetActiveResolvesOnFirstCallAndThenReusesTheResult(): void
    {
        $deepL = new LanguageOnDemandDeepL();
        $deepL->cookies['langOnDemand'] = 'it';

        $this->assertSame('it', $deepL->getActiveLanguageOnDemand());

        $deepL->cookies['langOnDemand'] = 'fr';

        $this->assertSame('it', $deepL->getActiveLanguageOnDemand(), 'resolution must happen once per request');
    }

    // ---------------------------------------------------------------- offered languages

    public function testOffersTheLanguagesTheModuleSupports(): void
    {
        $deepL = new LanguageOnDemandDeepL();
        $languages = $deepL->getLanguagesOnDemand();

        $this->assertArrayHasKey('fr', $languages);
        $this->assertArrayHasKey('it', $languages);
        $this->assertArrayNotHasKey('de', $languages, 'the shop base language is not an on-demand language');
        $this->assertArrayNotHasKey('en', $languages, 'the fixed source language is not an on-demand language');
    }
}
