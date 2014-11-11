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
 * Program management page.
 *
 * @package    totara
 * @subpackage program
 * @author     Yuliya Bozhko <yuliya.bozhko@totaralms.com>
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir . '/coursecatlib.php');
require_once($CFG->dirroot . '/totara/program/lib.php');
require_once($CFG->dirroot . '/totara/cohort/lib.php');

define('MAX_MOVE_CATEGORY', 500);

// Category id.
$id = optional_param('categoryid', 0, PARAM_INT);
// Which page to show.
$page = optional_param('page', 0, PARAM_INT);
// Type of a page, program or certification.
$viewtype = optional_param('viewtype', 'program', PARAM_TEXT);
// How many per page.
$perpage = optional_param('perpage', $CFG->coursesperpage, PARAM_INT);
// Search words.
$search = optional_param('search', '', PARAM_RAW);

// Check if programs or certifications are enabled.
if ($viewtype == 'program') {
    check_program_enabled();
} else {
    check_certification_enabled();
}

if (!$id && !empty($search)) {
    $searchcriteria = array('search' => $search);
} else {
    $searchcriteria = array();
}

// Actions to manage programs.
$hide = optional_param('hide', 0, PARAM_INT);
$show = optional_param('show', 0, PARAM_INT);
$moveup = optional_param('moveup', 0, PARAM_INT);
$movedown = optional_param('movedown', 0, PARAM_INT);
$moveto = optional_param('moveto', 0, PARAM_INT);
$resort = optional_param('resort', 0, PARAM_BOOL);

// Actions to manage categories.
$deletecat = optional_param('deletecat', 0, PARAM_INT);
$hidecat = optional_param('hidecat', 0, PARAM_INT);
$showcat = optional_param('showcat', 0, PARAM_INT);
$movecat = optional_param('movecat', 0, PARAM_INT);
$movetocat = optional_param('movetocat', -1, PARAM_INT);
$moveupcat = optional_param('moveupcat', 0, PARAM_INT);
$movedowncat = optional_param('movedowncat', 0, PARAM_INT);

require_login();

// Retrieve coursecat object
// This will also make sure that category is accessible and create default category if missing
$coursecat = coursecat::get($id);

if ($id) {
    $PAGE->set_category_by_id($id);
    $PAGE->set_url(new moodle_url('/totara/program/manage.php', array('categoryid' => $id, 'viewtype' => $viewtype)));
    // This is sure to be the category context.
    $context = $PAGE->context;
    // Add program breadcrumbs.
    $navname = $viewtype == 'program' ? get_string('programs', 'totara_program') : get_string('certifications', 'totara_certification');
    $PAGE->navbar->add($navname, new moodle_url('/totara/program/index.php', array('viewtype' => $viewtype)));
    $category_breadcrumbs = prog_get_category_breadcrumbs($id, $viewtype);
    foreach ($category_breadcrumbs as $crumb) {
        $PAGE->navbar->add($crumb['name'], $crumb['link']);
    }
    if (!can_edit_in_category($coursecat->id)) {
        redirect(new moodle_url('/totara/program/index.php', array('viewtype' => $viewtype)));
    }
} else {
    $context = context_system::instance();
    $PAGE->set_context($context);
    $PAGE->set_url(new moodle_url('/totara/program/manage.php', array('viewtype' => $viewtype)));
    if (!can_edit_in_category()) {
        redirect(new moodle_url('/totara/program/index.php', array('viewtype' => $viewtype)));
    }
}

$canmanage = has_capability('moodle/category:manage', $context);

