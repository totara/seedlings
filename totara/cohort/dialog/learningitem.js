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
 * @subpackage cohort
 */
/**
 * This file contains the Javascript for the dialog that lets you add courses & programs
 * to a cohort's enrolled learning
 */

M.totara_cohortlearning = M.totara_cohortlearning || {

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
            throw new Error('M.totara_cohortlearning.init()-> jQuery dependency required for this module.');
        }

        var url = M.cfg.wwwroot + '/totara/cohort/dialog/';
        var assgnval = this.config.assign_value ? this.config.assign_value : this.config.COHORT_ASSN_VALUE_ENROLLED;
        var assgnstring = this.config.assign_string ? this.config.assign_string : 'enrolled';

        // init courses dialog
        var csaveurl = url + 'updatelearning.php?type=' + this.config.COHORT_ASSN_ITEMTYPE_COURSE + '&cohortid=' + this.config.cohortid + '&sesskey=' + 
            M.cfg.sesskey + '&v=' + assgnval + '&u=';
        var chandler = new totaraDialog_handler_cohortlearning();
        chandler.baseurl = url;
        var cbuttons = {};
        cbuttons[M.util.get_string('cancel','moodle')] = function() { chandler._cancel() }
        cbuttons[M.util.get_string('save','totara_core')] = function() { chandler._save(csaveurl) }

        totaraDialogs['learningitemcourses'] = new totaraDialog(
            'learningitemcourses',
            'add-course-learningitem-dialog',
            {
                buttons: cbuttons,
                title: '<h2>' + M.util.get_string('assign'+assgnstring+'learning', 'totara_cohort') + '</h2>'
            },
            url+'browselearning.php?cohortid=' + this.config.cohortid  + '&v=' + assgnval + '&type=' + this.config.COHORT_ASSN_ITEMTYPE_COURSE,
            chandler
        );

        // init programs dialog
        var psaveurl = url + 'updatelearning.php?type=' + this.config.COHORT_ASSN_ITEMTYPE_PROGRAM + '&cohortid=' + this.config.cohortid + '&sesskey=' +
            M.cfg.sesskey + '&v=' + assgnval + '&u=';
        var phandler = new totaraDialog_handler_cohortlearning();
        phandler.baseurl = url;
        var pbuttons = {};
        pbuttons[M.util.get_string('cancel','moodle')] = function() { phandler._cancel() }
        pbuttons[M.util.get_string('save','totara_core')] = function() { phandler._save(psaveurl) }

        totaraDialogs['learningitemprograms'] = new totaraDialog(
            'learningitemprograms',
            'add-program-learningitem-dialog',
            {
                buttons: pbuttons,
                title: '<h2>' + M.util.get_string('assign'+assgnstring+'learning', 'totara_cohort') + '</h2>'
            },
            url+'browselearning.php?cohortid=' + this.config.cohortid + '&v=' + assgnval + '&type=' + this.config.COHORT_ASSN_ITEMTYPE_PROGRAM,
            phandler
        );

        this.init_deletelisteners();
    },  // init

    init_deletelisteners: function() {
        $('a.learning-delete').unbind('click');
        $('a.learning-delete').bind('click', function(e, postdeletecallback) {
            e.preventDefault();

            var link = $(this);
            var row = link.closest('tr');
            var confirmed = confirm(M.util.get_string('deletelearningconfirm', 'totara_cohort'));

            if (!confirmed) {
                return;
            }

            $.ajax({
                url: link.attr('href'),
                type: "GET",
                data: ({}),
                beforeSend: function() {
                    var loadingimg = '<img src="' + M.util.image_url('i/ajaxloader', 'moodle') + '" alt="' + M.util.get_string('savinglearning', 'totara_cohort') + '" class="iconsmall" />';
                    link.replaceWith(loadingimg);
                },
                success: function(o) {
                    row.remove();
                },
                error: function(h, t, e) {
                    alert(M.util.get_string('error:badresponsefromajax', 'totara_cohort'));
                    //Reload the broken page
                    location.reload();
                }
            }); // ajax

            // Call the postdeletecallback method, if provided
            if (postdeletecallback != undefined && postdeletecallback.object != undefined) {
                postdeletecallback.object[postdeletecallback.method]();
            }
        });
    }  // init_deletelisteners
}


// Create handler for the dialog
totaraDialog_handler_cohortlearning = function() {
    // Base url
    var baseurl = '';
}

totaraDialog_handler_cohortlearning.prototype = new totaraDialog_handler_treeview_multiselect();

/**
 * Add a row to a table on the calling page
 * Also hides the dialog and any no item notice
 *
 * @param string    HTML response
 * @return void
 */
totaraDialog_handler_cohortlearning.prototype._update = function(response) {

    // Hide dialog
    this._dialog.hide();

    //TODO: the stuff to add table rows :-P
    if (M.totara_cohortlearning.config.saveurl) {
        location.replace(M.cfg.wwwroot + M.totara_cohortlearning.config.saveurl + '?id=' + M.totara_cohortlearning.config.cohortid);
    } else {
        location.replace(M.cfg.wwwroot + '/totara/cohort/enrolledlearning.php?id=' + M.totara_cohortlearning.config.cohortid);
    }
}
