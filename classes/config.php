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
 * Class for loading/storing data settings from the DB.
 *
 * @package   local_sibguexporttest
 * @copyright 2024, Yuriy Yurinskiy <yuriyyurinskiy@yandex.ru>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_sibguexporttest;
use local_sibguexporttest\form\course_settings_form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/dataprivacy/lib.php');

/**
 * Class for loading/storing data settings from the DB.
 *
 * @copyright 2024, Yuriy Yurinskiy <yuriyyurinskiy@yandex.ru>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class config extends \core\persistent {

    /**
     * Database table.
     */
    const TABLE = 'local_sibguexporttest_config';
    const TYPE_TICKET = 'ticket';

    public static function get_by_course(int $courseid) {
        $data = self::get_record(['courseid'=> $courseid]);
        if (!$data) {
            $data = new config(0);
            $data->raw_set('courseid', $courseid);
            $data->raw_set('type', self::TYPE_TICKET);
        }

        return $data;
    }

    public static function get_by_id(int $id) {
        $data = self::get_record(['id'=> $id]);
        if (!$data) {
            throw new \moodle_exception('error_not_found_config', 'sibguexporttest', '', null, 'config#'.$id);
        }

        return $data;
    }

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'courseid' => [
                'type' => PARAM_INT,
            ],
            'type' => [
                'choices' => [self::TYPE_TICKET],
                'type' => PARAM_ALPHA,
                'default' => self::TYPE_TICKET,
            ],
            'headerpage' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => '',
            ],
            'headerpageformat' => [
                'choices' => [FORMAT_HTML, FORMAT_MOODLE, FORMAT_PLAIN, FORMAT_MARKDOWN],
                'type' => PARAM_INT,
                'default' => FORMAT_HTML
            ],
            'footerpage' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => '',
            ],
            'footerpageformat' => [
                'choices' => [FORMAT_HTML, FORMAT_MOODLE, FORMAT_PLAIN, FORMAT_MARKDOWN],
                'type' => PARAM_INT,
                'default' => FORMAT_HTML
            ],
            'headerbodypage' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => '',
            ],
            'headerbodypageformat' => [
                'choices' => [FORMAT_HTML, FORMAT_MOODLE, FORMAT_PLAIN, FORMAT_MARKDOWN],
                'type' => PARAM_INT,
                'default' => FORMAT_HTML
            ],
            'footerbodypage' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => '',
            ],
            'footerbodypageformat' => [
                'choices' => [FORMAT_HTML, FORMAT_MOODLE, FORMAT_PLAIN, FORMAT_MARKDOWN],
                'type' => PARAM_INT,
                'default' => FORMAT_HTML
            ],
            'signmasterpage' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => '',
            ],
            'signmasterpageformat' => [
                'choices' => [FORMAT_HTML, FORMAT_MOODLE, FORMAT_PLAIN, FORMAT_MARKDOWN],
                'type' => PARAM_INT,
                'default' => FORMAT_HTML
            ],
            'content' => [
                'type' => PARAM_RAW,
            ]
        ];
    }

    /**
     * Hook to execute before validate.
     *
     * @return void
     */
    protected function before_validate() {

    }

    public function handle_form(course_settings_form $mform) {
        $data = $mform->get_data();
        $data->id = $this->get('id');
        $context = $mform->get_context();

        foreach (['headerpage', 'footerpage', 'headerbodypage', 'footerbodypage', 'signmasterpage'] as $field) {
            $data = file_postupdate_standard_editor($data, $field, $mform->get_editor_options(), $context, 'local_sibguexporttest', $field, $data->id);
            unset($data->{$field.'_editor'});
            unset($data->{$field.'trust'});
        }

        foreach ($data as $property => $value) {
            if (!static::has_property($property)) {
                continue;
            }

            $this->raw_set($property, $value);
        }

        foreach ($data->test_id as $key => $value){
            $content[] = [
                'id' => $data->test_id[$key],
                'order' => $data->test_order[$key],
            ];
        }

        $this->raw_set('content', json_encode($content ?? []));
    }

    public function set_form(course_settings_form $mform) {
        $data = $this->get_data($mform);
        $mform->set_data($data);
    }

    public function get_data(course_settings_form $mform) {
        $data = $this->to_record();
        $data->id = $this->get('id');

        return $this->prepare_data($data, $mform);
    }


    public function get_pdfdata() {
        $data = $this->to_record();
        $data->id = $this->get('id');

        $context = \context_course::instance($this->get('courseid'));
        $mform = new course_settings_form(null, ['id' => $this->get('id'), 'context' => $context, 'repeatno' => $this->get_repeatno()]);

        return $this->prepare_data($data, $mform);
    }

    protected function prepare_data($data, course_settings_form $mform) {
        $context = $mform->get_context();
        foreach (['headerpage', 'footerpage', 'headerbodypage', 'footerbodypage', 'signmasterpage'] as $field) {
            $data = file_prepare_standard_editor($data, $field, $mform->get_editor_options(), $context, 'local_sibguexporttest', $field, $data->id);
        }

        $content = json_decode($this->get('content'), true) ?? [];
        usort($content, function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });
        $data->test_repeats = count($content);
        $data->test_id = array_column($content, 'id');
        $data->test_order = array_column($content, 'order');

        return $data;
    }

    public function get_repeatno() {
        $content = json_decode($this->get('content'), true) ?? [];

        return count($content);
    }

    public function get_contents() {
        return json_decode($this->get('content'), true);
    }

    public function get_selected_quizzes() {
        $content = $this->get_contents();
        usort($content, function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        return array_map(function ($a) {
            return $a['id'];
        }, $content);
    }
}