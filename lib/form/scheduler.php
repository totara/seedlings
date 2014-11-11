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
 * @subpackage form
 */

global $CFG;
require_once($CFG->libdir . '/form/group.php');
require_once($CFG->libdir . '/formslib.php');

/**
 * Class for a group of elements used to input a schedule for events.
 *
 */
class MoodleQuickForm_scheduler extends MoodleQuickForm_group {

    /** @var array These complement separators, they are appended to the resultant HTML */
    public $_wrap = array('', '');

    /**
     * constructor
     *
     * @param string $elementName Element's name
     * @param mixed $elementLabel Label(s) for an element
     * @param array $options Options to control the element's display
     * @param mixed $attributes Either a typical HTML attribute string or an associative array
     */
    public function MoodleQuickForm_scheduler($elementName = null, $elementLabel = null, $options = array(), $attributes = null) {
        $this->HTML_QuickForm_element($elementName, $elementLabel, $attributes);
        $this->_persistantFreeze = true;
        $this->_appendName = true;
        $this->_type = 'scheduler';
        // set the options, do not bother setting bogus ones
        if (is_array($options)) {
            foreach ($options as $name => $value) {
                if (isset($this->_options[$name])) {
                    if (is_array($value) && is_array($this->_options[$name])) {
                        $this->_options[$name] = @array_merge($this->_options[$name], $value);
                    } else {
                        $this->_options[$name] = $value;
                    }
                }
            }
        }
    }

    /**
     * This will create date group element constisting of frequency and scheduled date/time
     *
     * @access private
     */
    function _createElements() {
        global $USER;
        $CALENDARDAYS = calendar_get_days();
        //Schedule type options
        $frequencyselect = array();
        foreach (scheduler::get_options() as $option => $code) {
            $frequencyselect[$code] = get_string('schedule' . $option, 'totara_reportbuilder');
        }

        //Daily selector
        $dailyselect = array();
        for ($i = 0; $i < 24; $i++) {
            $dailyselect[$i] = date('H:i', mktime($i, 0, 0));
        }

        //Weekly selector
        $weeklyselect = array();
        for ($i = 0; $i < 7; $i++) {
            $weeklyselect[$i] = $CALENDARDAYS[$i]['fullname'];
        }

        $monthlyselect = array();
        $dateformat = ($USER->lang == 'en') ? 'jS' : 'j';
        for ($i = 1; $i <= 31; $i++) {
            $monthlyselect[$i] = date($dateformat, mktime(0, 0, 0, 0, $i));
        }
        $this->_elements = array();
        $this->_elements['frequency'] = @MoodleQuickForm::createElement('select', 'frequency', get_string('schedule', 'totara_reportbuilder'), $frequencyselect);
        $this->_elements['daily'] = @MoodleQuickForm::createElement('select', 'daily', null, $dailyselect);
        $this->_elements['weekly'] = @MoodleQuickForm::createElement('select', 'weekly', null, $weeklyselect);
        $this->_elements['monthly'] = @MoodleQuickForm::createElement('select', 'monthly', null, $monthlyselect);
    }

    /**
     * Called by HTML_QuickForm whenever form event is made on this element
     *
     * @param string $event Name of event
     * @param mixed $arg event arguments
     * @param object $caller calling object
     * @return bool
     */
    function onQuickFormEvent($event, $arg, &$caller) {
        global $CFG;
        require_once($CFG->dirroot . '/totara/core/lib/scheduler.php');
        $scheduler_options = scheduler::get_options();
        switch ($event) {
            case 'updateValue':
                // constant values override both default and submitted ones
                // default values are overriden by submitted
                $value = $this->_findValue($caller->_constantValues);
                if (null === $value) {
                    // if no boxes were checked, then there is no value in the array
                    // yet we don't want to display default value in this case
                    if ($caller->isSubmitted()) {
                        $value = $this->_findValue($caller->_submitValues);
                    } else {
                        $value = $this->_findValue($caller->_defaultValues);
                    }
                }
                if (null !== $value) {
                    $this->setValue($value);
                }
                break;
            case 'createElement':
                $caller->disabledIf($arg[0] . '[daily]', $arg[0] . '[frequency]', 'neq', $scheduler_options['daily']);
                $caller->disabledIf($arg[0] . '[weekly]', $arg[0] . '[frequency]', 'neq', $scheduler_options['weekly']);
                $caller->disabledIf($arg[0] . '[monthly]', $arg[0] . '[frequency]', 'neq', $scheduler_options['monthly']);
                // Optional is an optional param, if its set we need to add a disabledIf rule.
                // If its empty or not specified then its not an optional dateselector.
                if (!empty($arg[2]['optional']) && !empty($arg[0])) {
                    $caller->disabledIf($arg[0], $arg[0] . '[enabled]');
                }
                return parent::onQuickFormEvent($event, $arg, $caller);
                break;
            default:
                return parent::onQuickFormEvent($event, $arg, $caller);
        }
    }