// Process any category actions.
if (!empty($deletecat) and confirm_sesskey()) {
    // Delete a category.
    $cattodelete = coursecat::get($deletecat);
    $context = context_coursecat::instance($deletecat);
    require_capability('moodle/category:manage', $context);
    require_capability('moodle/category:manage', get_category_or_system_context($cattodelete->parent));

    $heading = get_string('deletecategory', 'moodle', format_string($cattodelete->name, true, array('context' => $context)));

    require_once($CFG->dirroot.'/course/delete_category_form.php');
    $mform = new delete_category_form(null, $cattodelete);
    if ($mform->is_cancelled()) {
        redirect(new moodle_url('/totara/program/manage.php', array('viewtype' => $viewtype)));
    }

    // Start output.
    echo $OUTPUT->header();
    echo $OUTPUT->heading($heading);

    if ($data = $mform->get_data()) {
        // The form has been submit handle it.
        if ($data->fulldelete == 1 && $cattodelete->can_delete_full()) {
            $cattodeletename = $cattodelete->get_formatted_name();
            list($deletedcourses, $deletedprograms, $deletedcertifs) = $cattodelete->delete_full(true);
            if ($viewtype == 'program') {
                foreach ($deletedprograms as $program) {
                    echo $OUTPUT->notification(get_string('programdeletesuccess', 'totara_program', $program->shortname), 'notifysuccess');
                }
            } else {
                foreach ($deletedcertifs as $certif) {
                    echo $OUTPUT->notification(get_string('certificationdeletesuccess', 'totara_certification', $certif->shortname),
                         'notifysuccess');
                }
            }
            echo $OUTPUT->notification(get_string('coursecategorydeleted', 'moodle', $cattodeletename), 'notifysuccess');
            echo $OUTPUT->continue_button(new moodle_url('/totara/program/manage.php', array('viewtype' => $viewtype)));
        } else if ($data->fulldelete == 0 && $cattodelete->can_move_content_to($data->newparent)) {
            $cattodelete->delete_move($data->newparent, true);
            echo $OUTPUT->continue_button(new moodle_url('/totara/program/manage.php', array('viewtype' => $viewtype)));
        } else {
            // Some error in parameters (user is cheating?).
            $mform->display();
        }
    } else {
        // Display the form.
        $mform->display();
    }
    // Finish output and exit.
    echo $OUTPUT->footer();
    exit();
}

if (!empty($movecat) and ($movetocat >= 0) and confirm_sesskey()) {
    // Move a category to a new parent if required.
    $cattomove = coursecat::get($movecat);
    if ($cattomove->parent != $movetocat) {
        if ($cattomove->can_change_parent($movetocat)) {
            $cattomove->change_parent($movetocat);
        } else {
            print_error('cannotmovecategory');
        }
    }
}

// Hide or show a category.
if ($hidecat and confirm_sesskey()) {
    $cattohide = coursecat::get($hidecat);
    require_capability('moodle/category:manage', get_category_or_system_context($cattohide->parent));
    $cattohide->hide();
} else if ($showcat and confirm_sesskey()) {
    $cattoshow = coursecat::get($showcat);
    require_capability('moodle/category:manage', get_category_or_system_context($cattoshow->parent));
    $cattoshow->show();
}

if ((!empty($moveupcat) or !empty($movedowncat)) and confirm_sesskey()) {
    // Move a category up or down.
    prog_fix_program_sortorder();
    $swapcategory = null;

    if (!empty($moveupcat)) {
        require_capability('moodle/category:manage', context_coursecat::instance($moveupcat));
        if ($movecategory = $DB->get_record('course_categories', array('id' => $moveupcat))) {
            $params = array($movecategory->sortorder, $movecategory->parent);
            if ($swapcategory = $DB->get_records_select('course_categories', "sortorder<? AND parent=?", $params, 'sortorder DESC', '*', 0, 1)) {
                $swapcategory = reset($swapcategory);
            }
        }
    } else {
        require_capability('moodle/category:manage', context_coursecat::instance($movedowncat));
        if ($movecategory = $DB->get_record('course_categories', array('id' => $movedowncat))) {
            $params = array($movecategory->sortorder, $movecategory->parent);
            if ($swapcategory = $DB->get_records_select('course_categories', "sortorder>? AND parent=?", $params, 'sortorder ASC', '*', 0, 1)) {
                $swapcategory = reset($swapcategory);
            }
        }
    }
    if ($swapcategory and $movecategory) {
        $DB->set_field('course_categories', 'sortorder', $swapcategory->sortorder, array('id' => $movecategory->id));
        $DB->set_field('course_categories', 'sortorder', $movecategory->sortorder, array('id' => $swapcategory->id));
        cache_helper::purge_by_event('changesincoursecat');
        add_to_log(SITEID, "category", "move", "editcategory.php?id=$movecategory->id", $movecategory->id);
    }

    // Finally reorder programs.
    prog_fix_program_sortorder();
}

if ($coursecat->id && $canmanage && $resort && confirm_sesskey()) {
    // Resort the category.
    if ($programs = prog_get_programs($coursecat->id, '', 'p.id,p.fullname,p.sortorder', $viewtype)) {
        collatorlib::asort_objects_by_property($programs, 'fullname', collatorlib::SORT_NATURAL);
        $i = 1;
        foreach ($programs as $program) {
            $DB->set_field('prog', 'sortorder', $coursecat->sortorder + $i, array('id' => $program->id));
            $i++;
        }
        // This should not be needed but we do it just to be safe.
        prog_fix_program_sortorder();
    }
}

