<?php

namespace tool_imageorganizer\output;

defined('MOODLE_INTERNAL') || die;

use plugin_renderer_base;

class renderer extends plugin_renderer_base {
  public function link_back() {
      return $this->render_from_template('tool_imageorganizer/link_back',
              array('url' => new \moodle_url('/admin/tool/imageorganizer/index.php')));
  }
}
