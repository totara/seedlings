<?PHP //$Id$
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
 * @author Piers Harding <piers@catalyst.net.nz>
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage blocks_totara_tasks
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/totara/message/messagelib.php');

class block_totara_tasks extends block_base {
    function init() {
        $this->title = get_string('pluginname', 'block_totara_tasks');
    }

    // Only one instance of this block is required.
    function instance_allow_multiple() {
      return false;
    }

    // Label and button values can be set in admin.
    function has_config() {
      return true;
    }

    function preferred_width() {
        return 210;
    }

    function get_content() {
        global $CFG, $FULLME, $DB, $OUTPUT, $PAGE;

        // Cache block contents
        if ($this->content !== NULL) {
        return $this->content;
        }

        $this->content = new stdClass();
        // initialise jquery and confirm requirements
        require_once($CFG->dirroot.'/totara/reportbuilder/lib.php');
        require_once($CFG->dirroot.'/totara/core/js/lib/setup.php');

        $code = array();
        $code[] = TOTARA_JS_DIALOG;
        local_js($code);
        $PAGE->requires->js_init_call('M.totara_message.init');

        // Just get the tasks for this user.
        $total = tm_messages_count('totara_task', false);
        $this->msgs = tm_messages_get('totara_task', 'timecreated DESC ', false, true);
        $count = is_array($this->msgs) ? count($this->msgs) : 0;

        $this->title = get_string('tasks', 'block_totara_tasks');

        if (empty($this->instance)) {
            return $this->content;
        }

        // Now build the table of results.
        $table = new html_table();
        $table->attributes['class'] = 'fullwidth invisiblepadded';
        if (!empty($this->msgs)) {
            $cnt = 0;
            foreach ($this->msgs as $msg) {
                $cnt++;
                $msgmeta = $DB->get_record('message_metadata', array('messageid' => $msg->id));
                $msgacceptdata = totara_message_eventdata($msg->id, 'onaccept', $msgmeta);
                $msgrejectdata = totara_message_eventdata($msg->id, 'onreject', $msgmeta);
                $msginfodata = totara_message_eventdata($msg->id, 'oninfo', $msgmeta);

                // User name + link.
                $userfrom_link = $CFG->wwwroot.'/user/view.php?id='.$msg->useridfrom;
                $from = $DB->get_record('user', array('id' => $msg->useridfrom));
                $fromname = fullname($from);

                // Message creation time.
                $when = userdate($msg->timecreated, get_string('strftimedate', 'langconfig'));

                // Statement - multipart: user + statment + object.
                $rowbkgd = ($cnt % 2) ? 'shade' : 'noshade';
                $cssclass = totara_message_cssclass($msg->msgtype);
                $msglink = !empty($msg->contexturl) ? $msg->contexturl : '';

                // Status icon.
                $cells = array();
                $icon = $OUTPUT->pix_icon('msgicons/' . $msg->icon, format_string($msg->subject), 'totara_core', array('class'=>"msgicon {$cssclass}", 'title' => format_string($msg->subject)));
                if (!empty($msglink)) {
                    $url = new moodle_url($msglink);
                    $attributes = array('href' => $url);
                    $cellcontent = html_writer::tag('a', $icon, $attributes);
                } else {
                    $cellcontent = $icon;
                }
                $cell = new html_table_cell($cellcontent);
                $cell->attributes['class'] = 'status';
                $cells[] = $cell;

                // Details.
                $text = format_string($msg->subject ? $msg->subject : $msg->fullmessage);
                if (!empty($msglink)) {
                    $url = new moodle_url($msglink);
                    $attributes = array('href' => $url);
                    $cellcontent = html_writer::tag('a', $text, $attributes);
                } else {
                    $cellcontent = $text;
                }
                $cell = new html_table_cell($cellcontent);
                $cell->attributes['class'] = 'statement';
                $cells[] = $cell;

                // Info icon/dialog.
                $detailbuttons = array();
                // Add 'accept' button.
                if (!empty($msgacceptdata) && count((array)$msgacceptdata)) {
                    $btn = new stdClass();
                    $btn->text = !empty($msgacceptdata->acceptbutton) ?
                        $msgacceptdata->acceptbutton : get_string('onaccept', 'block_totara_tasks');
                    $btn->action = "{$CFG->wwwroot}/totara/message/accept.php?id={$msg->id}";
                    $btn->redirect = !empty($msgacceptdata->data['redirect']) ?
                        $msgacceptdata->data['redirect'] : $FULLME;
                    $detailbuttons[] = $btn;
                }
                // Add 'reject' button.
                if (!empty($msgrejectdata) && count((array)$msgrejectdata)) {
                    $btn = new stdClass();
                    $btn->text = !empty($msgrejectdata->rejectbutton) ?
                        $msgrejectdata->rejectbutton : get_string('onreject', 'block_totara_tasks');
                    $btn->action = "{$CFG->wwwroot}/totara/message/reject.php?id={$msg->id}";
                    $btn->redirect = !empty($msgrejectdata->data['redirect']) ?
                        $msgrejectdata->data['redirect'] : $FULLME;
                    $detailbuttons[] = $btn;
                }
                // Add 'info' button.
                if (!empty($msginfodata) && count((array)$msginfodata)) {
                    $btn = new stdClass();
                    $btn->text = !empty($msginfodata->infobutton) ?
                        $msginfodata->infobutton : get_string('oninfo', 'block_totara_tasks');
                    $btn->action = "{$CFG->wwwroot}/totara/message/link.php?id={$msg->id}";
                    $btn->redirect = $msginfodata->data['redirect'];
                    $detailbuttons[] = $btn;
                }
                $moreinfotext = get_string('clickformoreinfo', 'block_totara_tasks');
                $icon = $OUTPUT->pix_icon('i/info', $moreinfotext, 'moodle', array('class'=>'msgicon', 'title' => $moreinfotext, 'alt' => $moreinfotext));
                $detailjs = totara_message_alert_popup($msg->id, $detailbuttons, 'detailtask');
                $url = new moodle_url($msglink);
                $attributes = array('href' => $url, 'id' => 'detailtask'.$msg->id.'-dialog');
                $cellcontent = html_writer::tag('a', $icon, $attributes) . $detailjs;
                $cell = new html_table_cell($cellcontent);
                $cell->attributes['class'] = 'action';
                $cells[] = $cell;
                $row = new html_table_row($cells);
                $row->attributes['class'] = $rowbkgd;
                $table->data[] = $row;
            }
        }

        $this->content->text = '';
        $count = count($this->msgs);
        if ($count) {
            $this->content->text .= html_writer::tag('p', get_string('showingxofx', 'block_totara_tasks', array('count' => $count, 'total' => $total)));
        } else {
            if (!empty($CFG->block_totara_tasks_showempty)) {
                if (!empty($this->config->showempty)) {
                    $this->content->text .= html_writer::tag('p', get_string('notasks', 'block_totara_tasks'));
                } else {
                    return '';
                }
            } else {
                return '';
            }
        }

        $this->content->text .= html_writer::table($table);
        if (!empty($this->msgs)) {
            $url = new moodle_url('/totara/message/tasks.php', array('sesskey' => sesskey()));
            $link = html_writer::link($url, get_string('viewallnot', 'block_totara_tasks'));
            $this->content->footer = html_writer::tag('div', $link, array('class' => 'viewall'));
        }
        return $this->content;
    }
}