if (!empty($moveto) && ($data = data_submitted()) && confirm_sesskey()) {
    // Move a specified program to a new category.
    // User must have category update in both cats to perform this.
    require_capability('moodle/category:manage', $context);
    require_capability('moodle/category:manage', context_coursecat::instance($moveto));

    if (!$destcategory = $DB->get_record('course_categories', array('id' => $data->moveto))) {
        print_error('cannotfindcategory', '', '', $data->moveto);
    }

    $programs = array();
    foreach ($data as $key => $value) {
        if (preg_match('/^c\d+$/', $key)) {
            $programid = substr($key, 1);
            array_push($programs, $programid);
            // Check this program's category.
            if ($movingprogram = $DB->get_record('prog', array('id' => $programid))) {
                if ($id && $movingprogram->category != $id ) {
                    print_error('error:programdoesnotbelongtocategory', 'totara_program');
                }
            } else {
                print_error('error:prognotmoved', 'totara_program');
            }
        }
    }
    prog_move_programs($programs, $data->moveto);
}

if ((!empty($hide) or !empty($show)) && confirm_sesskey()) {
    if (empty($CFG->audiencevisibility)) {
        // Hide or show a program.
        if (!empty($hide)) {
            $program = $DB->get_record('prog', array('id' => $hide), '*', MUST_EXIST);
            $visible = 0;
        } else {
            $program = $DB->get_record('prog', array('id' => $show), '*', MUST_EXIST);
            $visible = 1;
        }
        $programcontext = context_program::instance($program->id);
        require_capability('totara/program:visibility', $programcontext);
        // Set the visibility of the program.
        $params = array('id' => $program->id, 'visible' => $visible, 'timemodified' => time());
        $DB->update_record('prog', $params);
        add_to_log($program->id, "program", ($visible ? 'show' : 'hide'), "edit.php?id=$program->id", $program->id);
    }
}

if ((!empty($moveup) or !empty($movedown)) && confirm_sesskey()) {
    // Move a program up or down.
    require_capability('moodle/category:manage', $context);

    // Ensure the program order has continuous ordering.
    prog_fix_program_sortorder();
    $swapprogram = null;

    if (!empty($moveup)) {
        if ($moveprogram = $DB->get_record('prog', array('id' => $moveup))) {
            $swapprogram = $DB->get_record('prog', array('sortorder' => $moveprogram->sortorder - 1));
        }
    } else {
        if ($moveprogram = $DB->get_record('prog', array('id' => $movedown))) {
            $swapprogram = $DB->get_record('prog', array('sortorder' => $moveprogram->sortorder + 1));
        }
    }
    if ($swapprogram && $moveprogram) {
        // Check program's category.
        if ($moveprogram->category != $id) {
            print_error('error:programdoesnotbelongtocategory', 'totara_program');
        }
        $DB->set_field('prog', 'sortorder', $swapprogram->sortorder, array('id' => $moveprogram->id));
        $DB->set_field('prog', 'sortorder', $moveprogram->sortorder, array('id' => $swapprogram->id));
        add_to_log($moveprogram->id, "program", "move", "edit.php?id=$moveprogram->id", $moveprogram->id);
    }
}

// Prepare the standard URL params for this page. We'll need them later.
$urlparams = array('categoryid' => $id);
if ($page) {
    $urlparams['page'] = $page;
}
if ($perpage) {
    $urlparams['perpage'] = $perpage;
}
$urlparams += $searchcriteria;
$urlparams['viewtype'] = $viewtype;

$PAGE->set_pagelayout('coursecategory');
$programrenderer = $PAGE->get_renderer('totara_program');

if (can_edit_in_category()) {
    // Integrate into the admin tree only if the user can edit categories at the top level,
    // otherwise the admin block does not appear to this user, and you get an error.
    require_once($CFG->libdir . '/adminlib.php');
    if ($id) {
        navigation_node::override_active_url(new moodle_url('/totara/program/index.php', array('categoryid' => $id, 'viewtype' => $viewtype)));
    }
    $pagetype = ($viewtype == 'program') ? 'programmgmt' : 'managecertifications';
    admin_externalpage_setup($pagetype, '', $urlparams, $CFG->wwwroot . "/totara/program/manage.php");
    $settingsnode = $PAGE->settingsnav->find_active_node();
    if ($id && $settingsnode) {
        $settingsnode->make_inactive();
        $settingsnode->force_open();
        $PAGE->navbar->add($settingsnode->text, $settingsnode->action);
    }
} else {
    $site = get_site();
    $PAGE->set_title("$site->shortname: $coursecat->name");
    $PAGE->set_heading($site->fullname);
    $PAGE->set_button($programrenderer->program_search_form($viewtype, '', 'navbar'));
}

