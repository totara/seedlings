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
 * @package totara
 * @subpackage totara_sync
 */

require_once($CFG->dirroot.'/admin/tool/totara_sync/elements/classes/element.class.php');
require_once($CFG->dirroot.'/totara/customfield/fieldlib.php');
require_once($CFG->dirroot.'/totara/hierarchy/prefix/position/lib.php');

class totara_sync_element_user extends totara_sync_element {

    protected $customfieldsdb = array();

    function get_name() {
        return 'user';
    }

    function has_config() {
        return true;
    }

    /**
     * Set customfieldsdb property with menu of choices options
     */
    function set_customfieldsdb() {
        global $DB;

        $rs = $DB->get_recordset('user_info_field', array(), '', 'id,shortname,datatype,required,locked,forceunique,param1');
        if ($rs->valid()) {
            foreach ($rs as $r) {
                $this->customfieldsdb['customfield_'.$r->shortname]['id'] = $r->id;
                $this->customfieldsdb['customfield_'.$r->shortname]['required'] = $r->required;
                $this->customfieldsdb['customfield_'.$r->shortname]['forceunique'] = $r->forceunique;

                if ($r->datatype == 'menu') {
                    $this->customfieldsdb['customfield_'.$r->shortname]['menu_options'] = array_map('strtolower', explode("\n", $r->param1));
                }
            }
        }
        $rs->close();
    }

    function config_form(&$mform) {
        $mform->addElement('selectyesno', 'sourceallrecords', get_string('sourceallrecords', 'tool_totara_sync'));
        $mform->addElement('static', 'sourceallrecordsdesc', '', get_string('sourceallrecordsdesc', 'tool_totara_sync'));

        // User email settings.
        $mform->addElement('selectyesno', 'allowduplicatedemails', get_string('allowduplicatedemails', 'tool_totara_sync'));
        $mform->addElement('text', 'defaultsyncemail', get_string('defaultemailaddress', 'tool_totara_sync'), array('size' => 50));
        $mform->addElement('static', 'emailsettingsdesc', '', get_string('emailsettingsdesc', 'tool_totara_sync'));
        $mform->setType('defaultsyncemail', PARAM_TEXT);
        $mform->disabledIf('defaultsyncemail', 'allowduplicatedemails', 'eq', 0);
        $mform->setDefault('defaultsyncemail', '');


        // User password settings.
        $mform->addElement('selectyesno', 'ignoreexistingpass', get_string('ignoreexistingpass', 'tool_totara_sync'));
        $mform->addElement('static', 'ignoreexistingpassdesc', '', get_string('ignoreexistingpassdesc', 'tool_totara_sync'));
        $mform->addElement('selectyesno', 'forcepwchange', get_string('forcepwchange', 'tool_totara_sync'));
        $mform->addElement('static', 'forcepwchangedesc', '', get_string('forcepwchange', 'tool_totara_sync'));

        $mform->addElement('header', 'crudheading', get_string('allowedactions', 'tool_totara_sync'));
        $mform->addElement('checkbox', 'allow_create', get_string('create', 'tool_totara_sync'));
        $mform->setDefault('allow_create', 1);
        $mform->addElement('checkbox', 'allow_update', get_string('update', 'tool_totara_sync'));
        $mform->setDefault('allow_update', 1);
        $mform->addElement('checkbox', 'allow_delete', get_string('delete', 'tool_totara_sync'));
        $mform->setDefault('allow_delete', 1);
        $mform->setExpanded('crudheading');
    }

    function config_save($data) {
        $this->set_config('sourceallrecords', $data->sourceallrecords);
        $this->set_config('allowduplicatedemails', $data->allowduplicatedemails);
        $this->set_config('defaultsyncemail', $data->defaultsyncemail);
        $this->set_config('ignoreexistingpass', $data->ignoreexistingpass);
        $this->set_config('forcepwchange', $data->forcepwchange);
        $this->set_config('allow_create', !empty($data->allow_create));
        $this->set_config('allow_update', !empty($data->allow_update));
        $this->set_config('allow_delete', !empty($data->allow_delete));
        if (!empty($data->source_user)) {
            $source = $this->get_source($data->source_user);
            // Build link to source config.
            $url = new moodle_url('/admin/tool/totara_sync/admin/sourcesettings.php', array('element' => $this->get_name(), 'source' => $source->get_name()));
            if ($source->has_config()) {
                // Set import_deleted and warn if necessary.
                $import_deleted_new = ($data->sourceallrecords == 0) ? '1' : '0';
                $import_deleted_old = $source->get_config('import_deleted');
                if ($import_deleted_new != $import_deleted_old) {
                    $source->set_config('import_deleted', $import_deleted_new);
                    totara_set_notification(get_string('checkuserconfig', 'tool_totara_sync', $url->out()), null, array('class'=>'notifynotice'));
                }
            }
        }
    }

