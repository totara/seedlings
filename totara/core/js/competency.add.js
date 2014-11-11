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
M.totara_competencyadd = M.totara_competencyadd || {

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
            throw new Error('M.totara_competencyadd.init()-> jQuery dependency required for this module to function.');
        }

        ///
        /// Competency dialog
        ///

        // Create handler for the addcompetency dialog
        totaraDialog_handler_addcompetency = function() {};
        totaraDialog_handler_addcompetency.prototype = new totaraDialog_handler();

        /**
         * Do handler specific binding
         *
         * @return void
         */
        totaraDialog_handler_addcompetency.prototype.every_load = function() {

            var handler = this;

            $('#addcompetency #id_submitbutton').click(function() {
                var formdata = $('#addcompetency #mform1');

                // submit form
                handler._dialog._request(
                    M.cfg.wwwroot+'/totara/hierarchy/item/add.php?'+formdata.serialize(),
                    {object: handler, method: 'submission'}
                );

                return false;
            });

            $('#addcompetency #id_cancel').click(function() {
                handler._dialog.hide();
                return false;
            });
        }

        /**
         * Handle form submission
         *
         * @param   post request response
         * @return  boolean
         */
        totaraDialog_handler_addcompetency.prototype.submission = function(response) {

            if (response.substr(0,8) == 'newcomp:') {
                // competency created, grab info and close popup
                if(match = response.match(/^newcomp:([0-9]*):(.*)$/)) {
                    var compid = match[1];
                    var compname = match[2];
                    $('input[name=competencyid]').val(compid);
                    $('span#competencytitle').text(compname);

                    var profinput = $('body.hierarchy-prefix-competency-evidence select#id_proficiency');
                    var jsonurl = M.cfg.wwwroot+'/totara/hierarchy/prefix/competency/evidence/competency_scale.json.php';
                    // only do JSON request if a proficiency select found to fill
                    if(profinput) {
                        // used by add competency evidence page to populate proficiency pulldown based on competency chosen
                        $.getJSON(jsonurl, {competencyid:compid}, function(scales) {
                            var i, htmlstr = '';
                            for (i in scales) {
                                htmlstr += '<option value="'+scales[i].name+'">'+scales[i].value+'</option>';
                            }
                            profinput.removeAttr('disabled').html(htmlstr);
                        });
                    }

                    this._dialog.hide();
                    return false;
                }
            }

            // Failed, rerender form
            return true;
        }

        // instantiate dialog and set handler
        var handler = new totaraDialog_handler_addcompetency();

        totaraDialogs['addcompetency'] = new totaraDialog(
            'addcompetency',
            'show-add-dialog',
            {title: '<h2>'+M.util.get_string('selectacompetencyframework', 'competency')+'</h2>'},
            M.cfg.wwwroot+'/totara/hierarchy/item/add.php?prefix=competency',
            handler
        );

        var url = M.cfg.wwwroot+'/totara/hierarchy/prefix/competency/assign/';

        totaraSingleSelectDialog(
            'competency',
            M.util.get_string('selectcompetency', 'totara_core'),
            M.totara_competencyadd.dialog_display_currently_selected,
            url+'find.php?',
            'competencyid',
            'competencytitle',
            function() {
                var jsonurl = M.cfg.wwwroot+'/totara/hierarchy/prefix/competency/evidence/competency_scale.json.php';
                compid = $('input[name=competencyid]').val();

                var profinput = $('body.hierarchy-prefix-competency-evidence select#id_proficiency');
                // only do JSON request if a proficiency select found to fill
                if(profinput) {
                    // used by add competency evidence page to populate proficiency pulldown based on competency chosen
                    $.getJSON(jsonurl, {competencyid:compid}, function(scales) {
                        var i, htmlstr = '';
                        for (i in scales) {
                            htmlstr += '<option value="'+scales[i].name+'">'+scales[i].value+'</option>';
                        }
                        profinput.removeAttr('disabled').html(htmlstr);
                    });
                }
            }
        );

    }
};
