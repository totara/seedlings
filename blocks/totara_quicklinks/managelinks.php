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
 * @author Alastair Munro <alastair.munro@totralms.com>
 * @package totara
 * @subpackage totara_quicklinks
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir . '/tablelib.php');
require_once('add_form.php');

require_login();

$blockinstanceid = required_param('blockinstanceid', PARAM_INTEGER);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$courseid = optional_param('courseid', 0, PARAM_INTEGER);
$linkid = optional_param('linkid', 0, PARAM_INTEGER);
$linktitle = optional_param('linktitle', '', PARAM_TEXT);
$linkurl = optional_param('linkurl', '', PARAM_URL);
$addlink = optional_param('btnAddLink', false, PARAM_BOOL);
$blockaction = optional_param('blockaction', '', PARAM_ALPHA);

if ($courseid == SITEID) {
    $courseid = 0;
}
if ($courseid) {
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    $PAGE->set_course($course);
    $context = $PAGE->context;
} else {
    $context = context_system::instance();
    $PAGE->set_context($context);
}

require_capability('block/totara_quicklinks:manageownlinks', $context);

$urlparams = array();
$extraparams = '';
if ($courseid) {
    $urlparams['courseid'] = $courseid;
    $extraparams = '&courseid=' . $courseid;
}
if ($returnurl) {
    $urlparams['returnurl'] = $returnurl;
    $extraparams = '&returnurl=' . $returnurl;
}
if ($blockinstanceid) {
    $urlparams['blockinstanceid'] = $blockinstanceid;
    $extraparams = '&blockinstanceid=' . $blockinstanceid;
}

$baseurl = new moodle_url('/blocks/totara_quicklinks/managelinks.php', $urlparams);
$PAGE->set_url($baseurl);

// Process any actions
if ($blockaction == 'delete' && confirm_sesskey()) {
    $DB->delete_records('block_quicklinks', array('id'=>$linkid));

    $sqlparams = array($blockinstanceid);
    $links = $DB->get_records_select('block_quicklinks', "block_instance_id=?", $sqlparams, 'displaypos');
    $links = array_keys($links);
    block_quicklinks_reorder_links($links);
}

if ($blockaction == 'add' && confirm_sesskey()) {
    // Save the block link
    if (empty($linkurl)) {
        print_error('error:linkurlrequired', 'block_totara_quicklinks', $baseurl);
    } else {
        $link = new stdClass;
        $link->userid = $USER->id;
        $link->block_instance_id = $blockinstanceid;
        $link->title = empty($linktitle) ? $linkurl : $linktitle;
        $link->url = $linkurl;
        $link->displaypos = $DB->count_records('block_quicklinks', array('block_instance_id' => $blockinstanceid)) > 0 ? $DB->get_field('block_quicklinks', 'MAX(displaypos)+1', array('block_instance_id' => $blockinstanceid)) : 0;
        $DB->insert_record('block_quicklinks', $link);
    }
}

if ($blockaction == 'moveup' && confirm_sesskey()) {
    block_quicklinks_move_vertical($linkid, 'up');
}

if ($blockaction == 'movedown' && confirm_sesskey()) {
    block_quicklinks_move_vertical($linkid, 'down');
}

// Display the list of links.
$select = 'block_instance_id = ' . $blockinstanceid;

$links = $DB->get_records_select('block_quicklinks', $select, null, $DB->sql_order_by_text('displaypos'));

$strmanage = get_string('managelinks', 'block_totara_quicklinks');

$PAGE->set_pagelayout('standard');
$PAGE->set_title($strmanage);
$PAGE->set_heading(format_string($SITE->fullname));

$managefeeds = new moodle_url('/blocks/totara_quicklinks/managelinks.php', $urlparams);
$PAGE->navbar->add(get_string('blocks'));
$PAGE->navbar->add(get_string('managelinks', 'block_totara_quicklinks'), $managefeeds);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managelinks', 'block_totara_quicklinks'), 2, null, 'managelinks');

$table = new flexible_table('totara-quicklinks-display-links');

