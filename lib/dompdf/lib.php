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
 * @subpackage dompdf
 */

/**
 * Dompdf wrapper class for moodle
 */
require_once($CFG->libdir.'/dompdf/moodle_config.php');
require_once($CFG->libdir.'/dompdf/dompdf_config.inc.php');

class totara_dompdf extends dompdf {

    /**
     * Get content of remote file with session support for local files
     *
     * @staticvar string $session_cookie_data
     * @param string $file
     * @return string | null
     */
    public static function file_get_contents($file) {
        global $CFG;
        static $session_cookie_data = '';
        if ($session_cookie_data == '') {
            $session_cookie_data = session_name().'='.session_id();
        }

        if (strpos($file, $CFG->wwwroot.'/pluginfile.php') !== false || strpos($file, $CFG->wwwroot.'/draftfile.php') !== false) {
            \core\session\manager::write_close();

            $curlhandler = new curl();
            $logfile = fopen('/tmp/curl.txt', 'w+');
            $options = array(
                'CURLOPT_BINARYTRANSFER' => true,
                'CURLOPT_FAILONERROR' => true,
                'CURLOPT_RETURNTRANSFER' => true,
                'CURLOPT_CONNECTTIMEOUT' => 5,
                'CURLOPT_TIMEOUT' => 3,
                'CURLOPT_SSL_VERIFYPEER' => false,
                'CURLOPT_SSL_VERIFYHOST' => false,
                'CURLOPT_USERAGENT' => 'TCPDF',
                'CURLOPT_STDERR' => $logfile,
                'CURLOPT_VERBOSE' => 1,
                'CURLOPT_COOKIE' => $session_cookie_data);
            if ((ini_get('open_basedir') == '') && (ini_get('safe_mode') == 'Off')) {
                $options['CURLOPT_FOLLOWLOCATION'] = true;
            }

            $imgdata = $curlhandler->get($file, array(), $options);
            fclose($logfile);

            if (!$imgdata) {
                return;
            }
            return $imgdata;
        } else {
            return file_get_contents($file);
        }
    }
}
