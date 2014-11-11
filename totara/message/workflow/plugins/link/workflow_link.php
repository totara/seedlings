<?php
/**
 *
 * @author  Piers Harding  piers@catalyst.net.nz
 * @version 0.0.1
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package local
 * @subpackage message
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/totara/plan/lib.php');

/**
* Extend the base plugin class
* This class contains the action for generic link/redirect onaccept message processing
*/
class totara_message_workflow_link extends totara_message_workflow_plugin_base {

    /**
     * Action called on accept for plan action
     *
     * @param array $eventdata
     * @param object $msg
     */
    function onaccept($eventdata, $msg) {
        global $USER, $CFG;
        $url = $eventdata['redirect'];

        // dismiss myself as will not reach the end
        // because of the redirect
        $result = tm_message_mark_message_read($msg, time());

        redirect($url);
        die(); // should not get here
    }


    /**
     * Action called on reject of a plan action
     *
     * @param array $eventdata
     * @param object $msg
     */
    function onreject($eventdata, $msg) {
        return true;
    }
}
