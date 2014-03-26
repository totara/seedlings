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
 * @author Valerii Kuznetsov
 * @package totara
 * @subpackage totara_appraisal
 */

M.totara_appraisal_message = M.totara_appraisal_message || {
  Y : null,

  config: {},
  /**
   * module initialisation method called by php js_init_call()
   *
   * @param object    YUI instance
   * @param string    args supplied in JSON format
   */
  init : function(Y, formid) {
    // save a reference to the Y instance (all of its dependencies included)
    this.Y = Y;

    // check jQuery dependency is available
    if ( typeof $ === 'undefined') {
      throw new Error('M.totara_appraisal_stage.init()-> jQuery dependency required for this module to function.');
    }
    Y.on('form:updateState', function(e) {
        $('#'+e+' .hide-disabled').parent('div').parent('div').show();
        $('#'+e+' .hide-disabled[disabled=disabled]').parent('div').parent('div').hide();
    });

    $('select[name=eventid]', '#'+formid).change(function() {
        M.totara_appraisal_message.checkTiming($('#'+formid));
    });
    $('select[name=eventtype]', '#'+formid).change(function() {
        M.totara_appraisal_message.checkTiming($('#'+formid));
    });

    M.totara_appraisal_message.checkTiming($('#'+formid));
  },

  checkTiming: function(form) {
      var eventid = $('select[name=eventid]', form);
      var eventtype = $('select[name=eventtype]', form);
      var eventradio = $('input[name="timinggrp[timing]"]', form);

      // eventtype.val() == appraisal_message::EVENT_STAGE_COMPLETE
      if (eventid.val() == 0 || eventtype.val() === 'appraisal_stage_completion') {
          if (eventradio.filter('[value=-1]').prop('checked')) {
                eventradio.filter('input[value=0]').prop("checked", true);
          }
          eventradio.filter('input[value=-1]').prop('disabled', true);
      } else {
          eventradio.filter('input[value=-1]').prop('disabled', false);
      }
  }
}
