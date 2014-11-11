<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2014 onwards Totara Learning Solutions LTD
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
 * @author Petr Skoda <petr.skoda@totaralms.com>
 * @package totara_facetoface
 */

require_once(dirname(__FILE__).'/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$page = optional_param('page', 0, PARAM_INT);

$contextsystem = context_system::instance();
admin_externalpage_setup('modfacetofacecustomfields');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('customfieldsheading', 'facetoface'));

$str_edit = get_string('edit', 'moodle');
$str_remove = get_string('delete', 'moodle');

$columns = array('name', 'shortname', 'type', 'options');
$headers = array(get_string('name'), get_string('shortname'), get_string('setting:type', 'facetoface'), get_string('options', 'facetoface'));

$table = new flexible_table('facetoface_notification_customfields');
$table->define_baseurl($CFG->wwwroot . '/mod/facetoface/customfields.php');
$table->define_columns($columns);
$table->define_headers($headers);
$table->set_attribute('class', 'generalbox mod-facetoface-customfield-list');
$table->sortable(true, 'name');
$table->no_sorting('options');
$table->setup();

if ($sort = $table->get_sql_sort()) {
    $sort = ' ORDER BY ' . $sort;
}

$sql = 'SELECT * FROM {facetoface_session_field}';

$perpage = 25;

$totalcount = $DB->count_records('facetoface_session_field');

$table->initialbars($totalcount > $perpage);
$table->pagesize($perpage, $totalcount);

$fields = $DB->get_records_sql($sql.$sort, array(), $table->get_page_start(), $table->get_page_size());

$options = array(CUSTOMFIELD_TYPE_TEXT        => get_string('field:text', 'facetoface'),
                 CUSTOMFIELD_TYPE_SELECT      => get_string('field:select', 'facetoface'),
                 CUSTOMFIELD_TYPE_MULTISELECT => get_string('field:multiselect', 'facetoface'),
);

foreach ($fields as $field) {
    $row = array();
    $buttons = array();

    $row[] = $field->name;
    $row[] = $field->shortname;

    $row[] = $options[$field->type];

    $buttons[] = $OUTPUT->action_icon(new moodle_url('/mod/facetoface/customfield.php', array('id' => $field->id, 'page' => $page)), new pix_icon('t/edit', $str_edit));
    $buttons[] = $OUTPUT->action_icon(new moodle_url('/mod/facetoface/customfield.php', array('id' => $field->id, 'd' => 1, 'sesskey' => sesskey())), new pix_icon('t/delete', $str_remove));

    $row[] = implode($buttons, '');

    $table->add_data($row);
}

$table->finish_html();

$addurl = new moodle_url('/mod/facetoface/customfield.php', array('id' => 0));

echo $OUTPUT->container_start('buttons');
echo $OUTPUT->single_button($addurl, get_string('addnewfieldlink', 'facetoface'), 'get');
echo $OUTPUT->container_end();

echo $OUTPUT->footer();