// Start output.
echo $OUTPUT->header();

if (!empty($searchcriteria)) {
    echo $OUTPUT->heading(new lang_string('searchresults'));
} else if (!$coursecat->id) {
    $catlist = array(0 => get_string('top')) + coursecat::make_categories_list('moodle/category:manage');
    $catlist = count($catlist) < MAX_MOVE_CATEGORY ? $catlist : false;

    // Print out the categories with all the knobs.
    $table = new html_table;
    $table->id = 'coursecategories';
    $table->attributes['class'] = 'admintable generaltable editcourse';
    if ($viewtype == 'program') {
        $strcategory = get_string('programcategories', 'totara_program');
        $strtype = get_string('programs', 'totara_program');
    } else {
        $strcategory = get_string('certifcategories', 'totara_certification');
        $strtype = get_string('certifications', 'totara_certification');
    }

    $headers = array();
    $headers[] = $strcategory;
    $headers[] = $strtype;
    $headers[] = get_string('edit');
    if (is_array($catlist)) {
        $headers[] = get_string('movecategoryto');
    }
    $table->head = $headers;

    $table->colclasses = array(
                    'leftalign name',
                    'centeralign count',
                    'centeralign icons',
                    'leftalign actions'
    );
    $table->data = array();

    print_category_edit($table, $coursecat, $catlist, $viewtype);

    echo html_writer::table($table);
} else {
    // Print the category selector.
    $displaylist = coursecat::make_categories_list();
    $select = new single_select(new moodle_url('/totara/program/manage.php', array('viewtype' => $viewtype)),
                  'categoryid', $displaylist, $coursecat->id, null, 'switchcategory');
    $strcategory = ($viewtype == 'program') ? get_string('programcategories', 'totara_program') :
                                              get_string('certifcategories', 'totara_certification');
    $select->set_label($strcategory . ':');

    echo html_writer::start_tag('div', array('class' => 'categorypicker'));
    echo $OUTPUT->render($select);
    echo html_writer::end_tag('div');
}

if ($canmanage) {
    echo $OUTPUT->container_start('buttons');
    // Print button to update this category.
    if ($id) {
        $url = new moodle_url('/course/editcategory.php', array('id' => $id, 'type' => $viewtype));
        echo $OUTPUT->single_button($url, get_string('editcategorythis'), 'get');
    }

    // Print button for creating new categories.
    $url = new moodle_url('/course/editcategory.php', array('parent' => $id, 'type' => $viewtype));
    if ($id) {
        $title = get_string('addsubcategory');
    } else {
        $title = get_string('addnewcategory');
    }
    echo $OUTPUT->single_button($url, $title, 'get');

    // Print button for switching to courses management.
    $url = new moodle_url('/course/management.php', array('categoryid' => $id));
    $coursecaps = array('moodle/course:create', 'moodle/course:delete', 'moodle/course:update');
    if (has_any_capability($coursecaps, $context)) {
        $title = get_string('managecoursesinthiscat', 'totara_program');
    }
    echo $OUTPUT->single_button($url, $title, 'get');
    if ($viewtype == 'program') {
        // Print button for switching to certification management.
        if (totara_feature_visible('certifications')) {
            $url = new moodle_url('/totara/program/manage.php', array('categoryid' => $id, 'viewtype' => 'certification'));
            $programcaps = array('totara/certification:createcertification',
                                 'totara/certification:deletecertification',
                                 'totara/certification:configurecertification');
            if (has_any_capability($programcaps, $context)) {
                $title = get_string('managecertifsinthiscat', 'totara_certification');
            }
            echo $OUTPUT->single_button($url, $title, 'get');
        }
    } else {
        // Print button for switching to program management.
        if (totara_feature_visible('programs')) {
            $url = new moodle_url('/totara/program/manage.php', array('categoryid' => $id));
            $programcaps = array('totara/program:createprogram',
                                 'totara/program:deleteprogram',
                                 'totara/program:configuredetails');
            if (has_any_capability($programcaps, $context)) {
                $title = get_string('manageprogramsinthiscat', 'totara_program');
            }
            echo $OUTPUT->single_button($url, $title, 'get');
        }
    }
    echo $OUTPUT->container_end();
}

