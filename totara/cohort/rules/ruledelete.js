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
 * This class defines the Javascript which allows for deleting a rule via the rules list page.
 */

M.totara_cohortruledelete = M.totara_cohortruledelete || {

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

        this.init_deletelisteners();
    },

    init_deletelisteners: function() {
        $('a.ruledef-delete').unbind('click');
        $('#cohort-rules').on('click', 'a.ruledef-delete', function(e, postdeletecallback) {
            e.preventDefault();
            var link = $(this);
            var ruleid = link.attr('data-ruleid');

            confirmed = confirm(M.util.get_string('deleteruleconfirm', 'totara_cohort'));

            if (!confirmed) {
                return;
            }

            $.ajax({
                url: M.cfg.wwwroot + '/totara/cohort/rules/ruledelete.php',
                type: "GET",
                data: ({
                    sesskey: M.cfg.sesskey,
                    ruleid: ruleid
                }),
                beforeSend: function() {
                    var loadingimg = '<img src="' + M.util.image_url('i/ajaxloader', 'moodle') + '" alt="' + M.util.get_string('savingrule', 'totara_cohort') + '" class="iconsmall" />';
                    link.replaceWith(loadingimg);
                },
                success: function(o) {
                    if (o.length > 0) {
                        o = JSON.parse(o);
                        if (o.action == 'delrule'){
                            remove_rule(o.ruleid);
                        } else if (o.action == 'delruleset'){
                            remove_ruleset(o.rulesetid);
                        }

                        $('#cohort_rules_action_box').show();
                    } else {
                        alert(M.util.get_string('error:noresponsefromajax', 'totara_cohort'));
                        location.reload();
                    }
                }, // success
                error: function(h, t, e) {
                    alert(M.util.get_string('error:badresponsefromajax', 'totara_cohort'));
                    //Reload the broken page
                    location.reload();
                } // error
            }); // ajax

            // Call the postdeletecallback method, if provided
            if (postdeletecallback != undefined && postdeletecallback.object != undefined) {
                postdeletecallback.object[postdeletecallback.method]();
            }
        });

        $('a img.ruleparam-delete').unbind('click');
        $('#cohort-rules').on('click', 'a img.ruleparam-delete', function(e, postdeletecallback) {
            e.preventDefault();
            var link = $(this);
            var ruleparamid = link.attr('ruleparam-id');
            var ruleparamcontainer = (link).closest('span.ruleparamcontainer');

            confirmed = confirm(M.util.get_string('deleteruleparamconfirm', 'totara_cohort'));

            if (!confirmed) {
                return;
            }

            $.ajax({
                url: M.cfg.wwwroot + '/totara/cohort/rules/ruleparamdelete.php',
                type: "GET",
                data: ({
                    sesskey: M.cfg.sesskey,
                    ruleparamid: ruleparamid
                }),
                beforeSend: function() {
                    var loadingimg = '<img src="' + M.util.image_url('i/ajaxloader', 'moodle') + '" alt="' + M.util.get_string('savingrule', 'totara_cohort') + '" class="iconsmall" />';
                    link.replaceWith(loadingimg);
                },
                success: function(o) {
                    if (o.length > 0) {
                        o = JSON.parse(o);
                        if (o.action == 'delruleparam') {
                            var separator = ruleparamcontainer.next('.ruleparamseparator');
                            if (separator.length) {
                                separator.remove();
                            } else {
                                separator = ruleparamcontainer.prev('.ruleparamseparator');
                                if (separator.length) {
                                    separator.remove();
                                }
                            }
                            ruleparamcontainer.remove();
                        } else if (o.action == 'delrule') {
                            remove_rule(o.ruleid);
                        } else if (o.action == 'delruleset') {
                            remove_ruleset(o.rulesetid);
                        }

                        $('#cohort_rules_action_box').show();
                    } else {
                        alert(M.util.get_string('error:noresponsefromajax', 'totara_cohort'));
                        location.reload();
                    }
                }, // success
                error: function(h, t, e) {
                    alert(M.util.get_string('error:badresponsefromajax', 'totara_cohort'));
                    // Reload the broken page
                    location.reload();
                } // error
            }); // ajax

            // Call the postdeletecallback method, if provided
            if (postdeletecallback != undefined && postdeletecallback.object != undefined) {
                postdeletecallback.object[postdeletecallback.method]();
            }
        });

        function remove_rule(ruleid) {
            var rulerow = $('div#ruledef' + ruleid).closest('tr');

            // If this row is the first one in the table, then blank out the "operator" in the next row
            if (!rulerow.prev('tr').length) {
                rulerow.next('tr').children('.operator').html('&nbsp;');
            }
            rulerow.remove();

        }

        function remove_ruleset(rulesetid) {
            var ruleset = $('fieldset#id_cohort-ruleset-header' + rulesetid);

            // Delete the operator immediately prior to this ruleset (if any)
            ruleset.prev('fieldset').has('.cohort-oplabel').remove();

            // If this is the first ruleset on the page, also delete the operator
            // immediately after it (if any)
            if (!ruleset.prevAll('fieldset [id^="id_cohort-ruleset-header"]').length) {
                ruleset.next('fieldset').has('.cohort-oplabel').remove();
            }

            // If there are no rulesets before this one, then delete the operator immediately after it
            ruleset.remove();
        }
    }  // init_deletelisteners
}
