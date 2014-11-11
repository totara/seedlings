<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2014 onwards Totara Learning Solutions LTD
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
 * @author Petr Skoda <petr.skoda@totaralms.com>
 * @package totara_reportbuilder
 */

namespace totara_reportbuilder\rb\display;

/**
 * Class describing column display formatting.
 *
 * @author Petr Skoda <petr.skoda@totaralms.com>
 * @package totara_reportbuilder
 */
class tinymce_textarea extends base {
    public static function display($value, $format, \stdClass $row, \rb_column $column, \reportbuilder $report) {
        if (is_null($value) or $value === '') {
            return '';
        }

        $extrafields = self::get_extrafields_row($row, $column);

        $context = \context_system::instance();
        if (!empty($extrafields->context) and !empty($extrafields->recordid)) {
            if ($extrafields->context === 'context_module') {
                if ($extrafields->component) {
                    $component = str_replace('mod_', '', $extrafields->component);
                    $cm = get_coursemodule_from_instance($component, $extrafields->recordid);
                    $context = \context_module::instance($cm->id);
                }
            } else {
                if (class_exists($extrafields->context)) {
                    $rowcontext = $extrafields->context;
                    $context = $rowcontext::instance($extrafields->recordid);
                }
            }
        }

        if (!empty($extrafields->component) and !empty($extrafields->filearea)) {
            if (isset($extrafields->fileid)) {
                $itemid = $extrafields->fileid;
            } else {
                $itemid = null;
            }

            $value = file_rewrite_pluginfile_urls($value, 'pluginfile.php', $context->id, $extrafields->component,
                                                    $extrafields->filearea, $itemid);
        }

        if (isset($extrafields->format)) {
            $textformat = $extrafields->format;
        } else {
            $textformat = FORMAT_HTML;
        }

        $displaytext = format_text($value, $textformat, array('context' => $context));

        if ($format !== 'html') {
            $displaytext = static::to_plaintext($displaytext, true);
        }

        return $displaytext;
    }
}
