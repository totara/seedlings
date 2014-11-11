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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage totara_hierarchy
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content_hierarchy.class.php');

require_once($CFG->dirroot.'/totara/hierarchy/prefix/competency/lib.php');
require_once($CFG->dirroot.'/totara/core/js/lib/setup.php');


///
/// Setup / loading data
///

// Template id
$id = required_param('templateid', PARAM_INT);

// Parent competency
$parentid = optional_param('parentid', 0, PARAM_INT);

// No javascript parameters
$nojs = optional_param('nojs', false, PARAM_BOOL);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$s = optional_param('s', '', PARAM_TEXT);

// string of params needed in non-js url strings
$urlparams = array('templateid' => $id, 'nojs' => $nojs, 'returnurl' => urlencode($returnurl), 's' => $s);;

// Setup page
admin_externalpage_setup('competencymanage', '', array(), '/totara/hierarchy/prefix/competency/template/assign_competency.php');

// Setup hierarchy object
$hierarchy = new competency();

// Load template
if (!$template = $hierarchy->get_template($id)) {
    print_error('incorrecttemplateid', 'totara_hierarchy');
}

// Load competencies to display
if (!$competenciesintemplate = $hierarchy->get_assigned_to_template($id)) {
    $competenciesintemplate = array();
}

///
/// Display page
///

if (!$nojs) {
    // Load dialog content generator
    $dialog = new totara_dialog_content_hierarchy_multi('competency', $template->frameworkid);

    // Load items to display
    $dialog->load_items($parentid);

    // Set disabled items
    $dialog->selected_items = $competenciesintemplate;

    // Set title
    $dialog->selected_title = 'selectedcompetencies';

    // Disable framework picker
    $dialog->disable_picker = true;

    // Display
    echo $dialog->generate_markup();

} else {
    // non JS version of page
    // Check permissions
    $sitecontext = context_system::instance();
    require_capability('totara/hierarchy:updatecompetencytemplate', $sitecontext);

    // Load framework
    if (!$framework = $hierarchy->get_framework($template->frameworkid)) {
        print_error('competencyframeworknotfound', 'totara_hierarchy');
    }
    $competencies = $hierarchy->get_items_by_parent($parentid);

    echo $OUTPUT->header();
    $out = html_writer::tag('h2', get_string('assigncompetency', 'totara_hierarchy'));
    $link = html_writer::link($returnurl, get_string('cancelwithoutassigning','totara_hierarchy'));
    $out .= html_writer::tag('p', $link);

    $out .= html_writer::start_tag('div', array('id' => 'nojsinstructions'));
    $out .= build_nojs_breadcrumbs($hierarchy,
        $parentid,
        '/totara/hierarchy/prefix/competency/template/find_competency.php',
        array(
            'templateid' => $id,
            'returnurl' => $returnurl,
            's' => $s,
            'nojs' => $nojs,
        ),
        false
    );
    $out .= html_writer::tag('p', get_string('clicktoassign', 'totara_hierarchy') . ' ' . get_string('clicktoviewchildren', 'totara_hierarchy'));
    $out .= html_writer::end_tag('div');

    $out .= html_writer::start_tag('div', array('class' => 'nojsselect'));
    $out .=build_nojs_treeview(
        $competencies,
        get_string('nochildcompetenciesfound', 'totara_hierarchy'),
        '/totara/hierarchy/prefix/competency/template/save_competency.php',
        array(
            's' => $s,
            'returnurl' => $returnurl,
            'nojs' => 1,
            'templateid' => $id,
        ),
        '/totara/hierarchy/prefix/competency/template/find_competency.php',
        $urlparams,
        $hierarchy->get_all_parents(),
        $competenciesintemplate
    );
    $out .= html_writer::end_tag('div');
    echo $out;
    echo $OUTPUT->footer();
}
