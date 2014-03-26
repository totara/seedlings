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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @package totara
 * @subpackage facetoface
 */

M.totara_f2f_attendees = M.totara_f2f_attendees || {

    Y: null,
    // optional php params and defaults defined here, args passed to init method
    // below will override these values
    config: {},
    // public handler reference for the dialog
    totaraDialog_handler_preRequisite: null,

    /**
     * module initialisation method called by php js_init_call()
     *
     * @param object    YUI instance
     * @param string    args supplied in JSON format
     */
    init: function(Y, args){
        var module = this;

        // save a reference to the Y instance (all of its dependencies included)
        this.Y = Y;

        // if defined, parse args into this module's config object
        if (args) {
            var jargs = Y.JSON.parse(args);
            for (var a in jargs) {
                if (Y.Object.owns(jargs, a)) {
                    this.config[a] = jargs[a];
                }
            }
        }

        // check jQuery dependency is available
        if (typeof $ === 'undefined') {
            throw new Error('M.totara_f2f_attendees.init()-> jQuery dependency required for this module to function.');
        }

        var notsetoption = M.totara_f2f_attendees.config.notsetop.toString();

        totaraDialog_handler_addremoveattendees = function() {};
        totaraDialog_handler_addremoveattendees.prototype = new totaraDialog_handler();

        /**
         * Upload background page when closing dialog
         */
        totaraDialog_handler_addremoveattendees.prototype.submit = function() {

            var handler = this;
            var url = M.cfg.wwwroot + '/mod/facetoface/editattendees.php?s=' + M.totara_f2f_attendees.config.sessionid + '&action=' + M.totara_f2f_attendees.config.action + '&onlycontent=1&save=1';

            // Grab new attendees list
            var attendees = $('input[name=attendees]', handler._container);
            url += '&attendees='+attendees.val();

            // check if screen errored. If it has, change nothing!
            if ($('input[name=attendees]').length == 0)
            {
                url += '&clear=true';
            } // end if - attendee field does not exist

            // Grab suppressemail value
            if ($('input#suppressemail:checked', handler._container).length) {
                url += '&suppressemail=1';
            }

            this._dialog._request(
                    url,
                    {
                        object: handler,
                        method: '_updatePage'
                    }
                );
        };

        /**
         * Upload background page
         */

        totaraDialog_handler_form.prototype._updatePage = function(response) {
            // Get all root elements in response
            var newtable = $(response);
            if (M.totara_f2f_attendees.config.approvalreqd == "1") {
                if ($('<div></div>').append(newtable).find('div.addedattendees').length > 0) {
                    //find the approval tab
                    var tab = $('span:contains("' + M.util.get_string('approvalreqd','facetoface') + '")');
                    if (tab.length > 0) {
                        //remove the nolink class if present and set up the link attributes
                        tab.parent('a').removeClass('nolink');
                        tab.parent('a').attr("href", M.cfg.wwwroot + '/mod/facetoface/attendees.php?s=' + M.totara_f2f_attendees.config.sessionid + '&action=approvalrequired');
                        tab.parent('a').attr("title", M.util.get_string('approvalreqd','facetoface'));
                    }
                }
            }

            // Replace any items on the main page with their content (if IDs match)
            $('div.f2f-attendees-table').empty();
            $('div.f2f-attendees-table').append(newtable);

            // Close dialog
            this._dialog.hide();
        };

        totaraDialog_handler_addremoveattendees.prototype._updatePage = function(response) {
            // Get all root elements in response
            var newtable = $(response);

            // Get tabs
            var waitlisttab = $('span:contains("' + M.util.get_string('wait-list','facetoface') + '")');
            var cancellationtab = $('span:contains("' + M.util.get_string('cancellations','facetoface') + '")');
            var takeattendancetab = $('span:contains("' + M.util.get_string('takeattendance','facetoface') + '")');
            var approvalrequiredtab = $('span:contains("' + M.util.get_string('approvalreqd','facetoface') + '")');

            // Activate or deactivate waitlist tab
            if (waitlisttab.length > 0) {
                if ($('input[name=waitlist]').val() == 1 && $('input[name=attendees]').val()) {
                    waitlisttab.parent('a').removeClass('nolink');
                    waitlisttab.parent('a').attr("href", M.cfg.wwwroot + '/mod/facetoface/attendees.php?s=' +
                        M.totara_f2f_attendees.config.sessionid + '&action=waitlist');
                } else {
                    waitlisttab.parent('a').addClass('nolink');
                    waitlisttab.parent('a').removeAttr("href");
                }
            }

            // Activate or deactivate cancellation tab
            if (cancellationtab.length > 0) {
                if ($('input[name=removedusers]').val()) {
                    cancellationtab.parent('a').removeClass('nolink');
                    cancellationtab.parent('a').attr("href", M.cfg.wwwroot + '/mod/facetoface/attendees.php?s=' +
                        M.totara_f2f_attendees.config.sessionid + '&action=cancellations');
                } else {
                    cancellationtab.parent('a').addClass('nolink');
                    cancellationtab.parent('a').removeAttr("href");
                }
            }

            // Activate or deactivate take attendance tab
            if (takeattendancetab.length > 0) {
                if ($('input[name=takeattendance]').val() == 1) {
                    takeattendancetab.parent('a').removeClass('nolink');
                    takeattendancetab.parent('a').attr("href", M.cfg.wwwroot + '/mod/facetoface/attendees.php?s=' +
                        M.totara_f2f_attendees.config.sessionid + '&action=takeattendance');
                } else {
                    takeattendancetab.parent('a').addClass('nolink');
                    takeattendancetab.parent('a').removeAttr("href");
                }
            }

            // Activate or deactivate approval required tab
            if (approvalrequiredtab.length > 0) {
                if (M.totara_f2f_attendees.config.approvalreqd == "1" && $('input[name=requireapproval]').val() == 1) {
                    approvalrequiredtab.parent('a').removeClass('nolink');
                    approvalrequiredtab.parent('a').attr("href", M.cfg.wwwroot + '/mod/facetoface/attendees.php?s=' +
                        M.totara_f2f_attendees.config.sessionid + '&action=approvalrequired');
                } else {
                    approvalrequiredtab.parent('a').addClass('nolink');
                    approvalrequiredtab.parent('a').removeAttr("href");
                }
            }

            // Replace any items on the main page with their content (if IDs match)
            $('div.f2f-attendees-table').empty();
            $('div.f2f-attendees-table').append(newtable);

            // Close dialog
            this._dialog.hide();
        };

        // Add/remove dialog
        (function() {
            var handler = new totaraDialog_handler_addremoveattendees();
            var name = 'addremove';

            var buttonsObj = {};
            buttonsObj[M.util.get_string('cancel','moodle')] = function() { handler._cancel(); };
            buttonsObj[M.util.get_string('save','admin')] = function() { handler.submit(); };

            totaraDialogs[name] = new totaraDialog(
                name,
                undefined,
                {
                    buttons: buttonsObj,
                    title: '<h2>' + M.util.get_string('addremoveattendees', 'facetoface') + '</h2>',
                    height: 600
                },
                M.cfg.wwwroot + '/mod/facetoface/editattendees.php?s=' + M.totara_f2f_attendees.config.sessionid + '&clear=1',
                handler
                );
        })();

        (function() {
            var handler = new totaraDialog_handler();
            var name = 'bulkaddfile';

            var buttonsObj = {};
            buttonsObj[M.util.get_string('cancel','moodle')] = function() { handler._cancel(); };
            buttonsObj[M.util.get_string('uploadfile','facetoface')] = function() {
                if ($('#id_userfile').val() !== "") {
                    $('div#bulkaddfile form.mform').unbind('submit').submit();
                }
            };

            totaraDialogs[name] = new totaraDialog(
                    name,
                    undefined,
                    {
                        buttons: buttonsObj,
                        title: '<h2>' + M.util.get_string('bulkaddattendeesfromfile', 'facetoface') + '</h2>',
                        height: 340
                    },
                    M.cfg.wwwroot + '/mod/facetoface/bulkadd_attendees.php?s=' + M.totara_f2f_attendees.config.sessionid + '&type=file&dialog=1',
                    handler
            );
        })();

        (function() {
            var handler = new totaraDialog_handler_form();
            var name = 'bulkaddinput';

            var buttonsObj = {};
            buttonsObj[M.util.get_string('cancel','moodle')] = function() { handler._cancel(); };
            buttonsObj[M.util.get_string('submitcsvtext','facetoface')] = function() {
                if ($('#id_csvinput').val() !== "") {
                    handler.submit();
                }
            };

            totaraDialogs[name] = new totaraDialog(
                name,
                undefined,
                {
                    buttons: buttonsObj,
                    title: '<h2>' + M.util.get_string('bulkaddattendeesfrominput', 'facetoface') + '</h2>',
                    height: 340
                },
                M.cfg.wwwroot + '/mod/facetoface/bulkadd_attendees.php?s=' + M.totara_f2f_attendees.config.sessionid + '&type=input&dialog=1',
                handler
                );
        })();

        (function() {
            var handler = new totaraDialog_handler_form();
            var name = 'bulkaddresults';

            var buttonsObj = {};
            buttonsObj[M.util.get_string('cancel','moodle')] = function() { handler._cancel(); };

            totaraDialogs[name] = new totaraDialog(
                name,
                'f2f-import-results',
                {
                    buttons: buttonsObj,
                    title: '<h2>' + M.util.get_string('bulkaddattendeesresults', 'facetoface') + '</h2>'
                },
                M.cfg.wwwroot + '/mod/facetoface/bulkadd_results.php?s=' + M.totara_f2f_attendees.config.sessionid,
                handler
                );
        })();

        function mark_set_unset(val, operator) {
            // Reset all checkboxes.
            $('.selectedcheckboxes').prop('checked', false);
            $(":checkbox").filter(function() {
                if (operator == 'EQ') {
                    return this.value == val;
                } else {
                    return this.value != val;
                }
            }).prop("checked", "true");
        }

        // Set error (boolean).
        function set_error(error) {
            if (error) {
                $('select#menubulk_select').addClass('error');
                $('#selectoptionbefore').removeClass('hide');
            } else {
                $('select#menubulk_select').removeClass('error');
                $('#selectoptionbefore').addClass('hide');
            }
        }

        // Print notice of operation (boolean: true =>success false=>failure).
        function print_notice(success) {
            var notice = M.util.get_string('updateattendeessuccessful','facetoface');
            var classname = 'notifysuccess';
            if (!success) {
                notice = M.util.get_string('updateattendeesunsuccessful','facetoface');
                classname = 'notifyproblem';
            }
            $('div#noticeupdate').removeClass('hide').addClass(classname).text(notice);
        }

        function options_validated(selectbulk) {
            var proceed = false;

            if (!selectbulk) {
                set_error(false);
            } else if ($(':checkbox:checked').length == 0) {
                set_error(true);
            } else {
                proceed = true;
            }

            return proceed;
        }

        // Handle select list.
        $('select#menubulk_select').change(function() {
            var selected = $(this).val();
            set_error(false); // Delete error if any.

            switch(selected) {
                case M.totara_f2f_attendees.config.selectall.toString():
                    $('.selectedcheckboxes').prop('checked', true);
                    break;
                case M.totara_f2f_attendees.config.selectnone.toString():
                    $('.selectedcheckboxes').prop('checked', false);
                    break;
                case M.totara_f2f_attendees.config.selectset.toString():
                    mark_set_unset(notsetoption, 'NE');
                    break;
                case M.totara_f2f_attendees.config.selectnotset.toString():
                    mark_set_unset(notsetoption, 'EQ');
                    break;
                default:
                    $('.selectedcheckboxes').prop('checked', false);
                    break;
            }

            // Reset drop-down bulk list.
            $('select#menubulkattendanceop').prop('selectedIndex', 0);
        });

        // Handle drop-down menu attendees current status.
        $('select[id^="menusubmissionid"]').change(function() {
            var datatosubmit = {s: $('input[name="s"]').val()};
            var idchecked = (this.name).substring(13); // Select list name (starts with submissionid_).
            // Mark value of the checkbox peer with the selected option.
            $('input[name="check_submissionid_'+idchecked+'"]').val($(this).val());

            if ($(this).val() != notsetoption) {
                datatosubmit[this.name] = $(this).val();
                save_attendance_status(datatosubmit);
            }
        });

        // Handle drop-down bulk attendance actions.
        $('select#menubulkattendanceop').change(function() {
            var selected = $(this).val();
            var idchecked = 0;
            var datatosubmit = {s: $('input[name="s"]').val()}; // Create object to send via ajax.

            if (selected != notsetoption && options_validated(selected)) {
                $(':checkbox:checked').each(function(index) {
                    idchecked = (this.name).substring(19); // Checkbox id.
                    $(this).val(selected); // Mark value of this checkbox with the selected option.
                    $('select#menusubmissionid_'+idchecked+' option[value='+selected+']').prop('selected', true);
                    datatosubmit['submissionid_'+idchecked] = selected; // Add data to submit via ajax.
                });

                save_attendance_status(datatosubmit);
            }
        });

        // Save attendance via AJAX.
        function save_attendance_status(data) {
            $.ajax({
                type: "POST",
                url: M.cfg.wwwroot + '/mod/facetoface/updateattendance.php',
                data: ({
                    courseid:  M.totara_f2f_attendees.config.courseid,
                    facetofaceid:  M.totara_f2f_attendees.config.facetofaceid,
                    datasubmission: data
                }),
                success: function(o) {
                    // If success, update operators description in the client side.
                    if (o.length > 0) {
                        print_notice(true);
                    } else {
                        print_notice(false);
                    }
                },
                error: function() {
                    print_notice(false)
                }
            });
        }

        // Handle actions drop down.
        $(document).on('change', 'select#menuf2f-actions', function() {
            var select = $(this);

            var data = {
                submitbutton: "1",
                ajax: "1",
                sesskey: M.totara_f2f_attendees.config.sesskey
            };

            // Get current value
            var current = select.val();

            // Reset to default
            select.val(0);

            // Do an action dependant on what value was chosen
            if (current == "addremove" || current == "bulkaddfile" || current == "bulkaddinput") {
                totaraDialogs[current].open();
            }

            // If exporting, redirect to that url
            if (current.substr(0, 6) == "export") {
                var url = M.cfg.wwwroot + '/mod/facetoface/attendees.php?s=' + M.totara_f2f_attendees.config.sessionid + '&action=' + M.totara_f2f_attendees.config.action + '&download=';
                url += current.substr(6);
                url += '&onlycontent=1';
                window.location.href = url;
            }

            // If taking attendance
            if (current.substr(0, 5) == "mark_") {
                // Set hidden form element
                $('form.f2f-takeattendance-form input.bulk_update').val(current.substr(5));

                // Submit form
                $('form.f2f-takeattendance-form').submit();
            }
        });
    }
}
