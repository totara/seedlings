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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @author Dave Wallace <dave.wallace@kineo.co.nz>
 * @package totara
 * @subpackage totara_core
 */
M.totara_coursecompetency = M.totara_coursecompetency || {

    Y: null,
    // optional php params and defaults defined here, args passed to init method
    // below will override these values
    config: {
        id:0
    },

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
            throw new Error('M.totara_competencyadd.init()-> jQuery dependency required for this module to function.');
        }

        if (typeof this.config.competencyuseresourcelevelevidence !== 'undefined' &&
                   this.config.competencyuseresourcelevelevidence) {

            // Create handler for the assign evidence dialog
            totaraDialog_handler_assignCourseEvidence = function() {
                // Base url
                var baseurl = '';
            };

            totaraDialog_handler_assignCourseEvidence.prototype = new totaraDialog_handler_treeview_singleselect(null, null, dualpane=true);

            totaraDialog_handler_assignCourseEvidence.prototype.handle_click = function(clicked) {

                // Get id, format item_XX
                var id = clicked.attr('id');
                var url = this.baseurl+'evidence.php?id='+module.config.id+'&add='+id;

                // Indicate loading...
                this._dialog.showLoading();

                this._dialog._request(url, {object: this, method: 'display_evidence'});
            };

            totaraDialog_handler_assignCourseEvidence.prototype.display_evidence = function(response) {

                var handler = this;

                // Hide loading animation
                this._dialog.hideLoading();

                $('.selected', this._dialog.dialog).html(response);

                // Bind click event
                $('#available-evidence', this._dialog.dialog).find('.addbutton').click(function(e) {
                    e.preventDefault();
                    var competency_id=$('#evitem_competency_id').val();
                    var type = $(this).parent().attr('type');
                    var instance = $(this).parent().attr('id');
                    var url = handler.baseurl+'save.php?competency='+competency_id+'&course='+module.config.id+'&type='+type+'&instance='+instance;
                    handler._dialog._request(url, {object: handler, method: '_update'});
                });

                return false;
            };

            ///
            /// Add course evidence to competency dialog
            ///
            (function() {
                var url = M.cfg.wwwroot+'/totara/hierarchy/prefix/competency/course/';

                var handler = new totaraDialog_handler_assignCourseEvidence();
                handler.baseurl = url;

                var buttonsObj = {};
                buttonsObj[M.util.get_string('cancel','moodle')] = function() { handler._cancel() };

                totaraDialogs['coursecompetency'] = new totaraDialog(
                    'coursecompetency',
                    'show-coursecompetency-dialog',
                    {
                        buttons: buttonsObj,
                        title: '<h2>'+M.util.get_string('addcourseevidencetocompetencies', 'totara_hierarchy')+'</h2>'
                    },
                    url+'add.php?id='+this.config.id,
                    handler
                );
            })();

        } else {
            ///
            /// non resource-level dialog
            ///
            // Create handler for the dialog
            totaraDialog_handler_courseEvidence = function() {
                // Base url
                var baseurl = '';
            };

            totaraDialog_handler_courseEvidence.prototype = new totaraDialog_handler_treeview_multiselect();

            /**
             * Add a row to a table on the calling page
             * Also hides the dialog and any no item notice
             *
             * @param string    HTML response
             * @return void
             */
            totaraDialog_handler_courseEvidence.prototype._update = function(response) {

                // Hide dialog
                this._dialog.hide();

                // Remove no item warning (if exists)
                $('.noitems-'+this._title).remove();

                //Split response into table and div
                var new_table = $(response).filter('table');

                // Grab table
                var table = $('table#list-coursecompetency');

                // If table found
                if (table.size()) {
                    table.replaceWith(new_table);
                }
                else {
                    // Add new table
                    $('div#coursecompetency-table-container').append(new_table);
                }
            };

            (function() {
                var url = M.cfg.wwwroot+'/totara/hierarchy/prefix/competency/course/';
                var saveurl = url + 'save.php?course='+module.config.id+'&type=coursecompletion&instance='+module.config.id+'&deleteexisting=1&update=';

                var handler = new totaraDialog_handler_courseEvidence();
                handler.baseurl = url;

                var buttonsObj = {};
                buttonsObj[M.util.get_string('cancel', 'moodle')] = function() { handler._cancel() };
                buttonsObj[M.util.get_string('save', 'totara_core')] = function() { handler._save(saveurl) };

                totaraDialogs['evidence'] = new totaraDialog(
                    'coursecompetency',
                    'show-coursecompetency-dialog',
                    {
                        buttons: buttonsObj,
                        title: '<h2>'+M.util.get_string('assigncoursecompletiontocompetencies', 'totara_hierarchy')+'</h2>'
                    },
                    url+'add.php?id='+module.config.id,
                    handler
                );
            })();

            // TODO SCANMSG: when this component is rewritten as a component action
            // select, the following fix will need to be applied to re-assign
            // Moodle auto-submission. Until then, inline jQuery onChange does the
            // work.

            /*
            // when the AJAX call to retrieve new HTML for the assigned table
            // completes we can set up the component action on the new select
            // element because it has a new, unique ID different from when rendered
            // on page load.
            var tableid = 'coursecompetency-table-container';
            $(totaraDialogs.evidence.dialog).bind('ajaxComplete', function(event) {
                // to be double sure our newly appended DOM elements are ready to
                // have a listener bound by a component action generated ID, respond
                // when the attached parent node's 'contentready' event is fired.
                Y.on('contentready', function(e){
                    Y.all('#'+tableid+' select').each(function(n, idx, li){
                        var id = n.get('id');
                        // call the original component action again so it handles the
                        // auto submission of a selected option based on the new select
                        M.core.init_formautosubmit(Y, tableid, id);
                    });
                }, '#'+tableid, Y);
            });
            */
        }
    }
};
