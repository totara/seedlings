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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @package totara
 * @subpackage cohort/rules
 */

/**
 * This file defines the Totara dialog for creating/editing a single rule for a dynamic cohort.
 */

M.totara_cohortrules = M.totara_cohortrules || {

    Y: null,

    // optional php params and defaults defined here, args passed to init method
    // below will override these values
    config: {},

    /**
     * module initialisation method called by php js_init_call()
     *
     * @param object    YUI instance
     * @param string    args supplied in JSON format
     */
    init: function(Y, args) {
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
            throw new Error('M.totara_cohortrules.init()-> jQuery dependency required for this module.');
        }

        this.totara_cohortrules_init_dialogs();
    },

    totara_cohortrules_init_dialogs: function() {
        // Dialog & handler for form-based UIs
        var url = M.cfg.wwwroot + '/totara/cohort/rules/ruledetail.php';
        var saveurl = url;

        var fhandler = new totaraDialog_handler_cohortruleform();
        fhandler.baseurl = url;

        var fbuttons = {};
        fbuttons[M.util.get_string('cancel','moodle')] = function() { fhandler._cancel() }
        fbuttons[M.util.get_string('save','totara_core')] = function() { fhandler.submit() }
        var fdialog = new totaraDialog(
            'cohortruleformdialog',
            'nobutton',
            {
                buttons: fbuttons,
                title: '<h2>' + M.util.get_string('addrule', 'totara_cohort') + '</h2>'
            },
            url,
            fhandler
        );
        fdialog.cohort_base_url = url;
        totaraDialogs['cohortruleform'] = fdialog;

        // Hide update operators buttons
        $('#fgroup_id_buttonar').hide();

        // Update AND/OR operators
        $(document).on('click', 'input:radio', function(event) {

             var selected = $(this);
             var radioname = selected.attr('name');
             var opvalue  = selected.val();
             var cohortid = M.totara_cohortrules.config.cohortid;
             var type = radioname;
             var id = '';

             if (radioname === 'cohortoperator') {
                 id = cohortid;
                 type = M.totara_cohortrules.config.operator_type_cohort;
             } else if (radioname.substr(0, 15) === 'rulesetoperator') {
                 type = M.totara_cohortrules.config.operator_type_ruleset;
                 // Pattern for ruleset operators. e.g. rulesetoperator[422].
                 var match = radioname.match(/\[(\d+)\]/);
                 if (match) {
                     id = match[1];
                 }
             } else {
                 return;
             }

             // Updating operators via AJAX.
             $.ajax({
                 type: "POST",
                 url: M.cfg.wwwroot + '/totara/cohort/rules/updateoperator.php',
                 data: ({
                     id: id,
                     type: type,
                     value: opvalue,
                     cohortid : cohortid,
                     sesskey: M.cfg.sesskey
                 }),
                 success: function(o) {
                     // If success, update operators description in the client side.
                     if (o.length > 0) {
                         o = JSON.parse(o);
                         var operator = null;
                         if (o.result === false) {
                             show_notifications(o.action, id, o.result);
                             return false;
                         }
                         if (o.action === 'updcohortop') {
                             operator = M.util.get_string('andcohort', 'totara_cohort');
                             if (o.value !== 0) {
                                 operator = M.util.get_string('orcohort', 'totara_cohort');
                             }
                             // Change cohort operator.
                             $("div .cohort-oplabel").html(operator);
                             // Enable approve - cancel options and notify success.
                             show_notifications(o.action, id, o.result);
                         } else {
                             operator = M.util.get_string('and', 'totara_cohort');
                             if (o.value !== 0) {
                                 operator = M.util.get_string('or', 'totara_cohort');
                             }
                             var divid = '#id_cohort-ruleset-header' + id + " td.operator ";
                             $(divid).each(function (index, value) {
                                 if (index !== 0) {
                                      // Change ruleset operator.
                                      $(this).text(operator);
                                 }
                             });
                             // Enable approve - cancel options and notify success.
                             show_notifications(o.action, id, o.result);
                         }
                     }
                 }
             });
         });

        function show_notifications(type, id, success) {
            var notification_class = 'notifysuccess';
            var notification_message = M.util.get_string('rulesupdatesuccess', 'totara_cohort');
            if (success) {
                // Show approve - cancel options
                $('div#cohort_rules_action_box').removeAttr("style");
            } else {
                notification_class = 'notifyproblem';
                notification_message = M.util.get_string('rulesupdatefailure', 'totara_cohort');
            }

            // Notify result of operation.
            var notice = "<div id='notify"+ id +"' class="+notification_class+">" +
                notification_message + "<div>";

            if ($('div#notify'+ id).length === 0) {
                if (type === 'updcohortop') {
                    $('#fgroup_id_cohortoperator').prepend(notice);
                } else {
                    $('#fgroup_id_rulesetoperator_' + id).prepend(notice);
                }
            }
            $('div#notify'+ id).fadeOut(600).fadeIn(600);
        }

        // Dialog & handler for hierarchy picker
        var url = M.cfg.wwwroot + '/totara/cohort/rules/ruledetail.php';
        var thandler = new totaraDialog_handler_cohortruletreeview();
        var tbuttons = {};
        tbuttons[M.util.get_string('cancel','moodle')] = function() { thandler._cancel() }
        tbuttons[M.util.get_string('save','totara_core')] = function() { thandler._save() }
        var tdialog = new totaraDialog(
            'cohortruletreeviewdialog',
            'nobutton',
            {
                buttons: tbuttons,
                title: '<h2>' + M.util.get_string('addrule', 'totara_cohort') + '</h2>'
            },
            url,
            thandler
        );
        tdialog.cohort_base_url = url;
        totaraDialogs['cohortruletreeview'] = tdialog;

        // Bind open event to rule_selector menu(s)
        // Also set their default value
        $(document).on('change', 'select.rule_selector', function(event) {

            // Stop any default event occuring
            event.preventDefault();

            // Open default url
            var select = $(this);
            var ruletype = select.val();
            var idtype = select.attr('data-idtype');
            var id = select.attr('data-id');

            // Validate completion date rules.
            validate_completion();

            var dialog = totaraDialogs['cohortrule' + ruleHandlerMap[ruletype]];
            var url = dialog.cohort_base_url;
            var handler = dialog.handler;

            if (idtype == 'ruleset') {
                handler.responsetype = 'newrule';
                handler.responsegoeshere = select.parent().parent().parent().find('.rulelist .rule_table tr:last');
            }

            if (idtype == 'cohort') {
                handler.responsetype = 'newruleset';
                handler.responsegoeshere = $('#id_addruleset');
            }

            dialog.default_url = url + '?rule=' + select.val() + '&id=' + id + '&type=' + idtype;
            dialog.saveurl = dialog.default_url + '&update=1&sesskey=' + M.cfg.sesskey;
            dialog.open();

            // Set the value of the menu back to "Add rule" if they cancel
            select.val('');
        });

        // Also bind open event to rule edit links
        $(document).on('click', 'a.ruledef-edit', function(event) {

            // Stop any default event occurring
            event.preventDefault();

            var link = $(this);
            var ruleid = link.attr('data-ruleid');
            var ruletype = link.attr('data-ruletype');

            // Validate completion date rules.
            validate_completion();

            // Get the appropriate dialog
            var dialog = totaraDialogs['cohortrule' + ruleHandlerMap[ruletype]];
            var url = dialog.cohort_base_url;

            // Tell the handler how to handle the response
            var handler = dialog.handler;
            handler.responsetype = 'updaterule';
            handler.responsegoeshere = $('#ruledef' + ruleid).parent().parent();

            // Set the URL
            dialog.default_url = link.attr('href');
            dialog.saveurl = dialog.default_url + '&update=1&sesskey=' + M.cfg.sesskey;
            dialog.open();
        });
    }
}