    /**
     * Search for required value in array
     * @param array $values
     */
    public function _findValue(&$values) {
        if (empty($values)) {
            return null;
        }
        $elementname = $this->getName();
        $fields = array('frequency' => null, 'schedule' => null, 'daily' => null, 'weekly' => null,
            'monthly' => null);
        foreach ($fields as $key => $field) {
            if (isset($values[$elementname][$key])) {
                $fields[$key] = $values[$elementname][$key];
            } elseif (isset($values[$key])) {
                $fields[$key] = $values[$key];
            } elseif (isset($values[$elementname . "[$key]"])) {
                $fields[$key] = $values[$elementname . "[$key]"];
            }
        }
        if (!isset($fields['frequency'])) {
            return null;
        }
        switch ($fields['frequency']) {
            case scheduler::DAILY:
                $name = 'daily';
                $schedule = (isset($fields['schedule'])) ? $fields['schedule'] : $fields['daily'];
                break;
            case scheduler::WEEKLY:
                $name = 'weekly';
                $schedule = (isset($fields['schedule'])) ? $fields['schedule'] : $fields['weekly'];
                break;
            case scheduler::MONTHLY:
                $name = 'monthly';
                $schedule = (isset($fields['schedule'])) ? $fields['schedule'] : $fields['monthly'];
                break;
            default:
                $name = $schedule = '';
                mtrace("Wrong scheduler frequency code: {$fields['frequency']} in element {$this->getName()}");
                break;
        }
        return array('frequency' => $fields['frequency'], $name => $schedule);
    }

    /**
     * Returns HTML for advchecbox form element.
     *
     * @return string
     */
    function toHtml() {
        //$html = parent::toHtml();
        include_once('HTML/QuickForm/Renderer/Default.php');
        $renderer = new HTML_QuickForm_Renderer_Default();
        $renderer->setElementTemplate('{element}');
        parent::accept($renderer);
        return $this->_wrap[0] . $renderer->toHTML() . $this->_wrap[1];
    }

    /**
     * Accepts a renderer
     *
     * @param HTML_QuickForm_Renderer $renderer An HTML_QuickForm_Renderer object
     * @param bool $required Whether a group is required
     * @param string $error An error message associated with a group
     */
    function accept(&$renderer, $required = false, $error = null) {
        $renderer->renderElement($this, $required, $error);
    }

    /**
     * Return array where array[0] - frequency, array[1] - schedule
     *
     * @param array $submitValues values submitted.
     * @param bool $assoc specifies if returned array is associative
     * @return array
     */
    function exportValue(&$submitValues, $assoc = false) {
        if (!isset($this->_elements['frequency'])) return array();
        $value = array();
        $value['frequency'] = $this->_elements['frequency']->exportValue($submitValues[$this->getName()], false);
        $value['schedule'] = 0;

        switch ($value['frequency']) {
            case scheduler::DAILY:
                $value['schedule'] = $this->_elements['daily']->exportValue($submitValues[$this->getName()], false);
                break;
            case scheduler::WEEKLY:
                $value['schedule'] = $this->_elements['weekly']->exportValue($submitValues[$this->getName()], false);
                break;
            case scheduler::MONTHLY:
                $value['schedule'] = $this->_elements['monthly']->exportValue($submitValues[$this->getName()], false);
                break;
        }
        return ($assoc) ? $value : array_values($value);
    }
}
