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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @package totara
 * @subpackage reportbuilder
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot.'/totara/reportbuilder/lib.php');

/// Check access
require_sesskey();
require_login();
require_capability('totara/reportbuilder:managereports', context_system::instance());

/// Get params
$action = required_param('action', PARAM_ALPHA);
$reportid = required_param('id', PARAM_INT);

switch ($action) {
    case 'add' :
        $column = required_param('col', PARAM_TEXT);
        $heading = optional_param('heading', '', PARAM_TEXT);

        $column = explode('-', $column);
        $coltype = $column[0];
        $colvalue = $column[1];

        /// Prevent duplicates
        $params = array('reportid' => $reportid, 'type' => $coltype, 'value' => $colvalue);
        if ($DB->record_exists('report_builder_columns', $params)) {
            echo false;
            exit;
        }

        /// Save column
        $todb = new stdClass();
        $todb->reportid = $reportid;
        $todb->type = $coltype;
        $todb->value = $colvalue;
        $todb->heading = $heading;
        $sortorder = $DB->get_field('report_builder_columns', 'MAX(sortorder) + 1', array('reportid' => $reportid));
        if (!$sortorder) {
            $sortorder = 1;
        }
        $todb->sortorder = $sortorder;
        $id = $DB->insert_record('report_builder_columns', $todb);
        reportbuilder_set_status($reportid);

        echo $id;
        break;
    case 'delete':
        $colid = required_param('cid', PARAM_INT);

        if ($column = $DB->get_record('report_builder_columns', array('id' => $colid))) {
            $DB->delete_records('report_builder_columns', array('id' => $colid));
            reportbuilder_set_status($reportid);
            echo json_encode((array) $column);
        } else {
            echo false;
        }
        break;
    case 'hide':
        $colid = required_param('cid', PARAM_INT);

        $todb = new stdClass();
        $todb->id = $colid;
        $todb->hidden = 1;
        $DB->update_record('report_builder_columns', $todb);
        reportbuilder_set_status($reportid);

        echo $colid;
        break;
    case 'show':
        $colid = required_param('cid', PARAM_INT);

        $todb = new stdClass();
        $todb->id = $colid;
        $todb->hidden = 0;
        $DB->update_record('report_builder_columns', $todb);
        reportbuilder_set_status($reportid);

        echo $colid;
        break;
    case 'movedown':
    case 'moveup':
        $colid = required_param('cid', PARAM_INT);

        $operator = ($action == 'movedown') ? '>' : '<';
        $sortorder = ($action == 'movedown') ? 'ASC' : 'DESC';

        $col = $DB->get_record('report_builder_columns', array('id' => $colid));
        $sql = "SELECT * FROM {report_builder_columns}
            WHERE reportid = ? AND sortorder $operator ?
            ORDER BY sortorder $sortorder";
        if (!$sibling = $DB->get_record_sql($sql, array($reportid, $col->sortorder), IGNORE_MULTIPLE)) {
            echo false;
            exit;
        }

        $transaction = $DB->start_delegated_transaction();

        $todb = new stdClass();
        $todb->id = $col->id;
        $todb->sortorder = $sibling->sortorder;
        $DB->update_record('report_builder_columns', $todb);

        $todb = new stdClass();
        $todb->id = $sibling->id;
        $todb->sortorder = $col->sortorder;
        $DB->update_record('report_builder_columns', $todb);
        reportbuilder_set_status($reportid);

        $transaction->allow_commit();

        echo "1";
        break;
    default:
        echo '';
        break;
}