// Function to validate completion date field.
var funccompletiondate =  function(element) {
    element = $(element);
    var parent = element.parent();
    if (!element.val().match(M.util.get_string('datepickerlongyearregexjs', 'totara_core'))){
        parent.addClass('error');
        if ($('#id_error_completiondate').length == 0) {
            parent.prepend('<span id="id_error_completiondate" class="error">' +
                M.util.get_string('error:baddate','totara_cohort') +
                '</span>');
        }
        return false;
    } else {
        $('#id_error_completiondate').remove();
        parent.removeClass('error');
        return true;
    }
};

// Function to validate completion duration field.
var funccompletionduration = function(element) {
    element = $(element);
    var parent = element.parent();
    if (!element.val().match(/[1-9]+[0-9]*/)){
        parent.addClass('error');
        if ( $('#id_error_completiondurationdate').length == 0 ) {
            parent.prepend('<span id="id_error_completiondurationdate" class="error">' +
                M.util.get_string('error:badduration','totara_cohort') +
                '</span>');
        }
        return false;
    } else {
        $('#id_error_completiondurationdate').remove();
        parent.removeClass('error');
        return true;
    }
};

function validate_completion() {
    Y.on("contentready", initial_validation, '#form_course_program_date')
}

function initial_validation() {
    $('#completiondate').datepicker(
        {
            dateFormat: M.util.get_string('datepickerlongyeardisplayformat','totara_core'),
            showOn: 'both',
            buttonImage: M.util.image_url('t/calendar'),
            buttonImageOnly: true,
            beforeShow: function() { $('#ui-datepicker-div').css('z-index', 1600); },
            constrainInput: true
        }
    );

    // Validate radio button options.
    if ($('#fixedordynamic1').is(':checked')) {
        $('#completiondurationdate').prop('disabled', true);
        $('#completiondate').get(0).cohort_validation_func = funccompletiondate;
    }

    if ($('#fixedordynamic2').is(':checked')) {
        $('#completiondate').prop('disabled', true);
        $('#completiondurationdate').get(0).cohort_validation_func = funccompletionduration;
    }
}