if (!empty($searchcriteria)) {
    $options = array('offset' => $page * $perpage, 'limit' => $perpage, 'sort' => array('fullname' => 1));
    $programs = coursecat::get(0)->search_programs($searchcriteria, $options, $viewtype);
    $numprograms = count($programs);
    $totalcount = coursecat::get(0)->search_programs_count($searchcriteria, $options, $viewtype);
} else if ($coursecat->id) {
    // Print out all the sub-categories (plain mode).
    // In order to view hidden subcategories the user must have the viewhiddencategories.
    // capability in the current category.
    if (has_capability('moodle/category:viewhiddencategories', $context)) {
        $categorywhere = '';
    } else {
        $categorywhere = 'AND cc.visible = 1';
    }
    // We're going to preload the context for the subcategory as we know that we
    // need it later on for formatting.
    $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
    $sql = "SELECT cc.*, $ctxselect
                FROM {course_categories} cc
                JOIN {context} ctx ON cc.id = ctx.instanceid
                    WHERE cc.parent = :parentid AND
                          ctx.contextlevel = :contextlevel
                          $categorywhere
                    ORDER BY cc.sortorder ASC";
    $subcategories = $DB->get_recordset_sql($sql, array('parentid' => $coursecat->id, 'contextlevel' => CONTEXT_COURSECAT));
    // Prepare a table to display the sub categories.
    $table = new html_table;
    $table->attributes = array(
                    'border' => '0',
                    'cellspacing' => '2',
                    'cellpadding' => '4',
                    'class' => 'generalbox boxaligncenter category_subcategories'
        );
    $table->head = array(new lang_string('subcategories'));
    $table->data = array();
    $baseurl = new moodle_url('/totara/program/manage.php', array('viewtype' => $viewtype));
    foreach ($subcategories as $subcategory) {
        // Preload the context we will need it to format the category name shortly.
        context_helper::preload_from_record($subcategory);
        $context = context_coursecat::instance($subcategory->id);
        // Prepare the things we need to create a link to the subcategory.
        $attributes = $subcategory->visible ? array() : array('class' => 'dimmed');
        $text = format_string($subcategory->name, true, array('context' => $context));
        // Add the subcategory to the table.
                        $baseurl->param('categoryid', $subcategory->id);
                        $table->data[] = array(html_writer::link($baseurl, $text, $attributes));
}

$subcategorieswereshown = (count($table->data) > 0);
if ($subcategorieswereshown) {
echo html_writer::table($table);
}

$programs = prog_get_programs_page($coursecat->id, 'p.sortorder ASC',
            'p.id,p.sortorder,p.shortname,p.fullname,p.summary,p.visible,p.audiencevisible',
                $totalcount, $page*$perpage, $perpage, $viewtype);
                $numprograms = count($programs);
                } else {
    $subcategorieswereshown = true;
    $programs = array();
    $numprograms = $totalcount = 0;
}

