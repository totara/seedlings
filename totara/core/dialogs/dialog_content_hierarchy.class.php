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
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @package totara
 * @subpackage totara_core/dialogs
 */

/**
 * Hierarchy dialog generator
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content.class.php');
require_once($CFG->dirroot.'/totara/hierarchy/lib.php');

/**
 * Class for generating single select hierarchy dialog markup
 *
 * @access  public
 */
class totara_dialog_content_hierarchy extends totara_dialog_content {

    /**
     * Hierarchy object instance
     *
     * @access  public
     * @var     object
     */
    public $hierarchy;

    /**
     * Supplied framework id, not necessarily the one used however
     *
     * @access  public
     * @var     object
     */
    public $frameworkid;

    /**
     * Flag to disable framework picker
     *
     * @access  public
     * @var     boolean
     */
    public $disable_picker = false;


    /**
     * If you are making access checks seperately, you can disable
     * the internal checks by setting this to true
     *
     * @access  public
     * @var     boolean
     */
    public $skip_access_checks = false;


    /**
     * Only display hierarchy templates, rather than items
     *
     * @access  public
     * @var     boolean
     */
    public $templates_only = false;


    /**
     * Type of search to perform (generally relates to dialog type)
     *
     * @access  public
     * @var     string
     */
    public $searchtype = 'hierarchy';


    /**
     * Switching frameworks
     *
     * @access  public
     * @var     boolean
     */
    public $switch_frameworks = false;

    /**
     * Show hidden frameworks
     *
     * @access public
     * @var    boolean
     */
    public $showhidden = false;

    /**
     * Require hierarchy items to have evidence
     *
     * @access public
     * @var    boolean
     */
    public $requireevidence = false;

    /**
     * Load hierarchy specific information and make some
     * capability checks (which can be disabled)
     *
     * @see     totara_dialog_hierarchy::skip_access_checks
     *
     * @access  public
     * @param   $prefix         string  Hierarchy prefix
     * @param   $frameworkid    int     Framework id (optional)
     * @param   $showhidden     boolean When listing frameworks, include hidden frameworks (optional)
     * @param bool $skipaccesschecks
     */
    public function __construct($prefix, $frameworkid = 0, $showhidden = false, $skipaccesschecks = false) {

        // Make some capability checks
        $this->skip_access_checks = $skipaccesschecks;
        if (!$this->skip_access_checks) {
            require_login();
            require_capability("totara/hierarchy:view{$prefix}", context_system::instance());
        }
        // Save supplied frameworkid
        $this->frameworkid = $frameworkid;

        // Load hierarchy instance
        $this->hierarchy = hierarchy::load_hierarchy($prefix);

        // Should the dialog display hidden frameworks?
        $this->showhidden = $showhidden;

        // Set lang file
        $this->lang_file = 'totara_hierarchy';
        $this->string_nothingtodisplay = "{$prefix}error:dialognotreeitems";

        // Load framework
        $this->set_framework($frameworkid);

        // Print error message then die if there are no frameworks
        if (empty($this->framework)) {
            echo '<p>' . get_string($prefix.'noframeworkssetup', 'totara_hierarchy') . '</p>';
            die();
        }

        // Check if switching frameworks
        $this->switch_frameworks = optional_param('switchframework', false, PARAM_BOOL);
    }


    /**
     * Set framework hierarchy
     *
     * @access  public
     * @param   $frameworkid    int
     */
    public function set_framework($frameworkid) {

        $this->framework = $this->hierarchy->get_framework($frameworkid, $this->showhidden, true);

    }


    /**
     * Load hierarchy items to display
     *
     * @access  public
     * @param   $parentid   int
     */
    public function load_items($parentid) {
        $this->items = $this->hierarchy->get_items_by_parent($parentid);

        // If we are loading non-root nodes, tell the dialog_content class not to
        // return markup for the whole dialog
        if ($parentid > 0) {
            $this->show_treeview_only = true;
        }

        // Also fill parents array
        $this->parent_items = $this->hierarchy->get_all_parents();
    }


    /**
     * Prepend custom markup before treeview
     *
     * @access  protected
     * @return  string
     */
    protected function _prepend_markup() {
        if ($this->disable_picker) {
            return '';
        }

        return $this->hierarchy->display_framework_selector('', true, true, $this->showhidden);
    }


    /**
     * Should we show the treeview root?
     *
     * @access  protected
     * @return  boolean
     */
    protected function _show_treeview_root() {
        return !$this->show_treeview_only || $this->switch_frameworks;
    }

    /**
     * Generate item path for use in search results
     *
     * Returns the name of the item, preceeded by all parent nodes that lead to it
     *
     * @access  public
     * @param   integer $id ID of the hierarchy item to generate path for
     * @return  string  Text string containing ordered path to this item in hierarchy
     */
    public function search_get_item_hover_data($id) {
        $path = '';

        // this gives all items in path, but not in order
        $members = $this->hierarchy->get_item_lineage($id);

        // find order by starting from parent id of 0 (top
        // of tree) and working down

        // prevent infinite loop in case of bad members list
        $escape = 0;

        // start at top of tree
        $parentid = 0;
        while (count($members) && $escape < 100) {
            foreach ($members as $key => $member) {
                if ($member->parentid == $parentid) {
                    // add to path
                    if ($parentid) {
                        // include ' > ' before name except on top element
                        $path .= ' &gt; ';
                    }
                    $path .= $member->fullname;
                    // now update parent id and
                    // unset this element
                    $parentid = $member->id;
                    unset($members[$key]);
                }
            }
            $escape++;
        }

        return $path;
    }
}


/**
 * Class for generating multi select hierarchy dialog markup
 *
 * @access  public
 */
class totara_dialog_content_hierarchy_multi extends totara_dialog_content_hierarchy {

    /**
     * Load hierarchy specific information and make some
     * capability checks (which can be disabled)
     *
     * @see     totara_dialog_hierarchy::skip_access_checks
     *
     * @access  public
     * @param   $prefix               string  Hierarchy prefix
     * @param   $frameworkid        int     Framework id (optional)
     * @param   $showhidden     boolean When listing frameworks, include hidden frameworks (optional)
     * @param   $skipaccesschecks   boolean Indicate whether access checks should be performed
     */
    public function __construct($prefix, $frameworkid = 0, $showhidden = false, $skipaccesschecks=false) {

        $this->skip_access_checks = $skipaccesschecks;

        // Run parent constructor
        parent::__construct($prefix, $frameworkid, $showhidden);

        // Set to type multi
        $this->type = self::TYPE_CHOICE_MULTI;

        // Set titles
        $this->select_title = 'locate'.$prefix;
    }
}
