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
 * @author Jonathan Newman <jonathan.newman@catalyst.net.nz>
 * @package totara
 * @subpackage totara_core
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/totara/core/totara.php');
require_once($CFG->dirroot . '/totara/core/deprecatedlib.php');

/* Core event handler classes.
 *
 */
class totara_core_event_handler {

    /**
    * Triggered by the user_enrolled event,  this function is run when a user is enrolled in the course
    * and creates a completion_completion record for the user if completion is enabled for this course
    *
    * @param   object      $event
    * @return  boolean
    */
    public static function user_enrolment(\totara_core\event\user_enrolment $event) {
        global $CFG, $DB;
        include_once($CFG->dirroot . '/completion/completion_completion.php');

        $eventdata = $event->get_data();

        $courseid = $eventdata['other']['courseid'];
        $userid = $eventdata['other']['userid'];
        $timestart = $eventdata['other']['timestart'];

        // Load course
        if (!$course = $DB->get_record('course', array('id' => $courseid))) {
            debugging('Could not load course id '.$courseid);
            return true;
        }

        // Create completion object.
        $cinfo = new completion_info($course);

        // Check completion is enabled for this site and course.
        if (!$cinfo->is_enabled()) {
            return true;
        }

        // If no start on enrol, don't create a record
        if (empty($course->completionstartonenrol)) {
            return true;
        }

        // Create completion record
        $data = array(
            'userid'    => $userid,
            'course'    => $course->id
        );
        $completion = new completion_completion($data);
        $completion->mark_enrolled($timestart);

        // Review criteria
        completion_handle_criteria_recalc($course->id, $userid);

        return true;
    }

    /**
    * Triggered by the module_completion event, this function
    * checks if the criteria exists, if it is applicable to the user
    * and then reviews the user's state in it.
    *
    * @param   object      $event
    * @return  boolean
    */
    public static function criteria_course_calc(\totara_core\event\module_completion $event) {
        global $CFG, $DB;
        include_once($CFG->dirroot . '/completion/completion_completion.php');

        $eventdata = $event->get_data();
        // Check if applicable course criteria exists.
        $criteria = completion_criteria::factory($eventdata['other']);
        $params = array_intersect_key($eventdata['other'], array_flip($criteria->required_fields));

        $criteria = $DB->get_records('course_completion_criteria', $params);
        if (!$criteria) {
            return true;
        }

        // Loop through, and see if the criteria apply to this user.
        foreach ($criteria as $criterion) {

            $course = new stdClass();
            $course->id = $criterion->course;
            $cinfo = new completion_info($course);


            if (!$cinfo->is_tracked_user($eventdata['other']['userid'])) {
                continue;
            }

            // Load criterion.
            $criterion = completion_criteria::factory((array) $criterion);

            // Load completion record.
            $data = array(
                'criteriaid'    => $criterion->id,
                'userid'        => $eventdata['other']['userid'],
                'course'        => $criterion->course
            );
            $completion = new completion_criteria_completion($data);

            // Review and mark complete if necessary.
            $criterion->review($completion);
        }

        return true;
    }
}
/**
 *  * Resize an image to fit within the given rectange, maintaing aspect ratio
 *
 * @param string Path to image
 * @param string Destination file - without file extention
 * @param int Width to resize to
 * @param int Height to resize to
 * @param string Force image to this format
 *
 * @global $CFG
 * @return string Path to new file else false
 */
