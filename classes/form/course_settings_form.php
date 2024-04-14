<?php

namespace local_sibguexporttest\form;

global $CFG;
require_once($CFG->libdir . '/formslib.php');

class course_settings_form extends \moodleform {
    protected function definition() {
        $mform =& $this->_form;
        $id = $this->_customdata['id'];
        $repeatno = $this->_customdata['repeatno'];

        // Настройки страницы
        $mform->addElement('header', 'settingspage', get_string('settingspage', 'local_sibguexporttest'));

        $mform->addElement('editor', 'headerpage_editor', get_string('headerpage', 'local_sibguexporttest'), array('rows' => 5), $this->get_editor_options('headerpage'));
        $mform->setType('headerpage_editor', PARAM_RAW);

        $mform->addElement('editor', 'footerpage_editor', get_string('footerpage', 'local_sibguexporttest'), array('rows' => 5), $this->get_editor_options('footerpage'));
        $mform->setType('footerpage_editor', PARAM_RAW);

        $mform->addElement('editor', 'headerbodypage_editor', get_string('headerbodypage', 'local_sibguexporttest'), array('rows' => 5), $this->get_editor_options('headerbodypage'));
        $mform->setType('headerbodypage_editor', PARAM_RAW);

        $mform->addElement('editor', 'footerbodypage_editor', get_string('footerbodypage', 'local_sibguexporttest'), array('rows' => 5), $this->get_editor_options('footerbodypage'));
        $mform->setType('footerbodypage_editor', PARAM_RAW);

        // Настройки тестов
        $mform->addElement('header', 'settingstest', get_string('settingstest', 'local_sibguexporttest'));

        $repeatarray = [
            $mform->createElement('text', 'test_id', get_string('test_id', 'local_sibguexporttest')),
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

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', $id);

        //-------------------------------------------------------------------------------
        // buttons
        $this->add_action_buttons();
    }

    /**
     * Returns the description editor options.
     * @return array
     */
    public function get_editor_options(string $fieldname) {
        global $CFG;
        $context = $this->_customdata['context'];
        $id = $this->_customdata['id'];
        return array(
            'maxfiles'  => 1,
            'maxbytes'  => $CFG->maxbytes,
            'trusttext' => false,
            'noclean'   => true,
            'context'   => $context,
            'subdirs'   => file_area_contains_subdirs($context, 'local_sibguexporttest', $fieldname, $id),
            'enable_filemanagement' => true,
        );
    }
}