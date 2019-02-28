<?php

namespace tool_image_organizer\util;

class util {

  private static function save_image($save_location, $image_url) {
    file_put_contents($save_location, file_get_contents($image_url));
  }

  private static function update_url_in_content($content, $old_url, $new_url) {
    return preg_replace("/$old_url/", $new_url, $content);
  }

  public static function update_courses($course_ids = array()) {
    global $DB;

    // For each of the courses to organize.
    foreach ($course_ids as $course_id) {

      // Retrive the course object from the database.
      $course = $DB->get_record('course', array('id' => $course_id), 'id, fullname, shortname');

      // If the course was found.
      if ($course) {

        // Where we will be placing images for this course.
        $server_directory = "$CFG->dirroot/_LOR/course_pics/" . $course_id . "_" . rawurlencode(preg_replace('/\s+/', '_', $course->shortname));

        // Check if the directory exists, if not... create it.
        if (!file_exists($server_directory)) {
          if (!mkdir($server_directory)) {
            die("Failed to create new directory '$server_directory'.");
          }
        }

        // Retrive all book chapters for this course.
        $book_chapters = $DB->get_records_sql(
          'SELECT {book_chapters}.id, {book_chapters}.content
           FROM {book_chapters}, {book}
           WHERE {book_chapters}.bookid = {book}.id
           AND {book}.course = ?', array($course->id)
         );

         // If we found at least one book chapter.
         if ($book_chapters) {

           // For each of the book chapters.
           foreach ($book_chapters as $book_chapter) {

             // Store content.
             $content = $book_chapter->content;

             // Search content for images.
             $image_urls = array();
             $pattern = '/(?i)http(s?):\/\/(bclearningnetwork|wcln).{1,100}\/((.{1,50})(\.png|\.jpg|\.jpeg|\.gif))/';
             preg_match_all($pattern, $content, $image_urls, PREG_SET_ORDER);


             echo "<br><br>";
             echo "<br><br>";

             // For each of the image links we found.
             foreach ($image_urls as $image_url) {

               echo "Full path: " . $image_url[0] . "<br>";
               echo "Image name: " . $image_url[3] . "<br>";
               echo "Image name no file type: " . $image_url[4] . "<br>";
               echo "Image file type: " . $image_url[5] . "<br>";

               $full_image_path = $image_url[0];
               $image_name = $image_url[3];
               $image_name_without_file_type = $image_url[4];
               $image_file_type = $image_url[5];
               $save_location = $server_directory . "/" . $image_name;

               // Ensure we aren't overwriting an existing image.
               if (!file_exists($save_location)) {

                 // Save the image.
                 save_image($save_location, $full_image_path);

               } else if (md5(file_get_contents($save_location)) != md5(file_get_contents($full_image_path))) {

                 echo md5(file_get_contents($save_location)) . "<br>";
                 echo md5(file_get_contents($full_image_path)) . "<br>";
                 echo "Save location: $save_location ... Full image path: $full_image_path<br>";

                 // Not the same image... Change the name of the new image.
                 $i = 0;
                 $save = true;
                 while(file_exists($save_location)) {

                   if (md5(file_get_contents($save_location)) == md5(file_get_contents($full_image_path))) {
                     $save = false;
                     break;
                   }

                   $i++;
                   $save_location = "$server_directory/$image_name_without_file_type"."_"."$i$image_file_type";
                 }

                 // Save the image.
                 if ($save) {
                    save_image($save_location, $full_image_path);
                 }

               }

               $new_url = str_replace($CFG->dirroot, $CFG->wwwroot, $save_location);
               echo "New URL: $new_url<br>";

               // Update the link in the content.
               //$book_chapter->content = update_url_in_content($content, $full_image_path, "https://wcln.ca" . preg_replace("/$CFG->dirroot/", "", $save_location));
               //$DB->update_record('book_chapters', $book_chapter);

               echo "Updated image '$image_name'.<br>";
             }

           }

         } else {
           echo "No book chapters found for course with ID: '$course_id'.";
         }
      } else {
        echo "Could not find course with ID: '$course_id'.";
      }
    }
  }

}
