<?php
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
 * Library for handling basic search queries
 *
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage totara_core
 */

/**
 * Parse a query into individual keywords, treating quoted phrases one item
 *
 * Pairs of matching double or single quotes are treated as a single keyword.
 *
 * @param string $query Text from user search field
 *
 * @return array Array of individual keywords parsed from input string
 */
function totara_search_parse_keywords($query) {
    $query = trim($query);

    $out = array();
    // break query down into quoted and unquoted sections
    $split_quoted = preg_split('/(\'[^\']+\')|("[^"]+")/', $query, 0,
        PREG_SPLIT_DELIM_CAPTURE);
    foreach ($split_quoted as $item) {
        // strip quotes from quoted strings but leave spaces
        if (preg_match('/^(["\'])(.*)\\1$/', trim($item), $matches)) {
            $out[] = $matches[2];
        } else {
            // split unquoted text on whitespace
            $keyword = preg_split('/\s/u', $item, 0,
                PREG_SPLIT_NO_EMPTY);
            $out = array_merge($out, $keyword);
        }
    }
    return $out;
}


/**
 * Return an SQL WHERE clause to search for the given keywords
 *
 * @param array $keywords Array of strings to search for
 * @param array $fields Array of SQL fields to search against
 * @param int $type bound param type SQL_PARAMS_QM or SQL_PARAMS_NAMED
 * @param string $prefix named parameter placeholder prefix (unique counter value is appended to each parameter name)
 *
 * @return array Containing SQL WHERE clause and parameters
 */
function totara_search_get_keyword_where_clause($keywords, $fields, $type=SQL_PARAMS_QM, $prefix='param') {
    global $DB;

    $queries = array();
    $params = array();
    static $TOTARA_SEARCH_PARAM_COUNTER = 1;
    foreach ($keywords as $keyword) {
        $matches = array();
        foreach ($fields as $field) {
            if ($type == SQL_PARAMS_QM) {
                $matches[] = $DB->sql_like($field, '?', false);
                $params[] = '%' . $DB->sql_like_escape($keyword) . '%';
            } else {
                $paramname = $prefix . $TOTARA_SEARCH_PARAM_COUNTER;
                $matches[] = $DB->sql_like($field, ":$paramname", false);
                $params[$paramname] = '%' . $DB->sql_like_escape($keyword) . '%';

                $TOTARA_SEARCH_PARAM_COUNTER++;
            }
        }
        // Look for each keyword in any field.
        if (!empty($matches)) {
            $queries[] = '(' . implode(' OR ', $matches) . ')';
        }
    }
    // All keywords must be found in at least one field.
    return array(implode(' AND ', $queries), $params);
}

/**
 * Return an SQL snippet to search for the given keywords
 *
 * @param string $field the field to search in
 * @param array $keywords Array of strings to search for
 * @param boolean $negate negate the conditions
 * @param string $operator can be 'contains', 'equal', 'startswith', 'endswith'
 *
 * @return array containing SQL clause and params
 */
function search_get_keyword_where_clause_options($field, $keywords, $negate=false, $operator='contains') {
    global $DB;

    $count = 1;
    $presign = '';
    $postsign = '';
    $queries = array();
    $params  = array();

    if ($negate) {
        $not = true;
        $token = ' AND ';
    } else {
        $not = false;
        $token = ' OR ';
    }

    switch ($operator) {
        case 'contains':
            $presign = $postsign = '%';
            break;
        case 'startswith':
            $postsign = '%';
            break;
        case 'endswith':
            $presign = '%';
            break;
        default:
            break;
    }

    foreach ($keywords as $keyword) {
        $uniqueparam = "{$operator}_{$count}_" . uniqid();
        $queries[] = $DB->sql_like($field, ":{$uniqueparam}", false, true, $not);
        $params[$uniqueparam] = $presign . $DB->sql_like_escape($keyword) . $postsign;
        ++$count;
    }

    $sql =  implode($token, $queries);

    return array($sql, $params);
}