if (!$programs) {
    // There is no program to display.
    if (empty($subcategorieswereshown)) {
        echo $OUTPUT->heading(get_string('noprogramsyet', 'totara_program'));
    }
} else {
    // Display a basic list of programs with paging/editing options.
    $table = new html_table;
    $table->attributes = array('border' => 0, 'cellspacing' => 0, 'cellpadding' => '4', 'class' => 'generalbox boxaligncenter');
    $strtype = ($viewtype == 'program') ? get_string('programs', 'totara_program') : get_string('certifications', 'totara_certification');
    $table->head = array(
                    $strtype,
                    get_string('edit'),
                    get_string('select')
    );
    $table->colclasses = array(null, null, 'mdl-align');
    if (!empty($searchcriteria)) {
        // add 'Category' column
        array_splice($table->head, 1, 0, array(get_string('category')));
        array_splice($table->colclasses, 1, 0, array(null));
    }
    $table->data = array();

    $count = 0;
    $abletomoveprograms = false;

    // Checking if we are at the first or at the last page, to allow programs to
    // be moved up and down beyond the paging border.
    if ($totalcount > $perpage) {
        $atfirstpage = ($page == 0);
        if ($perpage > 0) {
            $atlastpage = (($page + 1) == ceil($totalcount / $perpage));
        } else {
            $atlastpage = true;
        }
    } else {
        $atfirstpage = true;
        $atlastpage = true;
    }

    $baseurl = new moodle_url('/totara/program/manage.php', $urlparams + array('sesskey' => sesskey(), 'viewtype' => $viewtype));
    foreach ($programs as $aprogram) {
        $programcontext = context_program::instance($aprogram->id);

        $count++;
        $up = ($count > 1 || !$atfirstpage);
        $down = ($count < $numprograms || !$atlastpage);

        $programurl = new moodle_url('/totara/program/view.php', array('id' => $aprogram->id, 'viewtype' => $viewtype));
        $attributes = array();
        $attributes['class'] = totara_get_style_visibility($aprogram);
        $programname = format_string($aprogram->fullname);
        $programname = html_writer::link($programurl, $programname, $attributes);

        $icons = array();
        // "Update program" icon.
        $capability = ($viewtype == 'program') ? 'totara/program:configuredetails' : 'totara/certification:configuredetails';
        if (has_capability($capability, $programcontext)) {
            $url = new moodle_url('/totara/program/edit.php', array('id' => $aprogram->id, 'category' => $id));
            $icons[] = $OUTPUT->action_icon($url, new pix_icon('t/edit', get_string('settings')));
        }

        // "Role assignment" icon.
        if (has_capability('totara/program:configureassignments', $programcontext)) {
            $url = new moodle_url('/totara/program/edit_assignments.php', array('id' => $aprogram->id));
            $icons[] = $OUTPUT->action_icon($url, new pix_icon('t/enrolusers', get_string('enrolledusers', 'enrol')));
        }

        // "Delete program" icon.
        $capability = ($viewtype == 'program') ? 'totara/program:deleteprogram' : 'totara/certification:deletecertification';
        if (has_capability($capability, $programcontext)) {
            $url = new moodle_url('/totara/program/delete.php', array('id' => $aprogram->id, 'category' => $id));
            $icons[] = $OUTPUT->action_icon($url, new pix_icon('t/delete', get_string('delete')));
        }

        // "Change visibility" icon.
        // Users with no capability to view hidden programs, should not be able to lock themselves out.
        // In case we have audience-based visibility, this icon links to a form where audiences can be managed.
        if (!empty($CFG->audiencevisibility) && has_capability('totara/coursecatalog:manageaudiencevisibility', $context)) {
            $url = new moodle_url('/totara/program/edit.php', array('id' => $aprogram->id, 'action' => 'edit'));
            $url->set_anchor('id_visiblecohortshdr');
            $icon = 'hide';
            if ($aprogram->audiencevisible == COHORT_VISIBLE_NOUSERS) {
                $icon = 'show';
            }
            $icons[] = $OUTPUT->action_icon($url, new pix_icon("t/{$icon}", get_string('manageaudincevisibility', 'totara_cohort')));
        } else {
            $capabilities = ($viewtype == 'program') ? array('totara/program:visibility', 'totara/program:viewhiddenprograms') :
                                                       array('totara/certification:viewhiddencertifications', 'totara/program:visibility');
            if (has_any_capability($capabilities, $programcontext)) {
                if (!empty($aprogram->visible)) {
                    $url = new moodle_url($baseurl, array('hide' => $aprogram->id));
                    $icons[] = $OUTPUT->action_icon($url, new pix_icon('t/hide', get_string('hide')));
                } else {
                    $url = new moodle_url($baseurl, array('show' => $aprogram->id));
                    $icons[] = $OUTPUT->action_icon($url, new pix_icon('t/show', get_string('show')));
                }
            }
        }

        if ($canmanage) {
            if ($up && empty($searchcriteria)) {
                $url = new moodle_url($baseurl, array('moveup' => $aprogram->id));
                $icons[] = $OUTPUT->action_icon($url, new pix_icon('t/up', get_string('moveup')));
            }
            if ($down && empty($searchcriteria)) {
                $url = new moodle_url($baseurl, array('movedown' => $aprogram->id));
                $icons[] = $OUTPUT->action_icon($url, new pix_icon('t/down', get_string('movedown')));
            }
            $abletomoveprograms = true;
        }

        $table->data[] = new html_table_row(array(
                        new html_table_cell($programname),
                        new html_table_cell(join('', $icons)),
                        new html_table_cell(html_writer::empty_tag('input', array('type' => 'checkbox', 'name' => 'c'.$aprogram->id)))
        ));

        if (!empty($searchcriteria)) {
            // add 'Category' column
            $category = coursecat::get($aprogram->category, IGNORE_MISSING, true);
            $cell = new html_table_cell($category->get_formatted_name());
            $cell->attributes['class'] = $category->visible ? '' : 'dimmed_text';
            array_splice($table->data[count($table->data) - 1]->cells, 1, 0, array($cell));
        }
    }

    if ($abletomoveprograms) {
        $movetocategories = coursecat::make_categories_list('moodle/category:manage');
        $label = ($viewtype == 'program') ? get_string('moveselectedprogramsto', 'totara_program') :
                                            get_string('moveselectedcertificationsto', 'totara_certification');
        $movetocategories[$id] = $label;

        $cell = new html_table_cell();
        $cell->colspan = 3;
        $cell->attributes['class'] = 'mdl-right';
        $cell->text = html_writer::label($label, 'movetoid', false, array('class' => 'accesshide'));
        $cell->text .= html_writer::select($movetocategories, 'moveto', $id, null, array('id' => 'movetoid', 'class' => 'autosubmit'));
        $cell->text .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'categoryid', 'value' => $id));
        $PAGE->requires->yui_module('moodle-core-formautosubmit',
                        'M.core.init_formautosubmit',
                        array(array('selectid' => 'movetoid', 'nothing' => $id))
        );
        $table->data[] = new html_table_row(array($cell));
    }

    $actionurl = new moodle_url('/totara/program/manage.php', array('viewtype' => $viewtype));
    $pagingurl = new moodle_url('/totara/program/manage.php',
                    array('categoryid' => $id, 'perpage' => $perpage, 'viewtype' => $viewtype) + $searchcriteria);

    echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $pagingurl);
    echo html_writer::start_tag('form', array('id' => 'moveprograms', 'action' => $actionurl, 'method' => 'post'));
    echo html_writer::start_tag('div');
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
    foreach ($searchcriteria as $key => $value) {
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $key, 'value' => $value));
    }
    echo html_writer::table($table);
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('form');
    echo html_writer::empty_tag('br');
}