function resize_image($originalfile, $destination, $newwidth, $newheight, $forcetype = false) {
    global $CFG;

    require_once($CFG->libdir.'/gdlib.php');

    if(!(is_file($originalfile))) {
        return false;
    }

    $imageinfo = GetImageSize($originalfile);
    if (empty($imageinfo)) {
        return false;
    }

    $image = new stdClass;

    $image->width  = $imageinfo[0];
    $image->height = $imageinfo[1];
    $image->type   = $imageinfo[2];

    $ratiosrc = $image->width / $image->height;

    if ($newwidth/$newheight > $ratiosrc) {
        $newwidth = $newheight * $ratiosrc;
    } else {
        $newheight = $newwidth / $ratiosrc;
    }

    switch ($image->type) {
    case IMAGETYPE_GIF:
        if (function_exists('ImageCreateFromGIF')) {
            $im = ImageCreateFromGIF($originalfile);
            $outputformat = 'png';
        } else {
            notice('GIF not supported on this server');
            return false;
        }
        break;
    case IMAGETYPE_JPEG:
        if (function_exists('ImageCreateFromJPEG')) {
            $im = ImageCreateFromJPEG($originalfile);
            $outputformat = 'jpeg';
        } else {
            notice('JPEG not supported on this server');
            return false;
        }
        break;
    case IMAGETYPE_PNG:
        if (function_exists('ImageCreateFromPNG')) {
            $im = ImageCreateFromPNG($originalfile);
            $outputformat = 'png';
        } else {
            notice('PNG not supported on this server');
            return false;
        }
        break;
    default:
        return false;
    }

    if ($forcetype) {
        $outputformat = $forcetype;
    }

    $destname = $destination.'.'.$outputformat;

    if (function_exists('ImageCreateTrueColor') and $CFG->gdversion >= 2) {
        $im1 = ImageCreateTrueColor($newwidth,$newheight);
    } else {
        $im1 = ImageCreate($newwidth, $newheight);
    }
    ImageCopyBicubic($im1, $im, 0, 0, 0, 0, $newwidth, $newheight, $image->width, $image->height);

    switch($outputformat) {
    case 'jpeg':
        imagejpeg($im1, $destname, 90);
        break;
    case 'png':
        imagepng($im1, $destname, 9);
        break;
    default:
        return false;
    }
    return $destname;
}


/**
 * hook to add extra sticky-able page types.
 */
function local_get_sticky_pagetypes() {
    return array(
        // not using a constant here because we're doing funky overrides to PAGE_COURSE_VIEW in the learning path format
        // and it clobbers the page mapping having them both defined at the same time
        'Totara' => array(
            'id' => 'Totara',
            'lib' => '/totara/core/lib.php',
            'name' => 'Totara'
        ),
    );
}

/**
 * Require login for ajax supported scripts
 *
 * @see require_login()
 */
function ajax_require_login($courseorid = null, $autologinguest = true, $cm = null, $setwantsurltome = true,
        $preventredirect = false) {
    if (is_ajax_request($_SERVER)) {
        try {
            require_login($courseorid, $autologinguest, $cm, $setwantsurltome, true);
        } catch (require_login_exception $e) {
            ajax_result(false, $e->getMessage());
            exit();
        }
    } else {
        require_login($courseorid, $autologinguest, $cm, $setwantsurltome, $preventredirect);
    }
}

/**
 * Return response to AJAX request
 * @param bool $success
 * @param string $message
 */
function ajax_result($success = true, $message = '') {
    if ($success) {
        echo 'success';
    } else {
        header('HTTP/1.0 500 Server Error');
        echo $message;
    }
}

/**
 * Drop table if exists
 *
 * @param string $table
 * @return bool
 */
function sql_drop_table_if_exists($table) {
    global $DB;
    $table = $DB->get_prefix() . trim($table, '{}');
    switch ($DB->get_dbfamily()) {
        case 'mssql':
            $sql = "IF OBJECT_ID('dbo.{$table}','U') IS NOT NULL DROP TABLE dbo.{$table}";
            break;
        case 'mysql':
            $sql = "DROP TABLE IF EXISTS `{$table}`";
            break;
        case 'postgres':
        default:
            $sql = "DROP TABLE IF EXISTS \"{$table}\"";
            break;
    }
    $DB->change_database_structure($sql);
    return true;
}

