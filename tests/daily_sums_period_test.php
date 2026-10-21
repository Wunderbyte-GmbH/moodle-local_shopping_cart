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
use local_shopping_cart\form\daily_sums_date_selector_form;
use local_shopping_cart\local\daily_sums_pdf;

/**
 * Tests for the period of the daily sums (cash report): timezone, form validation and boundaries.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte GmbH {@link http://www.wunderbyte.at}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_shopping_cart\form\daily_sums_date_selector_form
 * @covers \local_shopping_cart\shopping_cart::get_daily_sums_data
 * @covers \local_shopping_cart\shopping_cart::format_daily_sums_period
 * @covers \local_shopping_cart\shopping_cart::end_of_minute
 * @covers \local_shopping_cart\local\daily_sums_pdf::filename
 */
final class daily_sums_period_test extends advanced_testcase {
    /**
     * Set up: server in Vienna, the current user in Chicago (7 hours behind).
     */
    protected function setUp(): void {
        global $USER;
        parent::setUp();
        $this->resetAfterTest();
        $this->setTimezone('Europe/Vienna', 'Europe/Vienna');
        $this->setAdminUser();
        $USER->timezone = 'America/Chicago';
    }

    /**
     * Timestamp of a date and time in the server timezone (Vienna).
     *
     * @param string $datetime e.g. '2026-10-21 00:00'
     * @return int
     */
    private function vienna(string $datetime): int {
        return (new \DateTime($datetime, new \DateTimeZone('Europe/Vienna')))->getTimestamp();
    }

    /**
     * Inserts a successful cash payment into the ledger.
     *
     * @param int $timecreated
     * @param float $price
     * @return int record id
     */
    private function add_cash_ledger_entry(int $timecreated, float $price): int {
        global $DB, $USER;
        return $DB->insert_record('local_shopping_cart_ledger', (object)[
            'userid' => $USER->id,
            'itemid' => 1,
            'itemname' => 'Test item',
            'price' => $price,
            'currency' => 'EUR',
            'componentname' => 'local_shopping_cart',
            'area' => 'testarea',
            'payment' => LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_CASH,
            'paymentstatus' => LOCAL_SHOPPING_CART_PAYMENT_SUCCESS,
            'usermodified' => $USER->id,
            'timemodified' => $timecreated,
            'timecreated' => $timecreated,
        ]);
    }

    /**
     * The default period is the current day of the server, not of the user.
     */
    public function test_default_period_uses_server_timezone(): void {
        [$from, $to] = daily_sums_date_selector_form::default_period();

        $serverday = date('Y-m-d');
        $this->assertSame($this->vienna("$serverday 00:00"), $from);
        $this->assertSame($this->vienna("$serverday 23:59"), $to);
    }

    /**
     * The period label and the file name are built from the server day, with the user in another timezone.
     */
    public function test_period_label_and_filename_use_server_timezone(): void {
        $from = $this->vienna('2026-10-21 00:00');
        $to = $this->vienna('2026-10-21 23:59');

        // In Chicago this period is Oct 20 17:00 - Oct 21 16:59, so a user timezone would give another label.
        $this->assertSame('Oct 21, 2026', shopping_cart::format_daily_sums_period($from, $to));
        $this->assertSame('daily_sums_2026-10-21.pdf', daily_sums_pdf::filename($from, $to));
        $this->assertSame('daily_sums_2026-10-21', daily_sums_pdf::filename($from, $to, false));

        // Several days or a part of a day: from - to with the time of day.
        $to = $this->vienna('2026-10-23 23:59');
        $this->assertSame(
            'Oct 21, 2026 00:00 - Oct 23, 2026 23:59',
            shopping_cart::format_daily_sums_period($from, $to)
        );
        $this->assertSame('daily_sums_2026-10-21_2026-10-23.pdf', daily_sums_pdf::filename($from, $to));

        $from = $this->vienna('2026-10-21 08:30');
        $to = $this->vienna('2026-10-21 12:00');
        $this->assertSame(
            'Oct 21, 2026 08:30 - Oct 21, 2026 12:00',
            shopping_cart::format_daily_sums_period($from, $to)
        );
        $this->assertSame('daily_sums_2026-10-21.pdf', daily_sums_pdf::filename($from, $to));
    }

