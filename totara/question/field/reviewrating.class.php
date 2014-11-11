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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage totara_question
 */

require_once('review.class.php');

abstract class reviewrating extends review {

    /**
     * Customfield specific settings elements
     *
     * @param MoodleQuickForm $form
     */
    protected function add_field_specific_settings_elements(MoodleQuickForm $form, $readonly, $moduleinfo) {
        $form->addElement('advcheckbox', 'includerating', get_string('reviewincluderating', 'totara_question'));
        parent::add_field_specific_settings_elements($form, $readonly, $moduleinfo);
    }


    /**
     * Add database fields definition that represent current question
     *
     * @see question_base::get_xmldb()
     * @return array()
     */
    public function define_get(stdClass $toform) {
        parent::define_get($toform);

        $toform->includerating = $this->param4;

        return $toform;
    }


    /**
     * Set values from configuration form
     *
     * @param stdClass $fromform
     * @return stdClass $fromform
     */
    public function define_set(stdClass $fromform) {
        $fromform = parent::define_set($fromform);

        $this->param4 = $fromform->includerating;

        return $fromform;
    }


    public function include_rating() {
        return $this->param4;
    }


    /**
     * Add any form elements which are specific to the field, for each review item.
     *
     * @param MoodleQuickForm $form
     * @param object $item
     */
    public function add_item_specific_edit_elements(MoodleQuickForm $form, $item) {
        // Add the rating scale.
        if ($this->include_rating()) {
            $this->add_rating_selector($form, $item);
        }
        parent::add_item_specific_edit_elements($form, $item);
    }

    /**
     * Add a rating selector to the form.
     *
     * The select element you define must include classes "rating_selector rating_item_<item-identifier>"
     * so that the ratings of all of the same items on the same page will automatically be updated to keep
     * them in sync. See goals for an example.
     *
     * @param MoodleQuickForm $form
     * @param object $item
     */
    protected abstract function add_rating_selector(MoodleQuickForm $form, $item);
}
