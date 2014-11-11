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

class totara_message_workflow_plugin_base {

    /**
    * Constructor for the base totara_message workflow plugin class
    *
    * This is a stub providing hooks into the commandline options,
    * help and execution events for the processing of commandline
    *  Moodle processes.
    *
    *  for both accept and reject events $eventdata can be expected
    *  to look like:
    *    $onreject = new stdClass();
    *    $onreject->action = 'pingpong';
    *    $onreject->text = 'To stop playing please press reject';
    *    $onreject->data = array('forward_to' => $eventdata->userto->id, 'message' => 'You stopped playing :-(');
    *
    *  Where all events have action (the plugin invoked), text (description displayed to user), data (an arbitrary lump of
    *  data to be passed to the workflow plugin).
    */
    function totara_message_plugin_base() {

    }

    /**
     * Entry point to plugin onaccept.
     *
     * @param eventdata object - deserialised data stored for the message
     *                           for the onaccept event
     * @param $msg object - messages table entry for this message
     */
    function onaccept($eventdata, $msg) {

        throw new Exception('plugin onaccept not implemented');
        return true;
    }

    /**
     * Entry point to plugin onreject.
     *
     * @param eventdata object - deserialised data stored for the message
     *                           for the onreject event
     * @param $msg object - messages table entry for this message
     */
    function onreject($eventdata, $msg) {

        throw new Exception('plugin onreject not implemented');
        return true;
    }

}