/**
 * Reorder elements based on order field
 *
 * @param int $id Element ID
 * @param int $pos It's new relative position
 * @param string $table Table name
 * @param string $parentfield Field name
 * @param string $orderfield Order field name
 */
function db_reorder($id, $pos, $table, $parentfield, $orderfield='sortorder') {
    global $DB;
    $transaction = $DB->start_delegated_transaction();
    $sql = 'SELECT tosort.id
              FROM {'.$table.'} tosort
              LEFT JOIN {'.$table.'} element
                ON (element.'.$parentfield.' = tosort.'.$parentfield.')
             WHERE element.id = ?
               AND tosort.id <> ?
             ORDER BY tosort.'.$orderfield;
    $records = $DB->get_records_sql($sql, array($id, $id));
    $newpos = 0;
    $todb = new stdClass();
    $todb->id = $id;
    $todb->$orderfield = $pos;
    foreach ($records as $record) {
        if ($newpos == $pos) {
            ++$newpos;
        }
        $record->$orderfield = $newpos;
        $DB->update_record($table, $record);
        ++$newpos;
    }
    $DB->update_record($table, $todb);
    $transaction->allow_commit();
}

/**
 * Include code to pull in site version check code to notify the admin if
 * their site is not on the most current release.
 *
 * This function should only be included on the admin notification page.
 */
function totara_site_version_tracking() {
    global $CFG, $PAGE, $TOTARA;

    require_once($CFG->dirroot.'/totara/core/js/lib/setup.php');
    local_js();

    //Params for JS
    $totara_version = $TOTARA->version;
    $major_version = substr($TOTARA->version, 0, 3);
    $siteurl = parse_url($CFG->wwwroot);
    if (!empty($siteurl['scheme'])) {
        $protocol = $siteurl['scheme'];
    } else if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
        $protocol = 'https';
    } else {
        $protocol = 'http';
    }

    $PAGE->requires->strings_for_js(array('unsupported_branch_text', 'supported_branch_text', 'supported_branch_old_release_text'), 'totara_core', $major_version);
    $PAGE->requires->strings_for_js(array('old_release_text_singular', 'old_release_text_plural', 'old_release_security_text_singular', 'old_release_security_text_plural', 'totarareleaselink'), 'totara_core');

    $args = array('args' => '{"totara_version":"'.$totara_version.'", "major_version":"'.$major_version.'", "protocol":"'.$protocol.'"}');

    $jsmodule = array(
        'name' => 'totara_version_tracking',
        'fullpath' => '/totara/core/js/version_tracking.js',
        'requires' => array('json'));
    $PAGE->requires->js_init_call('M.totara_version_tracking.init', $args, false, $jsmodule);

}

function totara_core_cron() {

    // Temporary manager tasks.
    totara_update_temporary_managers();

    return true;
}

function totara_update_temporary_managers() {
    global $CFG, $DB;

    if (empty($CFG->enabletempmanagers)) {
        // Unassign all current temporary managers.
        if ($rs = $DB->get_recordset('temporary_manager', null, '', 'userid')) {
            mtrace('Removing obsolete temporary managers...');
            foreach ($rs as $tmassignment) {
                totara_unassign_temporary_manager($tmassignment->userid);
            }
        }

        return true;
    }

    if (!$CFG->tempmanagerrestrictselection) {
        // Ensure only users that are currently managers are assigned as temporary managers.
        // We need this check for scenarios where tempmanagerrestrictselection was previously disabled.
        $sql = "SELECT DISTINCT tm.userid
                  FROM {temporary_manager} tm
             LEFT JOIN {pos_assignment} pa ON tm.tempmanagerid = pa.managerid
                 WHERE pa.managerid IS NULL";
        if ($rs = $DB->get_recordset_sql($sql)) {
            mtrace('Removing non-manager temporary managers...');
            foreach ($rs as $assignment) {
                totara_unassign_temporary_manager($assignment->userid);
            }
        }
    }

    // Remove expired temporary managers.
    $timenow = time();
    $expiredmanagers = $DB->get_records_select('temporary_manager', 'expirytime < ?', array($timenow));
    if (!empty($expiredmanagers)) {
        mtrace('Removing expired temporary managers...');

        foreach ($expiredmanagers as $m) {
            totara_unassign_temporary_manager($m->userid);
        }

        mtrace('DONE Removing expired temporary managers');
    }
}

