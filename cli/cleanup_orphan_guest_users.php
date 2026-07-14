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
 * Deletes orphaned guest checkout users.
 *
 * A guest checkout user is "orphaned" when its mdl_user row was created by
 * {@see \local_shopping_cart\local\guestcheckout::create_guest_user()} but the matching
 * local_shopping_cart_guestusers tracking row was never written (e.g. the insert threw because the
 * table did not exist). Such users were never logged in and no cleanup path can ever reach them,
 * because every cleanup gate checks the guestusers table. This script removes exactly those rows.
 *
 * Dry-run by default; pass --execute to actually delete.
 *
 * @package    local_shopping_cart
 * @copyright  2024 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/user/lib.php');

[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'execute' => false,
        'minage' => 3600,
        'limit' => 0,
    ],
    [
        'h' => 'help',
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    echo "Delete orphaned guest checkout users (mdl_user rows with no guestusers tracking row).

By default this only reports what it would delete. Pass --execute to delete.

Options:
  -h, --help       Print this help.
      --execute    Actually delete the users (default is a dry run).
      --minage=N   Only consider users created more than N seconds ago (default 3600).
      --limit=N    Process at most N users (0 = no limit, default).

Example:
  php cli/cleanup_orphan_guest_users.php
  php cli/cleanup_orphan_guest_users.php --execute
";
    exit(0);
}

global $DB, $CFG;

// Safety guard: never run when the tracking table is missing, otherwise the NOT EXISTS below would
// match every guest user and we would mass-delete legitimate accounts. Run the plugin upgrade first.
if (!$DB->get_manager()->table_exists('local_shopping_cart_guestusers')) {
    cli_error('local_shopping_cart_guestusers does not exist. Run the plugin upgrade first, then re-run this script.');
}

$minage = (int) $options['minage'];
$cutoff = time() - $minage;
$execute = !empty($options['execute']);

// Orphans: temporary guest_checkout_ users, on this host, never logged in, older than the cutoff,
// and without a guestusers tracking row. Converted guests are renamed to checkout_* so never match.
$likeuname = $DB->sql_like('u.username', ':uname', false);
$sql = "SELECT u.id
          FROM {user} u
         WHERE $likeuname
           AND u.deleted = 0
           AND u.auth = :auth
           AND u.mnethostid = :localmnet
           AND u.lastlogin = 0
           AND u.lastaccess = 0
           AND u.timecreated < :cutoff
           AND NOT EXISTS (
                   SELECT 1
                     FROM {local_shopping_cart_guestusers} g
                    WHERE g.userid = u.id
               )
      ORDER BY u.id";
$params = [
    'uname' => $DB->sql_like_escape('guest_checkout_') . '%',
    'auth' => 'manual',
    'localmnet' => $CFG->mnet_localhost_id,
    'cutoff' => $cutoff,
];

$ids = $DB->get_fieldset_sql($sql, $params);
if (!empty($options['limit'])) {
    $ids = array_slice($ids, 0, (int) $options['limit']);
}

$count = count($ids);

if (!$execute) {
    cli_writeln("[DRY RUN] {$count} orphan guest checkout user(s) match (older than {$minage}s, no tracking row, "
        . "never logged in).");
    cli_writeln('Re-run with --execute to delete them.');
    exit(0);
}

$deleted = 0;
foreach ($ids as $id) {
    $user = $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
    // The delete_user() call refuses guests/site admins itself; this guard makes the intent explicit.
    if (is_siteadmin($user)) {
        continue;
    }
    delete_user($user);
    $deleted++;
}

cli_writeln("Deleted {$deleted} orphan guest checkout user(s).");
exit(0);
