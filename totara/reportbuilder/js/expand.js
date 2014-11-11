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
 * @author Nathan Lewis <nathan.lewis@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

/**
 * Javascript file containing JQuery bindings for processing expanding information
 */

M.totara_reportbuilder_expand = M.totara_reportbuilder_expand || {
    /** Selectors. */
    SELECTORS: {
        ENROLMENTBUTTONS:     '.expandenrol'
    },

    init: function(Y, args) {
        $('body').on('click', '.rb-display-expand', M.totara_reportbuilder_expand.displayExpand);
        $('body').on('click', this.SELECTORS.ENROLMENTBUTTONS, M.totara_reportbuilder_expand.clickEnrol);
    },

    /*
     * Inserts the expanded contents after the clicked row.
     * Keeps track of whether a _link_ was clicked so that if it is clicked again then it will clear the expanded contents instead.
     */
    displayExpand: function(event) {
        var that = this;
        if ($(this).attr('clicked')) {
            // We reclicked a link, so remove the expanded contents, unmark as clicked and return.
            $('.rb-expand-row').remove();
            $(this).attr({clicked: null});
            return;
        }
        var id = $('.rb-display-table-container').attr('id');
        var url = M.cfg.wwwroot + '/totara/reportbuilder/ajax/expand.php?id=' + id + '&expandname=' + $(this).data('name');
        if ($(this).data('param')) {
            url = url + '&' + $(this).data('param');
        }
        $.post(url).done(function(data) {
            // Remove any existing expanded contents.
            $('.rb-expand-row').remove();
            // Unmark any links as clicked.
            $('.rb-display-table-container div').attr({clicked: null});
            // Insert the content in the following row. We calculate colspan using the clicked row.
            var content = $(data).find('.rb-expand-row');
            var colspan = $(that).closest('tr').find('td').length;
            content.find('td.rb-expand-cell').attr({ colspan: colspan});
            content.insertAfter($(that).closest('tr'));
            // Mark the link as clicked.
            $(that).attr({clicked: true});
        });
    },

    /*
     * When clicking an enrol button in a course expander
     * Post the form values with expand data, render the result, redirect if told to do so.
     */
    clickEnrol: function(event) {
        var button=$(event.target);

        var courseid = $('input[type="hidden"][name="courseid"]').attr('value');
        var id = $('.rb-display-table-container').attr('id');
        var url = M.cfg.wwwroot + '/totara/reportbuilder/ajax/expand.php';

        var form = $(button).parents('form')[0];
        var formdata = $(form).serialize();

        formdata += '&expandname=course_details';
        formdata += '&expandcourseid=' + courseid;
        formdata += '&id=' + id;
        formdata += '&instancesubmitted=' + button.attr('name');

        var that = $('[data-param="expandcourseid=' + courseid + '"]');

        $.post(url, formdata).done(function(data) {
            var redirect = $(data).find('input[type="hidden"][name="redirect"]');
            if (redirect.length) {
                window.location = redirect.attr('value');
                return;
            }

            // Remove any existing expanded contents.
            $('.rb-expand-row').remove();
            // Unmark any links as clicked.
            $('.rb-display-table-container div').attr({clicked: null});
            // Insert the content in the following row. We calculate colspan using the clicked row.
            var content = $(data).find('.rb-expand-row');
            var colspan = $(that).closest('tr').find('td').length;
            content.find('td.rb-expand-cell').attr({ colspan: colspan});
            content.insertAfter($(that).closest('tr'));
            // Mark the link as clicked.
            that.attr({clicked: true});
        });
    }
};
