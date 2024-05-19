<?php

namespace local_sibguexporttest\form;

global $CFG;

use context_user;
use MoodleQuickForm;

require_once($CFG->libdir . '/formslib.php');

class course_settings_form extends \moodleform {
    protected function definition() {
        global $CFG;

        $mform =& $this->_form;
        $repeatno = $this->_customdata['repeatno'];

        // Настройки страницы
        $mform->addElement('header', 'settingspage', get_string('settingspage', 'local_sibguexporttest'));

        MoodleQuickForm::registerElementType('atto_editor', "$CFG->dirroot/local/sibguexporttest/classes/atto_editor_form_element.php", 'local_sibguexporttest_atto_editor_form_element');

        foreach (['headerpage', 'footerpage', 'headerbodypage', 'footerbodypage', 'signmasterpage'] as $field) {
            $mform->addElement('atto_editor', $field.'_editor', get_string($field, 'local_sibguexporttest'), array('rows' => 5), $this->get_editor_options());
            $mform->setType($field.'_editor', PARAM_RAW);
        }

        // Настройки тестов
        $mform->addElement('header', 'settingstest', get_string('settingstest', 'local_sibguexporttest'));

        $quizs = $this->get_quizzes();

        $repeatarray = [
            $mform->createElement('select', 'test_id', get_string('test_id', 'local_sibguexporttest'), $quizs),
            $mform->createElement('text', 'test_order', get_string('test_order', 'local_sibguexporttest')),
            $mform->createElement('submit', 'test_delete', get_string('test_delete', 'local_sibguexporttest')),
        ];

        $repeateloptions = [];

        $mform->setType('test_id', PARAM_INT);
        $mform->setType('test_order', PARAM_INT);

        $this->repeat_elements(
            $repeatarray,
            $repeatno,
            $repeateloptions,
            'test_repeats',
            'test_add_fields',
            1,
            null,
            true,
            'test_delete',
        );

        //-------------------------------------------------------------------------------
        // buttons
        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $max_tests = 2;

        $quizzes = $this->get_quizzes();
        $used_test_id = [];
        foreach ($data['test_id'] as $key => $test_id) {
            if (count($used_test_id) > $max_tests - 1) {
                $errors['test_id['.$key.']'] = get_string('test_id-error_max', 'local_sibguexporttest');
                continue;
            }

            if (!\array_key_exists($test_id, $quizzes)) {
                $errors['test_id['.$key.']'] = get_string('test_id-error_not_found', 'local_sibguexporttest');
                continue;
            }

            if (\in_array($test_id, $used_test_id)) {
                $errors['test_id['.$key.']'] = get_string('test_id-error_exists', 'local_sibguexporttest', $quizzes[$test_id]);
                continue;
            }

            $used_test_id[] = $test_id;
        }

        return $errors;
    }

    /**
     * Returns the description editor options.
     * @return array<string, mixed>
     */
    public function get_editor_options(): array
    {
        global $CFG;

        $attobuttons = [
            'undo' => 'undo',
            'style1' => 'bold, italic',
            'style2' => 'fontsize',
            'style3' => 'indent',
            'align' => 'align, justify',
            'files' => 'image',
            'other'=> 'clear, html'
        ];

        array_walk($attobuttons, fn (&$value, $key) => $value = $key . '=' . $value);

        return [
            'maxfiles'  => 5,
            'maxbytes'  => $CFG->maxbytes,
            'noclean'   => true,
            'context'   => $this->get_context(),
            'accepted_types' => 'web_image',
            'removeorphaneddrafts' => true,
            'enable_filemanagement' => true,
            'atto:toolbar' => \implode(PHP_EOL, $attobuttons),
        ];
    }

    protected function validate_draft_files() {
        global $USER;

        $errors = parent::validate_draft_files();
        if ($errors !== true) {
            return $errors;
        }

        $errors = [];

        $mform =& $this->_form;
        foreach ($mform->_elements as $element) {
            if ($element->_type == 'editor') {
                $maxfiles = $element->getMaxfiles();
                if ($maxfiles > 0) {
                    $draftid = (int) ($element->getValue()['itemid'] ?? 0);
                    $fs = get_file_storage();
                    $context = context_user::instance($USER->id);
                    $files = $fs->get_area_files($context->id, 'user', 'draft', $draftid, '', false);
                    if (count($files) > $maxfiles) {
                        $errors[$element->getName()] = get_string('err_maxfiles', 'form', $maxfiles);
                    }
                }
            }
        }

        if (empty($errors)) {
            return true;
        } else {
            return $errors;
        }
    }

    public function get_context(): \context_course {
        return $this->_customdata['context'];
    }

    protected function get_quizzes(): array
    {
        global $COURSE;
        $list = get_coursemodules_in_course('quiz', $COURSE->id);

        foreach ($list as $item) {
            $choices[$item->instance] = $item->name;
        }

        return $choices ?? [];
    }
}