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
 * @subpackage reportbuilder
 */


require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content.class.php');
require_once($CFG->dirroot.'/totara/hierarchy/prefix/position/lib.php');

class totara_dialog_content_cachenow extends totara_dialog_content {

    /**
     * If you are making access checks seperately, you can disable
     * the internal checks by setting this to true
     *
     * @access  public
     * @var     boolean
     */
    public $skip_access_checks = false;
    public $selected_title = 'cachenow';
    public $lang_file = 'totara_reportbuilder';
    public $search_code = true;

    /**
     * Is cache generation started successful
     * @var bool
     */
    protected $status = false;

    /**
     * Message to show
     * @var string
     */
    protected $message = '';

    /**
     * Construct
     */
    public function __construct() {

        // Make some capability checks
        if (!$this->skip_access_checks) {
            require_login();
        }
    }

    /**
     * Set status
     *
     * @param bool $status
     * @return totara_dialog_content_cachenow $this
     */
    public function set_status($status) {
        $this->status = $status;
        return $this;
    }

    /**
     * Set message
     *
     * @param string $message
     * @return totara_dialog_content_cachenow $this
     */
    public function set_message($message) {
        $this->message = $message;
    }

    /**
     * Generate dialog markup
     * @return string
     */
    public function generate_markup() {
        global $OUTPUT;
        $markup = '';
        header('Content-Type: text/html; charset=UTF-8');

        $markup .= $OUTPUT->container_start('dialog-content');

        $markup .= $OUTPUT->container_start('header');
        if (!empty($this->select_title)) {
            $markup .= '<p>'.get_string($this->select_title, $this->lang_file).'</p>';
        }
        $markup .= $OUTPUT->container_end();

        $markup .= $OUTPUT->container_start('dialog-content-select');
        $class = ($this->status) ? 'success' : 'error';
        $markup .= html_writer::tag('span', $this->message, array('class' => $class));
        $markup .= $OUTPUT->container_end();

        $markup .= $OUTPUT->container_end();
        return $markup;
    }
}