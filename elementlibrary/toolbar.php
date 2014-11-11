<?php

require_once(dirname(__FILE__) . '/../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/totaratablelib.php');

$strheading = 'Element Library: Totara Toolbar';
$url = new moodle_url('/elementlibrary/toolbar.php');

$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_title($strheading);
$PAGE->set_heading($strheading);

admin_externalpage_setup('elementlibrary');

echo $OUTPUT->header();

echo html_writer::link(new moodle_url('/elementlibrary/'), '&laquo; Back to index');
echo $OUTPUT->heading($strheading);

echo html_writer::tag('p', 'The Totara Toolbar is currently only available on tables that use flexible_tables(), we need to decide if we should keep that requirement, and update other tables to use flexible_tables, or if we should allow toolbars to be generated independently for use in manually generated tables or those built using the html_table class.');

$table = new totara_table('uniqueid');
$table->attributes = array('class' => 'generaltable fullwidth');

$table->define_headers(array('Name', 'Options'));
$table->define_columns(array('name', 'options'));
$table->define_baseurl($url);

$table->add_toolbar_content('Top left is the default spot if no position given');

// add content to the same section twice and they should sit along side each other
$table->add_toolbar_content('<input type="text" size="10" placeholder="Search..." />', 'left', 'top', 1);
$table->add_toolbar_content('<div>2nd toolbar item<br />Across multiple lines</div>', 'left', 'top', 1);
$table->add_toolbar_content('3rd toolbar, floats and centres', 'left', 'top', 1);

// example with form elements via renderers
$select = $OUTPUT->single_select($PAGE->url, 'test', array(1=>'test',2=>'test 2'));
$table->add_toolbar_content($select, 'right', 'top', 1);
$button = $OUTPUT->single_button($PAGE->url, 'My Button');
$table->add_toolbar_content($button, 'right', 'top', 1);

// a bottom toolbar
$table->add_toolbar_content('Bottom Right', 'right', 'bottom');
$table->add_toolbar_content('Bottom Left', 'left', 'bottom');

$table->setup();

$data = array(
    array(
        'a name',
        'some options'
    ),
    array(
        'This is the name of a field',
        'some options'
    ),
);
foreach ($data as $row) {
    $table->add_data($row);
}

echo $table->print_html();

echo $OUTPUT->heading('A toolbar based table with no data to show');
echo $OUTPUT->container('Unlike the flexible table class, totara tables are still shown if there are no results, so you can access the toolbar options and for consistency. You can customise the "Nothing to display" message via the method set_no_records_message().');

$table = new totara_table('uniqueid2');
$table->attributes = array('class' => 'generaltable fullwidth');

$table->define_headers(array('Name', 'Options'));
$table->define_columns(array('name', 'options'));
$table->define_baseurl($url);
$table->set_no_records_message('This message is printed when there are no results');

$table->add_toolbar_content('Top left is the default spot if no position given');

// add content to the same section twice and they should sit along side each other
$table->add_toolbar_content('<input type="text" size="10" placeholder="Search..." />', 'left', 'top', 1);
$table->add_toolbar_content('<div>2nd toolbar item<br />Across multiple lines</div>', 'left', 'top', 1);
$table->add_toolbar_content('3rd toolbar, floats and centres', 'left', 'top', 1);

// example with form elements via renderers
$select = $OUTPUT->single_select($PAGE->url, 'test', array(1=>'test',2=>'test 2'));
$table->add_toolbar_content($select, 'right', 'top', 1);
$button = $OUTPUT->single_button($PAGE->url, 'My Button');
$table->add_toolbar_content($button, 'right', 'top', 1);

// a bottom toolbar
$table->add_toolbar_content('Bottom Right', 'right', 'bottom');
$table->add_toolbar_content('Bottom Left', 'left', 'bottom');

$table->setup();

echo $table->print_html();

echo $OUTPUT->footer();