$table->define_columns(array('title', 'url', 'actions'));
$table->define_headers(array(get_string('linktitle', 'block_totara_quicklinks'), get_string('url', 'block_totara_quicklinks'), get_string('actions', 'moodle')));
$table->define_baseurl($baseurl);

$table->set_attribute('cellspacing', '0');
$table->set_attribute('id', 'quicklinks');
$table->set_attribute('class', 'generaltable generalbox');
$table->column_class('title', 'linkname');
$table->column_class('url', 'link_url');
$table->column_class('actions', 'actions');

$table->setup();

foreach ($links as $link) {
    $linktitle = $link->title;

    $linkurl = html_writer::link($link->url, $link->url);

    $deleteurl = new moodle_url('managelinks.php?linkid=' . $link->id . '&sesskey=' . sesskey() . '&blockaction=delete' . $extraparams);
    $deleteicon = new pix_icon('t/delete', get_string('delete'));
    $linkicons = $OUTPUT->action_icon($deleteurl, $deleteicon, new confirm_action(get_string('deletelinkconfirm', 'block_totara_quicklinks'))) . ' ';

    if ($link->displaypos != 0) {
        $moveupurl = new moodle_url('managelinks.php?linkid=' . $link->id . '&sesskey=' . sesskey() . '&blockaction=moveup' . $extraparams);
        $moveupicon = new pix_icon('/t/up', get_string('up'));
        $linkicons .= $OUTPUT->action_icon($moveupurl, $moveupicon) . ' ';
    }
    if ($DB->get_field('block_quicklinks', 'MAX(displaypos)', array('block_instance_id' => $blockinstanceid)) != $link->displaypos) {
        $movedownurl = new moodle_url('managelinks.php?linkid=' . $link->id . '&sesskey=' . sesskey() . '&blockaction=movedown' . $extraparams);
        $movedownicon = new pix_icon('/t/down', get_string('down'));
        $linkicons .= $OUTPUT->action_icon($movedownurl, $movedownicon) . ' ';
    }

    $table->add_data(array($linktitle, $linkurl, $linkicons));
}
$table->print_html();

$mform = new totara_quicklinks_add_quicklink_form(null, array('blockinstanceid' => $blockinstanceid));
$mform->display();

//If we have a return url then print back button
if ($returnurl) {
    echo $OUTPUT->container('backlink', html_writer::link($returnurl, get_string('back')));
}

echo $OUTPUT->footer();


/**
 * Function that figure out new ids of an item when reordering
 * @param int $id ID of link to move
 * @param string $direction 'up' or 'down'
 *
 * @return true to make sure the page refreshes
 */
function block_quicklinks_move_vertical($id, $direction) {
    global $DB;

    if (!$link = $DB->get_record('block_quicklinks', array('id' => $id))) {
        return;
    }

    $links = $DB->get_records('block_quicklinks', array('block_instance_id' => $link->block_instance_id), 'displaypos');
    $links = array_keys($links);
    $itemkey = array_search($link->id, $links);
    switch ($direction) {
        case 'up':
            if (isset($links[$itemkey-1])) {
                $olditem = $links[$itemkey-1];
                $links[$itemkey-1] = $links[$itemkey];
                $links[$itemkey] = $olditem;
            }
            break;
        case 'down':
            if (isset($links[$itemkey+1])) {
                $olditem = $links[$itemkey+1];
                $links[$itemkey+1] = $links[$itemkey];
                $links[$itemkey] = $olditem;
            }
            break;
        default:
            break;
    }

    block_quicklinks_reorder_links($links);

    return true;
}


/**
 * Reorders a list of links given an array of links that have moved
 * @param array $links array of links to move where key is the
 *                     new position and value is the id
 *
 * @return true to make sure the page refreshes
 */
function block_quicklinks_reorder_links($links) {
    global $DB;

    foreach ($links as $key=>$l) {
        if (!$DB->set_field('block_quicklinks', 'displaypos', $key, array('id' => $l))) {
            print_error('linkreorderfail');
        }
    }
    return true;
}
