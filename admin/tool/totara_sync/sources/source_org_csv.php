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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage totara_sync
 */

require_once($CFG->dirroot.'/admin/tool/totara_sync/sources/classes/source.org.class.php');
require_once($CFG->dirroot.'/admin/tool/totara_sync/lib.php');

class totara_sync_source_org_csv extends totara_sync_source_org {

    function get_filepath() {
        $path = '/csv/ready/org.csv';
        $pathos = $this->get_canonical_filesdir($path);
        return $pathos;
    }

    function config_form(&$mform) {
        global $CFG;

        $filepath = $this->get_filepath();

        $this->config->import_idnumber = "1";
        $this->config->import_fullname = "1";
        $this->config->import_frameworkidnumber = "1";
        $this->config->import_timemodified = "1";

        if (empty($filepath) && get_config('totara_sync', 'fileaccess') == FILE_ACCESS_DIRECTORY) {
            $mform->addElement('html', html_writer::tag('p', get_string('nofilesdir', 'tool_totara_sync')));
            return false;
        }

        // Display file example
        $fieldmappings = array();
        foreach ($this->fields as $f) {
            if (!empty($this->config->{'fieldmapping_'.$f})) {
                $fieldmappings[$f] = $this->config->{'fieldmapping_'.$f};
            }
        }

        $filestruct = array();
        foreach ($this->fields as $f) {
            if (!empty($this->config->{'import_'.$f})) {
                $filestruct[] = !empty($fieldmappings[$f]) ? '"'.$fieldmappings[$f].'"' : '"'.$f.'"';
            }
        }

        $delimiter = $this->config->delimiter;
        $info = get_string('csvimportfilestructinfo', 'tool_totara_sync', implode($delimiter, $filestruct));
        $mform->addElement('html',  html_writer::tag('div', html_writer::tag('p', $info, array('class' => "informationbox"))));

        // Add some source file details
        $mform->addElement('header', 'fileheader', get_string('filedetails', 'tool_totara_sync'));
        $mform->setExpanded('fileheader');
        if (get_config('totara_sync', 'fileaccess') == FILE_ACCESS_DIRECTORY) {
             $mform->addElement('static', 'nameandloc', get_string('nameandloc', 'tool_totara_sync'), html_writer::tag('strong', $filepath));
        } else {
             $link = "{$CFG->wwwroot}/admin/tool/totara_sync/admin/uploadsourcefiles.php";
             $mform->addElement('static', 'uploadfilelink', get_string('uploadfilelink', 'tool_totara_sync', $link));
        }

        $encodings = textlib::get_encodings();
        $mform->addElement('select', 'csvorgencoding', get_string('csvencoding', 'tool_totara_sync'), $encodings);
        $mform->setType('csvorgencoding', PARAM_TEXT);
        $default = $this->get_config('csvorgencoding');
        $default = (!empty($default) ? $default : 'UTF-8');
        $mform->setDefault('csvorgencoding', $default);

        $delimiteroptions = array(
            ',' => get_string('comma', 'tool_totara_sync'),
            ';' => get_string('semicolon', 'tool_totara_sync'),
            ':' => get_string('colon', 'tool_totara_sync'),
            '\t' => get_string('tab', 'tool_totara_sync'),
            '|' => get_string('pipe', 'tool_totara_sync')
        );

        $mform->addElement('select', 'delimiter', get_string('delimiter', 'tool_totara_sync'), $delimiteroptions);
        $default = $this->config->delimiter;
        if (empty($default)) {
            $default = ',';
        }
        $mform->setDefault('delimiter', $default);

        parent::config_form($mform);
    }

    function config_save($data) {
        $this->set_config('delimiter', $data->{'delimiter'});
        $this->set_config('csvorgencoding', $data->{'csvorgencoding'});

        parent::config_save($data);
    }

