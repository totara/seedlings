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
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage facetoface
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/facetoface/lib.php');

// Face-to-face session ID
$s = required_param('s', PARAM_INT);

// Load data
if (!$session = facetoface_get_session($s)) {
    print_error('error:incorrectcoursemodulesession', 'facetoface');
}
if (!$facetoface = $DB->get_record('facetoface', array('id' => $session->facetoface))) {
    print_error('error:incorrectfacetofaceid', 'facetoface');
}
if (!$course = $DB->get_record('course', array('id' => $facetoface->course))) {
    print_error('error:coursemisconfigured', 'facetoface');
}
if (!$cm = get_coursemodule_from_instance("facetoface", $facetoface->id, $course->id)) {
    print_error('error:incorrectcoursemoduleid', 'facetoface');
}
$context = context_module::instance($cm->id);

// Cap checks
require_login($course, false, $cm);
require_capability('mod/facetoface:addattendees', $context);

// Get import results
if (isset($_SESSION['f2f-bulk-results'][$session->id])) {
   $results = $_SESSION['f2f-bulk-results'][$session->id];
} else {
    print_error('error:noimportresultsfound', 'facetoface');
}

// Legacy Totara HTML ajax, this should be converted to json + AJAX_SCRIPT.
send_headers('text/html; charset=utf-8', false);

$added = $results[0];
$errors = $results[1];
$bulkaddsource = empty($results[2]) ? 'bulkaddsourceuserid' : $results[2];

// Check capability

if ($data = data_submitted()) {
    require_sesskey();

    if (!empty($data->f2f_conflict)) {
        $errors = array();
        $added = array();

        // Prepare params
        $params = array();
        $params['suppressemail']   = false;
        $params['ignoreconflicts'] = true;
        $params['bulkaddsource']   = $bulkaddsource;
        // Do not need the approval, change the status
        $params['approvalreqd'] = 0;
        // If it is a list of user, do not need to notify manager
        $params['ccmanager'] = 0;

        foreach ($data->f2f_conflict as $conflict => $val) {
            $conflict = clean_param($conflict, PARAM_NOTAGS);
            if (!$conflict) {
                continue;
            }

            $result = facetoface_user_import($course, $facetoface, $session, $conflict, $params);
            if ($result['result'] !== true) {
                $errors[] = $result;
            } else {
                $result['result'] = get_string('addedsuccessfully', 'facetoface');
                $added[] = $result;
            }
        }
    }

    $_SESSION['f2f-bulk-results'][$session->id] = array($added, $errors);

    $result_message = facetoface_generate_bulk_result_notice(array($added, $errors));
    $numattendees = facetoface_get_num_attendees($session->id);
    $overbooked = ($numattendees > $session->capacity);
    if ($overbooked) {
        $overbookedmessage = get_string('capacityoverbookedlong', 'facetoface', array('current' => $numattendees, 'maximum' => $session->capacity));
        $result_message .= $OUTPUT->notification($overbookedmessage, 'notifynotice');
    }

    require($CFG->dirroot . '/mod/facetoface/attendees.php');
    die();
}

$url = $CFG->wwwroot . '/mod/facetoface/bulkadd_results.php?s=' . $s . '&onlycontent=1';

// Display results
$result_message = '';
if ($errors) {
    if ($added) {
        $result_message .= get_string('successfullyaddededitedxattendees', 'facetoface', count($added)).'<br>';
    }
    $result_message .= get_string('xerrorsencounteredduringimport', 'facetoface', count($errors));
} else {
    $result_message .= get_string('successfullyaddededitedxattendees', 'facetoface', count($added));
}


echo $result_message;
echo '<br />';
echo '<br />';

// Add 'Select All' Button
echo('
    <script language="javascript">
        function selectAll()
        {
            $(\'input[type="checkbox"]\').prop("checked", true);
        }
        function selectNone()
        {
            $(\'input[type="checkbox"]\').prop("checked", false);
        }
    </script>
');

echo '<form action="'.$url.'" method="post" class="mform">';
echo '<input type="hidden" name="sesskey" value="'.$USER->sesskey.'" />';
echo '<input type="hidden" name="s" value="'.$s.'" />';

// Check for conflicts
$has_conflict = false;
if ($errors) {
    foreach ($errors as $error) {
        if (isset($error['conflict'])) {
            $has_conflict = true;
            break;
        }
    }
}

$table = new html_table();
$table->head = array(get_string($bulkaddsource, 'facetoface'), get_string('name'), get_string('result', 'facetoface'));
$table->align = array('left', 'left', 'left');

if ($has_conflict) {
    // Add 'Select All' Button
    echo '<input type="button" onclick="selectAll()" value="' . get_string('selectall', 'facetoface') . '" />';
    echo '<input type="button" onclick="selectNone()" value="' . get_string('selectnone', 'facetoface') . '" />';
    array_unshift($table->head, '');
    array_unshift($table->align, 'left');
}

foreach (array_merge($errors, $added) as $error) {

    $data = array();

    if ($has_conflict) {
        if (isset($error['conflict'])) {
            $data[] = '<input type="checkbox" name="f2f_conflict['.$error['id'].']" />';
            $can_update = true;
        } else {
            $data[] = '';
        }
    }

    $data[] = $error['id'];
    $data[] = $error['name'];
    $data[] = $error['result'];
    $table->data[] = $data;
}

echo html_writer::table($table);

if ($has_conflict) {
    echo '<p><input type="submit" value="'.get_string('allowselectedschedulingconflicts', 'facetoface').'" /></p>';
}
echo '</form>';
