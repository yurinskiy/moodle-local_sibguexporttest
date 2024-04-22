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
class view_renderer extends plugin_renderer_base {

    private ?moodle_url $baseurl = null;
    private ?course_enrolment_manager $manager = null;

    private ?settings $settings = null;
    private array $quizzes = [];

    public function init_manager() {
        global $PAGE, $COURSE, $DB;

        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);

        $this->manager = new course_enrolment_manager($PAGE, $COURSE, null, $studentrole->id);
        $this->settings = settings::get_by_course($COURSE->id);

        $contents = $this->settings->get_contents();
        $this->quizzes = $DB->get_records_list('quiz', 'id', array_column($contents, 'id'));
        usort($this->quizzes, fn ($a, $b) => $contents[$a->id]['order'] <=> $contents[$b->id]['order']);


    }

    public function init_baseurl(moodle_url $url) {
        $this->baseurl = $url;
    }

    /**
     * @return string HTML to output.
     */
    public function view(): string {
        global $COURSE, $DB;

        $settings = settings::get_by_course($COURSE->id);
        $contents = $settings->get_selected_quizzes();

        $users = $this->get_manager()->get_users('lastcourseaccess');

        $attempts = $DB->get_records('quiz_attempts', ['quiz' => current($contents), 'state' => 'finished'], 'id');
        debug::dump($users);
        debug::dd($attempts);

        return 'hello';
    }

    public function get_table($page = 0, $perpage = 25) {
        $output = html_writer::start_tag('table', ['class' => 'generaltable table-hover table-bordered']) . "\n";
        $output .= $this->get_head();
        $output .= $this->get_body($page, $perpage);
        $output .= html_writer::end_tag('table') . "\n";

        return $output;
    }

    public function get_paginator($page = 0, $perpage = 25) {
        $total = $this->get_manager()->get_total_users();

        return $this->output->paging_bar($total, $page, $perpage, $this->get_baseurl());
    }

    private function get_head() {
        $output = html_writer::start_tag('thead', array()) . "\n";
        $output .= html_writer::start_tag('tr', array()) . "\n";

        $attrcell = ['scope' => 'col', 'class' => 'cell', 'style' => 'vertical-align: top'];

        $output .= html_writer::tag('th', '', array_merge($attrcell, ['rowspan' => 2])) . "\n";
        $output .= html_writer::tag('th', \implode(' ', [get_string('lastname'), get_string('firstname')]), array_merge($attrcell, ['rowspan' => 2])) . "\n";
        $output .= html_writer::tag('th', get_string('email'), array_merge($attrcell, ['rowspan' => 2])) . "\n";


        $suboutput = html_writer::start_tag('tr', array()) . "\n";
        foreach ($this->quizzes as $quiz) {
            $output .= html_writer::tag('th', $quiz->name, array_merge($attrcell, ['colspan' => 2])) . "\n";
            $suboutput .= html_writer::tag('td', 'Дата выполнения', $attrcell) . "\n";
            $suboutput .= html_writer::tag('td', 'Состояние', $attrcell) . "\n";
        }
        $suboutput .= html_writer::end_tag('tr') . "\n";

        $output .= html_writer::tag('th', get_string('actions'), array_merge($attrcell, ['rowspan' => 2])) . "\n";

        $output.= $suboutput . "\n";
        $output .= html_writer::end_tag('tr') . "\n";
        $output .= html_writer::end_tag('thead') . "\n";

        return $output;
    }

    private function get_body($page, $perpage) {
        $output = html_writer::start_tag('tbody') . "\n";

        foreach ($this->get_users($page, $perpage) as $user) {
            $output .= html_writer::start_tag('tr') . "\n";
            $output .= html_writer::tag('th', $this->col_select($user), ['scope' => 'row']) . "\n";

            $url = new moodle_url('/user/view.php', ['id' => $user->id]);
            $output .= html_writer::tag('td', html_writer::link($url, \implode(' ', [$user->lastname, $user->firstname]))) . "\n";
            $output .= html_writer::tag('td', $user->email) . "\n";

            foreach ($this->quizzes as $quiz) {
                $attempts = quiz_get_user_attempts($quiz->id, $user->id, 'all', true);
                $lastattempt = end($attempts);

                if (!empty($lastattempt->state)) {
                    $output .= html_writer::tag('td', userdate($lastattempt->timefinish)) . "\n";
                    $output .= html_writer::tag('td', quiz_attempt::state_name($lastattempt->state)) . "\n";
                } else {
                    $output .= html_writer::tag('td', '-', ['colspan' => 2]) . "\n";
                }
            }

            $url = new moodle_url('/local/sibguexporttest/generate.php', ['courseid' => $this->baseurl->param('courseid'), 'userid' => $user->id]);
            $output .= html_writer::tag('td', html_writer::link($url, 'Скачать')) . "\n";
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

    private function get_manager() {
        if (!$this->manager) {
            throw new \coding_exception('Не инициализирован компонент управления пользователями');
        }

        return $this->manager;
    }

    private function get_users($page = 0, $perpage = 25) {
        return $this->get_manager()->get_users('lastcourseaccess', 'DESC', $page, $perpage);
    }

    private function col_select($data) {
        global $OUTPUT;

        $checkbox = new \core\output\checkbox_toggleall('participants-table', false, [
            'classes' => 'usercheckbox m-1',
            'id' => 'user' . $data->id,
            'name' => 'user' . $data->id,
            'checked' => false,
            'label' => get_string('selectitem', 'moodle', fullname($data)),
            'labelclasses' => 'accesshide',
        ]);

        return $OUTPUT->render($checkbox);
    }
}