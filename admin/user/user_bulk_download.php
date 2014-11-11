<?php
/**
* script for downloading of user lists
*/

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

$format = optional_param('format', '', PARAM_ALPHA);

require_login();
admin_externalpage_setup('userbulk');
require_capability('moodle/user:update', context_system::instance());

$return = $CFG->wwwroot.'/'.$CFG->admin.'/user/user_bulk.php';

if (empty($SESSION->bulk_users)) {
    redirect($return);
}

if ($format) {
    $fields = array('id'        => 'Id',
                    'username'  => get_string('username'),
                    'email'     => get_string('email'),
                    'firstname' => get_string('firstname'),
                    'lastname'  => get_string('lastname'),
                    'idnumber'  => get_string('idnumber'),
                    'institution' => get_string('institution'),
                    'department' => get_string('department'),
                    'phone1'    => get_string('phone'),
                    'phone2'    => get_string('phone2'),
                    'city'      => get_string('city'),
                    'country'   => get_string('country'),
                    'url'       => get_string('webpage'),
                    'icq'       => get_string('icqnumber'),
                    'skype'     => get_string('skypeid'),
                    'aim'       => get_string('aimid'),
                    'yahoo'     => get_string('yahooid'),
                    'msn'       => get_string('msnid'));
    ;

    if ($extrafields = $DB->get_records('user_info_field')) {
        foreach ($extrafields as $n=>$v){
            $fields['profile_field_'.$v->shortname] = $v->name;
        }
    }

    switch ($format) {
        case 'csv' : user_download_csv($fields);
        case 'ods' : user_download_ods($fields);
        case 'xls' : user_download_xls($fields);

    }
    die;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('download', 'admin'));

echo $OUTPUT->box_start();
echo '<ul>';
echo '<li><a href="user_bulk_download.php?format=csv">'.get_string('downloadtext').'</a></li>';
echo '<li><a href="user_bulk_download.php?format=ods">'.get_string('downloadods').'</a></li>';
echo '<li><a href="user_bulk_download.php?format=xls">'.get_string('downloadexcel').'</a></li>';
echo '</ul>';
echo $OUTPUT->box_end();

echo $OUTPUT->continue_button($return);

echo $OUTPUT->footer();

function user_download_ods($fields) {
    global $CFG, $SESSION, $DB;

    require_once("$CFG->libdir/odslib.class.php");
    require_once($CFG->dirroot.'/user/profile/lib.php');

    $filename = clean_filename(get_string('users').'.ods');

    $workbook = new MoodleODSWorkbook('-');
    $workbook->send($filename);

    $worksheet = array();

    $worksheet[0] = $workbook->add_worksheet('');
    $col = 0;
    foreach ($fields as $fieldname) {
        $worksheet[0]->write(0, $col, $fieldname);
        $col++;
    }

    $row = 1;
    foreach ($SESSION->bulk_users as $userid) {
        if (!$user = $DB->get_record('user', array('id'=>$userid))) {
            continue;
        }
        $col = 0;
        profile_load_data($user, true);
        foreach ($fields as $field=>$unused) {
            $worksheet[0]->write($row, $col, $user->$field);
            $col++;
        }
        $row++;
    }

    $workbook->close();
    die;
}

function user_download_xls($fields) {
    global $CFG, $SESSION, $DB;

    require_once("$CFG->libdir/excellib.class.php");
    require_once($CFG->dirroot.'/user/profile/lib.php');

    $filename = clean_filename(get_string('users').'.xls');

    $workbook = new MoodleExcelWorkbook('-');
    $workbook->send($filename);

    $worksheet = array();

    $worksheet[0] = $workbook->add_worksheet('');
    $col = 0;
    foreach ($fields as $fieldname) {
        $worksheet[0]->write(0, $col, $fieldname);
        $col++;
    }

    $row = 1;
    foreach ($SESSION->bulk_users as $userid) {
        if (!$user = $DB->get_record('user', array('id'=>$userid))) {
            continue;
        }
        $col = 0;
        profile_load_data($user, true);
        foreach ($fields as $field=>$unused) {
            $worksheet[0]->write($row, $col, $user->$field);
            $col++;
        }
        $row++;
    }

    $workbook->close();
    die;
}

function user_download_csv($fields) {
    global $CFG, $SESSION, $DB;

    require_once($CFG->dirroot.'/user/profile/lib.php');
    require_once($CFG->libdir . '/csvlib.class.php');

    $filename = clean_filename(get_string('users'));

    $csvexport = new csv_export_writer();
    $csvexport->set_filename($filename);
    $csvexport->add_data($fields);

    foreach ($SESSION->bulk_users as $userid) {
        $row = array();
        if (!$user = $DB->get_record('user', array('id'=>$userid))) {
            continue;
        }
        profile_load_data($user, true);
        $userprofiledata = array();
        foreach ($fields as $field=>$unused) {
            $userprofiledata[] = $user->$field;
        }

        $csvexport->add_data($userprofiledata);
    }
    $csvexport->download_file();
    die;
}
