<?php

require_once(dirname(__FILE__) . '/../config.php');
require_once($CFG->libdir . '/adminlib.php');

$currenttab = optional_param('tab', 'tab1', PARAM_TEXT);

$strheading = 'Element Library: Vertical tabs';

// Start setting up the page.
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/elementlibrary/verticaltabs.php', array('tab' => $currenttab)));
$PAGE->set_title($strheading);
$PAGE->set_heading($strheading);

admin_externalpage_setup('elementlibrary');

echo $OUTPUT->header();

echo html_writer::link(new moodle_url('/elementlibrary/'), '&laquo; Back to index');
echo $OUTPUT->heading($strheading);

echo $OUTPUT->box_start();

echo $OUTPUT->container_start();

$tabs = array();
$row = array();
$inactive = array();
$activated = array();

echo $OUTPUT->box('In Totara 2.5 we added a new UI element, vertical tab bars. These work pretty much the same as regular ' .
        'tabs but the tabs appear stacked to the side of the main content. Useful in cases where there are too many tabs ' .
        'or the tab names are too long to fit horizontally.');

$url = new moodle_url('/elementlibrary/verticaltabs.php');

$url1 = clone $url;
$url1->params(array('tab' => 'tab1'));
$row[] = new tabobject('tab1',
    $url1->out(),
    'Default selected Tab',
    'This is the hover text for tab1'
);

$url2 = clone $url;
$url2->params(array('tab' => 'tab2'));
$row[] = new tabobject('tab2',
    $url2->out(),
    'Another tab',
    'This is the hover text for tab2'
);

$url3 = clone $url;
$url3->params(array('tab' => 'tab3'));
$row[] = new tabobject('tab3',
    $url3->out(),
    'Yet Another tab',
    'This is the hover text for tab3'
);

$url4 = clone $url;
$url4->params(array('tab' => 'tab4'));
$row[] = new tabobject('tab4',
    $url4->out(),
    'A disabled tab',
    'This is the hover text for tab4'
);

$url5 = clone $url;
$url5->params(array('tab' => 'tab5'));
$row[] = new tabobject('tab5',
    $url5->out(),
    'Even more tabs',
    'This is the hover text for tab5'
);

$tabs[] = $row;

$inactive = array('tab4');

echo $OUTPUT->container_start('verticaltabtree-wrapper');
echo $OUTPUT->container(print_tabs($tabs, $currenttab, $inactive,
    $activated, true),
    array('class' => 'verticaltabtree'));
echo $OUTPUT->container_start('verticaltabtree-content');
echo "Content for right panel when using vertical tabs. In this case the text is quite short vertically but the container " .
        "should be at least as tall as the tab stack.";
echo $OUTPUT->container_end();
echo $OUTPUT->container_end();

echo $OUTPUT->container('This is some text that appears immediately after the vertical tabs section.<br /><br />');

echo $OUTPUT->box('Here is another example where the content takes up more vertical space than the tabs.');

$tabs = array();
$row = array();
$inactive = array();
$activated = array();
$url = new moodle_url('/elementlibrary/verticaltabs.php');

$url1 = clone $url;
$url1->params(array('tab' => 'tab1'));
$row[] = new tabobject('tab1',
    $url1->out(),
    'Default selected Tab',
    'This is the hover text for tab1'
);

$url2 = clone $url;
$url2->params(array('tab' => 'tab2'));
$row[] = new tabobject('tab2',
    $url2->out(),
    'Another tab',
    'This is the hover text for tab2'
);

$url3 = clone $url;
$url3->params(array('tab' => 'tab3'));
$row[] = new tabobject('tab3',
    $url3->out(),
    'Yet Another tab',
    'This is the hover text for tab3'
);

$url4 = clone $url;
$url4->params(array('tab' => 'tab4'));
$row[] = new tabobject('tab4',
    $url4->out(),
    'A disabled tab',
    'This is the hover text for tab4'
);

$url5 = clone $url;
$url5->params(array('tab' => 'tab5'));
$row[] = new tabobject('tab5',
    $url5->out(),
    'Even more tabs',
    'This is the hover text for tab5'
);

$tabs[] = $row;

$inactive = array('tab4');

echo $OUTPUT->container_start('verticaltabtree-wrapper');
echo $OUTPUT->container(print_tabs($tabs, $currenttab, $inactive,
    $activated, true),
    array('class' => 'verticaltabtree'));
echo $OUTPUT->container_start('verticaltabtree-content');
echo "Content for right panel when using vertical tabs.<p>In this case the text is taller than the tabs</p><p>In this case " .
        "the text is taller than the tabs</p><p>In this case the text is taller than the tabs</p><p>In this case the text " .
        "is taller than the tabs</p><p>In this case the text is taller than the tabs</p>";
echo $OUTPUT->container_end();
echo $OUTPUT->container_end();

echo $OUTPUT->container_end();

echo $OUTPUT->box_end();

echo $OUTPUT->footer();