// Add category / program buttons.
echo html_writer::start_tag('div', array('class' => 'buttons'));

if ($canmanage && $numprograms > 1 && empty($searchcriteria)) {
    // Print button to re-sort programs by name.
    $url = new moodle_url('/totara/program/manage.php',
                    array('categoryid' => $id, 'resort' => 'name', 'sesskey' => sesskey(), 'viewtype' => $viewtype));
    echo $OUTPUT->single_button($url, get_string('resortprogramsbyname', 'totara_program'), 'get');
}

$cancreateprog = has_capability('totara/program:createprogram', $context) && !totara_feature_disabled('programs');
$cancreatecert = has_capability('totara/certification:createcertification', $context) && !totara_feature_disabled('certifications');
if (empty($searchcriteria)) {
    if ($viewtype == 'program' && $cancreateprog) {
        // Print button to create a new program.
        $url = new moodle_url('/totara/program/add.php');
        if ($coursecat->id) {
            $url->params(array('category' => $coursecat->id));
        } else {
            $url->params(array('category' => $CFG->defaultrequestcategory));
        }
        echo $OUTPUT->single_button($url, get_string('addnewprogram', 'totara_program'), 'get');
    } else if ($viewtype == 'certification' && $cancreatecert) {
        // Print button to create a new certification.
        $url = new moodle_url('/totara/certification/add.php');
        if ($coursecat->id) {
            $url->params(array('category' => $coursecat->id));
        } else {
            $url->params(array('category' => $CFG->defaultrequestcategory));
        }
        echo $OUTPUT->single_button($url, get_string('addnewcertification', 'totara_certification'), 'get');
    }
}

echo html_writer::end_tag('div');

echo $programrenderer->program_search_form($viewtype);

echo $OUTPUT->footer();

/**
 * Recursive function to print all the categories ready for editing.
 *
 * @param html_table $table The table to add data to.
 * @param coursecat $category The category to render
 * @param int $depth The depth of the category.
 * @param bool $up True if this category can be moved up.
 * @param bool $down True if this category can be moved down.
 */
