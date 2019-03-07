<?php

namespace tool_imageorganizer\util;

class util {

  private static $saved_count = 0;
  private static $total_image_count = 0;

  public static function get_all_courses() {
    global $DB;

    return $DB->get_records_menu('course', null, '', 'id, fullname');
  }

  private static function save_image($save_location, $image_url, $fileinfo = null) {

    // Pluginfile saving.
    if (!is_null($fileinfo)) {

      $browser = get_file_browser();
      $context = get_system_context();

      $file = $browser->get_file_info($fileinfo['context'], $fileinfo['component'], $fileinfo['filearea'], $fileinfo['itemid'], '/', $fileinfo['filename']);
      if (!is_null($file)) {
        if ($file->copy_to_pathname($save_location)) {
          self::$saved_count++;
          mtrace("Saved to $save_location.");
          return true;
        } else {
          mtrace("Error saving pluginfile image.");
        }
      } else {
        mtrace("ERROR 404 failed to save image.");
        return false;
      }

    // Normal file saving.
    } else {
      $file_contents = file_get_contents($image_url);

      if ($file_contents !== false && filesize($file_contents) !== 0) {
        file_put_contents($save_location, $file_contents);
        self::$saved_count++;
        mtrace("Saved to $save_location.");
        return true;
      } else {
        mtrace("ERROR 404 failed to save image.");
        return false;
      }
    }
  }

  private static function update_url_in_content($content, $old_url, $new_url) {
    $old_url = '/'.preg_quote($old_url, '/').'/';
    return preg_replace($old_url, $new_url, $content, 1);
  }

