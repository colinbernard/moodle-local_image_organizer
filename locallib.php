<?php

function save_image($save_location, $image_url) {
  file_put_contents($save_location, file_get_contents($image_url));
}

function update_url_in_content($content, $old_url, $new_url) {
  return preg_replace("/$old_url/", $new_url, $content);
}
