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

require_once($CFG->dirroot.'/grade/export/lib.php');

class grade_export_fusion extends grade_export {

    public $plugin = 'fusion';

    public $separator; // default separator

    public $tablename; // The table being created

    public function grade_export_fusion($course, $groupid=0, $itemlist='', $export_feedback=false, $updatedgradesonly = false, $displaytype = GRADE_DISPLAY_TYPE_REAL, $decimalpoints = 2, $tablename = false, $separator='comma') {
        $this->grade_export($course, $groupid, $itemlist, $export_feedback, $updatedgradesonly, $displaytype, $decimalpoints);
        $this->separator = $separator;
        $this->tablename = preg_replace('/\s/', '_', clean_filename(trim($tablename)));
        $this->course = $course;
    }

    public function set_table($tablename) {
        $this->tablename = preg_replace('/\s/', '_', clean_filename(trim($tablename)));
    }

    public function get_export_params() {
        $params = parent::get_export_params();
        $params['separator'] = $this->separator;
        $params['tablename'] = $this->tablename;
        return $params;
    }

    // Dummy function.
    public function print_grades() {
    }

    public function table_exists($tables, $name) {
        foreach ($tables as $table) {
            if ($table['name'] == $name) {
                return true;
            }
        }
        return false;
    }

    public function clean_column_name($name) {
        $name = preg_replace('/[^a-zA-Z0-9\_ ]/', ' ', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = preg_replace('/\s/', '_', $name);
        return $name;
    }

    public function export_grades($oauth) {
        global $CFG, $OUTPUT;

        $export_tracking = $this->track_exports();
        $errors = array();

        $this->tablename .= ' ' . date("Y-m-d H:i:s", strtotime('+0 days'));
        if (!$oauth->table_exists($this->tablename)) {
            $columns = array(
                 "firstname" => 'STRING',
                 "lastname" => 'STRING',
                 "idnumber" => 'STRING',
                 "institution" => 'STRING',
                 "department" => 'STRING',
                 "email" => 'STRING',
                 );

            foreach ($this->columns as $grade_item) {
                $column = self::clean_column_name($this->format_column_name($grade_item));
                $columns[$column] = 'NUMBER';
            }
            $errors = $oauth->create_table($this->tablename, $columns);
        }

        /// Print all the lines of data.
        $geub = new grade_export_update_buffer();
        $gui = new graded_users_iterator($this->course, $this->columns, $this->groupid);
        $gui->init();
        $rows = array();
        $separator = ' | ';
        while ($userdata = $gui->next_user()) {
            $user = $userdata->user;
            $row = array($user->firstname, $user->lastname, $user->idnumber, $user->institution, $user->department, $user->email,);
            $grades = array();
            foreach ($userdata->grades as $itemid => $grade) {
                $grades[(int)$itemid] = $this->format_grade($grade);
            }

            ksort($grades);
            foreach ($grades as $itemid => $grade) {
                $row[] = $grade;
            }
            $rows[] = $row;
        }
        $gui->close();
        $geub->close();

        $errors = array_merge($errors, $oauth->insert_rows($this->tablename, $rows));

        if (!empty($errors)) {
            $errormessages = array();

            foreach ($errors as $error) {
                $errormessages[] = $error->message;
            }

            $brtag = html_writer::empty_tag('br');
            $errordetails = implode($brtag, $errormessages);
            $url = new moodle_url('/grade/report/grader/index.php', array('id' => $this->course->id));

            totara_set_notification(get_string('error:fusionexport', 'gradeexport_fusion', format_string($errordetails)), $url);
        }

        $table = $oauth->table_by_name($this->tablename, true);
        $table_id = $table->tableId;
        // Output a basic page and do the popup and redirect.
        $table_url = 'https://www.google.com/fusiontables/DataSource?docid='.$table_id;
        $course_url = $CFG->wwwroot.'/course/view.php?id='.$this->course->id;
        print_grade_page_head($this->course->id, 'export', 'fusion', get_string('exportto', 'grades') . ' ' . get_string('modulename', 'gradeexport_fusion'));
        echo $OUTPUT->heading(get_string('popup', 'gradeexport_fusion'));
        $noscript = get_string('noscript', 'gradeexport_fusion');
        echo "<script type='text/javascript'>
            //<![CDATA[
                window.open('".$table_url."', '_blank', 'left=20,top=20,width=1024,height=768,toolbar=1,resizable=1,menubar=1,scrollbars=1,status=1,location=1');
                window.location = '".$course_url."';
            //]]>
            </script>
            <noscript>".$noscript."</noscript>";

        echo $OUTPUT->footer();
        exit;
    }

    /**
     * Either prints a "Export" box, which will redirect the user to the download page,
     * or prints the URL for the published data.
     * @return void
     */
    public function print_continue() {
        global $CFG, $OUTPUT;

        $params = $this->get_export_params();
        $return_to = new moodle_url('/grade/export/'.$this->plugin.'/export.php', $params);
        $params['return_to'] = urlencode($return_to->out());

        echo $OUTPUT->heading(get_string('export', 'grades'));

        echo $OUTPUT->container_start('gradeexportlink');

        if (!$this->userkey) {
            // This button should trigger a download prompt.
            echo $OUTPUT->single_button(new moodle_url('/grade/export/'.$this->plugin.'/export.php', $params), get_string('export', 'grades'));
        } else {
            $paramstr = '';
            $sep = '?';
            foreach ($params as $name=>$value) {
                $paramstr .= $sep.$name.'='.$value;
                $sep = '&';
            }

            $link = $CFG->wwwroot.'/grade/export/'.$this->plugin.'/dump.php'.$paraM.str.'&key='.$this->userkey;

            echo get_string('download', 'admin') . ': ' . html_writer::link($link, $link);
        }
        echo $OUTPUT->container_end();

        echo $OUTPUT->heading(get_string('tablename', 'gradeexport_fusion').': '.$this->tablename);
    }
}