/**
 * To download the file we upload in totara_core filearea
 *
 * @param $course
 * @param $cm
 * @param $context
 * @param $filearea
 * @param $args
 * @param $forcedownload
 * @param array $options
 * @return void Download the file
 */
function totara_core_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options=array()) {
    $component = 'totara_core';
    $itemid = $args[0];
    $filename = $args[1];
    $fs = get_file_storage();

    $file = $fs->get_file($context->id, $component, $filearea, $itemid, '/', $filename);

    if (empty($file)) {
        send_file_not_found();
    }

    send_stored_file($file, 60*60*24, 0, false, $options); // Enable long cache and disable forcedownload.
}

/**
 * Resize all images found in a filearea.
 *
 * @param int $contextid Context id where image(s) are
 * @param string $component Component where image(s) are
 * @param string $filearea Filearea where image(s) are
 * @param int $itemid Itemid where image(s) are
 * @param int $width Width that the image(s) should have
 * @param int $height Height that the image(s) should have
 * @param bool $replace If true, replace the file for the resized one
 * @return array $resizedimages Array of resized images
 */
function totara_resize_images_filearea($contextid, $component, $filearea, $itemid, $width, $height, $replace=false) {
    global $CFG, $USER;
    require_once($CFG->dirroot .'/lib/gdlib.php');

    $resizedimages = array();
    $fs = get_file_storage();
    $files = $fs->get_area_files($contextid, $component, $filearea, $itemid, 'id');

    foreach ($files as $file) {
        if (!$file->is_valid_image()) {
            continue;
        }
        $tmproot = make_temp_directory('thumbnails');
        $tmpfilepath = $tmproot . '/' . $file->get_contenthash();
        $file->copy_content_to($tmpfilepath);
        $imageinfo = getimagesize($tmpfilepath);
        if (empty($imageinfo) || ($imageinfo[0] <= $width && $imageinfo[1] <= $height)) {
            continue;
        }
        // Generate thumbnail.
        $data = generate_image_thumbnail($tmpfilepath, $width, $height);
        $resizedimages[] = $data;
        unlink($tmpfilepath);

        if ($replace) {
            $record = array(
                'contextid' => $file->get_contextid(),
                'component' => $file->get_component(),
                'filearea'  => $file->get_filearea(),
                'itemid'    => $file->get_itemid(),
                'filepath'  => '/',
                'filename'  => $file->get_filename(),
                'status'    => $file->get_status(),
                'source'    => $file->get_source(),
                'author'    => $file->get_author(),
                'license'   => $file->get_license(),
                'mimetype'  => $file->get_mimetype(),
                'userid'    => $USER->id,
            );
            $file->delete();
            $fs->create_file_from_string($record, $data);
        }
    }
    return $resizedimages ;
}

/**
 * Create recursively totara menu table
 *
 * @param html_table $table to add data to.
 * @param totara_core_menu $item to render
 * @param int $depth of the category.
 * @param bool $up true if this category can be moved up.
 * @param bool $down true if this category can be moved down.
 */
