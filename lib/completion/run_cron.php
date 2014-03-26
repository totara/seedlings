<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010 onwards Totara Learning Solutions LTD
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @package totara
 * @subpackage completion
 */

/**
 * Script to individually run the completion cron
 */
define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->libdir.'/clilib.php');      // cli only functions
require_once($CFG->dirroot.'/completion/cron.php');
require_once($CFG->dirroot . '/admin/cron_lockfile.php');

/// Check cli options
list($options, $unrecognized) = cli_get_params(array('help'=>false),
                                               array('h'=>'help'));

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help =
"Execute the completion cron.

Options:
-h, --help            Print out this help

Example:
\$sudo -u www-data /usr/bin/php completion/run_cron.php
";

    echo $help;
    die;
}


/// Check maintenance and upgrade
if (CLI_MAINTENANCE) {
    echo "CLI maintenance mode active, completion cron execution suspended.\n";
    exit(1);
}

if (moodle_needs_upgrading()) {
    echo "Moodle upgrade pending, completion cron execution suspended.\n";
    exit(1);
}


/// Completion cron lock
$ccronlock = new cron_lockfile($CFG->dirroot . '/completion/cron.php');
if (!$ccronlock->locked()) {
    echo 'Completion cron already being executed. Quitting.' . PHP_EOL;
    exit(1);
}


/// Main cron lock
$mcronlock = new cron_lockfile($CFG->libdir . '/cronlib.php');
if (!$mcronlock->locked()) {
    echo 'Main cron already being executed. Quitting.' . PHP_EOL;
    exit(1);
}


/// Execute the completion cron
completion_cron();
