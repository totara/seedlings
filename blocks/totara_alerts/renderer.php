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
 * @author Brian Barnes <brian.barnes@totaralms.com>
 * @package totara
 * @subpackage blocks_totara_alerts
 */
class block_totara_alerts_renderer extends plugin_renderer_base {
    /**
     * Displays a list of alerts
     *
     * @param array $alerts The list of stats to display in the block.
     * @param integer $total The total number of alerts in this block.
     *
     * @returns the rendered results.
     */
    public function display_alerts($alerts, $total) {
        $output = '';
        $count = count($alerts);
        if ($count) {
            $output .= html_writer::tag('p', get_string('showingxofx', 'block_totara_alerts', array('count' => $count, 'total' => $total)));
        } else {
            if (!empty($CFG->block_totara_alerts_showempty)) {
                if (!empty($this->config->showempty)) {
                    $output .= html_writer::tag('p', get_string('noalerts', 'block_totara_alerts'));
                } else {
                    return '';
                }
            } else {
                return '';
            }
        }

        $output .= html_writer::start_tag('ul');
        foreach ($alerts as $alert) {
            $output .= $this->display_alert($alert);
        }
        $output .= html_writer::end_tag('ul');

        if (!empty($alerts)) {
            $url = new moodle_url('/totara/message/alerts.php', array('sesskey' => sesskey()));
            $link = html_writer::link($url, get_string('viewallnot', 'block_totara_alerts'));
            $output .= html_writer::tag('div', $link, array('class' => 'viewall'));
        }
        return $output;
    }

    /**
     * Displays a list of alerts
     *
     * @param array $alerts the list of stats to display in the block.
     *
     * @returns the rendered results.
     */
    public function display_alert($alert) {
        $output = '';
        $cssclass = totara_message_cssclass($alert->msgtype);
        $msglink = !empty($alert->contexturl) ? $alert->contexturl : '';
        $output .= html_writer::start_tag('li', array('class' => $cssclass));
        if (!empty($msglink)) {
            $url = new moodle_url($msglink);
            $output .= html_writer::start_tag('a', array('href' => $url));
        }

        $icon = $this->pix_icon(
            'msgicons/' . $alert->icon, format_string($alert->subject),
            'totara_core',
            array('class' => "msgicon {$cssclass}", 'alt'=>format_string($alert->subject))
        );

        $output .= $icon;

        $text = format_string($alert->subject ? $alert->subject : $alert->fullmessage);
        $output .= html_writer::tag('span', $text);
        $output .= html_writer::end_tag('a');

        $moreinfotext = get_string('clickformoreinfo', 'block_totara_alerts');
        $icon = $this->pix_icon('i/info', $moreinfotext, 'moodle', array('class'=>'informationicon', 'title' => $moreinfotext, 'alt' => $moreinfotext));
        $detailjs = totara_message_alert_popup($alert->id, null, 'detailalert');
        $url = new moodle_url($msglink);
        $attributes = array('href' => $url, 'id' => "detailalert{$alert->id}-dialog", 'class' => 'information');
        $output .= html_writer::tag('a', $icon, $attributes) . $detailjs;

        $output .= html_writer::end_tag('li');
        return $output;
    }
}
