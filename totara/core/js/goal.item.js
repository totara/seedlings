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
 * @author David Curry <david.curry@totaralms.com>
 * @package totara
 * @subpackage totara_core
 */
 M.totara_goalitem = M.totara_goalitem || {

    Y: null,
    // optional php params and defaults defined here, args passed to init method
    // below will override these values
    config: {
                id:0
            },

    /**
     * module initialisation method called by php js_init_call()
     *
     * @param object YUI instance
     * @param string args supplied in JSON format
     */
    init: function(Y, args){
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
            throw new Error('M.totara_goalitem.init()-> jQuery dependency required for this module to function.');
        }

        ///
        /// Add related goal dialog
        ///
        (function() {
            var url = M.cfg.wwwroot+'/totara/hierarchy/prefix/goal/related/';
            totaraMultiSelectDialog(
                'related',
                M.util.get_string('assignrelatedgoals', 'totara_hierarchy'),
                url+'find.php?id='+M.totara_goalitem.config.id,
                url+'save.php?id='+M.totara_goalitem.config.id+'&deleteexisting=1&add='
            );
        })();

        if (typeof M.totara_goalitem.config.goaluseresourcelevelevidence !== 'undefined' &&
            M.totara_goalitem.config.goaluseresourcelevelevidence) {

            // Create handler for the assign evidence dialog
            totaraDialog_handler_assignEvidence = function() {
                // Base url
                var baseurl = '';
            }

            totaraDialog_handler_assignEvidence.prototype = new totaraDialog_handler_treeview();


            totaraDialog_handler_assignEvidence.prototype._handle_update_hierarchy = function(list) {
                var handler = this;
                $('span', list).click(function() {
                    var par = $(this).parent();

                    // Get the id in format item_list_XX
                    var id = par.attr('id').substr(10);

                    // Check it's not a category
                    if (id.substr(0, 3) == 'cat') {
                        return;
                    }

                    handler._handle_course_click(id);
                });
            }

            totaraDialog_handler_assignEvidence.prototype._handle_course_click = function(id) {
                // Load course details
                var url = this.baseurl+'course.php?id='+id+'&goal='+M.totara_goalitem.config.id;

                // Indicate loading...
                this._dialog.showLoading();

                this._dialog._request(url, {object: this, method: '_display_evidence'});
            }

            /**
             * Display course evidence items
             *
             * @param string    HTML response
             */
            totaraDialog_handler_assignEvidence.prototype._display_evidence = function(response) {
                this._dialog.hideLoading();

                $('.selected', this._dialog.dialog).html(response);

                var handler = this;

                // Bind click event
                $('#available-evidence', this._dialog.dialog).find('.addbutton').click(function(e) {
                    e.preventDefault();
                    var type = $(this).parent().attr('type');
                    var instance = $(this).parent().attr('id');
                    var url = handler.baseurl+'add.php?goal='+M.totara_goalitem.config.id+'&type='+type+'&instance='+instance;
                    handler._dialog._request(url, {object: handler, method: '_update'});
                });

            }

        } else { // use course-level dialog

            // Create handler for the dialog
            totaraDialog_handler_compEvidence = function() {
                // Base url
                var baseurl = '';
            }

            totaraDialog_handler_compEvidence.prototype = new totaraDialog_handler_treeview_multiselect();

            /**
             * Add a row to a table on the calling page
             * Also hides the dialog and any no item notice
             *
             * @param string    HTML response
             * @return void
             */
            totaraDialog_handler_compEvidence.prototype._update = function(response) {

                // Hide dialog
                this._dialog.hide();

                // Remove no item warning (if exists)
                $('.noitems-'+this._title).remove();

                //Split response into table and div
                var new_table = $(response).find('table.list-evidence');

                // Grab table
                var table = $('table.list-evidence');

                // If table found
                if (table.size()) {
                    table.replaceWith(new_table);
                }
                else {
                    // Add new table
                    $('div#evidence-list-container').append(new_table);
                }
            };

            (function() {
                var url = M.cfg.wwwroot+'/totara/hierarchy/prefix/goal/evidenceitem/';
                var saveurl = url + 'add.php?goal='+M.totara_goalitem.config.id+'&type=coursecompletion&instance=0&deleteexisting=1&update=';
                var buttonsObj = {};
                var handler = new totaraDialog_handler_compEvidence();
                handler.baseurl = url;

                buttonsObj[M.util.get_string('cancel','moodle')] = function() { handler._cancel() }
                buttonsObj[M.util.get_string('save','totara_core')] = function() { handler._save(saveurl) }

                totaraDialogs['evidence'] = new totaraDialog(
                    'evidence',
                    'show-evidence-dialog',
                    {
                         buttons: buttonsObj,
                         title: '<h2>' +  M.util.get_string('assigncoursecompletions','totara_hierarchy') + '</h2>'
                    },
                    url+'edit.php?id='+M.totara_goalitem.config.id,
                    handler
                );
            })();
        }

        if (typeof M.totara_goalitem.config.goaluseresourcelevelevidence !== 'undefined' &&
            M.totara_goalitem.config.goaluseresourcelevelevidence) {

            ///
            /// Assign evidence item dialog (resource-level)
            ///
            (function() {
                var url = M.cfg.wwwroot+'/totara/hierarchy/prefix/goal/evidenceitem/';
                var buttonsObj = {};
                var handler = new totaraDialog_handler_assignEvidence();
                handler.baseurl = url;

                buttonsObj[M.util.get_string('cancel','moodle')] = function() { handler._cancel() }

                totaraDialogs['evidence'] = new totaraDialog(
                    'evidence',
                    'show-evidence-dialog',
                    {
                        buttons: buttonsObj,
                        title: '<h2>' + M.util.get_string('assignnewevidenceitem','totara_hierarchy') + '</h2>'
                    },
                    url+'edit.php?id='+M.totara_goalitem.config.id,
                    handler
                );
            })();
        }
    }
};
