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
 * @author Darko Miletic
 * @package totara
 * @subpackage cron
 */

define('CLI_SCRIPT', true);

require_once (dirname(__FILE__).'/../config.php'    );
require_once (dirname(__FILE__).'/cron_procfile.php');

if (!empty($_SERVER['GATEWAY_INTERFACE'])){
    error_log("should not be called from web server!");
    return;
}


$limit = cron_get_max_time();
//No time limits
if ($limit == 0) {
    echo 'No cron time limit!'.PHP_EOL;
    return;
}

$procfile = new cron_process_file(true);
if (!$procfile->already_running()) {
    echo 'Cron is not running.'.PHP_EOL;
    return;
}

//it is running
if ($procfile->running_time() <= $limit) {
    echo 'Cron is running within time limits.'.PHP_EOL;
    return;
}

//Should we notify the admin?
//Only one time per day
$sentext  = 'file';
$prefix   = 'totara_';
$dir      = $CFG->dataroot . DIRECTORY_SEPARATOR;
$sentfile = $dir . $prefix . date('dmY.') . $sentext;
$strmgr = get_string_manager();

if (isset($CFG->cron_max_time_mail_notify)
    && ((bool)$CFG->cron_max_time_mail_notify)
    && !file_exists($sentfile)) {

    //cleanup any previous sent files to keep things tidy
    $files = scandir($dir);
    if (is_array($files)) {
        foreach ($files as $file) {
            $filename = $dir . $file;
            if ((stripos($file,$prefix) === 0) &&
                is_file($filename) &&
                ($sentext == pathinfo($file, PATHINFO_EXTENSION))) {
                unlink($filename);
            }
        }
    }

    $admin = get_admin();

    if (isset($CFG->cron_max_time_kill) && ((bool)$CFG->cron_max_time_kill)) {
        $send_kill_mail = true;
    } else {
        $mail_sent = email_to_user($admin,
            $admin,
            $strmgr->get_string('cron_max_time_mail_notify_title','admin', null, $admin->lang),
            $strmgr->get_string('cron_max_time_mail_notify_msg','admin', null, $admin->lang));
    }
}

//Should we kill the process?
if (isset($CFG->cron_max_time_kill) && ((bool)$CFG->cron_max_time_kill)) {
    $pid = $procfile->pid();
    $result = $procfile->kill();
    if ($result) {
        $mail_title = $strmgr->get_string('cron_kill_mail_notify_title','admin', null, $admin->lang);
        $mail_msg = $strmgr->get_string('cron_kill_mail_notify_msg','admin', null, $admin->lang);

        echo "We killed existing cron process! PID:\t{$pid}".PHP_EOL;
    } else {
        $mail_title = $strmgr->get_string('cron_kill_mail_fail_notify_title','admin', null, $admin->lang);
        $mail_msg = $strmgr->get_string('cron_kill_mail_fail_notify_msg','admin', null, $admin->lang);

        echo "Failed to kill cron process! PID:\t{$pid}".PHP_EOL;
    }

    if (!empty($send_kill_mail)) {
        $mail_sent = email_to_user($admin,
            $admin,
            $mail_title,
            $mail_msg);
    }
}

if (!empty($mail_sent)) {
    $result = file_put_contents($sentfile, 'sent');
    if (!$result) {
        echo 'Unable to create sent file!'.PHP_EOL;
    }
}
