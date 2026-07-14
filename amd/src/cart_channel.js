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
 * Same-origin notification channel for shopping cart changes.
 *
 * Lets independent browsing contexts on the same origin (e.g. an embedded
 * booking listing iframe and a separate cart badge iframe, or two tabs) tell
 * each other that the cart changed. The producer (cart.js) calls notifyChange()
 * after an explicit add/remove; consumers (the badge, other cart views) refresh
 * on onChange(). Consumers must never call notifyChange() from their callback,
 * or two listening contexts would ping-pong forever.
 *
 * @module     local_shopping_cart/cart_channel
 * @copyright  2026 Wunderbyte GmbH
 * @author     Georg Maißer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const CHANNEL_NAME = 'local_shopping_cart';
const STORAGE_KEY = 'local_shopping_cart_cartchanged';

let channel = null;

/**
 * Lazily creates the BroadcastChannel where supported.
 *
 * @returns {BroadcastChannel|null}
 */
const getChannel = () => {
    if (channel === null && typeof BroadcastChannel !== 'undefined') {
        channel = new BroadcastChannel(CHANNEL_NAME);
    }
    return channel;
};

/**
 * Announces that the cart changed. Call only on explicit user actions.
 *
 * @returns {void}
 */
export const notifyChange = () => {
    const ch = getChannel();
    if (ch) {
        ch.postMessage({action: 'cartchanged'});
    }
    // Fallback for browsers without BroadcastChannel: a localStorage write
    // fires a 'storage' event in every other same-origin document.
    try {
        window.localStorage.setItem(STORAGE_KEY, String(Date.now()));
    } catch (e) {
        // Storage may be unavailable (private mode); the BroadcastChannel path covers most cases.
        return;
    }
};

/**
 * Runs the callback whenever the cart changes in another context.
 *
 * The callback must not call notifyChange(), or listening contexts loop.
 *
 * @param {Function} callback
 * @returns {void}
 */
export const onChange = (callback) => {
    const ch = getChannel();
    if (ch) {
        ch.addEventListener('message', (ev) => {
            if (ev.data && ev.data.action === 'cartchanged') {
                callback();
            }
        });
    }
    window.addEventListener('storage', (ev) => {
        if (ev.key === STORAGE_KEY) {
            callback();
        }
    });
};
