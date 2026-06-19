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
}
