<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_shopping_cart;

use advanced_testcase;
use local_shopping_cart\local\guestcheckout;

/**
 * Tests for the guest checkout user lifecycle (create, identify, convert).
 *
 * The full paid guest checkout cannot run in Behat (a guest has no credits and
 * the real payment gateway is not simulated), so the guest -> real user
 * conversion - the part not covered by the guest_checkout Behat feature - is
 * verified here at unit level.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_shopping_cart\local\guestcheckout
 */
final class guestcheckout_test extends advanced_testcase {
    /**
     * Set up the test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Creates a Moodle user and the pending-guest record the same way
     * create_guest_user() persists it, but without the UI-only complete_user_login()
     * call (which is exercised by the guest_checkout Behat feature). Returns the userid.
     *
     * @return int
     */
    private function make_pending_guest(): int {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['auth' => 'manual']);
        $DB->insert_record('local_shopping_cart_guestusers', (object) [
            'userid' => $user->id,
            'timecreated' => time(),
        ]);
        return (int) $user->id;
    }

    /**
     * A pending guest user is recognised as a guest; regular users and invalid ids are not.
     *
     * @return void
     */
    public function test_identify_guest_user(): void {
        $guestid = $this->make_pending_guest();
        $this->assertTrue(
            guestcheckout::is_guest_checkout_user($guestid),
            'A pending guest user must be recognised as a guest checkout user.'
        );

        // A regular account and an invalid id are not guest checkout users.
        $regular = $this->getDataGenerator()->create_user();
        $this->assertFalse(guestcheckout::is_guest_checkout_user((int) $regular->id));
        $this->assertFalse(guestcheckout::is_guest_checkout_user(0));
    }

    /**
     * Converting a guest applies the registration data, switches to manual auth
     * and clears the pending-guest record.
     *
     * @return void
     */
    public function test_convert_guest_to_real_user(): void {
        global $DB;

        // The conversion sends a set-password e-mail; capture it.
        $sink = $this->redirectEmails();

        $guestid = $this->make_pending_guest();

        $result = guestcheckout::convert_guest_to_real_user(
            $guestid,
            'Maxi',
            'Muster',
            'maxi.muster@example.com'
        );
        $this->assertTrue($result, 'Converting a pending guest user should succeed.');

        // The Moodle user record now carries the registration data and manual auth.
        $user = $DB->get_record('user', ['id' => $guestid], '*', MUST_EXIST);
        $this->assertEquals('Maxi', $user->firstname);
        $this->assertEquals('Muster', $user->lastname);
        $this->assertEquals('maxi.muster@example.com', $user->email);
        $this->assertEquals('manual', $user->auth, 'Converted user must use manual auth.');
        $this->assertEquals('checkout_maxi.muster@example.com', $user->username);

        // It is no longer a pending guest user (the guestusers record is gone).
        $this->assertFalse(
            guestcheckout::is_guest_checkout_user($guestid),
            'After conversion the user must no longer be a pending guest user.'
        );

        $sink->close();
    }

    /**
     * A regular (non-guest) user cannot be converted.
     *
     * @return void
     */
    public function test_convert_non_guest_returns_false(): void {
        $regular = $this->getDataGenerator()->create_user();
        $this->assertFalse(
            guestcheckout::convert_guest_to_real_user((int) $regular->id, 'A', 'B', 'a.b@example.com'),
            'A non-guest user must not be convertible.'
        );
    }

    /**
     * Calls the private pattern matcher.
     *
     * @param string $url
     * @param string $patterns
     * @return bool
     */
    private function match_patterns(string $url, string $patterns): bool {
        set_config('guestautocreatepatterns', $patterns, 'local_shopping_cart');
        $method = new \ReflectionMethod(guestcheckout::class, 'url_matches_auto_create_patterns');
        return (bool) $method->invoke(null, new \moodle_url($url));
    }

    /**
     * Data provider for {@see test_url_matches_auto_create_patterns}.
     *
     * @return array
     */
    public static function url_pattern_provider(): array {
        return [
            'root matches front page only' => ['/', '/', true],
            'root does not match subpage' => ['/mod/page/view.php?id=5', '/', false],
            'exact path matches regardless of params' => ['/mod/page/view.php?id=6', '/mod/page/view.php', true],
            'wildcard prefix matches' => ['/course/view.php?id=2', '/course/*', true],
            'wildcard prefix mismatch' => ['/mod/page/view.php', '/course/*', false],
            'query pattern matches same params' => ['/mod/page/view.php?id=5', '/mod/page/view.php?id=5', true],
            'query pattern mismatching value' => ['/mod/page/view.php?id=6', '/mod/page/view.php?id=5', false],
            'query pattern missing param' => ['/mod/page/view.php', '/mod/page/view.php?id=5', false],
            'query pattern ignores extra request params' => [
                '/mod/page/view.php?id=5&forceview=1',
                '/mod/page/view.php?id=5',
                true,
            ],
            'query pattern with multiple params' => [
                '/course/view.php?id=2&section=3',
                '/course/view.php?id=2&section=3',
                true,
            ],
            'query pattern with one wrong of multiple params' => [
                '/course/view.php?id=2&section=4',
                '/course/view.php?id=2&section=3',
                false,
            ],
            'query pattern on wrong path' => ['/mod/url/view.php?id=5', '/mod/page/view.php?id=5', false],
            'second line matches' => ['/mod/page/view.php?id=5', "/my\n/mod/page/view.php?id=5", true],
        ];
    }

    /**
     * The configured auto-create patterns support paths, wildcards and query parameters.
     *
     * @dataProvider url_pattern_provider
     * @param string $url
     * @param string $patterns
     * @param bool $expected
     * @return void
     */
    public function test_url_matches_auto_create_patterns(string $url, string $patterns, bool $expected): void {
        $this->assertSame($expected, $this->match_patterns($url, $patterns));
    }

    /**
     * A site-guest session (e.g. autologinguests) is converted into a guest checkout
     * user on a matching page, just like a visitor without any session.
     *
     * @return void
     */
    public function test_auto_create_converts_site_guest_session(): void {
        global $DB, $USER;

        set_config('guestoncheckout', 1, 'local_shopping_cart');
        set_config('guestautocreatepatterns', '/mod/page/view.php?id=5', 'local_shopping_cart');

        // The visitor already carries a site-guest session from a previous page view.
        $this->setGuestUser();

        try {
            // The error suppression hides session header errors from complete_user_login,
            // same as core's test_complete_user_login does.
            @guestcheckout::maybe_auto_create_guest_user_for_url(
                new \moodle_url('/mod/page/view.php', ['id' => 5])
            );
            $this->fail('The auto-create must end in a redirect to reload the page.');
        } catch (\moodle_exception $e) {
            $this->assertSame('redirecterrordetected', $e->errorcode);
        }

        $this->assertTrue(
            guestcheckout::is_guest_checkout_user((int) $USER->id),
            'The site guest session must have been replaced by a guest checkout user.'
        );
        $this->assertEquals(1, $DB->count_records('local_shopping_cart_guestusers'));
    }

    /**
     * A real, logged-in user must never be replaced by a guest checkout user.
     *
     * @return void
     */
    public function test_auto_create_skips_real_users(): void {
        global $DB;

        set_config('guestoncheckout', 1, 'local_shopping_cart');
        set_config('guestautocreatepatterns', '/mod/page/view.php?id=5', 'local_shopping_cart');

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->assertFalse(
            guestcheckout::maybe_auto_create_guest_user_for_url(
                new \moodle_url('/mod/page/view.php', ['id' => 5])
            )
        );
        $this->assertEquals(0, $DB->count_records('local_shopping_cart_guestusers'));
    }

    /**
     * When the gcattempt marker of a previous attempt comes back on a still
     * anonymous request (browser rejected the session cookie, e.g. third-party
     * cookie blocking inside an iframe), no further guest user is created —
     * otherwise the auto-create would redirect forever. The marker is appended
     * by the redirect in maybe_auto_create_guest_user_for_url() itself.
     *
     * @return void
     */
    public function test_auto_create_stops_on_cookie_failure_marker(): void {
        global $DB;

        set_config('guestoncheckout', 1, 'local_shopping_cart');
        set_config('guestautocreatepatterns', '/mod/booking/embed.php', 'local_shopping_cart');

        // Anonymous request whose URL carries the marker of a failed attempt.
        $this->assertFalse(
            guestcheckout::maybe_auto_create_guest_user_for_url(
                new \moodle_url('/mod/booking/embed.php', ['key' => str_repeat('a', 32), 'gcattempt' => 1])
            )
        );
        $this->assertEquals(0, $DB->count_records('local_shopping_cart_guestusers'));

        // Without the marker the same URL still auto-creates (ends in redirect).
        try {
            @guestcheckout::maybe_auto_create_guest_user_for_url(
                new \moodle_url('/mod/booking/embed.php', ['key' => str_repeat('a', 32)])
            );
            $this->fail('The auto-create must end in a redirect to reload the page.');
        } catch (\moodle_exception $e) {
            $this->assertSame('redirecterrordetected', $e->errorcode);
        }
        $this->assertEquals(1, $DB->count_records('local_shopping_cart_guestusers'));
    }
}
