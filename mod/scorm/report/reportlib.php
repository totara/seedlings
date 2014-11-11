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
 * Returns an array of reports to which are currently readable.
 * @package    mod_scorm
 * @author     Ankit Kumar Agarwal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/* Generates and returns list of available Scorm report sub-plugins
 *
 * @param context context level to check caps against
 * @return array list of valid reports present
 */
function scorm_report_list($context) {
    global $CFG;
    static $reportlist;
    if (!empty($reportlist)) {
        return $reportlist;
    }
    $installed = core_component::get_plugin_list('scormreport');
    foreach ($installed as $reportname => $notused) {
        $pluginfile = $CFG->dirroot.'/mod/scorm/report/'.$reportname.'/report.php';
        if (is_readable($pluginfile)) {
            include_once($pluginfile);
            $reportclassname = "scorm_{$reportname}_report";
            if (class_exists($reportclassname)) {
                $report = new $reportclassname();

                if ($report->canview($context)) {
                    $reportlist[] = $reportname;
                }
            }
        }
    }
    return $reportlist;
}
/**
 * Returns The maximum numbers of Questions associated with an Scorm Pack
 *
 * @param int Scorm ID
 * @return int an integer representing the question count
 */
function get_scorm_question_count($scormid) {
    global $DB;
    $count = 0;
    $params = array();
    $select = "scormid = ? AND ";
    $select .= $DB->sql_like("element", "?", false);
    $params[] = $scormid;
    $params[] = "cmi.interactions_%.id";
    $rs = $DB->get_recordset_select("scorm_scoes_track", $select, $params, 'element');
    $keywords = array("cmi.interactions_", ".id");
    if ($rs->valid()) {
        foreach ($rs as $record) {
            $num = trim(str_ireplace($keywords, '', $record->element));
            if (is_numeric($num) && $num > $count) {
                $count = $num;
            }
        }
        // Done as interactions start at 0 (do only if we have something to report).
        $count++;
    }
    $rs->close(); // closing recordset
    return $count;
}

/**
 * Returns The maximum numbers of Questions associated with a SCO
 *
 * @param int Scorm ID
 * @return array An array of integers representing the question count for each SCO
 */
function get_scorm_sco_question_count($scormid) {
    global $DB;

    // Get a list of interactions for the SCORM but exclude the 'objectives' ones.
    $select = "SELECT id, scoid, element
                FROM {scorm_scoes_track}
                WHERE scormid = ?
                AND " . $DB->sql_like('element', '?', false, true) . "
                AND " . $DB->sql_like('element', '?', false, true, true);
    $params = array($scormid, "cmi.interactions\_%.id", "cmi.interactions\_%.objectives\_%.id");
    $rs = $DB->get_records_sql($select, $params);

    // Store a count of the number of interactions for each SCO.
    $count = array();

    foreach ($rs as $record) {
        // The highest interactions number + 1 will give us the number of interactions in each SCO.
        $interactions_num = intval(trim(str_replace(array('cmi.interactions_', '.id'), '', $record->element))) + 1;

        if (isset($count[$record->scoid])) {
            if ($interactions_num > $count[$record->scoid]) {
                $count[$record->scoid] = $interactions_num;
            }
        } else {
            $count[$record->scoid] = $interactions_num;
        }
    }

    return $count;
}