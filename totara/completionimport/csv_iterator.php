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
 * @package    totara
 * @subpackage completionimport
 * @author     Russell England <russell.england@catalyst-eu.net>
 */

defined('MOODLE_INTERNAL') || die;

class csv_iterator extends SplFileObject
{
    /**
     * @var object $rowcount current row count
     */
    private $rowcount;

    /**
     * @var string $encoding current encoding eg utf-8
     */
    private $encoding;

    /**
     * @var array $requiredfields array of the required field names, passed as a parameter to the class
     */
    private $requiredfields;

    /**
     * @var array $headerfields array of the column names in the first row of the csv file
     */
    private $headerfields;

    /**
     * @var array $importfields array of columns in the csv file with a true/false value to import or ignore
     */
    private $importfields;

    /**
     * @var int $fieldcount count of $headerfields
     */
    private $fieldcount;

    /**
     * @var int $importtime time of import run
     */
    private $importtime;

    /**
     * @var int $userid current user id
     */
    private $userid;

    public function __construct($filename, $separator, $delimiter, $encoding, $requiredfields, $importtime) {
        global $USER;
        parent::__construct($filename, 'r');
        // Drop new line doesn't work - not keen on using SplFileObject::SKIP_EMPTY because the rownumber will be incorect.
        // See here https://bugs.php.net/bug.php?id=61032&edit=1 .
        $this->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);
        $this->setCsvControl($separator, $delimiter);

        $this->encoding = $encoding;
        $this->requiredfields = $requiredfields;
        $this->importtime = $importtime;
        $this->rowcount = 0;
        $this->fieldcount = 0;
        $this->importfields = array();
        $this->headerfields = array();
        $this->userid = $USER->id;
    }

    public function rewind() {
        parent::rewind();

        // First row has the column names.
        $this->headerfields = $this->clean_fields(parent::current());
        $this->rowcount++;

        if (!empty($this->headerfields)) {
            // Check which columns are to be imported.
            foreach ($this->headerfields as $field) {
                if (in_array($field, $this->requiredfields)) {
                    $this->importfields[$field] = true;
                } else {
                    // Not a required column so ignore it.
                    $this->importfields[$field] = false;
                }
            }

            $this->fieldcount = count($this->importfields);
        }

        parent::next();
    }

    public function current() {
        $fields = parent::current();

        // Test for EOF.
        if (!$this->valid()) {
            return null;
        }

        $values = $this->clean_fields($fields);
        $this->rowcount++;

        $data = new stdClass();
        $data->timecreated = $this->importtime;
        $data->timeupdated = 0;
        $data->importuserid = $this->userid;
        $data->importerror = 0;
        $data->importerrormsg = '';
        $data->rownumber = $this->rowcount;

        if (!is_array($fields) || ((count($fields) == 1) && ($fields[0] == null))) {
            $data->importerror = 1;
            $data->importerrormsg = 'emptyrow;';
        } else if (count($fields) != $this->fieldcount) {
            $data->importerror = 1;
            $data->importerrormsg = 'fieldcountmismatch;';
        } else {
            $emptyrow = '';
            $values = array_combine($this->headerfields, $values);
            foreach ($values as $field => $value) {
                if ($this->importfields[$field]) {
                    $data->$field = $value;
                    $emptyrow .= $data->$field;
                }
            }
            if ($emptyrow === '') {
                $data->importerror = 1;
                $data->importerrormsg = 'emptyrow;';
            }
        }

        return $data;
    }

    /**
     * Cleans the values and returns as an array
     *
     * @param array $fields
     * @return array $fields
     */
    private function clean_fields($fields) {
        if (!empty($fields) && is_array($fields)) {
            foreach ($fields as $key => $value) {
                $value = html_entity_decode(clean_text(trim($value)), ENT_QUOTES, 'UTF-8');
                $fields[$key] = textlib::convert($value, $this->encoding, 'utf-8');
            }
        }
        return $fields;
    }
}
