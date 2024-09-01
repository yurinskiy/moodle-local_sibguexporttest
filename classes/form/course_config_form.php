<?php

namespace local_sibguexporttest\form;

global $CFG;

use context_user;
use MoodleQuickForm;

require_once($CFG->libdir . '/formslib.php');

class course_config_form extends course_settings_form {
    protected function definition() {
        parent::definition();

        $mform =& $this->_form;
        $countTicket = $mform->createElement('text', 'count_ticket', 'Количество билетов для генерации');
        $mform->setType('count_ticket', PARAM_INT);

        $mform->insertElementBefore($countTicket, 'buttonar');
    }

    public function validation($data, $files)
    {
        $errors = parent::validation($data, $files);

        if ($data['count_ticket'] < 1) {
            $errors['count_ticket'] = 'Введите число больше 0';
        }

        return $errors;
    }
}