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
namespace local_sibguexporttest\output;

defined('MOODLE_INTERNAL') || die();

use local_sibguexporttest\debug;
use local_sibguexporttest\settings;
use plugin_renderer_base;

/**
 * Renderer class for 'local_sibguexporttest' component.
 *
 * @package   local_sibguexporttest
 * @copyright 2024, Yuriy Yurinskiy <yuriyyurinskiy@yandex.ru>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view_renderer extends plugin_renderer_base {

    /**
     * @return string HTML to output.
     */
    public function view(): string
    {
        global $COURSE, $DB;

        $settings = settings::get_by_course($COURSE->id);
        $contents = $settings->get_selected_quizzes();

        $attempts = $DB->get_records('quiz_attempts', ['quiz' => current($contents), 'state' => 'finished'], 'id');
debug::dump($settings->get_selected_quizzes());
        debug::dd($attempts);

        return 'hello';
    }

}