  public static function update_courses($course_ids = array(), $target_directory, $pluginfile) {
    global $DB, $CFG;

    mtrace("Starting update for " . count($course_ids) . " courses using $target_directory.");

    self::$total_image_count = 0;
    self::$saved_count = 0;

    // For each of the courses to organize.
    foreach ($course_ids as $course_id) {

      // Retrive the course object from the database.
      $course = $DB->get_record('course', array('id' => $course_id), 'id, fullname, shortname');

      mtrace('#####################################################################################');

      // If the course was found.
      if ($course) {

        mtrace("### Starting update for course: '$course->fullname'");
        mtrace('#####################################################################################');

        // Where we will be placing images for this course.
        $server_directory = "$CFG->dirroot$target_directory" . $course_id . "_" . rawurlencode(preg_replace('/\s+/', '_', $course->shortname));

        // Check if the directory exists, if not... create it.
        if (!file_exists($server_directory)) {
          if (!mkdir($server_directory)) {
            die("ERROR Failed to create new directory '$server_directory'.");
          } else {
            mtrace("Created new directory '$server_directory'.");
          }
        } else {
          mtrace("Existing directory '$server_directory' found.");
        }

        // Retrive all book chapters for this course.
        $book_chapters = $DB->get_records_sql(
          'SELECT {book_chapters}.id, {book_chapters}.content, {book_chapters}.title, bookid
           FROM {book_chapters}, {book}
           WHERE {book_chapters}.bookid = {book}.id
           AND {book}.course = ?', array($course->id)
         );

         // Retrieve all quizzes for this course.
         $quizzes = $DB->get_records_sql(
           'SELECT id, intro, name
            FROM {quiz}
            WHERE course = ?', array($course->id)
         );

         // Retrieve all assignments for this course.
         $assigns = $DB->get_records_sql(
           'SELECT id, intro, name
            FROM {assign}
            WHERE course = ?', array($course->id)
         );

         // If we found at least one book chapter.
         if ($book_chapters || $quizzes || $assigns) {

           // For each of the book chapters.
           foreach ($book_chapters as $book_chapter) {

             mtrace('-------------------------------------------------');
             mtrace("Searching book chapter '$book_chapter->title' for images...");

             // Store content.
             $content = $book_chapter->content;
             $image_urls = self::find_all_image_urls($content);

             // For each of the image links we found.
             foreach ($image_urls as $image_url) {

               $image_info = self::get_image_info($image_url, 'book', $course_id, $book_chapter, $pluginfile);
               $new_url = self::process_image($server_directory, $image_info);

               // Update the link in the content.
               if ($image_info['full_image_path'] != $new_url) {
                 //$book_chapter->content = self::update_url_in_content($content, $image_info['full_image_path'], $new_url);
                 //$DB->update_record('book_chapters', $book_chapter);
                 mtrace("Updated this image URL in the database.");
               }
             }
           }

           foreach ($quizzes as $quiz) {
             mtrace('-------------------------------------------------');
             mtrace("Searching quiz '$quiz->name' for images...");

             // Store content.
             $content = $quiz->intro;
             $image_urls = self::find_all_image_urls($content);

             // For each of the image links we found.
             foreach ($image_urls as $image_url) {

               $image_info = self::get_image_info($image_url, 'quiz', $course_id, $quiz, $pluginfile);
               $new_url = self::process_image($server_directory, $image_info);

               // Update the link in the content.
               if ($image_info['full_image_path'] != $new_url) {
                 //$book_chapter->content = self::update_url_in_content($content, $image_info['full_image_path'], $new_url);
                 //$DB->update_record('book_chapters', $book_chapter);
                 mtrace("Updated this image URL in the database.");
               }
             }
           }

           foreach ($assigns as $assign) {
             mtrace('-------------------------------------------------');
             mtrace("Searching assignment '$assign->name' for images...");

             // Store content.
             $content = $assign->intro;
             $image_urls = self::find_all_image_urls($content);

             // For each of the image links we found.
             foreach ($image_urls as $image_url) {

               $image_info = self::get_image_info($image_url, 'assign', $course_id, $assign, $pluginfile);
               $new_url = self::process_image($server_directory, $image_info);

               // Update the link in the content.
               if ($image_info['full_image_path'] != $new_url) {
                 //$book_chapter->content = self::update_url_in_content($content, $image_info['full_image_path'], $new_url);
                 //$DB->update_record('book_chapters', $book_chapter);
                 mtrace("Updated this image URL in the database.");
               }
             }
           }

         } else {
           mtrace("ERROR No book chapters, assignments, or quizzes found for course with ID: '$course_id'.");
         }
      } else {
        mtrace("ERROR Could not find course with ID: '$course_id'.");
      }
    }
    mtrace('#####################################################################################');
    mtrace("Done. ".self::$total_image_count." images were found and organized. ".self::$saved_count." were uploaded to the directory.");
  }

  private static function find_all_image_urls($content, $domains = 'bclearningnetwork|wcln') {
    // Search content for images.
    $image_urls = array();
    $pattern = "/(?i)http(s?):\/\/($domains).{1,100}\/((.{1,50})(\.png|\.jpg|\.jpeg|\.gif))|@@pluginfile@@\/(.{1,50})(\.png|\.jpg|\.jpeg|\.gif)/";
    preg_match_all($pattern, $content, $image_urls, PREG_SET_ORDER);
    mtrace("Found " . count($image_urls) . " images.");
    self::$total_image_count += count($image_urls);
    return $image_urls;
  }

