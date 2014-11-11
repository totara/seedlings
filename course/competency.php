<?php

///////////////////////////////////////////////////////////////////////////
//                                                                       //
// NOTICE OF COPYRIGHT                                                   //
//                                                                       //
// Moodle - Modular Object-Oriented Dynamic Learning Environment         //
//          http://moodle.com                                            //
//                                                                       //
// Copyright (C) 1999 onwards Martin Dougiamas  http://dougiamas.com     //
//                                                                       //
// This program is free software; you can redistribute it and/or modify  //
// it under the terms of the GNU General Public License as published by  //
// the Free Software Foundation; either version 3 of the License, or     //
// (at your option) any later version.                                   //
//                                                                       //
// This program is distributed in the hope that it will be useful,       //
// but WITHOUT ANY WARRANTY; without even the implied warranty of        //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         //
// GNU General Public License for more details:                          //
//                                                                       //
//          http://www.gnu.org/copyleft/gpl.html                         //
//                                                                       //
///////////////////////////////////////////////////////////////////////////

// View/add course competencies

require_once(dirname(dirname(__FILE__)) . '/config.php');
require_once($CFG->dirroot.'/totara/hierarchy/prefix/competency/lib.php');
require_once($CFG->dirroot.'/totara/core/js/lib/setup.php');

// Get paramaters
$id = required_param('id', PARAM_INT);                  // course id

/// basic access control checks
if (!$id) {
    require_login();
    print_error('needcourseid');
}

// Courses only
if($id == SITEID){
    // don't allow editing of 'site course' using this from
    print_error('cannoteditsiteform');
}

if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('invalidcourseid');
}

$hierarchy = new competency();

$context = context_system::instance();

require_login($course->id);
require_capability('totara/hierarchy:viewcompetency', $context);

// Can edit?
$can_edit = has_capability('totara/hierarchy:updatecompetency', $context);


local_js(array(
    TOTARA_JS_DIALOG,
    TOTARA_JS_TREEVIEW
));

// Include course competency js module
$PAGE->requires->string_for_js('cancel', 'moodle');
$PAGE->requires->string_for_js('save', 'totara_core');
$PAGE->requires->string_for_js('assigncoursecompletiontocompetencies', 'totara_hierarchy');
$jargs = '{"id":'.$course->id;
if (!empty($CFG->competencyuseresourcelevelevidence)) {
    $jargs .= ', "competencyuseresourcelevelevidence":true';
}
$jargs .= '}';
$args = array('args'=>$jargs);
$jsmodule = array(
        'name' => 'totara_coursecompetency',
        'fullpath' => '/totara/core/js/course.competency.js',
        'requires' => array('json'));
$PAGE->requires->js_init_call('M.totara_coursecompetency.init', $args, false, $jsmodule);

$strcompetenciesusedincourse = get_string("competenciesusedincourse", 'totara_hierarchy');

$title = $strcompetenciesusedincourse;
$fullname = $course->fullname;
$PAGE->set_course($course);
$PAGE->set_url(new moodle_url('/course/competency.php', array('id' => $id)));
$PAGE->navbar->add($strcompetenciesusedincourse);
$PAGE->set_title($title);
$PAGE->set_heading($fullname);
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();
echo $OUTPUT->heading($strcompetenciesusedincourse);


echo '<div id="coursecompetency-table-container">';
echo $hierarchy->print_linked_evidence_list($id);
echo '</div>';

if ($can_edit) {

    // Add course competencies button
?>

<div class="buttons">

<script type="text/javascript">
    <!-- //
    var course_id = '<?php echo $course->id ?>';
    // -->
</script>

<div class="singlebutton centerbutton">
    <form action="<?php echo $CFG->wwwroot ?>/totara/hierarchy/prefix/competency/course/add.php?id=<?php echo $id ?>" method="get">
        <div>
            <?php if (!empty($CFG->competencyuseresourcelevelevidence)) { ?>
                <input type="submit" id="show-coursecompetency-dialog" value="<?php echo get_string('addcourseevidencetocompetencies', 'totara_hierarchy'); ?>" />
            <?php } else { ?>
                <input type="submit" id="show-coursecompetency-dialog" value="<?php echo get_string('assigncoursecompletiontocompetencies', 'totara_hierarchy'); ?>" />
            <?php } ?>
            <input type="hidden" name="id" value="<?php echo $id ?>">
            <input type="hidden" name="nojs" value="1">
            <input type="hidden" name="returnurl" value="<?php echo qualified_me(); ?>">
            <input type="hidden" name="s" value="<?php echo sesskey(); ?>">
        </div>
    </form>
</div>

</div>

<?php

}

echo '<br /><div class="buttons"><div class="centerbutton">';

$options = array('id'=>$id);
echo $OUTPUT->single_button(
    new moodle_url('/course/view.php',
    $options),
    get_string('returntocourse', 'totara_core'),
    'get'
);

echo '</div></div>';

echo $OUTPUT->footer($course);

?>