// Validate completion date field when changing.
$(document).on('change', '#completiondate', function(element) {
    $('#completiondate').get(0).cohort_validation_func = funccompletiondate;
});

// Validate duration field when changing.
$(document).on('change', '#completiondurationdate', function() {
    $('#completiondurationdate').get(0).cohort_validation_func = funccompletionduration;
});

// Validate when radio buttons selected.
$(document).on('click', 'input[name="fixeddynamic"]', function(event) {
    if ($(this).val() == 1) { // Fixed date.
        $('#fixedordynamic1').addClass('cohorttreeviewsubmitfield');
        $('#fixedordynamic2').removeClass('cohorttreeviewsubmitfield');
        $('#completiondate').prop('disabled', false);
        $('#completiondurationdate').prop('disabled', true);
        $('#completiondate').get(0).cohort_validation_func = funccompletiondate;
        $('#completiondurationdate').get(0).cohort_validation_func = null;
    } else { // Relative date.
        $('#fixedordynamic2').addClass('cohorttreeviewsubmitfield');
        $('#fixedordynamic1').removeClass('cohorttreeviewsubmitfield');
        $('#completiondurationdate').prop('disabled', false);
        $('#completiondate').prop('disabled', true);
        $('#completiondurationdate').get(0).cohort_validation_func = funccompletionduration;
        $('#completiondate').get(0).cohort_validation_func = null;
    }
});

