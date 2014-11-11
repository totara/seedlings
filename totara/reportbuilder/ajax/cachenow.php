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
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->dirroot.'/totara/reportbuilder/lib.php');
require_once($CFG->dirroot.'/totara/reportbuilder/dialogs/dialog_content_cachenow.class.php');

/**
 * Start report generation using process fork when it possible
 *
 * @param int $reportid Report id
 */
function forkreportcache($reportid) {
    $child = -1;
    if (function_exists('pcntl_fork') && function_exists('posix_setsid')) {
        $child = pcntl_fork();
    }
    $message = get_string('cachegenstarted', 'totara_reportbuilder', userdate(time()));
    ob_start();
    switch($child) {
        case -1:
            // No multi-process support/fork fail. Do everything in one process:
            // Display results and flush buffers + generate cache
            cachenow_showresult(true, $message);
            reportbuilder_generate_cache($reportid);
            reportbuilder_fix_schedule($reportid);
            break;
        case 0:
            cachenow_showresult(true, $message);
            break;
        default:
            posix_setsid();
            reportbuilder_generate_cache($reportid);
            reportbuilder_fix_schedule($reportid);
            break;
    }
}

/**
 * Force browser to close connection without interrupting script execution
 */
function close_connection() {
    ignore_user_abort(true);
    $size = ob_get_length();
    header("Content-Length: $size");
    header("Content-Encoding: none\r\n");
    header('Connection: close');
    ob_flush();

    if (ob_get_level()) {
        // Flush all write buffers
        flush();
    }
    if (ob_get_level()) {
        //Bullet-proof clean output buffer (not sure if it will ever run)
        ob_end_clean();
    }
    if (session_id()) {
        session_write_close();
    }
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
}

/**
 * Display result of cache generation
 * @staticvar boolean $display
 * @param bool $status
 * @param string $message
 */
function cachenow_showresult($status, $message) {
    static $display = false;
    if (!$display && $message != '') {
        $display = true;
        $dialog = new totara_dialog_content_cachenow();
        $dialog->set_status($status);
        $dialog->set_message($message);

        echo $dialog->generate_markup();
        close_connection();
    }
}

$context = context_system::instance();
require_login();
require_capability('totara/reportbuilder:managereports', context_system::instance());
$PAGE->set_context($context);

$reportid = required_param('reportid', PARAM_INT);
$report = new reportbuilder($reportid);

// Check that report is cached
$success = false;
$message = '';

set_time_limit(REPORT_CACHING_TIMEOUT);
raise_memory_limit(MEMORY_EXTRA);

if ($report->cache) {
    $success = true;
    // Check if caching is already started
    $start = isset($report->cacheschedule->genstart) ? $report->cacheschedule->genstart : 0;
    if ($start + REPORT_CACHING_TIMEOUT < time()) {
        // Cache
        forkreportcache($reportid);
    }
    $message = get_string('cachegenstarted', 'totara_reportbuilder', userdate($start));
}

cachenow_showresult($success, $message);