    /**
     * The form reads the submitted date and time in the server timezone.
     */
    public function test_form_submission_uses_server_timezone(): void {
        daily_sums_date_selector_form::mock_submit([
            'dailysumsfrom' => ['year' => 2026, 'month' => 10, 'day' => 21, 'hour' => 0, 'minute' => 0],
            'dailysumsto' => ['year' => 2026, 'month' => 10, 'day' => 21, 'hour' => 23, 'minute' => 59],
        ]);
        $form = new daily_sums_date_selector_form();
        $data = $form->get_data();

        $this->assertNotNull($data);
        $this->assertSame($this->vienna('2026-10-21 00:00'), (int)$data->dailysumsfrom);
        $this->assertSame($this->vienna('2026-10-21 23:59'), (int)$data->dailysumsto);
    }

    /**
     * "To" before "From" is rejected with an error on the "To" field.
     */
    public function test_form_rejects_end_before_start(): void {
        daily_sums_date_selector_form::mock_submit([
            'dailysumsfrom' => ['year' => 2026, 'month' => 10, 'day' => 21, 'hour' => 12, 'minute' => 0],
            'dailysumsto' => ['year' => 2026, 'month' => 10, 'day' => 21, 'hour' => 8, 'minute' => 0],
        ]);
        $form = new daily_sums_date_selector_form();

        $this->assertTrue($form->is_submitted());
        $this->assertNull($form->get_data());
        $this->assertFalse($form->is_validated());

        $errors = $form->validation([
            'dailysumsfrom' => $this->vienna('2026-10-21 12:00'),
            'dailysumsto' => $this->vienna('2026-10-21 08:00'),
        ], []);
        $this->assertSame(
            ['dailysumsto' => get_string('error:dailysums:rangeinvalid', 'local_shopping_cart')],
            $errors
        );

        // The rendered form carries the error message, so report.php can show it.
        ob_start();
        $form->display();
        $html = ob_get_clean();
        $this->assertStringContainsString(get_string('error:dailysums:rangeinvalid', 'local_shopping_cart'), $html);

        // Same minute is fine.
        $this->assertSame([], $form->validation([
            'dailysumsfrom' => $this->vienna('2026-10-21 12:00'),
            'dailysumsto' => $this->vienna('2026-10-21 12:00'),
        ], []));
    }

    /**
     * The end minute is included, the second after it and the second before the start are not.
     */
    public function test_end_of_minute(): void {
        $to = $this->vienna('2026-10-21 23:59');
        $this->assertSame($to + 59, shopping_cart::end_of_minute($to));
        $this->assertSame($to + 59, shopping_cart::end_of_minute($to + 30));
        $this->assertSame($to + 59, shopping_cart::end_of_minute($to + 59));
    }

    /**
     * Only ledger entries inside the period are summed, the boundaries are inclusive.
     */
    public function test_daily_sums_data_period_boundaries(): void {
        $from = $this->vienna('2026-10-21 00:00');
        $to = $this->vienna('2026-10-21 23:59');

        $this->add_cash_ledger_entry($from - 1, 100); // Just before the period.
        $this->add_cash_ledger_entry($from, 1); // First second.
        $this->add_cash_ledger_entry($this->vienna('2026-10-21 12:00'), 2);
        $this->add_cash_ledger_entry($to + 59, 4); // Last second of the end minute.
        $this->add_cash_ledger_entry($to + 60, 200); // Just after the period.

        $data = shopping_cart::get_daily_sums_data($from, $to);

        $this->assertSame('7.00', $data['totalsum']);
        $this->assertSame('7.00', $data['totalcash']);
        $this->assertSame('Oct 21, 2026', $data['date']);
        $this->assertTrue($data['dailysums:exist']);
        $this->assertStringContainsString("from=$from&to=" . ($to + 59), $data['dailysumspdfurl']->out(false));

        // The entry before the period belongs to the day before.
        $data = shopping_cart::get_daily_sums_data($this->vienna('2026-10-20 00:00'), $this->vienna('2026-10-20 23:59'));
        $this->assertSame('100.00', $data['totalsum']);

        // Nothing in an empty period.
        $data = shopping_cart::get_daily_sums_data($this->vienna('2026-10-19 00:00'), $this->vienna('2026-10-19 23:59'));
        $this->assertArrayNotHasKey('dailysums:exist', $data);
        $this->assertArrayNotHasKey('totalsum', $data);
    }
}
