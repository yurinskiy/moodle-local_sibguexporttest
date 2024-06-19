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

require_once($CFG->dirroot . '/enrol/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

use course_enrolment_manager;
use html_writer;
use local_sibguexporttest\debug;
use local_sibguexporttest\export;
use local_sibguexporttest\settings;
use moodle_url;
use plugin_renderer_base;
use quiz_attempt;

/**
 * Renderer class for 'local_sibguexporttest' component.
 *
 * @package   local_sibguexporttest
 * @copyright 2024, Yuriy Yurinskiy <yuriyyurinskiy@yandex.ru>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class task_renderer extends plugin_renderer_base {

    private ?moodle_url $baseurl = null;

    public function init_baseurl(moodle_url $url) {
        $this->baseurl = $url;
    }

    /**
     * @return string HTML to output.
     */
    public function view($page = 0, $perpage = 25): string {
        $output = $this->get_table($page, $perpage);
        $output .= $this->get_paginator($page, $perpage);

        return $output;
    }

    public function get_table($page = 0, $perpage = 25) {
        $output = html_writer::start_tag('table', ['class' => 'generaltable table-hover table-bordered']) . "\n";
        $output .= $this->get_head();
        $output .= $this->get_body($page, $perpage);
        $output .= html_writer::end_tag('table') . "\n";

        return $output;
    }

    public function get_paginator($page = 0, $perpage = 25) {
        global $COURSE, $USER;
        $total = export::count_records(['courseid' => $COURSE->id, 'userid' => $USER->id]);

        return $this->output->paging_bar($total, $page, $perpage, $this->get_baseurl());
    }

    private function get_head() {
        $output = html_writer::start_tag('thead', array()) . "\n";
        $output .= html_writer::start_tag('tr', array()) . "\n";

        $output .= html_writer::tag('th', 'ID') . "\n";
        $output .= html_writer::tag('th', 'Статус') . "\n";
        $output .= html_writer::tag('th', 'Дата создания') . "\n";
        $output .= html_writer::tag('th', 'Дата обработки') . "\n";
        $output .= html_writer::tag('th', 'Описание') . "\n";
        $output .= html_writer::tag('th', 'Действия') . "\n";


        $output .= html_writer::end_tag('tr') . "\n";
        $output .= html_writer::end_tag('thead') . "\n";

        return $output;
    }

    private function get_body($page, $perpage) {
        $output = html_writer::start_tag('tbody') . "\n";

        $fs = get_file_storage();
        foreach ($this->get_tasks($page, $perpage) as $task) {
            $output .= html_writer::start_tag('tr') . "\n";

            $output .= html_writer::tag('th', $task->get('id')) . "\n";
            $output .= html_writer::tag('td', $task->get('status')) . "\n";
            $output .= html_writer::tag('td', userdate($task->get('timecreated'))) . "\n";
            $output .= html_writer::tag('td', $task->get('status') !== 'new' ? userdate($task->get('timemodified')): '-') . "\n";

            $actions = [];
            $data = json_decode($task->get('description'), JSON_OBJECT_AS_ARRAY);

            $output .= html_writer::tag('td', $data['error'] ?? '-') . "\n";

            if (!empty($data['file']) && $file = $fs->get_file_by_id($data['file'])) {
                $url = \moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $file->get_filename());
                $actions[] = html_writer::link($url, 'Скачать') . "\n";
            }

            $output .= html_writer::tag('td', \implode(PHP_EOL, $actions)) . "\n";
            $output .= html_writer::end_tag('tr') . "\n";
        }

        $output .= html_writer::end_tag('tbody') . "\n";

        return $output;
    }

    private function get_baseurl() {
        if (!$this->baseurl) {
            throw new \coding_exception('Не инициализирован компонент базовой ссылки');
        }

        return $this->baseurl;
    }

    private function get_tasks($page = 0, $perpage = 25) {
        global $COURSE, $USER;
        return export::get_records(['courseid' => $COURSE->id, 'userid' => $USER->id], 'timecreated', 'DESC', $page*$perpage, $perpage);
    }
}