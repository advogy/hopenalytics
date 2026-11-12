<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use App\Support\IndonesiaIp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Calls SetLocale directly rather than through a route: the migrations use MySQL-only SQL,
 * so a page request (which needs the database) can't run on the test suite's SQLite.
 */
class LocaleDetectionTest extends TestCase
{
    private function localeFor(string $ip, array $headers = [], ?string $stored = null): string
    {
        $session = $this->app['session']->driver(); // the array driver under phpunit.xml
        $session->flush();

        if ($stored !== null) {
            $session->put('locale', $stored);
        }

        $request = Request::create('/login', 'GET', server: ['REMOTE_ADDR' => $ip]);
        $request->headers->add($headers);
        $request->setLaravelSession($session);
        $this->app->instance('request', $request);

        (new SetLocale)->handle($request, fn () => new Response);

        $this->assertSame(App::getLocale(), $session->get('locale'));

        return App::getLocale();
    }

    public function test_a_visitor_in_indonesia_sees_indonesian(): void
    {
        $this->assertSame('id', $this->localeFor('114.124.200.5', ['Accept-Language' => 'en-US'])); // Telkomsel
    }

    public function test_a_visitor_outside_indonesia_sees_english(): void
    {
        $this->assertSame('en', $this->localeFor('8.8.8.8', ['Accept-Language' => 'id-ID']));
    }

    public function test_an_indonesian_ipv6_visitor_sees_indonesian(): void
    {
        $this->assertSame('id', $this->localeFor('2001:448a:1::1')); // Telkom
    }

    public function test_a_local_address_falls_back_to_app_locale(): void
    {
        config(['app.locale' => 'en']);
        $this->assertSame('en', $this->localeFor('127.0.0.1'));

        config(['app.locale' => 'id']);
        $this->assertSame('id', $this->localeFor('192.168.1.5'));
    }

    public function test_a_language_already_chosen_overrides_location(): void
    {
        $this->assertSame('en', $this->localeFor('114.124.200.5', stored: 'en'));
    }

    public function test_ipv6_ranges_compare_as_text_not_numbers(): void
    {
        // All-digit hex strings like these are "numeric" to PHP's < and >, which once put
        // Telkom's block in the wrong order and missed it.
        IndonesiaIp::useRanges([
            'ipv4' => [],
            'ipv6' => [
                ['20014488000000000000000000000000', '20014488ffffffffffffffffffffffff'],
                ['2001448a000000000000000000000000', '2001448bffffffffffffffffffffffff'],
            ],
        ]);

        try {
            $this->assertTrue(IndonesiaIp::contains('2001:4488::1'));
            $this->assertTrue(IndonesiaIp::contains('2001:448b:ffff::1'));
            $this->assertFalse(IndonesiaIp::contains('2001:4489::1'));
        } finally {
            IndonesiaIp::useRanges(null);
        }
    }
}
