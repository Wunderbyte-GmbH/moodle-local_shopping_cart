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
 * Guards against stale AMD builds: every export of amd/src must exist in amd/build.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

/**
 * Guards against stale AMD builds.
 *
 * The browser only ever loads amd/build. When a commit changes amd/src but carries a build made
 * from an older source, a template that calls the new export fails with "is not a function" and
 * everything the same footer script initialises after it is dead - on the checkout page that
 * were the delete buttons of the cart (Wunderbyte-GmbH/moodle-local_shopping_cart#204, stale
 * build introduced with GH-2316). Behat did not see it because the export is only called for
 * users with a purchase history. This test needs no browser: it compares the exported names of
 * every source module with the minified module next to it.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_shopping_cart\shopping_cart
 */
final class amd_build_exports_test extends \basic_testcase {
    /**
     * Every module in amd/src has a build and the build contains every export of the source.
     *
     * @return void
     */
    public function test_every_source_export_exists_in_the_build(): void {
        global $CFG;

        $srcdir = $CFG->dirroot . '/local/shopping_cart/amd/src';
        $builddir = $CFG->dirroot . '/local/shopping_cart/amd/build';
        $sources = glob($srcdir . '/*.js');
        $this->assertNotEmpty($sources, 'No AMD sources found in ' . $srcdir);

        $missing = [];
        foreach ($sources as $source) {
            $module = basename($source, '.js');
            $build = $builddir . '/' . $module . '.min.js';
            if (!is_readable($build)) {
                $missing[] = $module . ': build file missing';
                continue;
            }
            $buildjs = file_get_contents($build);
            foreach ($this->get_exports(file_get_contents($source)) as $export) {
                // The minifier keeps export names verbatim (they are the module's public API), so
                // a plain search is enough and does not depend on the minifier's output format.
                if (strpos($buildjs, $export) === false) {
                    $missing[] = $module . '.' . $export;
                }
            }
        }

        $this->assertSame(
            [],
            $missing,
            'amd/build is stale, these exports of amd/src are missing in the build (run grunt amd): '
                . implode(', ', $missing)
        );
    }

    /**
     * Names exported by an ES module source: export const/let/function/async function/class.
     *
     * @param string $js
     * @return string[]
     */
    private function get_exports(string $js): array {
        preg_match_all(
            '/^\s*export\s+(?:const|let|var|async\s+function|function|class)\s+([A-Za-z_$][\w$]*)/m',
            $js,
            $matches
        );
        $exports = $matches[1];
        // Export lists: export { a, b as c }. The exported (outer) name is what the build must carry.
        preg_match_all('/^\s*export\s*\{([^}]*)\}/m', $js, $lists);
        foreach ($lists[1] as $list) {
            foreach (explode(',', $list) as $entry) {
                $parts = preg_split('/\s+as\s+/', trim($entry));
                $name = trim(end($parts));
                if ($name !== '') {
                    $exports[] = $name;
                }
            }
        }
        return array_values(array_unique($exports));
    }
}
