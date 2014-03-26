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
 * @author Ciaran Irvine <ciaran.irvine@totaralms.com>
 * @package tool
 * @subpackage totara_sync
 */
require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');
require_once($CFG->dirroot.'/admin/tool/totara_sync/lib.php');

require_login();

$systemcontext = context_system::instance();
require_capability('tool/totara_sync:manage', $systemcontext);

$pagetitle = get_string('syncexecute', 'tool_totara_sync');
$PAGE->set_context($systemcontext);
$PAGE->set_url('/admin/tool/totara_sync/admin/syncexecute.php');
$PAGE->set_pagelayout('admin');
$PAGE->set_title($pagetitle);
$PAGE->set_heading('');
$execute = optional_param('execute', null, PARAM_BOOL);

echo $OUTPUT->header();

if ($execute) {
    require_sesskey();
    // Increase memory limit
    raise_memory_limit(MEMORY_EXTRA);
    // Stop time outs, this might take a while
    set_time_limit(0);
    // Run the sync
    add_to_log(SITEID, 'totara_sync', 'Execute Sync', 'admin/syncexecute.php');
    $msg = get_string('runsynccronstart', 'tool_totara_sync');
    $msg .= get_string('runsynccronend', 'tool_totara_sync');
    if (!($succeed = tool_totara_sync_cron(true))) {
        $msg .= ' ' . get_string('runsynccronendwithproblem', 'tool_totara_sync');
    }
    $url = new moodle_url('/admin/tool/totara_sync/admin/synclog.php');
    $msg .= html_writer::empty_tag('br') . get_string('viewsynclog', 'tool_totara_sync', $url->out());
    echo $succeed ? $OUTPUT->notification($msg, 'notifysuccess') : $OUTPUT->notification($msg, 'notifynotice');
}
    $configured = true;
    //sanity checks
    $fileaccess = get_config('totara_sync', 'fileaccess');
    if ($fileaccess == FILE_ACCESS_DIRECTORY && !$filesdir = get_config('totara_sync', 'filesdir')) {
        $configured = false;
        echo $OUTPUT->notification(get_string('nofilesdir', 'tool_totara_sync'), 'notifyproblem');
    }
    // Check enabled sync element objects
    $elements = totara_sync_get_elements(true);
    if (empty($elements)) {
        $configured = false;
        echo $OUTPUT->notification(get_string('noenabledelements', 'tool_totara_sync'), 'notifyproblem');
    } else {
        $table = new html_table();
        $table->data = array();
        $table->head  = array(get_string('element', 'tool_totara_sync'), get_string('source', 'tool_totara_sync'), get_string('configuresource', 'tool_totara_sync'));
        foreach ($elements as $element) {
            $cells = array();
            $elname = $element->get_name();
            $cells[] = new html_table_cell(get_string('displayname:'.$elname, 'tool_totara_sync'));
            //check a source is enabled
            if (!$sourceclass = get_config('totara_sync', 'source_' . $elname)) {
                $configured = false;
                $url = new moodle_url('/admin/tool/totara_sync/admin/elementsettings.php', array('element' => $elname));
                $link = html_writer::link($url, get_string('sourcenotfound', 'tool_totara_sync'));
                $cells[] = new html_table_cell($link);
                $cells[] = new html_table_cell('');
            } else {
                $source = get_string('displayname:'.$sourceclass, 'tool_totara_sync');
                $cells[] = new html_table_cell($source);
            }
            //check source has configs - note get_config returns an object
            if ($sourceclass) {
                $configs = get_config($sourceclass);
                $props = get_object_vars($configs);
                if(empty($props)) {
                    $configured = false;
                    $url = new moodle_url('/admin/tool/totara_sync/admin/sourcesettings.php', array('element' => $elname, 'source' => $sourceclass));
                    $link = html_writer::link($url, get_string('nosourceconfig', 'tool_totara_sync'));
                    $cells[] = new html_table_cell($link);
                } else {
                    $cells[] = new html_table_cell(get_string('sourceconfigured', 'tool_totara_sync'));
                }
            }
            $row = new html_table_row($cells);
            $table->data[] = $row;
        }
        echo html_writer::table($table);
    }

    if ($configured) {
        echo $OUTPUT->single_button(new moodle_url('/admin/tool/totara_sync/admin/syncexecute.php', array('execute' => 1)), get_string('syncexecute', 'tool_totara_sync'), 'post');
    } else {
        //some problem with configuration
        echo $OUTPUT->notification(get_string('syncnotconfigured', 'tool_totara_sync'), 'notifyproblem');
    }
echo $OUTPUT->footer();

?>
