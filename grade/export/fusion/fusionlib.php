<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * OAuth library
 * @package   grade/export/fusion
 * @copyright 2010 Moodle Pty Ltd (http://moodle.com)
 * @author    Piers Harding
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir.'/googleapi.php');

/**
 * OAuth 2.0 client for Google Services
 *
 * @package   core
 * @copyright 2012 Dan Poltawski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class google_fusion_oauth extends google_oauth {
}

class fusion_grade_export_oauth_fusion_exception extends moodle_exception { };

class fusion_grade_export_oauth_fusion {

    private $scope = 'https://www.googleapis.com/auth/fusiontables';
    private $upload_api = 'https://www.googleapis.com/upload/fusiontables/v1/tables/';
    private $table_api = 'https://www.googleapis.com/fusiontables/v1/tables';
    private $googleapi = false;

    public function __construct($googleapi) {
        $this->googleapi = $googleapi;
    }

    /**
     * Make a get request and return decoded JSON
     *
     * @param string $url API url to make request to
     * @param array $parameters Associative array of url params
     * @return object Decoded JSON response
     */
    public function getJSON($url, $parameters = array()) {
        $response = $this->googleapi->get($url, $parameters);
        if (empty($response)) {
            return null;
        }

        $data = new stdClass();
        $data = json_decode($response);

        return $data;
    }

    /**
     * Get a list of items (eg. Tables or columns)
     *
     * @param string $url API URL
     * @param array $parameters Associative array of url params
     * @return array Items
     */
    public function getJSONItems($url, $parameters = array()) {
        $data = $this->getJSON($url, $parameters);
        if (empty($data)) {
            return null;
        }
        $items = isset($data->items) ? $data->items : array();

        return $items;
    }

    /**
     * Get list of tables
     *
     * @return array Array of tables
     */
    public function show_tables() {
        return $this->getJSONItems($this->table_api);
    }

    /**
     * Check to see if a table exists
     *
     * @param string $name Name of the table
     * @return bool True if table exists
     */
    public function table_exists($name) {
        $tables = $this->getJSONItems($this->table_api);
        foreach ($tables as $table) {
            if ($table->name == $name) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return a list of Fusion tables a user has
     *
     * @param string $id Fusion table id
     * @return object|null Fusion table object or null if not found
     */
    public function desc_table($id) {
        return $this->getJSONItems($this->table_api . '/' . $id . '/columns');
    }

    /**
     * Get fusion table given a name
     *
     * @param string $name Name of table
     * @return object|false Fusion table object or false if not found
     */
    public function table_by_name($name) {
        $tables = $this->getJSONItems($this->table_api);

        foreach ($tables as $table) {
            if ($table->name == $name) {
                return $table;
            }
        }
        return false;
    }

    /**
     * Create a fusion table given a name an list of fields
     *
     * @param string $tablename Name for new table
     * @param array $fields Associative array of fields ('name' => 'DATATYPE')
     * @return object Fusion table object including new table ID
     */
    public function create_table($tablename, $fields) {
        $table_info = new stdClass();
        $table_info->name = $tablename;

        $columns = array();
        foreach ($fields as $name => $type) {
            $col = new stdClass();
            $col->name = $name;
            $col->type = $type;
            $columns[] = $col;
            unset($col);
        }

        $table_info->isExportable = true;
        $table_info->columns = $columns;

        $json_data = json_encode($table_info);

        $curl_options = array(
            'CURLOPT_VERBOSE' => false,
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_FOLLOWLOCATION' => 0,
            'CURLOPT_SSL_VERIFYPEER' => true
        );

        $this->googleapi->setHeader('Content-Type: application/json');
        $response = $this->googleapi->post($this->table_api, $json_data, $curl_options);

        // Decode JSON response.
        $response = json_decode($response);
        if (isset($response->error)) {
            return $response->error->errors;
        }

        return array();
    }

    /**
     * Add rows to a fusion table
     *
     * @param string $tablename Name of table to insert row into
     * @param array $rows An array of row data
     * @return json Number of rows added
     */
    public function insert_rows($tablename, $rows) {
        $table = $this->table_by_name($tablename, true);

        $table_id = $table->tableId;
        $desc = $this->desc_table($table_id);

        $lines = array();
        foreach ($rows as $row) {
            $values = array();
            foreach ($row as $value) {
                $value = str_replace(",", "", $value); // Hack to stop commas crashing import.
                $value = str_replace("\n", "", $value); // Strip out newlines.

                $value = htmlentities($value);

                $values[] = $value;
            }
            $lines[] = implode(", ", $values);
        }
        // Bail if there are no lines to add.
        if (empty($lines)) {
            return null;
        }
        $data = implode("\n ", $lines);

        // Set appropriate headers for table import.
        $this->googleapi->resetHeader();
        $this->googleapi->setHeader('Content-Type: application/octet-stream');
        $response = $this->googleapi->post($this->upload_api . $table_id . '/import', $data);

        // Decode JSON response.
        $response = json_decode($response);
        if (isset($response->error)) {
            return $response->error->errors;
        }

        return array();
    }
}
