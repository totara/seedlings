<?php // $Id$
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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage totara_core/dialogs
 */

/**
 * Page containing hierarchy item search form template
 */

defined('MOODLE_INTERNAL') || die();

require_once("{$CFG->libdir}/formslib.php");

/**
 * Form definition for dialog search form
 *
 * @access  public
 */
class dialog_search_form extends moodleform {

    // Define the form
    function definition() {
        global $CFG;

        $mform =& $this->_form;

        // Search type
        $searchtype = $this->_customdata['searchtype'];

        // Other.
        $othertree = isset($this->_customdata['othertree']) ? $this->_customdata['othertree'] : array();

        // Hack to get around form namespacing
        static $formcounter = 1;
        $mform->updateAttributes(array('id' => 'mform_dialog_'.$formcounter));
        $formcounter++;

        // Search data
        $query = $this->_customdata['query'];

        // Check if we are searching a hierarchy
        if ($searchtype == 'hierarchy') {
            // Hierarchy specific code
            $hierarchy = $this->_customdata['hierarchy'];
            $frameworkid = $this->_customdata['frameworkid'];
            $showpicker = $this->_customdata['showpicker'];
            $showhidden = $this->_customdata['showhidden'];

            // If framework selector not shown, pass frameworkid as hidden field
            if (!$showpicker) {
                $mform->addElement('hidden', 'frameworkid');
                $mform->setType('frameworkid', PARAM_INT);
                $mform->setDefault('frameworkid', $frameworkid);
            }
        }

        // Generic hidden values
        $mform->addElement('hidden', 'dialog_form_target', '#search-tab');
        $mform->setType('dialog_form_target', PARAM_TEXT);
        $mform->addElement('hidden', 'search', 1);
        $mform->setType('search', PARAM_INT);

        // Custom hidden values
        if (!empty($this->_customdata['hidden'])) {
            foreach ($this->_customdata['hidden'] as $key => $value) {
                $mform->addElement('hidden', $key);
                $mform->setType($key, PARAM_TEXT);
                $mform->setDefault($key, $value);
            }
        }

        // Create actual form elements
        $searcharray = array();
        $searcharray[] =& $mform->createElement('static', 'tablestart', '', '<table id="dialog-search-table"><tbody><tr><td class="querybox">');

        // Query box
        $query = $this->_customdata['query'];
        $searcharray[] =& $mform->createElement('text', 'query', '', 'maxlength="254"');
        $mform->setType('query', PARAM_TEXT);
        $mform->setDefault('query', $query);

        $searcharray[] =& $mform->createElement('static', 'tabledivider1', '', '</td><td>');

        // Hierarchy specific code
        // Show framework selector
        if (($searchtype == 'hierarchy' && $showpicker) || !empty($othertree)) {
            if (empty($othertree)) {
                $frameworks = $hierarchy->get_frameworks(array(), $showhidden);
                $options = array(0 => get_string('allframeworks', 'totara_hierarchy'));
                if ($frameworks) {
                    foreach ($frameworks as $fw) {
                        $options[$fw->id] = $fw->fullname;
                    }
                }
            } else {
                $options = $othertree;
                $frameworkid = 0;
            }
            $attr = array(
                'class' => 'totara-limited-width-150',
                'onMouseDown'=>"if(document.all) this.className='totara-expanded-width';",
                'onBlur'=>"if(document.all) this.className='totara-limited-width-150';",
                'onChange'=>"if(document.all) this.className='totara-limited-width-150';"
            );

            $searcharray[] =& $mform->createElement('select', 'frameworkid', '', $options, $attr);
            $mform->setDefault('frameworkid', $frameworkid);
            $searcharray[] =& $mform->createElement('static', 'tabledivider2', '', '</td><td>');
        }


        // Show search button and close markup
        // Pad search string to make it look nicer
        $strsearch = '    '.get_string('search').'    ';
        $searcharray[] =& $mform->createElement('submit', 'dialogsearchsubmitbutton', $strsearch);
        $searcharray[] =& $mform->createElement('static', 'tableend', '', '</td></tr></tbody></table>');
        $mform->addGroup($searcharray, 'searchgroup', '', array(' '), false);

    }
}
