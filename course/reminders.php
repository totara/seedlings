<?php

// Edit course reminder settings

require_once(dirname(__FILE__).'/../config.php');
require_once($CFG->dirroot.'/course/reminders_form.php');
require_once($CFG->libdir.'/reminderlib.php');
require_once($CFG->libdir.'/completionlib.php');


// Reminder we are currently editing
$id = optional_param('id', 0, PARAM_INT);
$courseid = required_param('courseid', PARAM_INT); // Course id
$delete = optional_param('delete', 0, PARAM_INT); // Detete

// Basic access control checks
if ($courseid) { // editing course
    if($courseid == SITEID){
        // don't allow editing of  'site course' using this from
        print_error('noeditsite', 'totara_coursecatalog');
    }
    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        print_error('error:courseidincorrect', 'totara_core');
    }
    require_login($course->id);
    $coursecontext = context_course::instance($course->id);
    require_capability('moodle/course:update', $coursecontext);
}
else {
    require_login();
    print_error('error:courseidorcategory', 'totara_coursecatalog');
}

$PAGE->set_url('/course/reminders.php');
$PAGE->set_course($course);
$PAGE->set_context(context_course::instance($course->id));
$PAGE->set_pagelayout('admin');

// Get all course reminders
$reminders = get_course_reminders($course->id);

// Check if we are deleting any reminders
if ($delete) {
    // Check reminder exists
    if (in_array($id, array_keys($reminders))) {
        $reminder = $reminders[$id];
    }
    else {
        redirect($CFG->wwwroot.'/course/reminders.php?courseid='.$course->id);
    }

    // Check sesskey
    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad', 'error');
    }

    // Delete reminder
    $reminder->deleted = 1;
    $reminder->update();

    add_to_log($course->id, 'course', 'reminder deleted', 'reminders.php?courseid='.$course->id, $reminder->title);

    $PAGE->set_title(get_string('editcoursereminders', 'totara_coursecatalog'));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('deletedreminder', 'totara_coursecatalog', format_string($reminder->title)));
    echo $OUTPUT->continue_button(new moodle_url('/course/reminders.php', array('courseid' => $course->id)));
    echo $OUTPUT->footer();
    exit();
}

// Get current reminder
// Specified in get params
if (in_array($id, array_keys($reminders))) {
    $reminder = $reminders[$id];
}
// Grab the first one
else if (count($reminders) && $id === 0) {
    $reminder = reset($reminders);
}
// Otherwise we must be creating a new one
else {
    $reminder = new reminder();
    $reminder->courseid = $course->id;
}


// Load all form data
$formdata = $reminder->get_form_data();

// First create the form
$reminderform = new reminder_edit_form('reminders.php', compact('course', 'reminder'));
$reminderform->set_data($formdata);


