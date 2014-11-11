<?php

require_once(dirname(__FILE__) . '/../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot.'/totara/core/js/lib/setup.php');

$strheading = 'Element Library: Dialogs';
$url = new moodle_url('/elementlibrary/dialogs.php');

// Start setting up the page
$params = array();
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_title($strheading);
$PAGE->set_heading($strheading);

// Setup custom javascript
local_js(array(
    TOTARA_JS_DIALOG,
    TOTARA_JS_TREEVIEW
));
$PAGE->requires->strings_for_js(array('chooseposition', 'choosemanager','chooseorganisation'), 'totara_hierarchy');
$PAGE->requires->string_for_js('currentlyselected', 'totara_hierarchy');
$jsmodule = array(
        'name' => 'totara_positionuser',
        'fullpath' => '/totara/core/js/position.user.js',
        'requires' => array('json'));
$selected_position = json_encode( dialog_display_currently_selected(get_string('selected', 'totara_hierarchy'), 'position') );
$selected_organisation = json_encode( dialog_display_currently_selected(get_string("currentlyselected", "totara_hierarchy"), "organisation") );
$selected_manager = json_encode( dialog_display_currently_selected(get_string("selected", "totara_hierarchy"), "manager") );
$args = array('args'=>'{"userid":0,'.
        '"can_edit":true,'.
        '"dialog_display_position":'.$selected_position.','.
        '"dialog_display_organisation":'.$selected_organisation.','.
        '"dialog_display_manager":'.$selected_manager.'}');

$PAGE->requires->js_init_call('M.totara_positionuser.init', $args, false, $jsmodule);

admin_externalpage_setup('elementlibrary');
echo $OUTPUT->header();

echo html_writer::link(new moodle_url('/elementlibrary/'), '&laquo; Back to index');
echo $OUTPUT->heading($strheading);

echo html_writer::tag('p', 'Here are some example dialogs. They may require some data to be added to the database in order for them to appear.');

echo $OUTPUT->heading('Single select dialogs', 2);

echo html_writer::tag('p', 'Used to select a single item. When an item is selected it should appear in front of the button. Organisation and Managers can be deleted so an x also appears.');
echo $OUTPUT->box_start();

echo $OUTPUT->container_start();
echo html_writer::tag('span', '', array('class' => '', 'id' => 'positiontitle'));
echo html_writer::empty_tag('input', array('type' => 'button', 'value' => get_string('chooseposition', 'totara_hierarchy'), 'id' => 'show-position-dialog'));
echo $OUTPUT->container_end();

echo $OUTPUT->container_start();
echo html_writer::tag('span', '', array('class' => '', 'id' => 'organisationtitle'));
echo html_writer::empty_tag('input', array('type' => 'button', 'value' => get_string('chooseorganisation', 'totara_hierarchy'), 'id' => 'show-organisation-dialog'));
echo $OUTPUT->container_end();

echo $OUTPUT->container_start();
echo html_writer::tag('span', '', array('class' => '', 'id' => 'managertitle'));
echo html_writer::empty_tag('input', array('type' => 'button', 'value' => get_string('choosemanager', 'totara_hierarchy'), 'id' => 'show-manager-dialog'));
echo $OUTPUT->container_end();

echo $OUTPUT->box_end();

echo $OUTPUT->heading('Multi select dialogs', 2);

echo html_writer::tag('p', 'Used to select multiple items. The behaviour when the dialog is saved varies between pages but typically the background content is updated to include the new items.');
echo $OUTPUT->box_start();


$item = $DB->get_record('comp', array(), '*', IGNORE_MULTIPLE);
if (!$item) {
    echo "You have no competencies defined: you should have at least two for this example to work properly";
} else {
    echo 'You\'ll need at least two competencies defined for this to work:';
    $jargs = '{';
    if (!empty($item->id)) {
        $jargs .= '"id":'.$item->id;
    }
    if (!empty($CFG->competencyuseresourcelevelevidence)) {
        $jargs .= ', "competencyuseresourcelevelevidence":true';
    }
    $jargs .= '}';
    // Include competency item js module
    $PAGE->requires->strings_for_js(array('assignrelatedcompetencies',
        'assignnewevidenceitem','assigncoursecompletions'), 'totara_hierarchy');
    $jsmodule = array(
        'name' => 'totara_competencyitem',
        'fullpath' => '/totara/core/js/competency.item.js',
        'requires' => array('json'));
    $PAGE->requires->js_init_call('M.totara_competencyitem.init',
        array('args'=>$jargs), false, $jsmodule);

    $out = html_writer::start_tag('div', array('class' => 'buttons'));
    $out .= html_writer::start_tag('div', array('class' => 'singlebutton'));
    $action = new moodle_url('/totara/hierarchy/prefix/competency/related/find.php', array('id' => $item->id, 'frameworkid' => $item->frameworkid));
    $out .= html_writer::start_tag('form', array('action' => null, 'method' => 'get'));
    $out .= html_writer::start_tag('div');
    $out .= html_writer::empty_tag('input', array('type' => 'submit', 'id' => "show-related-dialog", 'value' => get_string('assignrelatedcompetencies', 'totara_hierarchy')));
    $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "id", 'value' => $item->id));
    $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "nojs", 'value' => '1'));
    $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "returnurl", 'value' => qualified_me()));
    $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "s", 'value' => sesskey()));
    $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "frameworkid", 'value' => $item->frameworkid));
    $out .= html_writer::end_tag('div');
    $out .= html_writer::end_tag('form');
    $out .= html_writer::end_tag('div');
    $out .= html_writer::end_tag('div');
    echo $out;
}


echo $OUTPUT->box_end();
echo $OUTPUT->footer();