    function sync() {
        global $DB, $CFG;

        $this->addlog(get_string('syncstarted', 'tool_totara_sync'), 'info', 'usersync');
        // Array to store the users we create or update that
        // will need to have their assignments synced.
        $assign_sync_users = array();

        try {
            // This can go wrong in many different ways - catch as a generic exception.
            $synctable = $this->get_source_sync_table();
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (debugging()) {
                $msg .= !empty($e->debuginfo) ? " - {$e->debuginfo}" : '';
            }
            totara_sync_log($this->get_name(), $msg, 'error', 'unknown');
            return false;
        }

        try {
            // This can go wrong in many different ways - catch as a generic exception.
            $synctable_clone = $this->get_source_sync_table_clone($synctable);
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (debugging()) {
                $msg .= !empty($e->debuginfo) ? " - {$e->debuginfo}" : '';
            }
            totara_sync_log($this->get_name(), $msg, 'error', 'unknown');
            return false;
        }

        $this->set_customfieldsdb();

        $invalidids = $this->check_sanity($synctable, $synctable_clone);
        $issane = (empty($invalidids) ? true : false);

        // Delete obsolete users.
        if (!empty($this->config->allow_delete)) {
            if (empty($this->config->sourceallrecords)) {
                // Get records with "deleted" flag set.
                $sql = "SELECT DISTINCT u.id, u.idnumber, u.auth
                         FROM {{$synctable}} s
                   INNER JOIN {user} u ON (s.idnumber = u.idnumber AND u.idnumber != '')
                        WHERE u.totarasync=1 AND u.deleted = 0 AND s.deleted = 1";
            } else {
                // All records provided by source - get missing user records.
                $sql = "SELECT DISTINCT u.id, u.idnumber, u.auth
                          FROM {user} u
               LEFT OUTER JOIN {{$synctable}} s ON (u.idnumber = s.idnumber AND u.idnumber != '')
                         WHERE u.totarasync=1 AND s.idnumber IS NULL AND u.deleted=0";
            }
            if ($rs = $DB->get_recordset_sql($sql)) {
                foreach ($rs as $user) {
                    // Remove user.
                    try {
                        // Do not delete the records which have invalid values(e.g. spelling mistake).
                        if (array_search($user->idnumber, $invalidids) === false) {
                            delete_user($DB->get_record('user', array('id' => $user->id)));
                            $this->addlog(get_string('deleteduserx', 'tool_totara_sync', $user->idnumber), 'info', 'deleteuser');
                        }
                    } catch (Exception $e) {
                        throw new totara_sync_exception('user', 'deleteuser', 'cannotdeleteuserx', $user->idnumber, $e->getMessage());
                    }
                }
                $rs->close();
            }
        }

        if (isset($this->config->sourceallrecords) && $this->config->sourceallrecords == 0) {
            // Remove the deleted records from the sync table.
            // This ensures that our create/update queries runs smoothly.
            $DB->execute("DELETE FROM {{$synctable}} WHERE deleted = 1");
            $DB->execute("DELETE FROM {{$synctable_clone}} WHERE deleted = 1");
        }

        if (!empty($this->config->allow_update)) {
            // This must be done before creating new accounts because once the accounts are created this query would return them as well,
            // even when they do not need to be updated.
            $sql = "SELECT s.*, u.id AS uid
                      FROM {user} u
                INNER JOIN {{$synctable}} s ON (u.idnumber = s.idnumber AND u.idnumber != '')
                     WHERE u.totarasync=1
                       AND (s.timemodified = 0 OR u.timemodified != s.timemodified)";  // If no timemodified, always update.
            $rsupdateaccounts = $DB->get_recordset_sql($sql);
        }

        if (!empty($this->config->allow_create)) {
            // Get accounts that must be created.
            $sql = "SELECT s.*
                      FROM {{$synctable}} s
           LEFT OUTER JOIN {user} u ON (s.idnumber=u.idnumber)
                     WHERE (u.idnumber IS NULL AND s.idnumber IS NOT NULL)";
            $rscreateaccounts = $DB->get_recordset_sql($sql);

            // The idea of doing this is to get the accounts that need to be created. Since users are created first and then user assignments,
            // it is not possible (after creating users) to know which accounts need to be created.
            $DB->execute("DELETE FROM {{$synctable_clone}}
                           WHERE idnumber IN (
                          SELECT s.idnumber
                            FROM {user} u
                      INNER JOIN {{$synctable}} s ON (u.idnumber = s.idnumber AND u.idnumber != ''))");

            if ($rscreateaccounts->valid()) {
                // Create missing accounts.
                foreach ($rscreateaccounts as $suser) {
                    try {
                        $this->create_user($suser);
                        $this->addlog(get_string('createduserx', 'tool_totara_sync', $suser->idnumber), 'info', 'createuser');
                    } catch (Exception $e) {
                        $this->addlog(get_string('cannotcreateuserx', 'tool_totara_sync', $suser->idnumber), 'error', 'createuser');
                    }
                }
                $rscreateaccounts->close(); // Free memory.

                // Get data for user assignments for assignment sync later.
                $sql = "SELECT sc.*, u.id as uid
                          FROM {{$synctable_clone}} sc
                    INNER JOIN {user} u ON (sc.idnumber = u.idnumber AND u.idnumber != '')";
                $rscreateassignments = $DB->get_recordset_sql($sql);
                foreach ($rscreateassignments as $suser) {
                    $assign_sync_users[] = $suser;
                }
                $rscreateassignments->close(); // Free memory.
            }
        }

        if (!empty($this->config->allow_update) && $rsupdateaccounts->valid()) {
            foreach ($rsupdateaccounts as $suser) {
                $user = $DB->get_record('user', array('id' => $suser->uid));

                if (!empty($this->config->allow_create) && !empty($user->deleted)) {
                    // Revive previously-deleted user.
                    if (undelete_user($user)) {
                        $user->deleted = 0;

                        // Tag the revived user for new password generation (if applicable).
                        $userauth = get_auth_plugin(strtolower($user->auth));
                        if ($userauth->can_change_password()) {
                            set_user_preference('auth_forcepasswordchange', 1, $user->id);
                            set_user_preference('create_password',          1, $user->id);
                        }
                        unset($userauth);

                        $this->addlog(get_string('reviveduserx', 'tool_totara_sync', $suser->idnumber), 'info', 'updateusers');
                    } else {
                        $this->addlog(get_string('cannotreviveuserx', 'tool_totara_sync', $suser->idnumber), 'warn', 'updateusers');
                    }
                }

                // Check if the user is going to be suspended before updating the $user object.
                $suspenduser = $user->suspended == 0 && (isset($suser->suspended) && $suser->suspended == 1);

                $transaction = $DB->start_delegated_transaction();

                // Update user.
                $this->set_sync_user_fields($user, $suser);

                try {
                    $DB->update_record('user', $user);
                } catch (dml_exception $e) {
                    $transaction->rollback($e);
                    throw new totara_sync_exception('user', 'updateusers', 'cannotupdateuserx', $user->idnumber, $e->getMessage());
                }

                // Update user password.
                if (empty($this->config->ignoreexistingpass) && isset($suser->password) && trim($suser->password) !== '') {
                    $userauth = get_auth_plugin(strtolower($user->auth));
                    if ($userauth->can_change_password()) {
                        if (!$userauth->user_update_password($user, $suser->password)) {
                            $this->addlog(get_string('cannotsetuserpassword', 'tool_totara_sync', $user->idnumber), 'warn', 'updateusers');
                        }
                    } else {
                        $this->addlog(get_string('cannotsetuserpasswordnoauthsupport', 'tool_totara_sync', $user->idnumber), 'warn', 'updateusers');
                    }
                    unset($userauth);
                }

                // Store user data for assignment sync later.
                $assign_sync_users[] = $suser;
                // Update custom field data.
                $user = $this->put_custom_field_data($user, $suser);

                $this->addlog(get_string('updateduserx', 'tool_totara_sync', $suser->idnumber), 'info', 'updateusers');

                $transaction->allow_commit();

                if ($suspenduser) {
                    $event = \totara_core\event\user_suspended::create(
                        array(
                            'objectid' => $user->id,
                            'context' => context_user::instance($user->id),
                            'other' => array(
                                'username' => $user->username,
                            )
                        )
                    );
                    $event->trigger();
                }

                $event = \core\event\user_updated::create(
                    array(
                        'objectid' => $user->id,
                        'context' => context_user::instance($user->id),
                    )
                );
                $event->trigger();
            }
            $rsupdateaccounts->close();
            unset($user, $pos_assignment, $posdata); // Free memory.
        }

        // Process the assignments after all the user records have been
        // created and updated so we know they're in the right state.
        foreach ($assign_sync_users as $suser) {
            try {
                $this->sync_user_assignments($suser->uid, $suser);
            } catch (Exception $e) {
                throw new totara_sync_exception('user', 'syncuserassignments', 'cannotcreateuserassignments', $suser->idnumber, $e->getMessage());
            }
        }
        // Free memory used by user assignment array.
        unset($assign_sync_users);

        $this->get_source()->drop_table();
        $this->addlog(get_string('syncfinished', 'tool_totara_sync'), 'info', 'usersync');

        return $issane;
    }

    /**
     * Create a user
     *
     * @param stdClass $suser escaped sync user object
     *
     * @return boolean true if successful
     * @throws totara_sync_exception
     */
    function create_user($suser) {
        global $CFG, $DB;

        $transaction = $DB->start_delegated_transaction();

        // Prep a few params.
        $user = new stdClass;
        $user->username = strtolower($suser->username);  // Usernames always lowercase in moodle.
        $user->idnumber = $suser->idnumber;
        $user->confirmed = 1;
        $user->totarasync = 1;
        $user->mnethostid = $CFG->mnet_localhost_id;
        $user->lang = $CFG->lang;
        $user->timecreated = time();
        $user->auth = isset($suser->auth) ? core_text::strtolower($suser->auth) : 'manual';
        $this->set_sync_user_fields($user, $suser);

        try {
            $user->id = $DB->insert_record('user', $user);  // Insert user.
        } catch (Exception $e) {
            $transaction->rollback($e);
            throw new totara_sync_exception('user', 'createusers', 'cannotcreateuserx', $user->idnumber);
        }

        $userauth = get_auth_plugin(strtolower($user->auth));
        if ($userauth->can_change_password()) {
            if (!isset($suser->password) || trim($suser->password) === '') {
                // Tag for password generation.
                set_user_preference('auth_forcepasswordchange', 1, $user->id);
                set_user_preference('create_password',          1, $user->id);
            } else {
                // Set user password.
                if (!$userauth->user_update_password($user, $suser->password)) {
                    $this->addlog(get_string('cannotsetuserpassword', 'tool_totara_sync', $user->idnumber), 'warn', 'createusers');
                } else if (!empty($this->config->forcepwchange)) {
                    set_user_preference('auth_forcepasswordchange', 1, $user->id);
                }
            }
        }
        unset($userauth);
        // Update custom field data.
        $user = $this->put_custom_field_data($user, $suser);

        $transaction->allow_commit();

        $event = \core\event\user_created::create(
            array(
                'objectid' => $user->id,
                'context' => context_user::instance($user->id),
            )
        );
        $event->trigger();

        return true;
    }

    /**
     * Store the custom field data for the given user.
     *
     * @param stdClass $suser escaped sync user object
     */
    public function put_custom_field_data($user, $suser) {
        global $CFG;

        $customfields = json_decode($suser->customfields);

        if ($customfields) {
            require_once($CFG->dirroot.'/user/profile/lib.php');
            foreach ($customfields as $name => $value) {
                $profile = str_replace('customfield_', 'profile_field_', $name);
                // If the custom field is a menu, we need to use the option index rather than the value.
                $user->{$profile} = (isset($this->customfieldsdb[$name]['menu_options'])) ? array_search(strtolower($value), $this->customfieldsdb[$name]['menu_options']) : $value;
            }
            profile_save_data($user);
        }

        return $user;
    }

    /**
     * Sync a user's position assignments
     *
     * @return boolean true on success
     */
    function sync_user_assignments($userid, $suser) {
        global $DB;

        $pos_assignment = new position_assignment(array(
            'userid' => $userid,
            'type' => POSITION_TYPE_PRIMARY
        ));

        // If we have no position info at all we do not need to set a position.
        if (!isset($suser->postitle) && empty($suser->posidnumber) && !isset($suser->posstartdate)
            && !isset($suser->posenddate) && empty($suser->orgidnumber) && empty($suser->manageridnumber)
            && empty($suser->appraiseridnumber)) {
            return false;
        }
        $posdata = new stdClass;
        $posdata->fullname = $pos_assignment->fullname;
        $posdata->shortname = $pos_assignment->shortname;
        $posdata->positionid = $pos_assignment->positionid;
        $posdata->organisationid = $pos_assignment->organisationid;
        $posdata->managerid = $pos_assignment->managerid;
        $posdata->appraiserid = $pos_assignment->appraiserid;
        if (isset($suser->postitle)) {
            $posdata->fullname = $suser->postitle;
            $posdata->shortname = empty($suser->postitleshortname) ? $suser->postitle : $suser->postitleshortname;
        }
        if (isset($suser->posidnumber)) {
            if (empty($suser->posidnumber)) {
                // Reset values.
                $posdata->positionid = 0;
            } else {
                $pos = $DB->get_record('pos', array('idnumber' => $suser->posidnumber));
                $posdata->positionid = $pos->id;
            }
        }
        if (isset($suser->posstartdate)) {
            if (empty($suser->posstartdate)) {
                $posdata->timevalidfrom = null;
            } else {
                $posdata->timevalidfrom = $suser->posstartdate;
            }
        }
        if (isset($suser->posenddate)) {
            if (empty($suser->posenddate)) {
                $posdata->timevalidto = null;
            } else {
                $posdata->timevalidto = $suser->posenddate;
            }
        }
        if (isset($suser->orgidnumber)) {
            if (empty($suser->orgidnumber)) {
                $posdata->organisationid = 0;
            } else {
                $posdata->organisationid = $DB->get_field('org', 'id', array('idnumber' => $suser->orgidnumber));
            }
        }
        if (isset($suser->manageridnumber)) {
            if (empty($suser->manageridnumber)) {
                $posdata->managerid = null;
            } else {
                try {
                    $posdata->managerid = $DB->get_field('user', 'id', array('idnumber' => $suser->manageridnumber, 'deleted' => 0), MUST_EXIST);
                } catch (dml_missing_record_exception $e) {
                    $posdata->managerid = null;
                }
            }
        }
        if (isset($suser->appraiseridnumber)) {
            if (empty($suser->appraiseridnumber)) {
                $posdata->appraiserid = null;
            } else {
                try {
                    $posdata->appraiserid = $DB->get_field('user', 'id',
                            array('idnumber' => $suser->appraiseridnumber, 'deleted' => 0), MUST_EXIST);
                } catch (dml_missing_record_exception $e) {
                    $posdata->appraiserid = null;
                }
            }
        }

        position_assignment::set_properties($pos_assignment, $posdata);

        $pos_assignment->managerid = $posdata->managerid;
        assign_user_position($pos_assignment);

        return true;
    }

    function set_sync_user_fields(&$user, $suser) {
        global $CFG;

        $fields = array('address', 'city', 'country', 'department', 'description',
            'email', 'firstname', 'institution', 'lang', 'lastname', 'firstnamephonetic',
            'lastnamephonetic', 'middlename', 'alternatename', 'phone1', 'phone2',
            'timemodified', 'timezone', 'url', 'username', 'suspended', 'emailstop', 'auth');

        $requiredfields = array('username', 'firstname', 'lastname', 'email');

        foreach ($fields as $field) {
            if (isset($suser->$field)) {
                if (!in_array($field, $requiredfields) || trim($suser->$field) !== '') {
                    // Not an empty required field - other fields are allowed to be empty.
                    // Handle exceptions first.
                    switch ($field) {
                        case 'username':
                            // Must be lower case.
                            $user->$field = strtolower($suser->$field);
                            break;
                        case 'country':
                            if (!empty($suser->$field)) {
                                // Must be upper case.
                                $user->$field = strtoupper($suser->$field);
                            } else if (empty($user->$field) && isset($CFG->country) && !empty($CFG->country)) {
                                // Sync and target are both empty - so use the default country.
                                $user->$field = $CFG->country;
                            }
                            break;
                        case 'city':
                            if (!empty($suser->$field)) {
                                $user->$field = $suser->$field;
                            } else if (empty($user->$field) && isset($CFG->defaultcity) && !empty($CFG->defaultcity)) {
                                // Sync and target are both empty - So use the default city.
                                $user->$field = $CFG->defaultcity;
                            }
                            break;
                        case 'timemodified':
                            // Default to now.
                            $user->$field = empty($suser->$field) ? time() : $suser->$field;
                            break;
                        default:
                            $user->$field = $suser->$field;
                    }
                }
            }
        }

        // If there is no email, check the default email.
        $usedefaultemail = !empty($this->config->allowduplicatedemails) && !empty($this->config->defaultsyncemail);
        if (empty($suser->email) && empty($user->email) && $usedefaultemail) {
            $user->email = $this->config->defaultsyncemail;
        }

        $user->suspended = empty($suser->suspended) ? 0 : $suser->suspended;
    }

    /**
     * Check if the data contains invalid values
     *
     * @param string $synctable sync table name
     * @param string $synctable_clone sync clone table name
     *
     * @return boolean true if the data is valid, false otherwise
     */
    function check_sanity($synctable, $synctable_clone) {
        global $DB;

        // Get a row from the sync table, so we can check field existence.
        if (!$syncfields = $DB->get_record_sql("SELECT * FROM {{$synctable}}", null, IGNORE_MULTIPLE)) {
            return; // Nothing to check.
        }

        $issane = array();
        $invalidids = array();
        // Get duplicated idnumbers.
        $badids = $this->get_duplicated_values($synctable, $synctable_clone, 'idnumber', 'duplicateuserswithidnumberx');
        $invalidids = array_merge($invalidids, $badids);
        // Get empty idnumbers.
        $badids = $this->check_empty_values($synctable, 'idnumber', 'emptyvalueidnumberx');
        $invalidids = array_merge($invalidids, $badids);

        // Get duplicated usernames.
        $badids = $this->get_duplicated_values($synctable, $synctable_clone, 'username', 'duplicateuserswithusernamex');
        $invalidids = array_merge($invalidids, $badids);
        // Get empty usernames.
        $badids = $this->check_empty_values($synctable, 'username', 'emptyvalueusernamex');
        $invalidids = array_merge($invalidids, $badids);
        // Check usernames against the DB to avoid saving repeated values.
        $badids = $this->check_values_in_db($synctable, 'username', 'duplicateusernamexdb');
        $invalidids = array_merge($invalidids, $badids);

        if (!isset($this->config->allowduplicatedemails)) {
            $this->config->allowduplicatedemails = 0;
        }
        if (!isset($this->config->ignoreexistingpass)) {
            $this->config->ignoreexistingpass = 0;
        }
        if (isset($syncfields->email) && !$this->config->allowduplicatedemails) {
            // Get duplicated emails.
            $badids = $this->get_duplicated_values($synctable, $synctable_clone, 'email', 'duplicateuserswithemailx');
            $invalidids = array_merge($invalidids, $badids);
            // Get empty emails.
            $badids = $this->check_empty_values($synctable, 'email', 'emptyvalueemailx');
            $invalidids = array_merge($invalidids, $badids);
            // Check emails against the DB to avoid saving repeated values.
            $badids = $this->check_values_in_db($synctable, 'email', 'duplicateusersemailxdb');
            $invalidids = array_merge($invalidids, $badids);
        }

        // Get invalid options (in case of menu of choices).
        if ($syncfields->customfields != '[]') {
            $badids = $this->validate_custom_fields($synctable);
            $invalidids = array_merge($invalidids, $badids);
        }

        // The idea of this loop is to make sure that all users in the synctable are valid regardless of the order they are created.
        // Example: user1 is valid but his manager is not and his manager is checked later, so user1 will be marked as valid when he is not.
        // This loop avoids that behaviour by checking in each iteration if there are still invalid users.
        while (1) {
            // Get invalid positions.
            if (isset($syncfields->posidnumber)) {
                $badids = $this->get_invalid_org_pos($synctable, 'pos', 'posidnumber', 'posxnotexist');
                $invalidids = array_merge($invalidids, $badids);
            }

            // Get invalid orgs.
            if (isset($syncfields->orgidnumber)) {
                $badids = $this->get_invalid_org_pos($synctable, 'org', 'orgidnumber', 'orgxnotexist');
                $invalidids = array_merge($invalidids, $badids);
            }

            // Get invalid managers and self-assigned users.
            if (isset($syncfields->manageridnumber)) {
                $badids = $this->get_invalid_roles($synctable, $synctable_clone, 'manager');
                $invalidids = array_merge($invalidids, $badids);
                $badids = $this->check_self_assignment($synctable, 'manageridnumber', 'selfassignedmanagerx');
                $invalidids = array_merge($invalidids, $badids);
            }

            // Get invalid appraisers and self-assigned users.
            if (isset($syncfields->appraiseridnumber)) {
                $badids = $this->get_invalid_roles($synctable, $synctable_clone, 'appraiser');
                $invalidids = array_merge($invalidids, $badids);
                $badids = $this->check_self_assignment($synctable, 'appraiseridnumber', 'selfassignedappraiserx');
                $invalidids = array_merge($invalidids, $badids);
            }

            if (count($invalidids)) {
                list($badids, $params) = $DB->get_in_or_equal($invalidids);
                // Collect idnumber for records which are invalid.
                $rs = $DB->get_records_sql("SELECT id, idnumber FROM {{$synctable}} WHERE id $badids", $params);
                foreach ($rs as $id => $record) {
                    $issane[] = $record->idnumber;
                }
                $DB->delete_records_select($synctable, "id $badids", $params);
                $DB->delete_records_select($synctable_clone, "id $badids", $params);
                $invalidids = array();
            } else {
                break;
            }
        }

        return $issane;
    }

    /**
     * Get duplicated values for a specific field
     *
     * @param string $synctable sync table name
     * @param string $synctable_clone sync clone table name
     * @param string $field field name
     * @param string $identifier for logging messages
     *
     * @return array with invalid ids from synctable for duplicated values
     */
    function get_duplicated_values($synctable, $synctable_clone, $field, $identifier) {
        global $DB;

        $params = array();
        $invalidids = array();
        $extracondition = '';
        if (empty($this->config->sourceallrecords)) {
            $extracondition = "WHERE deleted = ?";
            $params[0] = 0;
        }
        $sql = "SELECT id, idnumber, $field
                  FROM {{$synctable}}
                 WHERE $field IN (SELECT $field FROM {{$synctable_clone}} $extracondition GROUP BY $field HAVING count($field) > 1)";
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $r) {
            $this->addlog(get_string($identifier, 'tool_totara_sync', $r), 'error', 'checksanity');
            $invalidids[] = $r->id;
        }
        $rs->close();

        return $invalidids;
    }

