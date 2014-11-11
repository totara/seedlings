<?php
/**
 * Moodle - Modular Object-Oriented Dynamic Learning Environment
 *          http://moodle.org
 * Copyright (C) 1999 onwards Martin Dougiamas  http://dougiamas.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
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
 * @package    moodle
 * @subpackage local
 * @author     Jonathan Newman <jonathan.newman@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  Catalyst IT Limited
 *
 * this file should be used for all tao-specific methods
 * and will be included automatically in setup.php along
 * with other core libraries.
 *
 */
require_once('../config.php');


//Adjust some php variables to the execution of this script
@ini_set("max_execution_time","3000");
if (empty($CFG->extramemorylimit)) {
    raise_memory_limit('128M');
} else {
    raise_memory_limit($CFG->extramemorylimit);
}

print "Bulk course install<br><br>\n\n";
bulk_course_restore();

function bulk_course_restore() {

    global $db, $CFG;
    $olddebug = $db->debug;
    $db->debug = $CFG->debug;

    // silent course restores:
    require_once($CFG->dirroot.'/course/lib.php');
    require_once($CFG->dirroot.'/backup/lib.php');
    require_once($CFG->dirroot.'/backup/restorelib.php');

    $zips = get_course_backups($CFG->dirroot.'/local/defaultcoursebackups');

    foreach($zips as $zip) {
        if(file_exists($zip['filename'])) {
            $course = new StdClass;
            $course->fullname = $zip['fullname'];
            $course->shortname = $zip['shortname'];
            $course->category = 1; // Miscellaneous
            $course->enablecompletion = 1; // course completion on
            print "Trying to create course \"{$zip['fullname']}\" ({$zip['shortname']}) ID={$zip['courseid']}<br>\n";
            if ($newcourse = create_course($course)) {
                import_backup_file_silently($zip['filename'], $newcourse->id, true, true, array(), RESTORETO_NEW_COURSE);
            }
        }
    }



    rebuild_course_cache(SITEID);

    return true;

}



/**
 * Given a directory, returns an array of course zip files which match
 * the format [shortname]===[fullname]===[courseid].zip
 * 
 * Components are parse and returned as associative array. If no matching
 * zip files found, returns an empty array
 *
 * @param string $backupdir Directory to search
 * @return array Associative array of filename, shortname, fullname, courseid 
**/
function get_course_backups($backupdir) {

    $ret = array();
    if (is_dir($backupdir)) {
        if ($dh = opendir($backupdir)) {
            while (($file = readdir($dh)) !== false) {
                // exclude directories
                if (is_dir($file)) {
                    continue;
                }
                // not a zip
                if (substr($file, -4) != '.zip') {
                    continue;
                }

                // remove .zip extension
                $filename = substr(urldecode($file), 0, -4);

                // split name
                list($shortname, $fullname, $courseid) = explode("===", $filename);

                // filename isn't in expected format of:
                // [shortname]===[fullname]===[courseid].zip
                if(!$shortname || !$fullname || !$courseid) {
                    continue;
                }
                $row = array();
                $row['filename'] = $backupdir."/".$file;
                $row['shortname'] = $shortname;
                $row['fullname'] = $fullname;
                $row['courseid'] = (int) $courseid;
                $ret[] = $row;
            }
            closedir($dh);
        }
    }

    // Sort by course ID
    foreach($ret as $key => $row) {
        $sortby[$key] = $row['courseid'];
    }
    array_multisort($sortby, SORT_ASC, $ret);

    return $ret;

}


