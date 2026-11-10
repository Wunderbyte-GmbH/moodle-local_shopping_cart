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

namespace local_shopping_cart\event;

/**
 * An attempt to create, submit or pay an invoice at the invoicing platform failed.
 *
 * Logged on every failed attempt, so the site log keeps the reason per purchase after the task log is gone.
 * Payload in $event->other: identifier (cart purchase), renewalid (0 for a cart purchase), invoiceid
 * (the platform's invoice, '' if none was created), error, attemptsleft (0 = no retry follows).
 *
 * @package    local_shopping_cart
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class invoice_creation_failed extends \core\event\base {
    /** @var int Longest error text kept in the log. */
    private const MAX_ERROR_LENGTH = 1300;

    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Log a failed attempt.
     *
     * @param int $userid the buyer
     * @param int $identifier cart identifier
     * @param int $renewalid subscription renewal id, 0 for a cart purchase
     * @param string $invoiceid the platform's invoice id, '' if none was created
     * @param string $error why it failed
     * @param int $attemptsleft attempts the task queue still makes, 0 if none
     * @return void
     */
    public static function log(
        int $userid,
        int $identifier,
        int $renewalid,
        string $invoiceid,
        string $error,
        int $attemptsleft
    ): void {
        $event = self::create([
            'context' => \context_system::instance(),
            'relateduserid' => $userid ?: null,
            'other' => [
                'identifier' => $identifier,
                'renewalid' => $renewalid,
                'invoiceid' => $invoiceid,
                'error' => \core_text::substr($error, 0, self::MAX_ERROR_LENGTH),
                'attemptsleft' => $attemptsleft,
            ],
        ]);
        $event->trigger();
    }

    /**
     * Returns localized general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('invoicecreationfailed', 'local_shopping_cart');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $a = (object) [
            'userid' => $this->data['relateduserid'] ?? 0,
            'identifier' => $this->other['identifier'] ?? 0,
            'invoiceid' => ($this->other['invoiceid'] ?? '') !== '' ? $this->other['invoiceid'] : '-',
            'error' => $this->other['error'] ?? '',
            'attemptsleft' => $this->other['attemptsleft'] ?? 0,
        ];
        return get_string('invoicecreationfailed_desc', 'local_shopping_cart', $a);
    }
}
