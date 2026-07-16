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
 * Tests for the plugin-agnostic embed views.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @author     Georg Maißer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\output;

use advanced_testcase;

/**
 * Tests for the plugin-agnostic embed views.
 *
 * @covers \local_shopping_cart\output\embed_views
 */
final class embed_views_test extends advanced_testcase {
    /**
     * Set up.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * The badge view is listed with its callback and no required args.
     */
    public function test_list_views(): void {
        $views = embed_views::list_views();
        $this->assertArrayHasKey('cartbadge', $views);
        $this->assertSame(
            'local_shopping_cart\output\embed_views::render_badge',
            $views['cartbadge']['callback']
        );
        $this->assertSame([], $views['cartbadge']['requiredargs']);
    }

    /**
     * Redirect overrides are only honoured when they match an allow-list prefix.
     */
    public function test_validate_redirect_url(): void {
        // Empty allow list: nothing is allowed.
        set_config('embedredirectallowlist', '', 'local_shopping_cart');
        $this->assertNull(embed_views::validate_redirect_url('https://www.example.com/checkout'));
        $this->assertNull(embed_views::validate_redirect_url(''));

        set_config(
            'embedredirectallowlist',
            "https://www.example.com/\nhttps://shop.example.org/",
            'local_shopping_cart'
        );

        // Prefix match passes through unchanged.
        $this->assertSame(
            'https://www.example.com/checkout.html',
            embed_views::validate_redirect_url('https://www.example.com/checkout.html')
        );
        $this->assertSame(
            'https://shop.example.org/de/success',
            embed_views::validate_redirect_url('https://shop.example.org/de/success')
        );

        // Anything not matching a prefix is rejected (open-redirect guard).
        $this->assertNull(embed_views::validate_redirect_url('https://evil.example.net/phish'));
        $this->assertNull(embed_views::validate_redirect_url('https://www.example.com.evil.net/'));
    }

    /**
     * The badge renders for an empty cart and links to the default checkout;
     * a disallowed checkout override falls back to the default.
     */
    public function test_render_badge(): void {
        global $CFG;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        set_config('embedredirectallowlist', 'https://www.example.com/', 'local_shopping_cart');

        // Disallowed override → default checkout URL.
        $html = embed_views::render_badge(['checkouturl' => 'https://evil.example.net/']);
        $this->assertStringContainsString('sc-embed-badge', $html);
        $this->assertStringContainsString('/local/shopping_cart/checkout.php', $html);
        $this->assertStringNotContainsString('evil.example.net', $html);

        // Allowed override → used as the badge target, breaking out of the iframe.
        $html = embed_views::render_badge(['checkouturl' => 'https://www.example.com/de/checkout.html']);
        $this->assertStringContainsString('https://www.example.com/de/checkout.html', $html);
        $this->assertStringContainsString('target="_top"', $html);
    }
}