// Process current action
if ($reminderform->is_cancelled()){
    redirect($CFG->wwwroot.'/course/view.php?id='.$course->id);
}
else if ($data = $reminderform->get_data()) {
    $transaction = $DB->start_delegated_transaction();
    $config = array(
        'tracking' => $data->tracking,
        'requirement' => $data->requirement
    );

    // Create the reminder object
    $reminder->timemodified = time();
    $reminder->modifierid = $USER->id;
    $reminder->deleted = '0';
    $reminder->title = $data->title;
    $reminder->type = 'completion';
    $reminder->config = serialize($config);
    $reminder->timecreated = $reminder->timecreated ? $reminder->timecreated : $reminder->timemodified;

    if (empty($reminder->id)) {
        if (!$reminder->insert()) {
            print_error('error:createreminder', 'totara_coursecatalog');
        }

        add_to_log($course->id, 'course', 'reminder added',
            'reminders.php?courseid='.$course->id.'&id='.$reminder->id, $reminder->title);
    }
    else {
        if (!$reminder->update()) {
            print_error('error:updatereminder', 'totara_coursecatalog');
        }
        add_to_log($course->id, 'course', 'reminder updated',
            'reminders.php?courseid='.$course->id.'&id='.$reminder->id, $reminder->title);
    }

    // Create the messages
    foreach (array('invitation', 'reminder', 'escalation') as $mtype) {
        $nosend = "{$mtype}dontsend";
        $p = "{$mtype}period";
        $sm = "{$mtype}skipmanager";
        $s = "{$mtype}subject";
        $m = "{$mtype}message";

        $message = new reminder_message(
            array(
                'reminderid'    => $reminder->id,
                'type'          => $mtype,
                'deleted'       => 0
            )
        );

        // Do some unique stuff for escalation messages
        if ($mtype === 'escalation') {
            if (!empty($data->$nosend)) {
                // Delete any existing message
                if ($message->id) {
                    $message->deleted = 1;

                    if (!$message->update()) {
                        print_error('error:deletereminder', 'totara_coursecatalog');
                    }
                }

                // Do not create a new one
                continue;
            }
        }

        $message->period = $data->$p;
        $message->copyto = isset($data->$sm) ? $data->$sm : '';
        $message->subject = $data->$s;
        $message->message = $data->$m;
        $message->deleted = 0;

        if (empty($message->id)) {
            if (!$message->insert()) {
                print_error('errro:createreminder', 'totara_coursecatalog');
            }
        }
        else {
            if (!$message->update()) {
                print_error('error:updatereminder', 'totara_coursecatalog');
            }
        }
    }
    $transaction->allow_commit();
    redirect(new moodle_url("/course/reminders.php", array('courseid' => $course->id, 'id' => $reminder->id)));
}

// Print the page

// Generate the button HTML
$buttonhtml = '';
if ($reminder->id > 0) {
    $options = array(
        'courseid'  => $course->id,
        'id'        => $reminder->id,
        'delete'    => 1,
        'sesskey'   => sesskey()
    );

    $buttonhtml = $OUTPUT->single_button(
        new moodle_url('/course/reminders.php', $options),
        get_string('deletereminder', 'totara_coursecatalog', format_string($reminder->title)),
        'get'
    );
}


$streditcoursereminders = get_string('editcoursereminders', 'totara_coursecatalog');
$title = $streditcoursereminders;
$fullname = $course->fullname;

$PAGE->navbar->add($streditcoursereminders);
$PAGE->set_button($buttonhtml);
$PAGE->set_title($title);
$PAGE->set_heading($fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading($streditcoursereminders);

// Check if there are any activites we can use
$completion = new completion_info($course);

// Show tabs
$tabs = array();
foreach ($reminders as $r) {
    $tabs[] = new tabobject($r->id, $CFG->wwwroot.'/course/reminders.php?courseid='.$course->id.'&id='.$r->id, $r->title);
}

$tabs[] = new tabobject('new', $CFG->wwwroot.'/course/reminders.php?courseid='.$course->id.'&id=-1', get_string('new', 'totara_coursecatalog'));

if ($reminder->id < 1) {
    $reminder->id = 'new';
}

// If no current reminders or creating a new reminder, and no activites - do not show form
if (!$completion->is_enabled()) {
    echo $OUTPUT->box(get_string('noactivitieswithcompletionenabled', 'totara_coursecatalog'), 'generalbox adminerror boxwidthwide boxaligncenter');
    echo $OUTPUT->continue_button(new moodle_url('/course/view.php', array('id' => $course->id)));

}
else if (!get_coursemodules_in_course('feedback', $course->id)) {
    echo $OUTPUT->notification(get_string('nofeedbackactivities', 'totara_coursecatalog'), 'notifynotice');
    echo $OUTPUT->continue_button(new moodle_url('/course/view.php', array('id' => $course->id)));
}
else {
    if (count($tabs)) {
        print_tabs(array($tabs), $reminder->id);
    }
    // Show form
    $reminderform->display();
}

echo $OUTPUT->footer();