function print_category_edit(html_table $table, coursecat $category, $catlist, $viewtype = 'program', $depth = -1, $up = false, $down = false) {
    global $OUTPUT;

    static $str = null;

    if (is_null($str)) {
        $str = new stdClass;
        $str->edit = new lang_string('edit');
        $str->delete = new lang_string('delete');
        $str->moveup = new lang_string('moveup');
        $str->movedown = new lang_string('movedown');
        $str->edit = new lang_string('editthiscategory');
        $str->hide = new lang_string('hide');
        $str->show = new lang_string('show');
        $str->cohorts = new lang_string('cohorts', 'cohort');
        $str->spacer = $OUTPUT->spacer(array('width' => 11, 'height' => 11));
    }

    if ($category->id) {

        $categorycontext = context_coursecat::instance($category->id);
        $canmanage = has_capability('moodle/category:manage', $categorycontext);

        $attributes = array();
        $attributes['class'] = $category->visible ? '' : 'dimmed';
        $attributes['title'] = $str->edit;
        $categoryurl = new moodle_url('/totara/program/manage.php',
                        array('categoryid' => $category->id, 'sesskey' => sesskey(), 'viewtype' => $viewtype));
        $categoryname = $category->get_formatted_name();
        $categorypadding = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', $depth);
        $categoryname = $categorypadding . html_writer::link($categoryurl, $categoryname, $attributes);

        $icons = array();
        if ($canmanage) {
            // Edit category.
            $icons[] = $OUTPUT->action_icon(
                            new moodle_url('/course/editcategory.php', array('id' => $category->id, 'type' => $viewtype)),
                            new pix_icon('t/edit', $str->edit, 'moodle', array('class' => 'iconsmall')),
                            null, array('title' => $str->edit)
            );
            // Delete category.
            $icons[] = $OUTPUT->action_icon(
                            new moodle_url('/totara/program/manage.php',
                                           array('deletecat' => $category->id, 'sesskey' => sesskey(), 'viewtype' => $viewtype)),
                            new pix_icon('t/delete', $str->delete, 'moodle', array('class' => 'iconsmall')),
                            null, array('title' => $str->delete)
            );
            // Change visibility.
            if (!empty($category->visible)) {
                $icons[] = $OUTPUT->action_icon(
                                new moodle_url('/totara/program/manage.php',
                                               array('hidecat' => $category->id, 'sesskey' => sesskey(), 'viewtype' => $viewtype)),
                                new pix_icon('t/hide', $str->hide, 'moodle', array('class' => 'iconsmall')),
                                null, array('title' => $str->hide)
                );
            } else {
                $icons[] = $OUTPUT->action_icon(
                                new moodle_url('/totara/program/manage.php',
                                               array('showcat' => $category->id, 'sesskey' => sesskey(), 'viewtype' => $viewtype)),
                                new pix_icon('t/show', $str->show, 'moodle', array('class' => 'iconsmall')),
                                null, array('title' => $str->show)
                );
            }
            // Cohorts.
            if (has_any_capability(array('moodle/cohort:manage', 'moodle/cohort:view'), $categorycontext)) {
                $icons[] = $OUTPUT->action_icon(
                                new moodle_url('/cohort/index.php', array('contextid' => $categorycontext->id)),
                                new pix_icon('t/cohort', $str->cohorts, 'moodle', array('class' => 'iconsmall')),
                                null, array('title' => $str->cohorts)
                );
            }
            // Move up/down.
            if ($up) {
                $icons[] = $OUTPUT->action_icon(
                                new moodle_url('/totara/program/manage.php',
                                               array('moveupcat' => $category->id, 'sesskey' => sesskey(), 'viewtype' => $viewtype)),
                                new pix_icon('t/up', $str->moveup, 'moodle', array('class' => 'iconsmall')),
                                null, array('title' => $str->moveup)
                );
            } else {
                $icons[] = $str->spacer;
            }
            if ($down) {
                $icons[] = $OUTPUT->action_icon(
                                new moodle_url('/totara/program/manage.php',
                                               array('movedowncat' => $category->id, 'sesskey' => sesskey(), 'viewtype' => $viewtype)),
                                new pix_icon('t/down', $str->movedown, 'moodle', array('class' => 'iconsmall')),
                                null, array('title' => $str->movedown)
                );
            } else {
                $icons[] = $str->spacer;
            }
        }

        $actions = '';
        if ($canmanage && is_array($catlist)) {
            $popupurl = new moodle_url('/totara/program/manage.php',
                            array('movecat' => $category->id, 'sesskey' => sesskey(), 'viewtype' => $viewtype));
            $select = new single_select($popupurl, 'movetocat', $catlist, $category->parent, null, "moveform$category->id");
            $select->set_label(get_string('frontpagecategorynames'), array('class' => 'accesshide'));
            $actions = $OUTPUT->render($select);
        }

        $count = $viewtype == 'program' ? $category->programcount : $category->certifcount;
        $rowdata = array();
        $rowdata[] = new html_table_cell($categoryname);
        $rowdata[] = new html_table_cell($count);
        $rowdata[] = new html_table_cell(join(' ', $icons));
        if (is_array($catlist)) {
            $rowdata[] = new html_table_cell($actions);
        }
        $table->data[] = new html_table_row($rowdata);
    }

    if ($categories = $category->get_children()) {
        // Print all the children recursively.
        $countcats = count($categories);
        $count = 0;
        $first = true;
        $last = false;
        foreach ($categories as $cat) {
            $count++;
            if ($count == $countcats) {
                $last = true;
            }
            $up = $first ? false : true;
            $down = $last ? false : true;
            $first = false;

            print_category_edit($table, $cat, $catlist, $viewtype, $depth+1, $up, $down);
        }
    }
}
