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

    public function handle_form(course_settings_form $mform) {
        $data = $mform->get_data();
        $data->id = $this->get('id');
        $context = $mform->get_context();

        foreach (['headerpage', 'footerpage', 'headerbodypage', 'footerbodypage'] as $field) {
            $data = file_postupdate_standard_editor($data, $field, $mform->get_editor_options($field), $context, 'local_sibguexporttest', $field, 0);
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

        $context = $mform->get_context();
        foreach (['headerpage', 'footerpage', 'headerbodypage', 'footerbodypage'] as $field) {
            $data = file_prepare_standard_editor($data, $field, $mform->get_editor_options($field), $context, 'local_sibguexporttest', $field, 0);
        }

        $content = json_decode($this->get('content'), true) ?? [];
        usort($content, fn($a, $b) => $a['order'] <=> $b['order']);
        $data->test_repeats = count($content);
        $data->test_id = array_column($content, 'id');
        $data->test_order = array_column($content, 'order');

        return $data;
    }


    public function get_pdfdata(course_settings_form $mform) {
        $data = $this->to_record();
        $data->id = $this->get('id');

        $context = $mform->get_context();
        foreach (['headerpage', 'footerpage', 'headerbodypage', 'footerbodypage'] as $field) {
            $data = $this->file_prepare($data, $field, $mform->get_editor_options($field), $context, 'local_sibguexporttest', $field, 0);
        }

        $content = json_decode($this->get('content'), true) ?? [];
        usort($content, fn($a, $b) => $a['order'] <=> $b['order']);
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
        usort($content, fn($a, $b) => $a['order'] <=> $b['order']);

        return array_map(fn ($a) => $a['id'], $content);
    }

    public function file_prepare($data, $field, array $options, $context=null, $component=null, $filearea=null, $itemid=null) {
        $options = (array)$options;
        if (!isset($options['trusttext'])) {
            $options['trusttext'] = false;
        }
        if (!isset($options['forcehttps'])) {
            $options['forcehttps'] = false;
        }
        if (!isset($options['subdirs'])) {
            $options['subdirs'] = false;
        }
        if (!isset($options['maxfiles'])) {
            $options['maxfiles'] = 0; // no files by default
        }
        if (!isset($options['noclean'])) {
            $options['noclean'] = false;
        }

        //sanity check for passed context. This function doesn't expect $option['context'] to be set
        //But this function is called before creating editor hence, this is one of the best places to check
        //if context is used properly. This check notify developer that they missed passing context to editor.
        if (isset($context) && !isset($options['context'])) {
            //if $context is not null then make sure $option['context'] is also set.
            debugging('Context for editor is not set in editoroptions. Hence editor will not respect editor filters', DEBUG_DEVELOPER);
        } else if (isset($options['context']) && isset($context)) {
            //If both are passed then they should be equal.
            if ($options['context']->id != $context->id) {
                $exceptionmsg = 'Editor context ['.$options['context']->id.'] is not equal to passed context ['.$context->id.']';
                throw new coding_exception($exceptionmsg);
            }
        }

        if (is_null($itemid) or is_null($context)) {
            $contextid = null;
            $itemid = null;
            if (!isset($data)) {
                $data = new stdClass();
            }
            if (!isset($data->{$field})) {
                $data->{$field} = '';
            }
            if (!isset($data->{$field.'format'})) {
                $data->{$field.'format'} = editors_get_preferred_format();
            }
            if (!$options['noclean']) {
                $data->{$field} = clean_text($data->{$field}, $data->{$field.'format'});
            }

        } else {
            if ($options['trusttext']) {
                // noclean ignored if trusttext enabled
                if (!isset($data->{$field.'trust'})) {
                    $data->{$field.'trust'} = 0;
                }
                $data = trusttext_pre_edit($data, $field, $context);
            } else {
                if (!$options['noclean']) {
                    $data->{$field} = clean_text($data->{$field}, $data->{$field.'format'});
                }
            }
            $contextid = $context->id;
        }

        if ($options['maxfiles'] != 0) {
            $currenttext = $this->file_prepare_base64($contextid, $component, $filearea, $itemid, $options, $data->{$field});
            $data->{$field.'_editor'} = array('text'=>$currenttext, 'format'=>$data->{$field.'format'}, 'itemid'=>0);
        } else {
            $data->{$field.'_editor'} = array('text'=>$data->{$field}, 'format'=>$data->{$field.'format'}, 'itemid'=>0);
        }

        return $data;
    }

    public function file_prepare_base64($contextid, $component, $filearea, $itemid, array $options=null, $text=null) {
        global $CFG, $USER;

        $options = (array)$options;
        if (!isset($options['subdirs'])) {
            $options['subdirs'] = false;
        }

        $fs = get_file_storage();
        // create a new area and copy existing files into
        if (!is_null($itemid) and $files = $fs->get_area_files($contextid, $component, $filearea, $itemid)) {
            foreach ($files as $file) {
                if ($file->is_directory() and $file->get_filepath() === '/') {
                    // we need a way to mark the age of each draft area,
                    // by not copying the root dir we force it to be created automatically with current timestamp
                    continue;
                }
                if (!$options['subdirs'] and ($file->is_directory() or $file->get_filepath() !== '/')) {
                    continue;
                }

                return str_replace('src="@@PLUGINFILE@@/', 'src="data:'.$file->get_filename().';base64,'.base64_encode($file->get_content()).'" data-filename="', $text);
            }
        }

        return $text;
    }
}