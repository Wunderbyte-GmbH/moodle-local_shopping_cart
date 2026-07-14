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
 * Keeps the embedded cart badge (count and total) in sync with the cart.
 *
 * The badge is a server-rendered snapshot. When the cart changes in another
 * same-origin context (e.g. the listing iframe beside it), the badge fetches
 * the current total and count from the get_price web service and updates itself
 * in place — no reload, and the price stays correct, not just the count. It only
 * consumes the shared channel, so there is no notify loop.
 *
 * @module     local_shopping_cart/embed_badge
 * @copyright  2026 Wunderbyte GmbH
 * @author     Georg Maißer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {onChange} from 'local_shopping_cart/cart_channel';

/**
 * Fetches the current cart price/count and updates the badge in place.
 *
 * @param {HTMLElement} root The badge root element.
 * @returns {void}
 */
const refresh = (root) => {
    Ajax.call([{
        methodname: 'local_shopping_cart_get_price',
        // usecredit/useinstallments -1 = leave state unchanged; no coupon input here.
        args: {usecredit: -1, useinstallments: -1, couponvalue: '', couponenabled: false},
        done: function(data) {
            const count = parseInt(data.count, 10) || 0;
            root.classList.toggle('sc-embed-badge-empty', count === 0);

            const countel = root.querySelector('[data-region="count"]');
            if (countel) {
                countel.textContent = count;
            }
            const totalel = root.querySelector('[data-region="total"]');
            if (totalel) {
                const price = (parseFloat(data.price) || 0).toFixed(2);
                totalel.textContent = price + ' ' + (data.currency || '');
            }
        },
        fail: function() {
            return;
        },
    }]);
};

/**
 * Initialises the badge: sync now and whenever the cart changes elsewhere.
 *
 * @returns {void}
 */
export const init = () => {
    const root = document.querySelector('[data-region="sc-embed-badge"]');
    if (!root) {
        return;
    }
    refresh(root);
    onChange(() => refresh(root));
};
