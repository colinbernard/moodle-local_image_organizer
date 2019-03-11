<?php

namespace tool_imageorganizer\util;

/**
 * Utilities class to support image organization.
 */
class util {

  /**
   * The number of images which were saved to the server.
   * @var int
   */
  private static $saved_count = 0;

  /**
   * The number of images found.
   * @var int
   */
  private static $total_image_count = 0;

  /**
   * Returns all courses on the Moodle site.
   * @return array Array of courses.
   */
  public static function get_all_courses() {
    global $DB;

    return $DB->get_records_menu('course', null, '', 'id, fullname');
  }

  /**
   * Saves an image to a specified location on the server.
   * @param  string $save_location The location to save the image to.
   * @param  string $image_url     The URL of the image to save.
   * @param  array $fileinfo      Optional file information. Used for saving pluginfile images.
   * @return boolean                Success?
   */
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

  /**
   * Replaces a URL in a string.
   * @param  string $content HTML content as a string.
   * @param  string $old_url The old URL to be replaced.
   * @param  string $new_url The new URL to replace the old URL.
   * @return string          The HTML content with the URL replaced.
   */
  private static function update_url_in_content($content, $old_url, $new_url) {
    $old_url = '/'.preg_quote($old_url, '/').'/';
    return preg_replace($old_url, $new_url, $content, 1);
  }

  /**
   * The main "controller" function which loops through all the specified courses
   * and calls other functions to facilitate image organization.
   * @param  array  $course_ids  An array of course IDs to update.
   * @param  string  $target_directory  The target directory to store images in.
   * @param  boolean  $pluginfile  Whether or not we should search for Moodle database images.
   */
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
        $server_directory = "$CFG->dirroot$target_directory" . $course_id . "_" . rawurlencode(preg_replace('/\s+/', '', $course->shortname));

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
             mtrace("Searching book chapter '".str_replace("\"", "", $book_chapter->title)."' for images...");

             // Store content.
             $content = $book_chapter->content;
             $image_urls = self::find_all_image_urls($content);

             // For each of the image links we found.
             foreach ($image_urls as $image_url) {

               $image_info = self::get_image_info($image_url, 'book', $course_id, $book_chapter, $pluginfile);
               $new_url = self::process_image($server_directory, $image_info);

               // Update the link in the content.
               if ($image_info['full_image_path'] != $new_url) {
                 $book_chapter->content = self::update_url_in_content($content, $image_info['full_image_path'], $new_url);
                 $DB->update_record('book_chapters', $book_chapter);
                 mtrace("Updated this image URL in the database.");
               }
             }
           }

           foreach ($quizzes as $quiz) {
             mtrace('-------------------------------------------------');
             mtrace("Searching quiz '".str_replace("\"", "", $quiz->name)."' for images...");

             // Store content.
             $content = $quiz->intro;
             $image_urls = self::find_all_image_urls($content);

             // For each of the image links we found.
             foreach ($image_urls as $image_url) {

               $image_info = self::get_image_info($image_url, 'quiz', $course_id, $quiz, $pluginfile);
               $new_url = self::process_image($server_directory, $image_info);

               // Update the link in the content.
               if ($image_info['full_image_path'] != $new_url) {
                 $quiz->intro = self::update_url_in_content($content, $image_info['full_image_path'], $new_url);
                 $DB->update_record('quiz', $quiz);
                 mtrace("Updated this image URL in the database.");
               }
             }
           }

           foreach ($assigns as $assign) {
             mtrace('-------------------------------------------------');
             mtrace("Searching assignment '".str_replace("\"", "", $assign->name)."' for images...");

             // Store content.
             $content = $assign->intro;
             $image_urls = self::find_all_image_urls($content);

             // For each of the image links we found.
             foreach ($image_urls as $image_url) {

               $image_info = self::get_image_info($image_url, 'assign', $course_id, $assign, $pluginfile);
               $new_url = self::process_image($server_directory, $image_info);

               // Update the link in the content.
               if ($image_info['full_image_path'] != $new_url) {
                 $assign->intro = self::update_url_in_content($content, $image_info['full_image_path'], $new_url);
                 $DB->update_record('assign', $assign);
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

  /**
   * Returns an array of image URL matches.
   * @param  string $content HTML content to search for image URLs.
   * @param  string $domains A string of domains (separated by |) to match.
   * @return array         An array of image URL matches.
   */
  private static function find_all_image_urls($content, $domains = 'bclearningnetwork|wcln') {
    // Search content for images.
    $image_urls = array();
    $pattern = "/(?i)http(s?):\/\/($domains)\.[a-zA-Z 0-9\+\-\/_]{1,50}\/(([a-zA-Z 0-9\+\-\/_]{1,50})(\.png|\.jpg|\.jpeg|\.gif))|@@pluginfile@@\/([a-zA-Z 0-9\+\-\/_]{1,50})(\.png|\.jpg|\.jpeg|\.gif)/";
    preg_match_all($pattern, $content, $image_urls, PREG_SET_ORDER);
    mtrace("Found " . count($image_urls) . " images.");
    self::$total_image_count += count($image_urls);
    return $image_urls;
  }

  /**
   * Determine how to save an image, and generate a new URL for it.
   * @param  string $server_directory Where images are being placed for this course.
   * @param  array $image_info       Assoc. array of image information.
   * @return string                  The new URL of the image.
   */
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

    } else if (self::is_same_image($save_location, $image_info)) {

      // Not the same image... Change the name of the new image.
      $i = 0;
      $save = true;
      while(file_exists($save_location)) {

        // Check again for duplicate images as we increase file name counter.
        if (self::is_same_image($save_location, $image_info)) {
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

  /**
   * Given an image URL and other supporting information,
   * returns image information required for further processing.
   * @param  array  $image_url  The matches from the regex image URL search.
   * @param  string  $type       The type of module: book/quiz/assign.
   * @param  int  $course_id  The ID of the current course we are searching in.
   * @param  object  $item       The module we are currently searching inside of.
   * @param  boolean $pluginfile Whether or not we should be searching for pluginfile database images.
   * @return array             Returns an assoc. array of image information. Or false if unable to.
   */
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

  /**
   * Determines if two images are in fact the same image.
   * This helps to avoid duplicate images on the server.
   * @param  string  $save_location The existing image on the server.
   * @param  array  $image_info    The information of the new image we are comparing to the existing one.
   * @return boolean                True if the two images are the same, false if they are different.
   */
  private static function is_same_image($save_location, $image_info) {

    // If the image is not stored in the Moodle database (pluginfile).
    if (is_null($image_info['fileinfo'])) {
      return md5(file_get_contents($save_location)) == md5(file_get_contents($image_info['full_image_path']));
    } else {
      // The image is stored in the Moodle database (pluginfile).
      // This means are job is going to be a fair bit more complicated...

      // Generate a random and temporary URL to which we will save the pluginfile image.
      $rand = rand(1, 10000);
      $temp_url = str_replace($image_info['image_name_without_file_type'], "temp_$rand", $save_location);

      // Attempt to save the pluginfile image to our temp location.
      if (self::save_image($temp_url, $image_info['full_image_path'], $image_info['fileinfo'])) {

        // If the temp file and the existing file are the same.
        if (md5(file_get_contents($save_location)) == md5(file_get_contents($temp_url))) {
          // Delete the temp file.
          // They are the same image!
          unlink($temp_url);
          return true;
        } else {
          // Delete the temp file.
          // Not the same image...
          unlink($temp_url);
          return false;
        }
      } else {
        // Unable to save, return false.
        return false;
      }
      // Default: return false.
      return false;
    }
  }
}
