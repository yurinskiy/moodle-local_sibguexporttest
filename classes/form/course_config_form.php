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

        // Настройка тестов
        $header = $mform->createElement('header', 'configtest', 'Настройка генерации билетов');
        $mform->insertElementBefore($header, 'buttonar');

        $countTicket = $mform->createElement('text', 'count_ticket', 'Количество билетов для генерации');
        $mform->setType('count_ticket', PARAM_INT);
        $mform->insertElementBefore($countTicket, 'buttonar');

        $hasFirstPage = $mform->createElement('selectyesno', 'hasfirstpage', 'Включать в билет титульный лист');
        $mform->insertElementBefore($hasFirstPage, 'buttonar');

        $hasBreakFirstPage = $mform->createElement('selectyesno', 'hasbreakfirstpage', 'Формировать билет с новой страницы после титульного листа');
        $mform->insertElementBefore($hasBreakFirstPage, 'buttonar');

        $versionFormat = $mform->createElement('text', 'versionformat', 'Номер варианта');
        $mform->setType('versionformat', PARAM_TEXT);
        $mform->insertElementBefore($versionFormat, 'buttonar');

        $versionFrom = $mform->createElement('text', 'versionfrom', 'Начинать нумерацию варианта с:');
        $mform->setType('versionfrom', PARAM_INT);
        $mform->insertElementBefore($versionFrom, 'buttonar');

        $showPagination = $mform->createElement('selectyesno', 'showpagination', 'Показывать нумерацию страниц');
        $mform->insertElementBefore($showPagination, 'buttonar');

        $showRightAnswer = $mform->createElement('selectyesno', 'showrightanswer', 'Показывать правильные ответы');
        $mform->insertElementBefore($showRightAnswer, 'buttonar');
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