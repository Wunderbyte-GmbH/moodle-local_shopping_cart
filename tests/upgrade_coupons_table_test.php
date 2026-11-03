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
use xmldb_field;
use xmldb_table;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/upgradelib.php');
require_once($CFG->dirroot . '/local/shopping_cart/db/upgrade.php');

/**
 * The upgrade from a release line that skipped the creation of the coupons table.
 *
 * @package    local_shopping_cart
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::xmldb_local_shopping_cart_upgrade
 */
final class upgrade_coupons_table_test extends advanced_testcase {
    /**
     * A site whose plugin version lies between the creation of the coupons table (2026073001) and the
     * widening of discountpercentage (2026082700) never ran the creation step, because its version was
     * already higher. The upgrade must create the table before it touches its fields instead of
     * failing with a ddl_table_missing_exception.
     *
     * @return void
     */
    public function test_upgrade_creates_the_coupons_table_before_changing_it(): void {
        global $DB;
        $this->resetAfterTest();
        $dbman = $DB->get_manager();

        $coupons = new xmldb_table('local_shopping_cart_coupons');
        $guests = new xmldb_table('local_shopping_cart_guestusers');
        $dbman->drop_table($coupons);
        if ($dbman->table_exists($guests)) {
            $dbman->drop_table($guests);
        }
        $this->assertFalse($dbman->table_exists($coupons));

        // The savepoints must not look like a downgrade.
        set_config('version', 2026081802, 'local_shopping_cart');
        $this->assertTrue(xmldb_local_shopping_cart_upgrade(2026081802));

        $this->assertTrue($dbman->table_exists($coupons));
        $this->assertTrue($dbman->table_exists($guests));
        foreach (['discountpercentage', 'maxnumberperuser', 'countmode'] as $fieldname) {
            $this->assertTrue($dbman->field_exists($coupons, new xmldb_field($fieldname)), "field $fieldname");
        }
    }
}
