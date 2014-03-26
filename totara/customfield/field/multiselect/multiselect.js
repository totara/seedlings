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
 * @author Mark Webster <mark.webster@catalyst-eu.net>
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage totara_customfield
 */

M.totara_customfield_multiselect = M.totara_customfield_multiselect || {
  Y : null,

  config: {},
  /**
   * module initialisation method called by php js_init_call()
   *
   * @param object    YUI instance
   * @param string    args supplied in JSON format
   */
  init : function(Y, args) {
    // save a reference to the Y instance (all of its dependencies included)
    this.Y = Y;

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
      throw new Error('M.totara_customfield_multiselect.init()-> jQuery dependency required for this module to function.');
    }

    var savedchoices = M.totara_customfield_multiselect.config.savedchoices;
    var numVisible = 0;
    var $container = $('#id_' + M.totara_customfield_multiselect.config.jsid);
    var max = M.totara_customfield_multiselect.config.max;

    var $allOptions = $container.find('div[id^="fgroup_id_multiselectitem_"]').slice(0, max);

    $allOptions.each(function(i){
        $(this).find("input[type='text']").each(function(j, e) {
            if ($(e).val() != '') {
                numVisible = i + 1;
                return false;
            }
        });
    });

    if (numVisible < 3) {
        numVisible = 3;
    }

    // Hide from numVisible (default is 3) to last.
    $allOptions.slice(numVisible, max).addClass('js-hide');

    $allOptions.each(function(){
        var $this = $(this);
        if ($($this.find('span')).length > 0) {
            $this.find('span').find('.makedefault').parent().addClass('js-hide');
            // Make default part.
            var $makeDefault = $('<span class="makedefaultlink">');
            var $makeDefaultLink = $('<a href="#">' + M.util.get_string('defaultmake', 'totara_customfield') + '</a>');
            $makeDefault.append($makeDefaultLink);
            $makeDefaultLink.on('click', function(){
                if (M.totara_customfield_multiselect.config.oneAnswer == 1) {
                    $allOptions.find('.unselectlink').each(function(){
                        if (!$(this).hasClass('js-hide')) {
                            $(this).find('a').click();
                        }
                    });
                }

                $this.find('input.makedefault').prop('checked', true);

                $makeDefault.addClass('js-hide');
                $unselectLink.removeClass('js-hide');
                return false;
            });

            var $unselectLink = $('<a href="#" class="js-hide">' + M.util.get_string('defaultselected', 'totara_customfield') + '</a>');

            $unselectLink.on('click', function(){
                $this.find('input.makedefault').prop('checked', false);
                $makeDefault.removeClass('js-hide');
                $unselectLink.addClass('js-hide');
                return false;
            });

            // Delete part.
            $this.find('span').find('.delete').parent().addClass('js-hide');
            var $delete = $('<span class="deletelink">');
            var $deleteLink = $('<a href="#">' + M.util.get_string('delete', 'moodle') + '</a>');
            $delete.append($deleteLink);
            $deleteLink.on('click', function(){
                $this.find('input.delete').prop('checked', true);
                $this.addClass('js-delete');

                return false;
            });

            $this.find('fieldset').append($makeDefault);
            $this.find('fieldset').append($unselectLink);
            $this.find('fieldset').append('&nbsp;&nbsp;&nbsp;');
            $this.find('fieldset').append($delete);

            if ($this.find('input.makedefault').prop('checked')) {
              $makeDefaultLink.click();
            }
        }

    });

    // Make visible #addoptionlink_$jsid.
    $container.find('a.addoptionlink').addClass('js-show');
    $container.find('a.addoptionlink').on('click', function(){
        var $group = $container.find('.fcontainer .fitem_fgroup.js-hide').eq(0);

        if ($group.length) {
            $group.removeClass('js-hide');
            numVisible++;
        } else {
            $(this).removeClass('js-show');
        }

        if (numVisible == max) {
            $(this).removeClass('js-show');
        }

        return false;
    });
  }
}
