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
 * @author Aaron Wells <aaronw@catalyst.net.nz>
 * @package totara
 * @subpackage cohort/rules
 */
/**
 * This file contains the class which is used to define dynamic cohort rule options, in cohort/rules/settings.php
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

/**
 * A single rule option for a dynamic cohort. Used to construct the list of rule options in settings.php.
 * This class mostly acts as placeholder to map the sqlhandler and the ui class together.
 */
class cohort_rule_option {

    /**
     * The UI handler for this one
     * @var cohort_rule_ui
     */
    public $ui;

    /**
     * The SQL handler for this one
     * @var cohort_rule_sqlhandler
     */
    public $sqlhandler;

    /**
     * Each cohort rule definition is identified by a unique (group,name) combo. These are also
     * used to determine some of the labels in the menu
     * @var string
     */
    public $group, $name;

    /**
     * The label used in the menu of rule options, for this one. If false, it'll be
     * set to get_string("rulename-{$this->group}-{$this->label}", 'totara_cohort').
     * So basically, for dynamically created rule definitions (custom fields), you can
     * use this field to specify the label. For statically based ones (fixed table columns),
     * you should put them in totara_cohort.
     * @var string
     */
    public $label;

    /**
     * Whether to hide from the "add new rule" menu
     * @var unknown_type
     */
    public $hiddenfrommenu;

    public function __construct($group, $name, $ui, $sqlhandler, $label=false, $hiddenfrommenu=false) {
        $this->group = $group;
        $this->name = $name;
        $this->ui = $ui;
        $this->ui->setGroupAndName($group, $name);
        $this->sqlhandler = $sqlhandler;

        // If this is false we'll want to get it from totara_cohort based on the
        // group and name, but we'll lazy-initialize that.
        $this->label = $label;

        $this->hiddenfrommenu = $hiddenfrommenu;
    }

    /**
     * Return $this->label, lazy-initializing it with a string from
     * totara_cohort if it's not already populated.
     */
    public function getLabel(){
        if (!$this->label){
            $this->label = get_string("rulename-{$this->group}-{$this->name}", 'totara_cohort');
        }
        return $this->label;
    }
}