function totara_menu_table_load(html_table &$table, \totara_core\totara\menu\menu $item, $depth = 0, $up = false, $down = false) {
    global $OUTPUT;

    static $str = null;

    if (is_null($str)) {
        $str = new stdClass;
        $str->edit = new lang_string('edit');
        $str->delete = new lang_string('delete');
        $str->moveup = new lang_string('moveup');
        $str->movedown = new lang_string('movedown');
        $str->hide = new lang_string('hide');
        $str->show = new lang_string('show');
        $str->spacer = $OUTPUT->spacer(array('width' => 11, 'height' => 11));
    }

    if ($item->id) {
        $node = \totara_core\totara\menu\menu::node_instance($item->get_property());
        if ($node === false) {
            // Bad node, don't display.
            return;
        }
        $dimmed = ($item->visibility ? '' : ' dimmed');
        $url = '/totara/core/menu/index.php';
        $itemurl = new moodle_url($node->get_url());
        $itemurl = html_writer::link($itemurl, $itemurl, array('class' => $dimmed));
        $itemtitle = $node->get_title();
        $attributes = array();
        $attributes['title'] = $str->edit;
        $attributes['class'] = 'totara_item_depth'.$depth.$dimmed;
        $itemtitle = html_writer::link(new moodle_url('/totara/core/menu/edit.php',
                array('id' => $item->id)), $itemtitle, $attributes);

        $icons = array();
        // Edit category.
        $icons[] = $OUTPUT->action_icon(
                        new moodle_url('/totara/core/menu/edit.php', array('id' => $item->id)),
                        new pix_icon('t/edit', $str->edit, 'moodle', array('class' => 'iconsmall')),
                        null, array('title' => $str->edit)
        );
        // Change visibility.
        if ($item->visibility != \totara_core\totara\menu\menu::HIDE_ALWAYS) {
            $icons[] = $OUTPUT->action_icon(
                            new moodle_url($url, array('hideid' => $item->id, 'sesskey' => sesskey())),
                            new pix_icon('t/hide', $str->hide, 'moodle', array('class' => 'iconsmall')),
                            null, array('title' => $str->hide)
            );
        } else {
            $icons[] = $OUTPUT->action_icon(
                            new moodle_url($url, array('showid' => $item->id, 'sesskey' => sesskey())),
                            new pix_icon('t/show', $str->show, 'moodle', array('class' => 'iconsmall')),
                            null, array('title' => $str->show)
            );
        }
        // Move up/down.
        if ($up) {
            $icons[] = $OUTPUT->action_icon(
                            new moodle_url($url, array('moveup' => $item->id, 'sesskey' => sesskey())),
                            new pix_icon('t/up', $str->moveup, 'moodle', array('class' => 'iconsmall')),
                            null, array('title' => $str->moveup)
            );
        } else {
            $icons[] = $str->spacer;
        }
        if ($down) {
            $icons[] = $OUTPUT->action_icon(
                            new moodle_url($url, array('movedown' => $item->id, 'sesskey' => sesskey())),
                            new pix_icon('t/down', $str->movedown, 'moodle', array('class' => 'iconsmall')),
                            null, array('title' => $str->movedown)
            );
        } else {
            $icons[] = $str->spacer;
        }
        // Delete item.
        if ($item->custom == \totara_core\totara\menu\menu::DB_ITEM) {
            $icons[] = $OUTPUT->action_icon(
                            new moodle_url('/totara/core/menu/delete.php', array('id' => $item->id)),
                            new pix_icon('t/delete', $str->delete, 'moodle', array('class' => 'iconsmall')),
                            null, array('title' => $str->delete)
            );
        }

        $table->data[] = new html_table_row(array(
                         new html_table_cell($itemtitle),
                         new html_table_cell($itemurl),
                         new html_table_cell(\totara_core\totara\menu\menu::get_visibility($item->visibility)),
                         new html_table_cell(join(' ', $icons)),
        ));
    }

    if ($items = $item->get_children()) {

        // Print all the children recursively.
        $countitems = count($items);
        $count = 0;
        $first = true;
        $last  = false;
        foreach ($items as $node) {

            $count++;
            if ($count == $countitems) {
                $last = true;
            }
            $up    = $first ? false : true;
            $down  = $last  ? false : true;
            $first = false;

            totara_menu_table_load($table, $node, $depth+1, $up, $down);
        }
    }
}