    function import_data($temptable) {
        global $CFG, $DB;

        $fileaccess = get_config('totara_sync', 'fileaccess');
        if ($fileaccess == FILE_ACCESS_DIRECTORY) {
            if (!$this->filesdir) {
                throw new totara_sync_exception($this->get_element_name(), 'populatesynctablecsv', 'nofilesdir');
            }
            $filepath = $this->get_filepath();
            if (!file_exists($filepath)) {
                throw new totara_sync_exception($this->get_element_name(), 'populatesynctablecsv', 'nofiletosync', $filepath, null, 'warn');
            }
            $filemd5 = md5_file($filepath);
            while (true) {
                // Ensure file is not currently being written to
                sleep(2);
                $newmd5 = md5_file($filepath);
                if ($filemd5 != $newmd5) {
                    $filemd5 = $newmd5;
                } else {
                    break;
                }
            }

            // See if file is readable
            if (!$file = is_readable($filepath)) {
                throw new totara_sync_exception($this->get_element_name(), 'populatesynctablecsv', 'cannotreadx', $filepath);
            }

            // Move file to store folder
            $storedir = $this->filesdir . '/csv/store';
            if (!totara_sync_make_dirs($storedir)) {
                throw new totara_sync_exception($this->get_element_name(), 'populatesynctablecsv', 'cannotcreatedirx', $storedir);
            }

            $storefilepath = $storedir . '/' . time() . '.' . basename($filepath);

            rename($filepath, $storefilepath);
        } else if ($fileaccess == FILE_ACCESS_UPLOAD) {
            $fs = get_file_storage();
            $systemcontext = context_system::instance();
            $fieldid = get_config('totara_sync', 'sync_org_itemid');

            // Check the file exists
            if (!$fs->file_exists($systemcontext->id, 'totara_sync', 'org', $fieldid, '/', '')) {
                throw new totara_sync_exception($this->get_element_name(), 'populatesynctablecsv', 'nofileuploaded', $this->get_element_name(), null, 'warn');
            }

            // Get the file
            $fsfiles = $fs->get_area_files($systemcontext->id, 'totara_sync', 'org', $fieldid, 'id DESC', false);
            $fsfile = reset($fsfiles);

            // Set up the temp dir
            $tempdir = $CFG->tempdir . '/totarasync/csv';
            check_dir_exists($tempdir, true, true);

            // Create temporary file (so we know the filepath)
            $fsfile->copy_content_to($tempdir.'/org.php');
            $itemid = $fsfile->get_itemid();
            $fs->delete_area_files($systemcontext->id, 'totara_sync', 'org', $itemid);
            $storefilepath = $tempdir.'/org.php';

        }

        // Open file from store for processing
        if (!$file = fopen($storefilepath, 'r')) {
            throw new totara_sync_exception($this->get_element_name(), 'populatesynctablecsv', 'cannotopenx', $storefilepath);
        }

        // Map CSV fields.
        $fields = fgetcsv($file, 0, $this->config->delimiter);
        $fieldmappings = array();
        foreach ($this->fields as $f) {
            if (empty($this->config->{'import_'.$f})) {
                continue;
            }
            if (empty($this->config->{'fieldmapping_'.$f})) {
                $fieldmappings[$f] = $f;
            } else {
                $fieldmappings[$this->config->{'fieldmapping_'.$f}] = $f;
            }
        }

        // Throw an exception if fields contain invalid characters
        foreach ($fields as $field) {
            $invalidchars = preg_replace('/[?!A-Za-z0-9_-]/i', '', $field);
            if (strlen($invalidchars)) {
                $errorvar = new stdClass();
                $errorvar->invalidchars = $invalidchars[0];
                $errorvar->delimiter = $this->config->delimiter;
                throw new totara_sync_exception($this->get_element_name(), 'mapfields', 'csvnotvalidinvalidchars', $errorvar);
            }
        }

        // Ensure necessary fields are present
        foreach ($fieldmappings as $f => $m) {
            if (!in_array($f, $fields)) {
                if ($m == 'typeidnumber') {
                    // typeidnumber field can be optional if no custom fields specified
                    $customfieldspresent = false;
                    foreach ($fields as $ff) {
                        if (preg_match('/^customfield_/', $ff)) {
                            $customfieldspresent = true;
                            break;
                        }
                    }
                    if (!$customfieldspresent) {
                        // No typeidnumber and no customfields; this is not a problem then ;)
                        continue;
                    }
                }
                if ($f == $m) {
                    throw new totara_sync_exception($this->get_element_name(), 'mapfields', 'csvnotvalidmissingfieldx', $f);
                } else {
                    throw new totara_sync_exception($this->get_element_name(), 'mapfields', 'csvnotvalidmissingfieldxmappingx', (object)array('field' => $f, 'mapping' => $m));
                }
            }
        }
        // Finally, perform CSV to db field mapping
        foreach ($fields as $index => $field) {
            if (!preg_match('/^customfield_/', $field)) {
                if (in_array($field, array_keys($fieldmappings))) {
                    $fields[$index] = $fieldmappings[$field];
                }
            }
        }

        // Populate temp sync table from CSV
        $now = time();
        $datarows = array();    // holds csv row data
        $dbpersist = TOTARA_SYNC_DBROWS;  // # of rows to insert into db at a time
        $rowcount = 0;
        $fieldcount = new object();
        $fieldcount->headercount = count($fields);
        $fieldcount->rownum = 0;
        $csvdateformat = (isset($CFG->csvdateformat)) ? $CFG->csvdateformat : get_string('csvdateformatdefault', 'totara_core');
        $encoding = textlib::strtoupper($this->get_config('csvorgencoding'));

        while ($row = fgetcsv($file, 0, $this->config->delimiter)) {
            $fieldcount->rownum++;
            // Skip empty rows
            if (is_array($row) && current($row) === null) {
                $fieldcount->fieldcount = 0;
                $fieldcount->delimiter = $this->config->delimiter;
                $this->addlog(get_string('fieldcountmismatch', 'tool_totara_sync', $fieldcount), 'error', 'populatesynctablecsv');
                unset($fieldcount->delimiter);
                continue;
            }
            $fieldcount->fieldcount = count($row);
            if ($fieldcount->fieldcount !== $fieldcount->headercount) {
                $fieldcount->delimiter = $this->config->delimiter;
                $this->addlog(get_string('fieldcountmismatch', 'tool_totara_sync', $fieldcount), 'error', 'populatesynctablecsv');
                unset($fieldcount->delimiter);
                continue;
            }
            $row = array_combine($fields, $row);  // nice associative array

            // Encode and clean the data.
            $row = totara_sync_clean_fields($row, $encoding);

            $row['parentidnumber'] = !empty($row['parentidnumber']) ? $row['parentidnumber'] : '';
            $row['parentidnumber'] = $row['parentidnumber'] == $row['idnumber'] ? '' : $row['parentidnumber'];
            $row['typeidnumber'] = !empty($row['typeidnumber']) ? $row['typeidnumber'] : '';

            if (empty($row['timemodified'])) {
                $row['timemodified'] = $now;
            } else {
                // Try to parse the contents - if parse fails assume a unix timestamp and leave unchanged
                $parsed_date = totara_date_parse_from_format($csvdateformat, trim($row['timemodified']));
                if ($parsed_date) {
                    $row['timemodified'] = $parsed_date;
                }
            }

            // Custom fields - need to handle custom field formats like dates here
            $customfieldkeys = preg_grep('/^customfield_.*/', $fields);
            if (!empty($customfieldkeys)) {
                $customfields = array();
                foreach ($customfieldkeys as $key) {
                    // Get shortname and check if we need to do field type processing
                    $value = trim($row[$key]);
                    if (!empty($value)) {
                        $shortname = str_replace('customfield_', '', $key);
                        $datatype = $DB->get_field('org_type_info_field', 'datatype', array('shortname' => $shortname));
                        switch ($datatype) {
                            case 'datetime':
                                // Try to parse the contents - if parse fails assume a unix timestamp and leave unchanged
                                $parsed_date = totara_date_parse_from_format($csvdateformat, $value);
                                if ($parsed_date) {
                                    $value = $parsed_date;
                                }
                                break;
                            default:
                                break;
                        }
                    }
                    $customfields[$key] = $value;
                    unset($row[$key]);
                }

                $row['customfields'] = json_encode($customfields);
            }

            $datarows[] = $row;
            $rowcount++;

            if ($rowcount >= $dbpersist) {
                $this->check_length_limit($datarows, $DB->get_columns($temptable), $fieldmappings, 'org');
                // Bulk insert
                try {
                    totara_sync_bulk_insert($temptable, $datarows);
                } catch (dml_exception $e) {
                    throw new totara_sync_exception($this->get_element_name(), 'populatesynctablecsv', 'couldnotimportallrecords', $e->getMessage());
                }

                $rowcount = 0;
                unset($datarows);
                $datarows = array();

                gc_collect_cycles();
            }
        }

        $this->check_length_limit($datarows, $DB->get_columns($temptable), $fieldmappings, 'org');
        // Insert remaining rows
        try {
            totara_sync_bulk_insert($temptable, $datarows);
        } catch (dml_exception $e) {
            throw new totara_sync_exception($this->get_element_name(), 'populatesynctablecsv', 'couldnotimportallrecords', $e->getMessage());
        }
        unset($fieldmappings);

        fclose($file);
        // Done, clean up the file(s)
        if ($fileaccess == FILE_ACCESS_UPLOAD) {
            unlink($storefilepath); // don't store this file in temp
        }

        return true;
    }
}
