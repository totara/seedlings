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
 * @package mod_facetoface
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Glossary conversion handler
 */
class moodle1_mod_facetoface_handler extends moodle1_mod_handler {

    /** @var moodle1_file_manager */
    protected $fileman = null;

    /** @var int cmid */
    protected $moduleid = null;

    /**
     * Declare the paths in moodle.xml we are able to convert
     *
     * The method returns list of {@link convert_path} instances.
     * For each path returned, the corresponding conversion method must be
     * defined.
     *
     * Note that the path /MOODLE_BACKUP/COURSE/MODULES/MOD/FACETOFACE does not
     * actually exist in the file. The last element with the module name was
     * appended by the moodle1_converter class.
     *
     * @return array of {@link convert_path} instances
     */
    public function get_paths() {
        return array(
            new convert_path(
                'facetoface', '/MOODLE_BACKUP/COURSE/MODULES/MOD/FACETOFACE',
                array(
                    'renamefields' => array(
                        'description' => 'intro',
                    ),
                    'newfields' => array(
                        'introformat' => FORMAT_MOODLE,
                    ),
                )
            ),
            new convert_path('facetoface_sessions', '/MOODLE_BACKUP/COURSE/MODULES/MOD/FACETOFACE/SESSIONS'),
            new convert_path('facetoface_session', '/MOODLE_BACKUP/COURSE/MODULES/MOD/FACETOFACE/SESSIONS/SESSION'),
            new convert_path('facetoface_sessions_dates', '/MOODLE_BACKUP/COURSE/MODULES/MOD/FACETOFACE/SESSIONS/SESSION/DATES'),
            new convert_path('facetoface_sessions_date', '/MOODLE_BACKUP/COURSE/MODULES/MOD/FACETOFACE/SESSIONS/SESSION/DATES/DATE'),
        );
    }

    /**
     * This is executed every time we have one /MOODLE_BACKUP/COURSE/MODULES/MOD/FACETOFACE
     * data available
     */
    public function process_facetoface($data) {
        global $CFG;

        // get the course module id and context id
        $instanceid     = $data['id'];
        $cminfo         = $this->get_cminfo($instanceid);
        $this->moduleid = $cminfo['id'];
        $contextid      = $this->converter->get_contextid(CONTEXT_MODULE, $this->moduleid);

        // replay the upgrade step 2009042006
        if ($CFG->texteditors !== 'textarea') {
            $data['intro']       = text_to_html($data['description'], false, false, true);
            $data['introformat'] = FORMAT_HTML;
        }

        // get a fresh new file manager for this instance
        $this->fileman = $this->converter->get_file_manager($contextid, 'mod_facetoface');

        // convert course files embedded into the intro
        $this->fileman->filearea = 'intro';
        $this->fileman->itemid   = 0;
        $data['intro'] = moodle1_converter::migrate_referenced_files($data['intro'], $this->fileman);

        // start writing facetoface.xml
        $this->open_xml_writer("activities/facetoface_{$this->moduleid}/facetoface.xml");
        $this->xmlwriter->begin_tag('activity', array('id' => $instanceid, 'moduleid' => $this->moduleid,
            'modulename' => 'facetoface', 'contextid' => $contextid));
        $this->xmlwriter->begin_tag('facetoface', array('id' => $instanceid));

        unset($data['id']);
        foreach ($data as $field => $value) {
            $this->xmlwriter->full_tag($field, $value);
        }

        return $data;
    }

    /**
     * This is executed when the parser reaches the <SESSIONS> opening element
     */
    public function on_facetoface_sessions_start() {
        $this->xmlwriter->begin_tag('sessions');
    }

    /**
     * This is executed every time we have one /MOODLE_BACKUP/COURSE/MODULES/MOD/FACETOFACE/SESSIONS/SESSION
     * data available
     */
    public function process_facetoface_session($data) {
        $this->write_xml('session', $data, array('/session/id'));

    }

    /**
     * This is executed when the parser reaches the closing </SESSIONS> element
     */
    public function on_facetoface_sessions_end() {
        $this->xmlwriter->end_tag('sessions');
    }

    /**
     * This is executed every time we have one /MOODLE_BACKUP/COURSE/MODULES/MOD/FACETOFACE/SESSIONS/SESSION/DATES/DATE
     * data available
     */
    public function on_facetoface_sessions_dates_start() {
        $this->xmlwriter->begin_tag('sessions_dates');
    }

    public function process_facetoface_sessions_date($data) {
        $this->write_xml('sessions_date', $data, array('/date/id'));
    }

    public function on_facetoface_sessions_dates_end() {
        $this->xmlwriter->end_tag('sessions_dates');
    }

    /**
     * This is executed when we reach the closing </MOD> tag of our 'facetoface' path
     */
    public function on_facetoface_end() {
        // finalize glossary.xml
        $this->xmlwriter->end_tag('facetoface');
        $this->xmlwriter->end_tag('activity');
        $this->close_xml_writer();

        // write inforef.xml
        $this->open_xml_writer("activities/facetoface_{$this->moduleid}/inforef.xml");
        $this->xmlwriter->begin_tag('inforef');
        $this->xmlwriter->begin_tag('fileref');
        foreach ($this->fileman->get_fileids() as $fileid) {
            $this->write_xml('file', array('id' => $fileid));
        }
        $this->xmlwriter->end_tag('fileref');
        $this->xmlwriter->end_tag('inforef');
        $this->close_xml_writer();
    }

}

?>
