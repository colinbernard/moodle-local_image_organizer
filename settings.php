<?php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Link to Image Organizer tool.
    $ADMIN->add('tools', new admin_externalpage('imageorganizer',
        get_string('pluginname', 'tool_imageorganizer'), "$CFG->wwwroot/$CFG->admin/tool/imageorganizer/index.php"));
}
