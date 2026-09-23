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

namespace local_shopping_cart\local\subscriptions;

use stdClass;

/**
 * Builds the invoice DTO for a recorded renewal (pure: no HTTP, no database).
 *
 * The result is what {@see \local_shopping_cart\invoice\erpnext_invoice::create_invoice_from_data()} expects.
 *
 * @package    local_shopping_cart
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renewal_invoice_builder {
    /**
     * Build the DTO.
     *
     * @param stdClass $renewal row of local_shopping_cart_renewals
     * @param stdClass[] $ledgerrows the renewal's ledger rows (gross price, tax, itemname, vatnumber)
     * @param stdClass $user the buyer (id, email, firstname, lastname)
     * @param stdClass $billing billing address record (company, name, state, address, city, zip, id)
     * @return stdClass
     */
    public static function build(stdClass $renewal, array $ledgerrows, stdClass $user, stdClass $billing): stdClass {
        $vatnumber = '';
        $items = [];
        foreach ($ledgerrows as $row) {
            if ($vatnumber === '' && !empty($row->vatnumber)) {
                $vatnumber = (string) $row->vatnumber;
            }
            $item = new stdClass();
            $item->itemname = $row->itemname;
            $item->net = round((float) $row->price - (float) $row->tax, 2);
            $item->serviceperiodstart = (int) $renewal->periodstart ?: (int) $renewal->timecreated;
            $item->serviceperiodend = (int) $renewal->periodend ?: (int) $renewal->timecreated;
            $items[] = $item;
        }

        $data = new stdClass();
        // The provider's invoice id keeps the ERP invoice unique per renewal.
        $data->reference = (string) $renewal->externalinvoiceid;
        $data->currency = !empty($renewal->currency) ? (string) $renewal->currency : null;
        $data->conversionrate = null;
        $data->grosscheck = (float) $renewal->amount;
        $data->taxtemplate = null; // Auto-selected from country and VAT id.
        $data->vatnumber = $vatnumber;
        $data->markpaid = true;
        $data->user = $user;
        $data->billing = $billing;
        $data->items = $items;
        return $data;
    }
}
