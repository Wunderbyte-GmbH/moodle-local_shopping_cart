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

/**
 * Event observers used in forum.
 *
 * @package    local_shopping_cart
 * @copyright  2023 David Bogner <davidbogner@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\interfaces;

use core\event\base;

/**
 * Interface for creating invoices with external invoicing platforms.
 *
 * @package    local_shopping_cart
 * @copyright  2023 David Bogner <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface invoice {

    /**
     * Create invoice as task. When invoice provider is down, we can reschedule the invoice creation.
     * The task is created based on the checkout_completed event.
     *
     * @param base $event
     * @return mixed
     */
    public static function create_invoice_task(base $event);
    /**
     * Create an invoice.
     *
     * @param int $identifier Data for invoice as JSON ready for the post request.
     * @return bool true if it was successfull
     */
    public function create_invoice(int $identifier): bool;

    /**
     * Prepare the data for the curl request as JSON string.
     *
     */
    public function prepare_json_invoice_data(): bool;

    /**
     * Get an array of items for the invoice.
     *
     * @param string $response
     * @param string $url
     * @return bool true if no error
     */
    public function validate_response(string $response, string $url): bool;
}
