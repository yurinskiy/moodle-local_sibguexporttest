<?php

namespace local_sibguexporttest\form;

global $CFG;
require_once($CFG->libdir . '/formslib.php');

class course_settings_form extends \moodleform {
    protected function definition() {
        $mform =& $this->_form;

        // visible elements
        $mform->addElement('header', 'general', get_string('scale'));

        $mform->addElement('text', 'name', get_string('name'), 'size="40"');
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('advcheckbox', 'standard', get_string('scalestandard'));
        $mform->addHelpButton('standard', 'scalestandard');

        $mform->addElement('static', 'used', get_string('used'));

        $mform->addElement('textarea', 'scale', get_string('scale'), array('cols'=>50, 'rows'=>2));
        $mform->addHelpButton('scale', 'scale');
        $mform->addRule('scale', get_string('required'), 'required', null, 'client');
        $mform->setType('scale', PARAM_TEXT);

        //-------------------------------------------------------------------------------
        // buttons
        $this->add_action_buttons();
    }
}