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
 * Block for displaying user-defined links
 *
 * @package   totara
 * @author    Eugene Venter <eugene@catalyst.net.nz>
 * @author    Alastair Munro <alastair.munro@totaralms.com>
 */

require_once('../../config.php');

require_login();
global $USER;

if (!$referer = get_referer(false)) {
    $referer = $CFG->wwwroot.'/';
}

if (!confirm_sesskey() || isguestuser()) {
    print_error('accessdenied', 'block_totara_quicklinks', $referer);
}

$id = required_param('id', PARAM_ALPHANUM);
$blockinstanceid = required_param('blockinstance', PARAM_INT);
$action = required_param('blockaction', PARAM_ALPHANUM);

if (!$blockinstance = $DB->get_record('block_instance', array('id' => $blockinstanceid))) {
    print_error('accessdenied', 'block_totara_quicklinks');
}

$blockcontext = context_block::instance($blockinstanceid);

require_capability('block/totara_quicklinks:manageownlinks', $blockcontext);

switch ($action) {
    case 'deletelink' :
        if (!$DB->delete_records('block_quicklinks', array('id' => $id))) {
            print_error('error:deletequicklink', 'block_totara_quicklinks');
        }
        $sqlparams = array($blockinstanceid);
        $links = $DB->get_records_select('block_quicklinks', "block_instance_id=?", $sqlparams, 'displaypos');
        $links = array_keys($links);
        block_quicklinks_reorder_links($links);
        break;
    case 'movelinkup' :
        block_quicklinks_move_vertical($id, 'up');
        break;
    case 'movelinkdown' :
        block_quicklinks_move_vertical($id, 'down');
        break;
    default:
        break;
}

redirect($referer);


/** HELPER FUNCTIONS **/
function block_quicklinks_move_vertical($id, $direction) {
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
}

function block_quicklinks_reorder_links($links) {
    foreach ($links as $key=>$l) {
        if (!$DB->set_field('block_quicklinks', 'displaypos', 'id', array('id' => $l))) {
            print_error('linkreorderfail');
        }
    }
}

?>
