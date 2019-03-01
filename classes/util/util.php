<?php

namespace tool_imageorganizer\util;

class util {

  private static $saved_count = 0;

  public static function get_all_courses() {
    global $DB;

    return $DB->get_records_menu('course', null, '', 'id, fullname');
  }

  private static function save_image($save_location, $image_url) {

    $file_contents = file_get_contents($image_url);

    if ($file_contents !== false && filesize($file_contents) !== 0) {
      file_put_contents($save_location, $file_contents);
      self::$saved_count++;
      mtrace("Saved $image_name to $save_location.");
      return true;
    } else {
      mtrace("ERROR 404 failed to save image.");
      return false;
    }
  }

  private static function update_url_in_content($content, $old_url, $new_url) {
    $old_url = '/'.preg_quote($old_url, '/').'/';
    return preg_replace($old_url, $new_url, $content, 1);
  }

  public static function update_courses($course_ids = array(), $target_directory) {
    global $DB, $CFG;

    mtrace("Starting update for " . count($course_ids) . " courses using $target_directory.");

    $total_image_count = 0;
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
        $server_directory = "$CFG->dirroot/_LOR/course_pics/" . $course_id . "_" . rawurlencode(preg_replace('/\s+/', '_', $course->shortname));

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
          'SELECT {book_chapters}.id, {book_chapters}.content, {book_chapters}.title
           FROM {book_chapters}, {book}
           WHERE {book_chapters}.bookid = {book}.id
           AND {book}.course = ?', array($course->id)
         );

         // If we found at least one book chapter.
         if ($book_chapters) {

           // For each of the book chapters.
           foreach ($book_chapters as $book_chapter) {

             mtrace('-------------------------------------------------');
             mtrace("Searching book chapter '$book_chapter->title' for images...");

             // Store content.
             $content = $book_chapter->content;

             // Search content for images.
             $image_urls = array();
             $pattern = '/(?i)http(s?):\/\/(bclearningnetwork|wcln).{1,100}\/((.{1,50})(\.png|\.jpg|\.jpeg|\.gif))/';
             preg_match_all($pattern, $content, $image_urls, PREG_SET_ORDER);

             mtrace("Found " . count($image_urls) . " images.");
             $total_image_count += count($image_urls);

             // For each of the image links we found.
             foreach ($image_urls as $image_url) {

               $full_image_path = $image_url[0];
               $image_name = $image_url[3];
               $image_name_without_file_type = $image_url[4];
               $image_file_type = $image_url[5];
               $save_location = $server_directory . "/" . $image_name;

               mtrace("Found image: " . $image_url[0]);

               // Ensure we aren't overwriting an existing image.
               if (!file_exists($save_location)) {

                 // Save the image.
                 if(!self::save_image($save_location, $full_image_path)) {
                   break;
                 }

               } else if (md5(file_get_contents($save_location)) != md5(file_get_contents($full_image_path))) {

                 // Not the same image... Change the name of the new image.
                 $i = 0;
                 $save = true;
                 while(file_exists($save_location)) {

                   // Check again for duplicate images as we increase file name counter.
                   if (md5(file_get_contents($save_location)) == md5(file_get_contents($full_image_path))) {
                     $save = false;
                     break;
                   }

                   // Update the save location and try again.
                   $i++;
                   $save_location = "$server_directory/$image_name_without_file_type"."_"."$i$image_file_type";
                 }

                 // Save the image.
                 if ($save) {

                   if(!self::save_image($save_location, $full_image_path)) {
                     break;
                   }

                 } else {
                   mtrace("Image already exists on server. Will reference existing image.");
                 }

               } else {
                 mtrace("Image already exists on server. Will reference existing image.");
               }

               $new_url = str_replace($CFG->dirroot, $CFG->wwwroot, $save_location);
               mtrace("Created a new URL: '$new_url'");

               // Update the link in the content.
               if ($full_image_path != $new_url) {
                 $book_chapter->content = self::update_url_in_content($content, $full_image_path, $new_url);
                 $DB->update_record('book_chapters', $book_chapter);
                 mtrace("Updated this image URL in the database.");
               }
             }
           }

         } else {
           mtrace("ERROR No book chapters found for course with ID: '$course_id'.");
         }
      } else {
        mtrace("ERROR Could not find course with ID: '$course_id'.");
      }
    }
    mtrace('#####################################################################################');
    mtrace("Done. $total_image_count images were found and organized. ".self::$saved_count." were uploaded to the directory.");
  }
}
