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
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage totara_question
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

require_once('lib.php');

/**
 * Output renderer for totara_question module
 */
class totara_question_renderer extends plugin_renderer_base {

    /**
     * Adds review items to the given form.
     *
     * @param MoodleQuickForm form
     * @param array $items
     * @param review $review
     */
    public function add_review_items($form, $items, review $review) {
        foreach ($items as $scope) {
            foreach ($scope as $itemgroup) {
                $this->add_review_item($form, $itemgroup, $review);
            }
        }
    }

    /**
     * Adds review item to the given form.
     *
     * @param MoodleQuickForm form
     * @param array $itemgroup
     * @param review $review
     */
    public function add_review_item($form, $itemgroup, review $review) {
        global $CFG, $DB;

        require_once($CFG->libdir.'/form/textarea.php');
        require_once($CFG->libdir.'/form/static.php');

        $text_area_options = array('cols' => '90', 'rows' => '5');
        $otherroles = $review->roleinfo;
        $form_prefix = $review->get_prefix_form();
        $prefix = $review->storage->prefix;

        // Start a new fieldset so that we can identify it for deletion.
        $form->addElement('header', 'question-review-item');
        $form->setExpanded('question-review-item');

        if ($review->cananswer) {
            $currentuseritems = $itemgroup[$review->answerid];
        }

        // Delete button.
        $deleteicon = '';
        if ($review->can_select_items() && $review->can_delete_item($itemgroup)) {
            $deleteurl = new moodle_url("/totara/$prefix/ajax/removeitem.php",
                    array('id' => reset($currentuseritems)->id, 'sesskey' => sesskey()));
            $deleteicon = $this->output->action_icon($deleteurl, new pix_icon('t/delete', get_string('delete')),
                     null, array('class' => 'action-icon delete', 'data-reviewitemid' => reset($currentuseritems)->id));
        }

        // Item title.
        $anyitemset = reset($itemgroup);
        $anyitem = reset($anyitemset);
        if (isset($anyitem->planname)) {
            $a = new stdClass();
            $a->fullname = format_string($anyitem->fullname);
            $a->planname = format_string($anyitem->planname);
            $title = get_string('reviewnamewithplan', 'totara_question', $a);
        } else {
            $title = format_string($anyitem->fullname);
        }
        $form->addElement('html', html_writer::tag('h3', $title . $deleteicon,
                array('class' => $form_prefix . '_' . $prefix . '_review')));

        $review->add_item_specific_edit_elements($form, $anyitem);

        // Prepare for multifield headers.
        $multifield = $review->param1;
        if ($multifield) {
            $scalevalues = $DB->get_records($prefix . '_scale_value',
                    array($prefix .'scaleid' => $review->param1), 'id');
            $form->addElement('html', '<div class="review-multifield">');
        }

        if ($review->cananswer) {
            $currentuseritems = $itemgroup[$review->answerid];
            if ($review->viewonly) {
                if ($multifield) {
                    $content = '';
                    foreach ($scalevalues as $scalevalue) {
                        if ($content != '') {
                            $content .= html_writer::empty_tag('br');
                        }
                        $content .= html_writer::tag('b', format_string($scalevalue->name));
                        $content .= html_writer::empty_tag('br');
                        if ($currentuseritems[$scalevalue->id]->content != '') {
                            $content .= format_string($currentuseritems[$scalevalue->id]->content);
                        } else {
                            $content .= html_writer::tag('em', get_string('notanswered', 'totara_question'));
                        }
                    }
                } else {
                    $content = format_string($currentuseritems[0]->content);
                }
                $form->addElement(new MoodleQuickForm_static('', get_string('youranswer', 'totara_question'), $content));
            } else {
                if ($multifield) {
                    $youranswerlabel = get_string('youranswer', 'totara_question');
                    foreach ($scalevalues as $scalevalue) {
                        $form->addElement(new MoodleQuickForm_static('', $youranswerlabel,
                                html_writer::tag('b', format_string($scalevalue->name))));
                        $formelement = $form->addElement(new MoodleQuickForm_textarea(
                                $form_prefix . '_reviewitem_' . $currentuseritems[$scalevalue->id]->id, '', $text_area_options));
                        $youranswerlabel = '';
                    }
                } else {
                    $formelement = $form->addElement(
                            new MoodleQuickForm_textarea($form_prefix . '_reviewitem_' . $currentuseritems[0]->id,
                            get_string('youranswer', 'totara_question'), $text_area_options));
                }
            }
            if (!empty($review->viewers)) {
                $viewersstring = '<small class="visibleto-review">' . get_string('visibleto', 'totara_question') .
                        '<br>' . implode(', ', $review->viewers) . '</small>';
                $form->addElement('html', $viewersstring);
            }
        }

        if ($multifield) {
            $form->addElement('html', '</div>');
        }

        foreach ($otherroles as $role) {
            $content = '';
            if ($multifield) {
                foreach ($scalevalues as $scalevalue) {
                    if ($content != '') {
                        $content .= html_writer::empty_tag('br');
                    }
                    $content .= html_writer::tag('b', format_string($scalevalue->name));
                    $content .= html_writer::empty_tag('br');
                    if (isset($itemgroup[$role->answerid][$scalevalue->id]->content) &&
                             ($itemgroup[$role->answerid][$scalevalue->id]->content != '')) {
                        $content .= format_string($itemgroup[$role->answerid][$scalevalue->id]->content);
                    } else {
                        $content .= html_writer::tag('em', get_string('notanswered', 'totara_question'));
                    }
                }
            } else {
                if (isset($itemgroup[$role->answerid][0]->content) &&
                         ($itemgroup[$role->answerid][0]->content != '')) {
                    $content .= format_string($itemgroup[$role->answerid][0]->content);
                } else {
                    $content .= html_writer::tag('em', get_string('notanswered', 'totara_question'));
                }
            }
            $form->addElement('html', $role->userimage);
            $form->addElement('static', '', $role->label, $content);
        }
    }

}
