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
 * Plugin-agnostic shopping cart views for embedding in iframes.
 *
 * @package local_shopping_cart
 * @copyright 2026 Wunderbyte GmbH
 * @author Georg Maißer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\output;

use local_shopping_cart\local\cartstore;
use local_shopping_cart\local\checkout_process\checkout_page_data;
use local_shopping_cart\output\shoppingcart_history_list;
use local_shopping_cart\shopping_cart;

/**
 * Plugin-agnostic shopping cart views for embedding in iframes.
 *
 * This class exposes cart UI (badge, and later the checkout) as standalone
 * renderables that any seller plugin can surface through its own embed
 * endpoint. It holds no knowledge of mod_booking or any other consumer: the
 * consumer discovers the views via {@see self::list_views()} and renders one
 * via the callback named there, passing the embed definition's arguments.
 */
class embed_views {
    /**
     * Lists the embeddable cart views, keyed by view identifier.
     *
     * Each entry provides the render callback (invoked as ($args): string) and
     * the argument keys the view requires. Returned to consumers so they never
     * hardcode the view catalogue.
     *
     * @return array<string, array{callback: string, requiredargs: string[]}>
     */
    public static function list_views(): array {
        return [
            'cart_badge' => [
                'callback' => self::class . '::render_badge',
                'requiredargs' => [],
            ],
            'cart_checkout' => [
                'callback' => self::class . '::render_checkout',
                'requiredargs' => [],
            ],
        ];
    }

    /**
     * Renders the compact cart badge (icon, item count, total).
     *
     * Designed to sit in a small iframe (e.g. a marketing site's nav), so it is
     * not the popover — the whole badge is a single link that breaks out of the
     * iframe to the checkout. The optional checkouturl argument overrides the
     * checkout target and is validated against the redirect allow list.
     *
     * @param array $args Embed definition arguments (optional: checkouturl).
     * @return string
     */
    public static function render_badge(array $args): string {
        global $USER, $OUTPUT, $CFG, $PAGE;

        // Keep the badge in sync when the cart changes in another same-origin
        // context (e.g. the listing iframe beside it).
        $PAGE->requires->js_call_amd('local_shopping_cart/embed_badge', 'init');

        $cartstore = cartstore::instance((int) $USER->id);
        $data = $cartstore->get_localized_data();
        shopping_cart::convert_prices_to_number_format($data);

        $checkouturl = self::validate_redirect_url((string) ($args['checkouturl'] ?? ''))
            ?? ($CFG->wwwroot . '/local/shopping_cart/checkout.php');

        $count = (int) ($data['count'] ?? 0);
        $templatedata = [
            'count' => $count,
            'hasitems' => $count > 0,
            'total' => $data['price'] ?? '0.00',
            'currency' => $data['currency'] ?? '',
            'checkouturl' => $checkouturl,
        ];

        return $OUTPUT->render_from_template('local_shopping_cart/embed_badge', $templatedata);
    }

    /**
     * Renders the full checkout (stepper, VAT, credits, coupons, addresses).
     *
     * Reuses the exact checkout page data of the still-shopping case, so the
     * embedded checkout is identical to the Moodle one minus the theme chrome.
     * The optional checkouturl/successurl arguments override where the checkout
     * button and the post-payment return land, validated against the allow list;
     * the payment provider redirect itself always happens top-level (PSPs forbid
     * framing), and the stored success URL lets the return page continue to the
     * marketing site.
     *
     * @param array $args Embed definition arguments (optional: checkouturl, successurl).
     * @return string
     */
    public static function render_checkout(array $args): string {
        global $OUTPUT, $PAGE, $USER, $SESSION, $CFG;

        $data = checkout_page_data::build_cart_checkout((int) $USER->id);

        $checkouturl = self::validate_redirect_url((string) ($args['checkouturl'] ?? ''));
        if ($checkouturl !== null) {
            $data['checkouturl'] = $checkouturl;
        }
        $successurl = self::validate_redirect_url((string) ($args['successurl'] ?? ''));
        if ($successurl !== null) {
            // Stored so the top-level payment return page can continue to the
            // marketing success page after the provider round-trip.
            $SESSION->local_shopping_cart_embed_successurl = $successurl;
        }

        // The checkout stepper's JS, as on the standalone page. JS requirements
        // still flush in the footer, so this is fine after the header.
        $PAGE->requires->js_call_amd('local_shopping_cart/checkout_manager', 'preventBFCache', []);

        shopping_cart::convert_prices_to_number_format($data);
        if (get_config('local_shopping_cart', 'schistorysections')) {
            shoppingcart_history_list::organize_returnarray_into_collapsible_sections($data);
        }

        // The view renders after the page header, so the stylesheet can no longer
        // be added to <head> via $PAGE->requires->css(); include it inline (a
        // <link> in the body is valid HTML5 and loads the checkout styles).
        $css = \html_writer::empty_tag('link', [
            'rel' => 'stylesheet',
            'href' => $CFG->wwwroot . '/local/shopping_cart/styles.css',
        ]);

        return $css . $OUTPUT->render_from_template('local_shopping_cart/checkout', $data);
    }

    /**
     * Validates a redirect override URL against the configured allow list.
     *
     * Overrides arrive as request-derived arguments, so an unrestricted redirect
     * would be an open-redirect vector. Only URLs whose start matches one of the
     * configured prefixes (one per line in the embedredirectallowlist setting)
     * are accepted; everything else falls back to the Moodle default.
     *
     * @param string $url
     * @return string|null The URL if allowed, null otherwise.
     */
    public static function validate_redirect_url(string $url): ?string {
        if ($url === '') {
            return null;
        }

        $raw = (string) get_config('local_shopping_cart', 'embedredirectallowlist');
        foreach (preg_split('/\R/', $raw) as $prefix) {
            $prefix = trim((string) $prefix);
            if ($prefix !== '' && strpos($url, $prefix) === 0) {
                return $url;
            }
        }
        return null;
    }
}
