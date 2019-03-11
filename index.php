<?php

use \tool_imageorganizer\util\util;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

// Calls require_login and performs permission checks for admin pages.
admin_externalpage_setup('imageorganizer');

// Set up the page.
$title = get_string('pluginname', 'tool_imageorganizer');
$pagetitle = $title;
$url = new moodle_url("/admin/tool/imageorganizer/index.php");
$PAGE->set_url($url);
$PAGE->set_title($title);
$PAGE->set_heading($title);

// Output the page header.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('heading', 'tool_imageorganizer'));

$form = new \tool_imageorganizer\action_form();

if ($fromform = $form->get_data()) {

  $renderer = $PAGE->get_renderer('tool_imageorganizer');
  echo $renderer->link_back();

  echo html_writer::start_tag('pre');
  $CFG->mtrace_wrapper = 'tool_task_mtrace_wrapper';

  util::update_courses($fromform->courses, $fromform->directory, $fromform->pluginfile);

  echo html_writer::end_tag('pre');
  echo $renderer->link_back();

} else {
  $form->display();
}

echo $OUTPUT->footer();
