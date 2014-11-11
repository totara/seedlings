<?php

/**
 * URL used as reference for form creation below:
 * http://docs.moodle.org/dev/lib/formslib.php_Form_Definition#definition.28.29
 *
 * Todo:
 *  - it would be useful to apply an incremental className to form groups, to ctrl
 *    spacing between stacked groups, eg; 'class="felement fgroup fgroup1"'
 **/

require_once(dirname(__FILE__) . '/../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot.'/lib/formslib.php');


$strheading = 'Element Library: Moodle Forms';
$url = new moodle_url('/elementlibrary/moodleforms.php');

// Start setting up the page
$params = array();
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_title($strheading);
$PAGE->set_heading($strheading);

admin_externalpage_setup('elementlibrary');

echo $OUTPUT->header();

echo html_writer::link(new moodle_url('/elementlibrary/'), '&laquo; Back to index');
echo $OUTPUT->heading($strheading);

echo $OUTPUT->box_start();
echo $OUTPUT->container('Examples of different types of form.');
echo $OUTPUT->container_start();

echo html_writer::alist(array(
    html_writer::link(new moodle_url('/elementlibrary/mform_standard.php'), 'Standard form elements'),
    html_writer::link(new moodle_url('/elementlibrary/mform_grouped.php'), 'Grouped form elements'),
));

echo $OUTPUT->container_end();
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
