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

require_once(dirname(__FILE__).'/cron_lockfile.php');


/**
 *
 * Get's cron max. execution time value if it exists in platform
 * @return int - value is in seconds
 */
function cron_get_max_time() {
    global $CFG;
    $limit = 0; //unlimited execution
    if (isset($CFG->cron_max_time)) {
        $limit = (int)$CFG->cron_max_time * 3600;
    }

    return $limit;
}

/**
 *
 * Checks if process is running given the process id
 * Supports Windows and Linux
 * For this function to work following is needed:
 * Windows:
 *  One of these two is a must
 *   - win32ps extension installed - however this is only available for
 *     PHP older than 5.3 ( it can still be compiled though )
 *   - shell command execution functioning - this is for cases where you either
 *      use PHP 5.3.x or can not modify PHP setup for whatever reason
 *      (command line utility tasklist.exe must be present)
 * Linux:
 *   One of these two is a must
 *   - pcntl extension installed - this is present in almost all distributions
 *     and versions of PHP
 *   - shell command execution functioning - this is for cases where you can
 *     not modify PHP setup for whatever reason (command line utility ps must be present)
 * @param int $pid
 * @return bool
 */
function cron_is_process_running($pid) {
    $result = false;
    if (empty($pid)) {
        return $result;
    }
    $is_windows = (stristr(PHP_OS, 'win') && !stristr(PHP_OS, 'darwin'));
    $cpid = escapeshellarg($pid);
    $res = null;
    if ($is_windows) {
        //Do we have win32ps extension installed?
        if (function_exists('win32_ps_stat_proc')) {
            $res    = win32_ps_stat_proc($pid);
        } else {
            //otherwise just call tasklist
            $res    = exec("tasklist.exe /FI \"PID eq {$cpid}\" /FO CSV /NH");
        }
        $result = !empty($res);
    } else {
        //do we have pcntl extension installed
        if (function_exists('pcntl_getpriority')) {
            $res    = pcntl_getpriority($pid);
            $result = ($res !== false);
        } else {
            //otherwise just call ps
            $res    = exec("ps -p {$cpid} -o comm=");
            $result = !empty($res);
        }
    }

    return $result;
}

/**
 *
 * Kill process with specified process id
 * Works on Linux and Windows
 * For this function to work following is needed:
 * Windows:
 *   - shell command execution functioning
 *      (command line utility taskkill.exe must be present -
 *       it is a part of standard windows install)
 * Linux:
 *   One of these two is a must
 *   - posix extension installed - this is present in almost all distributions
 *     and versions of PHP
 *   - shell command execution functioning - this is for cases where you can
 *     not modify PHP setup for whatever reason (command line utility kill must be present)
 *
 * @param int $pid - id of the running process
 * @param bool $force - should we force process termination - default is true
 * @return bool
 */
function cron_killprocess($pid, $force=true) {
    $result = false;
    if (empty($pid)) {
        return $result;
    }

    $is_windows = (stristr(PHP_OS, 'win') && !stristr(PHP_OS, 'darwin'));
    $cpid = escapeshellarg($pid);

    if ($is_windows) {
        $signal = $force ? '/F' : '';
        $res = exec("taskkill.exe /PID {$cpid} {$signal}");
        $result = !empty($res);
    } else {
        if (function_exists('posix_kill')) {
            $result = posix_kill($pid, $force ? 9 : 15);
        } else {
            $signal = $force ? 'KILL' : 'TERM';
            $res = exec("kill -s {$signal} {$cpid}");
            $result = empty($res);
        }
    }

    return $result;
}


class cron_process_file {
    /**
     *
     * Location of the process file
     * @var string
     */
    private $procfile       = null ;
    private $alreadyrunning = false;
    private $alreadyexists  = false;
    private $pid            = null ;
    private $file_pid       = null ;
    private $lastmodtime    = null ;

    private function _check($file) {
        global $CFG;
        $result = false;
        $this->alreadyexists = file_exists($file);
        if ($this->alreadyexists) {
            $this->lastmodtime = filemtime($file);
            $this->file_pid = (int)file_get_contents($file);
            $this->procfile = $file;
            $result = cron_is_process_running($this->file_pid);
            $rpt = new cron_lockfile($CFG->dirroot. '/admin/cron.php');
            //if we locked and process is running - process recycling at work - cron crashed
            //if we locked and process is not running - cron crashed
            //if we did not locked and process is running - it is already running
            //if we did not locked and process is not running - process file was not updated (?!!)
            $result = $result && $rpt->locked();
            unset($rpt);
        }

        $this->alreadyrunning = $result;
        return $result;
    }

    public function __construct($justcheck=false, $procfile=null) {
        global $CFG;
        if (empty($procfile)) {
            $procfile = $CFG->dataroot . DIRECTORY_SEPARATOR . 'cron.pid';
        }
        if (!$this->_check($procfile) && !$justcheck) {
            $pid = getmypid();
            $result = file_put_contents($procfile, $pid);
            if (!empty($result)) {
                $this->pid = $pid;
                $this->file_pid = $pid;
                $this->procfile = $procfile;
                $this->lastmodtime = filemtime($procfile);
            }
        }
    }

    public function __destruct() {
        if ($this->pid) {
            $this->reset();
        }
    }

    private function reset() {
        unlink($this->procfile);
        $this->procfile       = null ;
        $this->pid            = null ;
        $this->file_pid       = null ;
        $this->alreadyexists  = false;
        $this->alreadyrunning = false;
        $this->lastmodtime    = null ;
    }

    /**
     *
     * Forces process termination
     * @return bool
     */
    public function kill() {
        $result = cron_killprocess($this->pid());
        if ($result) {
            $this->reset();
        }
        return $result;
    }

    /**
     *
     * Did we created procfile ok?
     * @return bool
     */
    public function ok() {
        return ($this->pid !== null);
    }

    /**
     *
     * Did process file already existed?
     * @return bool
     */
    public function existed() {
        return $this->alreadyexists;
    }

    /**
     *
     * Is the process already running
     * @return bool
     */
    public function already_running(){
        return $this->alreadyrunning;
    }

    /**
     *
     * Time passed between last modification time
     * and now
     */
    public function running_time() {
        return $this->lastmodtime ? (time() - $this->lastmodtime) : $this->lastmodtime;
    }

    public function pid() {
        return $this->pid ? $this->pid : $this->file_pid;
    }
}

/**
 *
 * Gets the nicely worded cron status
 * @return string
 */
function cron_status($proc = null) {
    $procfile = $proc;
    if ($procfile == null) {
        $procfile = new cron_process_file(true);
    }
    $status = 'cron_execution_stopped';
    if ($procfile->already_running()) {
        $status = 'cron_execution_running';
    } else if ($procfile->existed()) {
        $status = 'cron_execution_crashed';
    }
    $result = get_string($status,'admin');
    return $result;
}



