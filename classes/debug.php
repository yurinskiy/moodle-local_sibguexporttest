<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package   local_sibguexporttest
 * @copyright 2024, Yuriy Yurinskiy <yuriyyurinskiy@yandex.ru>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_sibguexporttest;

defined('MOODLE_INTERNAL') || die();

class debug {
    public static function dump(...$vars)
    {
        if (!\in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true) && !headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
        }

        echo '<pre>';

        if (array_key_exists(0, $vars) && 1 === count($vars)) {
            echo print_r($vars[0], 1);
        } else {
            foreach ($vars as $k => $v) {
                echo print_r($v, 1);
            }
        }
        echo '</pre>';
    }

    public static function dd(...$vars)
    {
        if (!\in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true) && !headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
        }

        echo '<pre>';

        if (array_key_exists(0, $vars) && 1 === count($vars)) {
            echo print_r($vars[0], 1);
        } else {
            foreach ($vars as $k => $v) {
                echo print_r($v, 1);
            }
        }
        echo '</pre>';

        exit(1);
    }
}
