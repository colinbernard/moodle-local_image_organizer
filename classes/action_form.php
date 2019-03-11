<?php

namespace tool_imageorganizer;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir.'/formslib.php');

class action_form extends \moodleform {
  protected function definition() {
    global $CFG;

    $mform = $this->_form;

    $mform->addElement('html', '<p>'.get_string('instructions', 'tool_imageorganizer').'</p>');

    $multi_select = $mform->addElement('select', 'courses', get_string('courses', 'tool_imageorganizer'), \tool_imageorganizer\util\util::get_all_courses());
    $multi_select->setMultiple(true);
    $mform->addRule('courses', get_string('required'), 'required', null, '');

    $mform->addElement('text', 'directory', get_string('directory', 'tool_imageorganizer'));
    $mform->setType('directory', PARAM_RAW);
    $mform->addRule('directory', get_string('required'), 'required', null, '');
    $mform->setDefault('directory', '/_LOR/course_pics/');
    $mform->addHelpButton('directory', 'directory', 'tool_imageorganizer');

    $mform->addElement('text', 'domains', get_string('domains', 'tool_imageorganizer'));
    $mform->setType('domains', PARAM_TEXT);
    $mform->setDefault('domains', 'wcln,bclearningnetwork');
    $mform->addHelpButton('domains', 'domains', 'tool_imageorganizer');

    $mform->addElement('checkbox', 'pluginfile', get_string('pluginfile', 'tool_imageorganizer'));
    $mform->setDefault('pluginfile', 1);
    $mform->addHelpButton('pluginfile', 'pluginfile', 'tool_imageorganizer');

    $mform->addElement('text', 'ignore', get_string('ignore', 'tool_imageorganizer'));
    $mform->setType('ignore', PARAM_RAW);
    $mform->setDefault('ignore', 'wcln.ca/_LOR/course_pics/projects,wcln.ca/_LOR/course_pics/learning_guides,wcln.ca/_LOR/course_pics/projects,wcln.ca/_LOR/course_pics/_general,wcln.ca/_LOR/course_pics/group_activities');
    $mform->addHelpButton('ignore', 'ignore', 'tool_imageorganizer');

    $this->add_action_buttons(false, get_string('run', 'tool_imageorganizer'));
  }

  public function validation($data, $files) {
    $errors = parent::validation($data, $files);

    if (!isset($data['courses'])) {
      $errors['courses'] = get_string('required');
    }

    return $errors;
  }
}