    /**
     * Get invalid organisations or positions
     *
     * @param string $synctable sync table name
     * @param string $table table name (org or pos)
     * @param string $field field name
     * @param string $identifier for logging messages
     *
     * @return array with invalid ids from synctable for organisations or positions that do not exist in the database
     */
    function get_invalid_org_pos($synctable, $table, $field, $identifier) {
        global $DB;

        $params = array();
        $invalidids = array();
        $sql = "SELECT s.id, s.idnumber, s.$field
                  FROM {{$synctable}} s
       LEFT OUTER JOIN {{$table}} t ON s.$field = t.idnumber
                 WHERE s.$field IS NOT NULL
                   AND s.$field != ''
                   AND t.idnumber IS NULL";
        if (empty($this->config->sourceallrecords)) {
            $sql .= ' AND s.deleted = ?'; // Avoid users that will be deleted.
            $params[0] = 0;
        }
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $r) {
            $this->addlog(get_string($identifier, 'tool_totara_sync', $r), 'error', 'checksanity');
            $invalidids[] = $r->id;
        }
        $rs->close();

        return $invalidids;
    }

    /**
     * Get invalid roles (such as managers or appraisers)
     *
     * @param string $synctable sync table name
     * @param string $synctable_clone sync clone table name
     * @param string $role Name of role to check e.g. 'manager' or 'appraiser'
     *                     There must be a {$role}idnumber field in the sync db table and '{$role}notexist'
     *                     language string in lang/en/tool_totara_sync.php
     *
     * @return array with invalid ids from synctable for roles that do not exist in synctable nor in the database
     */
    function get_invalid_roles($synctable, $synctable_clone, $role) {
        global $DB;

        $idnumberfield = "{$role}idnumber";
        $params = array();
        $invalidids = array();
        $sql = "SELECT s.id, s.idnumber, s.{$idnumberfield}
                  FROM {{$synctable}} s
       LEFT OUTER JOIN {user} u
                    ON s.{$idnumberfield} = u.idnumber
                 WHERE s.{$idnumberfield} IS NOT NULL
                   AND s.{$idnumberfield} != ''
                   AND u.idnumber IS NULL
                   AND s.{$idnumberfield} NOT IN
                       (SELECT idnumber FROM {{$synctable_clone}})";
        if (empty($this->config->sourceallrecords)) {
            $sql .= ' AND s.deleted = ?'; // Avoid users that will be deleted.
            $params[0] = 0;
        }
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $r) {
            $this->addlog(get_string($role.'xnotexist', 'tool_totara_sync', $r), 'error', 'checksanity');
            $invalidids[] = $r->id;
        }
        $rs->close();

        return $invalidids;
    }

    /**
     * Ensure options from menu of choices are valid
     *
     * @param string $synctable sync table name
     *
     * @return array with invalid ids from synctable for options that do not exist in the database
     */
    public function validate_custom_fields($synctable) {
        global $DB;

        $params = empty($this->config->sourceallrecords) ? array('deleted' => 0) : array();
        $invalidids = array();
        $rs = $DB->get_recordset($synctable, $params, '', 'id, idnumber, customfields');

        // Keep track of the fields that need to be tested for having unique values.
        $unique_fields = array ();

        foreach ($rs as $r) {
            $customfields = json_decode($r->customfields, true);
            if (!empty($customfields)) {
                foreach ($customfields as $name => $value) {
                    // Check each of the fields that have attributes that may affect
                    // whether the sync data will be accepted or not.
                    if ($this->customfieldsdb[$name]['required'] && trim($value) == '') {
                        $this->addlog(get_string('fieldrequired', 'tool_totara_sync', (object)array('idnumber' => $r->idnumber, 'fieldname' => $name)), 'error', 'checksanity');
                        $invalidids[] = intval($r->id);
                        break;
                    } else if (isset($this->customfieldsdb[$name]['menu_options'])) {
                        // Check menu value matches one of the available options.
                        if (!in_array(strtolower($value), $this->customfieldsdb[$name]['menu_options'])) {
                            $this->addlog(get_string('optionxnotexist', 'tool_totara_sync', (object)array('idnumber' => $r->idnumber, 'option' => $value, 'fieldname' => $name)), 'error', 'checksanity');
                            $invalidids[] = intval($r->id);
                            break;
                        }
                    } else if ($this->customfieldsdb[$name]['forceunique']) {

                        $sql = "SELECT uid.data
                                  FROM {user} usr
                                  JOIN {user_info_data} uid ON usr.id = uid.userid
                                 WHERE usr.idnumber != :idnumber
                                   AND uid.fieldid = :fieldid
                                   AND uid.data = :data";
                        // Check that the sync value does not exist in the user info data.
                        $params = array ('idnumber' => $r->idnumber, 'fieldid' => $this->customfieldsdb[$name]['id'], 'data' => $value);
                        $cfdata = $DB->get_records_sql($sql, $params);
                        // If the value already exists in the database then flag an error. If not, record
                        // it in unique_fields to later verify that it's not duplicated in the sync data.
                        if ($cfdata) {
                            $this->addlog(get_string('fieldduplicated', 'tool_totara_sync', (object)array('idnumber' => $r->idnumber, 'fieldname' => $name, 'value' => $value)), 'error', 'checksanity');
                            $invalidids[] = intval($r->id);
                            break;
                        } else {
                            $unique_fields[$name][intval($r->id)] = array ( 'idnumber' => $r->idnumber, 'value' => $value);
                        }
                    }
                }
            }
        }
        $rs->close();

        // Process any data that must have unique values.
        foreach ($unique_fields as $fieldname => $fielddata) {

            // We need to get all the field values into
            // an array so we can extract the duplicate values.
            $field_values = array ();
            foreach ($fielddata as $id => $values) {
                $field_values[$id] = $values['value'];
            }

            // Build up an array from the field values
            // where there are duplicates.
            $error_ids = array ();
            foreach ($field_values as $id => $value) {
                // Get a list of elements that match the current value.
                $matches = array_keys($field_values, $value);
                // If we've got more than one then we've got duplicates.
                if (count($matches) >  1) {
                    $error_ids = array_merge($error_ids, $matches);
                }
            }

            // The above process will create multiple occurences
            // for each problem value so remove the duplicates.
            $error_ids = array_unique ($error_ids);
            natsort($error_ids);

            // Loop through the error ids and produce a sync log entry.
            foreach ($error_ids as $id) {
                $log_data = (object) array('idnumber' => $fielddata[$id]['idnumber'], 'fieldname' => $fieldname, 'value' => $fielddata[$id]['value']);
                $this->addlog(get_string('fieldmustbeunique', 'tool_totara_sync', $log_data), 'error', 'checksanity');
            }
            $invalidids = array_merge ($invalidids, $error_ids);
        }

        $invalidids = array_unique($invalidids);

        return $invalidids;
    }

    /**
     * Avoid saving values from synctable that already exist in the database
     *
     * @param string $synctable sync table name
     * @param string $field field name
     * @param string $identifier for logging messages
     *
     * @return array with invalid ids from synctable for usernames or emails that are already registered in the database
     */
    function check_values_in_db($synctable, $field, $identifier) {
        global $DB;

        $params = array();
        $invalidids = array();
        $sql = "SELECT s.id, s.idnumber, s.$field
                  FROM {{$synctable}} s
            INNER JOIN {user} u ON s.idnumber <> u.idnumber
                   AND s.$field = u.$field";
        if (empty($this->config->sourceallrecords)) {
            $sql .= ' AND s.deleted = ?'; // Avoid users that will be deleted.
            $params[0] = 0;
        }
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $r) {
            $this->addlog(get_string($identifier, 'tool_totara_sync', $r), 'error', 'checksanity');
            $invalidids[] = $r->id;
        }
        $rs->close();

        return $invalidids;
    }

    /**
     * Get users who are their own superior
     *
     * @param string $synctable sync table name
     * @param string $role that will be checked
     * @param string $identifier for logging messages
     *
     * @return array with invalid ids from synctable for users who are their own superior
     */
    function check_self_assignment($synctable, $role, $identifier) {
        global $DB;

        $params = array();
        $invalidids = array();
        $sql = "SELECT id, idnumber
                  FROM {{$synctable}}
                 WHERE idnumber = $role";
        if (empty($this->config->sourceallrecords)) {
            $sql .= ' AND deleted = ?'; // Avoid users that will be deleted.
            $params[0] = 0;
        }
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $r) {
            $this->addlog(get_string($identifier, 'tool_totara_sync', $r), 'error', 'checksanity');
            $invalidids[] = $r->id;
        }
        $rs->close();

        return $invalidids;
    }

    /**
     * Check empty values for fields that are required
     *
     * @param string $synctable sync table name
     * @param string $field that will be checked
     * @param string $identifier for logging messages
     *
     * @return array with invalid ids from synctable for empty fields that are required
     */
    function check_empty_values($synctable, $field, $identifier) {
        global $DB;

        $params = array();
        $invalidids = array();
        $sql = "SELECT id, idnumber
                  FROM {{$synctable}}
                 WHERE $field = ''";
        if (empty($this->config->sourceallrecords) && $field != 'idnumber') {
            $sql .= ' AND deleted = ?'; // Avoid users that will be deleted.
            $params[0] = 0;
        }
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $r) {
            $this->addlog(get_string($identifier, 'tool_totara_sync', $r), 'error', 'checksanity');
            $invalidids[] = $r->id;
        }
        $rs->close();

        return $invalidids;
    }
}
