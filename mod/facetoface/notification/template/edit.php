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

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/mod/facetoface/lib.php');
require_once($CFG->dirroot . '/mod/facetoface/notification/template/edit_form.php');

// Parameters
$id = optional_param('id', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);

$contextsystem = context_system::instance();

// Check permissions.
admin_externalpage_setup('modfacetofacetemplates');

$redirectto = new moodle_url('/mod/facetoface/notification/template/index.php', array('page' => $page));

// Setup editors.
$editoroptions = array(
    'noclean'  => false,
    'maxfiles' => 0, // Files were never working here.
    'context'  => $contextsystem,
);

if ($id == 0) {
    $template = new stdClass();
    $template->id = 0;
    $template->body = '';
    $template->managerprefix = '';
    $template->status = '1';

} else {
    $template = $DB->get_record('facetoface_notification_tpl', array('id' => $id));
    if (!$template) {
        print_error('error:notificationtemplatecouldnotbefound', 'facetoface');
    }
}
$template->bodyformat = FORMAT_HTML;
$template->managerprefixformat = FORMAT_HTML;
$template->page = $page;
$template = file_prepare_standard_editor($template, 'body', $editoroptions, $contextsystem, null, null, $id);
$template = file_prepare_standard_editor($template, 'managerprefix', $editoroptions, $contextsystem, null, null, $id);

// Load data.
$form = new mod_facetoface_notification_template_form(null, compact('id', 'editoroptions'));
$form->set_data($template);

// Process data.
if ($form->is_cancelled()) {
    redirect($redirectto);

} else if ($data = $form->get_data()) {
    unset($data->page);

    $data = file_postupdate_standard_editor($data, 'body', $editoroptions, $contextsystem, 'mod_facetoface', null, null);
    $data = file_postupdate_standard_editor($data, 'managerprefix', $editoroptions, $contextsystem, 'mod_facetoface', null, null);

    if ($data->id) {
        $DB->update_record('facetoface_notification_tpl', $data);
    } else {
        $data->id = $DB->insert_record('facetoface_notification_tpl', $data);
    }

    totara_set_notification(get_string('notificationtemplatesaved', 'facetoface'), $redirectto, array('class' => 'notifysuccess'));
}

$url = new moodle_url('/admin/settings.php', array('section' => 'modsettingfacetoface'));

if ($id) {
    $heading = get_string('editnotificationtemplate', 'facetoface');
} else {
    $heading = get_string('addnotificationtemplate', 'facetoface');
}

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);

$form->display();

echo $OUTPUT->footer();
