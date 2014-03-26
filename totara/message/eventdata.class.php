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
 * @author Aaron Wells <aaronw@catalyst.net.nz>
 * @package totara
 * @subpackage message
 */
/**
 * Classes to help in generating the $eventdata needed to send totara messages
 */

defined('MOODLE_INTERNAL') || die();

/**
 * A basic totara message
 */
class tm_message_eventdata {
    /**
     * The mdl_user row for the recipient
     * @var object
     */
    public $userto;

    /**
     * The mdl_user row for the sender
     * @var object
     */
    public $userfrom;

    /**
     * Should be one of the MSG_STATUS_* constants
     * @var int
     */
    public $msgstatus;

    /**
     * Should be one of the MSG_URGENCY_* constants
     * @var int
     */
    public $urgency;

    /**
     * Should be one of the MSG_TYPE_* constants
     * @var int
     */
    public $msgtype;

    public $subject;
    public $fullmessage;

    /**
     * Should be one of the FORMAT_* constants
     * @var int
     */
    public $fullmessageformat;
    public $fullmessagehtml;
    public $notification;
    public $contexturl;
    public $contexturlname;
    public $component;
    public $name;
    public $onaccept;
    public $onreject;

    public function __construct( $userto, $userfrom, $msgstatus, $urgency, $msgtype) {
        $this->userto = $userto;
        $this->userfrom = $userfrom;
        $this->msgstatus = $msgstatus;
        $this->urgency = $urgency;
        $this->msgtype = $msgtype;
    }

    public function set_onaccept( $action, $data) {
        $this->set_on_event('onaccept', $action, $data);
    }

    public function set_onreject( $action, $data) {
        $this->set_on_event('onreject', $action, $data);
    }

    private function set_on_event( $eventtype, $action, $data) {
        $this->{$eventtype} = new stdClass();
        $this->{$eventtype}->action = $action;
        $this->{$eventtype}->data = $data;
    }
}

/**
 * A totara "task" (a message which prompts the user to make an accept/reject choice)
 */
class tm_task_eventdata extends tm_message_eventdata {

    /**
     * It has its own constructor because only $userto is mandatory for a task
     * @param object $userto
     */
    public function __construct( $userto, $acceptrejectaction, $onacceptdata, $onrejectdata) {
        $this->userto = $userto;
        $this->set_onaccept($acceptrejectaction, $onacceptdata);
        $this->set_onreject($acceptrejectaction, $onrejectdata);
    }
}

/**
 * A totara "alert" (message which requires no response)
 */
class tm_alert_eventdata extends tm_message_eventdata {

    public function __construct($userto) {
        $this->userto = $userto;
    }
}