// A function to handle the responses generated by cohort handlers
var cohort_handler_responsefunc = function(response) {
    if (response.substr(0,4) == 'DONE') {
        // Get all root elements in response
        var els = $(response.substr(4));

        // If we're updating an existing rule, then replace its content
        if (this.responsetype == 'updaterule') {
            this.responsegoeshere.replaceWith(els);
            els.effect('pulsate', { times: 3 }, 2000);
        }

        // If we're adding a new rule, insert it
        if (this.responsetype == 'newrule') {
            this.responsegoeshere.after(els);
        }

        // If we're adding a new ruleset, insert it
        if (this.responsetype == 'newruleset') {
            this.responsegoeshere.before(els);
        }

        $('#cohort_rules_action_box').show();

        // Close dialog
        this._dialog.hide();
    } else {
        this._dialog.render(response);
    }
}

// Create handler for the dialog
// As a totaraDialog_handler_form, it means that the content of this dialog should contain an HTML
// form. The form's action should point to a page that can receive the data and perform the necessary
// updates, and return what's needed.
totaraDialog_handler_cohortruleform = function() {
    // Base url
    var baseurl = '';
}

totaraDialog_handler_cohortruleform.prototype = new totaraDialog_handler_form();

/**
 * Update page with forms results
 *
 * @param   string  HTML response
 * @return  void
 */
totaraDialog_handler_cohortruleform.prototype._updatePage = cohort_handler_responsefunc;

/**
 * Add custom submit handler to forms in dialog
 */
totaraDialog_handler_cohortruleform.prototype.every_load = function() {
    var handler = this;
    var forms = $('form', this._container);
    // Get the original onsubmit (most likely from mforms)
    var orighandler = forms.get(0).onsubmit;

    forms.get(0).onsubmit = null;
    forms.unbind('submit');

    forms.bind('submit', function(e) {
        e.preventDefault();

        // Check whether the original onsubmit worked
        if (!(typeof(orighandler) == 'function') || orighandler(forms.get(0)) ) {

            handler._dialog.showLoading();

            var url = $(this).attr('action');
            var method = $(this).attr('method');
            var data = $(this).serialize();

            handler._dialog._request(
                url,
                {
                    object:     handler,
                    method:     '_updatePage' // Update page and close dialog on success
                },
                method,
                data
            );
        }
    });
};

totaraDialog_handler_cohortruletreeview = function() {};
totaraDialog_handler_cohortruletreeview.prototype = new totaraDialog_handler_treeview_multiselect();

/**
 * Serialize dropped items and send to url,
 * update table with result
 *
 * @param string URL to send dropped items to
 * @return void
 */
totaraDialog_handler_cohortruletreeview.prototype._save = function() {
    // Serialize data
    var elements = $('.selected > div > span', this._container);
    var selected = this._get_ids(elements);
    var extrafields = $('.cohorttreeviewsubmitfield');

    // If they're trying to create a new rule but haven't selected anything, just exit.
    // (If they are updating an existing rule, we'll want to delete the selected ones.)
    if (!selected.length) {
        if (this.responsetype == 'newrule' || this.responsetype == 'newruleset') {
            this._cancel();
            return;
        } else if (this.responsetype == 'updaterule') {
            // Trigger the "delete" link, closing this dialog if it's successful
            $('a.ruledef-delete', this.responsegoeshere).trigger('click', {object: this, method: '_cancel'});
            return;
        }
    }

    // Check for any validation functions
    var success = true;
    extrafields.each(
        function(intIndex) {
            if (typeof(this.cohort_validation_func) == 'function') {
                success = success && this.cohort_validation_func(this);
            }
        }
    );
    if (!success) {
        return;
    }
    $('#cohort_rules_action_box').show();

    var selected_str = selected.join(',');

    // Add to url
    var url = this._dialog.saveurl + '&selected=' + selected_str;

    extrafields.each(
        function(intIndex) {
            if ($(this).val() != null) {
                url = url + '&' + $(this).attr('name') + '=' + $(this).val();
            }
        }
    );

    // Send to server
    this._dialog._request(url, {object: this, method: '_update'});
}

// todo: need to figure out a better way to share this common code between this and the formpicker
totaraDialog_handler_cohortruletreeview.prototype._update = cohort_handler_responsefunc;
