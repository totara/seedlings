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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage totara_core
 */
require_once('tablelib.php');

/**
 * This class extends the flexible_table class and adds toolbar functionality
 */
class totara_table extends flexible_table {

    protected $toolbar;
    protected $no_records_message;

    function __construct($uniqueid) {
        parent::__construct($uniqueid);
        $this->toolbar = array(
            'top' => array(),
            'bottom' => array()
        );
    }

    /**
    * This method is used to retrieve the no_records_message property.
    * @return string the value of property no_records_message.
    */
    function get_no_records_message() {
        if (isset($this->no_records_message)) {
            return $this->no_records_message;
        } else {
            return get_string('nothingtodisplay');
        }
    }

    /**
    * This method is used to set the no_records_message property.
    * @param string $message value to set property to
    * @return void
    */
    function set_no_records_message($message) {
        $this->no_records_message = $message;
    }

    /**
     * Add some content to one of the table's toolbars
     *
     * @param string $content HTML to add
     * @param string $side Which side content should be added. Either 'left' or 'right'
     * @param string $position Which toolbar to add content to. Either 'top' or 'bottom'
     * @param integer $index Which toolbar to add content to
     * @return boolean If the content could be added or not
     */
    function add_toolbar_content($content, $side = 'left', $position = 'top', $index = 0) {
        if (!in_array($position, array('top', 'bottom'))) {
            debugging("print_toolbars: Unknown position '{$position}', should be 'top' or 'bottom'");
            return false;
        }
        if (!in_array($side, array('right', 'left'))) {
            debugging("print_toolbars: Unknown side '{$side}', should be 'right' or 'left'");
            return false;
        }

        if (!array_key_exists($index, $this->toolbar[$position])) {
            $this->toolbar[$position][$index] = array();
        }

        if (!array_key_exists($side, $this->toolbar[$position][$index])) {
            $this->toolbar[$position][$index][$side] = array();
        }

        $this->toolbar[$position][$index][$side][] = $content;

        return true;
    }


    /**
     * Render a set of toolbars (either top or bottom)
     *
     * @param string $position Which toolbar to render (top or bottom)
     * @return boolean True if the toolbar was successfully rendered
     */
    function print_toolbars($position = 'top') {
        global $PAGE;
        if (!in_array($position, array('top', 'bottom'))) {
            debugging("print_toolbars: Unknown position '{$position}', should be 'top' or 'bottom'");
            return false;
        }
        $numcols = count($this->columns);
        $renderer = $PAGE->get_renderer('totara_core');
        $renderer->print_toolbars($position, $numcols, $this->toolbar[$position]);

        return true;
    }


    /**
     * Add pagination to one of the table's toolbars
     *
     * @param string $side Which side pagination should be added. Either 'left' or 'right'
     * @param string $position Which toolbar to add pagination to. Either 'top' or 'bottom'
     * @param integer $index Which toolbar to add pagination to
     * @return boolean If the content could be added or not
     */
    function add_toolbar_pagination($side = 'left', $position = 'top', $index = 0) {

        global $OUTPUT;

        // paging bar
        if ($this->use_pages) {
            $pagingbar = new paging_bar($this->totalrows, $this->currpage, $this->pagesize, $this->baseurl);
            $pagingbar->pagevar = $this->request[TABLE_VAR_PAGE];

            $content = $OUTPUT->render($pagingbar);
        } else {
            throw new Exception("Paging must be turned on before pagination can be added to the toolbar. Put the call to
                add_toolbar_pagination() after you call pagesize().");
        }

        // don't add if there's nothing to show
        // TODO when creating custom paging renderer, return nothing if there's no links instead of empty div
        if ($content != '<div class="paging"></div>') {
            $this->add_toolbar_content($content, $side, $position, $index);
        }

    }

    /**
     * Start outputing the HTML
     *
     * Change made to parent function:
     * - insert the call to print_toolbars()
     * - remove pagination outside of table
     *
     * @return null
     */
    function start_html() {
        // Do we need to print initial bars?
        $this->print_initials_bar();

        if (in_array(TABLE_P_TOP, $this->showdownloadbuttonsat)) {
            echo $this->download_buttons();
        }

        $this->wrap_html_start();
        // Start of main data table

        echo html_writer::start_tag('div', array('class' => 'no-overflow'));
        echo html_writer::start_tag('table', $this->attributes);
    }

    /**
     * Output the end of the table
     *
     * Change made to parent function:
     * - insert the call to print_toolbars()
     * - remove pagination outside of table
     *
     * @return null|false
     */
    function finish_html() {
        if (!$this->started_output) {
            //no data has been added to the table.
            $this->print_nothing_to_display();
        }

        $this->print_toolbars('bottom');
        echo html_writer::end_tag('table');
        echo html_writer::end_tag('div');
        $this->wrap_html_finish();

        if (in_array(TABLE_P_BOTTOM, $this->showdownloadbuttonsat)) {
            echo $this->download_buttons();
        }

    }

    function print_extended_headers() {
        $this->print_toolbars('top');
    }

    /**
     * In Totara tables, we print the table anyway, just with a message
     * saying there are no records
     */
    function print_nothing_to_display() {
        $this->print_initials_bar();

        echo $this->start_html();
        $this->print_extended_headers();
        echo html_writer::tag('tr',
            html_writer::tag('td',
                $this->get_no_records_message(),
                array('colspan' => count($this->columns), 'class' => 'norecords')));
    }

    /**
     * Prints the headers and search bar if required
     */
    function print_headers() {
        $headerset = false;
        foreach ($this->headers as $header) {
            if ($header !== null) {
                $headerset = true;
            }
        }

        if ($headerset) {
            return parent::print_headers();
        }

        if (array_key_exists('top', $this->toolbar)) {
            echo html_writer::start_tag('thead');
            $this->print_extended_headers();
            echo html_writer::end_tag('thead');
        }
    }

    /**
     * Setup the table
     *
     * Re-use parent class, but also add 'totaratable' class
     */
    function setup() {
        parent::setup();
        // Always introduce the "totaratable" class for the table if not specified
        if (empty($this->attributes)) {
            $this->attributes['class'] = 'totaratable';
        } else if (!isset($this->attributes['class'])) {
            $this->attributes['class'] = 'totaratable';
        } else if (!in_array('totaratable', explode(' ', $this->attributes['class']))) {
            $this->attributes['class'] = trim('totaratable ' . $this->attributes['class']);
        }
    }
}
