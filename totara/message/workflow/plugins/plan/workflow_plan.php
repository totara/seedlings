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
* This class contains the action for IDP plan onaccept/onreject message processing
*/
class totara_message_workflow_plan extends totara_message_workflow_plugin_base {

    /**
     * Action called on accept for plan action
     *
     * @param array $eventdata
     * @param object $msg
     */
    function onaccept($eventdata, $msg) {
        // Load course.
        $userid = $eventdata['userid'];
        $planid = $eventdata['planid'];
        $reasonfordecision = (isset($eventdata['reasonfordecision'])) ? $eventdata['reasonfordecision'] : '';
        $plan = new development_plan($planid);
        if (!$plan) {
            print_error('planidnotfound', 'local_plan', $planid);
        }

        if (!in_array($plan->get_setting('approve'), array(DP_PERMISSION_ALLOW, DP_PERMISSION_APPROVE))) {
            return false;
        }

        // Change status.
        if (!$plan->set_status(DP_PLAN_STATUS_APPROVED, DP_PLAN_REASON_MANUAL_APPROVE, $reasonfordecision)) {
            return false;
        }

        return $plan->send_approved_alert($reasonfordecision);
    }


    /**
     * Action called on reject of a plan action
     *
     * @param array $eventdata
     * @param object $msg
     */
    function onreject($eventdata, $msg) {
        // Can manipulate the language by setting $SESSION->lang temporarily.
        // Load course.
        $userid = $eventdata['userid'];
        $planid = $eventdata['planid'];
        $reasonfordecision = (isset($eventdata['reasonfordecision'])) ? $eventdata['reasonfordecision'] : '';
        $plan = new development_plan($planid);
        if (!$plan) {
            print_error('planidnotfound', 'local_plan', $planid);
        }

        // Change status.
        if (!$plan->set_status(DP_PLAN_STATUS_UNAPPROVED, DP_PLAN_REASON_MANUAL_DECLINE, $reasonfordecision)) {
            return false;
        }

        if (!in_array($plan->get_setting('approve'), array(DP_PERMISSION_ALLOW, DP_PERMISSION_APPROVE))) {
            return false;
        }

        return $plan->send_declined_alert($reasonfordecision);
    }
}