  private static function process_image($server_directory, $image_info) {
    global $CFG;

    $save_location = $server_directory . "/" . $image_info['image_name'];

    mtrace("Found image: " . $image_info['full_image_path']);

    // Ensure we aren't overwriting an existing image.
    if (!file_exists($save_location)) {

      // Save the image.
      if(!self::save_image($save_location, $image_info['full_image_path'], $image_info['fileinfo'])) {
        return false;
      }

    } else if (md5(file_get_contents($save_location)) != md5(file_get_contents($image_info['full_image_path']))) { // TODO, this check will only work for non plugin file images.

      // Not the same image... Change the name of the new image.
      $i = 0;
      $save = true;
      while(file_exists($save_location)) {

        // Check again for duplicate images as we increase file name counter.
        if (md5(file_get_contents($save_location)) == md5(file_get_contents($image_info['full_image_path']))) {
          $save = false;
          break;
        }

        // Update the save location and try again.
        $i++;
        $save_location = "$server_directory/".$image_info['image_name_without_file_type']. "_". $i . $image_info['image_file_type'];
      }

      // Save the image.
      if ($save) {

        if(!self::save_image($save_location, $image_info['full_image_path'], $image_info['fileinfo'])) {
          return false;
        }

      } else {
        mtrace("Image already exists on server. Will reference existing image.");
      }

    } else {
      mtrace("Image already exists on server. Will reference existing image.");
    }

    $new_url = str_replace($CFG->dirroot, $CFG->wwwroot, $save_location);
    mtrace("Created a new URL: '$new_url'");

    return $new_url;
  }

  private static function get_image_info($image_url, $type, $course_id, $item, $pluginfile = false) {
    global $DB, $CFG;

    $image_info = [];

    // If we are searching for pluginfile images as well, and if one was found.
    if ($pluginfile && isset($image_url[6])) {
      $image_info['image_name'] = $image_url[6] . $image_url[7];
      $image_info['image_name_without_file_type'] = $image_url[6];
      $image_info['image_file_type'] = $image_url[7];

      if ($type === "book") {
        $cm = $DB->get_record('course_modules', array('instance' => $item->bookid, 'course' => $course_id));
        if ($cm) {
          $context = \context_module::instance($cm->id);
          $image_info['full_image_path'] = "$CFG->wwwroot/pluginfile.php/$context->id/mod_book/chapter/$item->id/" . $image_info['image_name'];

          $fileinfo = array(
             'component' => 'mod_book',
             'filearea' => 'chapter',
             'itemid' => $item->id,
             'context' => $context,
             'filepath' => '/',
             'filename' => $image_info['image_name']
          );

          $image_info['fileinfo'] = $fileinfo;


        } else {
          mtrace("ERROR retrieving context.");
          return false;
        }
      } else if ($type === "quiz") {
        $cm = $DB->get_record('course_modules', array('instance' => $item->id, 'course' => $course_id));
        if ($cm) {
          $context = \context_module::instance($cm->id);
          $image_info['full_image_path'] = "$CFG->wwwroot/pluginfile.php/$context->id/mod_quiz/intro/0/" . $image_info['image_name'];

          $fileinfo = array(
             'component' => 'mod_quiz',
             'filearea' => 'intro',
             'itemid' => 0,
             'context' => $context,
             'filepath' => '/',
             'filename' => $image_info['image_name']
          );

          $image_info['fileinfo'] = $fileinfo;

        } else {
          mtrace("ERROR retrieving context.");
          return false;
        }
      } else if ($type === "assign") {
        $cm = $DB->get_record('course_modules', array('instance' => $item->id, 'course' => $course_id));
        if ($cm) {
          $context = \context_module::instance($cm->id);
          $image_info['full_image_path'] = "$CFG->wwwroot/pluginfile.php/$context->id/mod_assign/intro/0/" . $image_info['image_name'];

          $fileinfo = array(
             'component' => 'mod_assign',
             'filearea' => 'intro',
             'itemid' => 0,
             'context' => $context,
             'filepath' => '/',
             'filename' => $image_info['image_name']
          );

          $image_info['fileinfo'] = $fileinfo;

        } else {
          mtrace("ERROR retrieving context.");
          return false;
        }
      } else {
        mtrace("ERROR Invalid type.");
        return false;
      }

    } else {
      $image_info['full_image_path'] = $image_url[0];
      $image_info['image_name'] = $image_url[3];
      $image_info['image_name_without_file_type'] = $image_url[4];
      $image_info['image_file_type'] = $image_url[5];
      $image_info['fileinfo'] = null;
    }
    return $image_info;
  }
}
