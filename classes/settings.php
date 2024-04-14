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
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/dataprivacy/lib.php');

/**
 * Class for loading/storing data settings from the DB.
 *
 * @copyright 2024, Yuriy Yurinskiy <yuriyyurinskiy@yandex.ru>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings extends \core\persistent {

    /**
     * Database table.
     */
    const TABLE = 'local_sibguexporttest';

    public static function get_by_course(int $courseid) {
        $data = self::get_record(['courseid'=> $courseid]);
        if (!$data) {
            $data = new settings(0);
            $data->raw_set('courseid', $courseid);
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

    public function from_form(\stdClass $formdata) {
        foreach ($formdata as $property => $value) {
            if (!static::has_property($property)) {
                continue;
            }

            $this->raw_set($property, $value);
        }

        foreach ($formdata->test_id as $key => $value){
            $content[] = [
                'id' => $formdata->test_id[$key],
                'order' => $formdata->test_order[$key],
            ];
        }

        $this->raw_set('content', json_encode($content ?? []));
    }

    public function to_form() {
        $data = $this->to_record();
        $data->id = $this->get('id');

        $content = json_decode($this->get('content'), true);
        usort($content, fn($a, $b) => $a['order'] <=> $b['order']);
        $data->test_repeats = count($content);
        $data->test_id = array_column($content, 'id');
        $data->test_order = array_column($content, 'order');

        return $data;
    }
}