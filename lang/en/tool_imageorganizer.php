<?php

$string['pluginname'] = 'Image Organizer';

$string['heading'] = 'Image Organizer';
$string['courses'] = 'Courses';
$string['run'] = 'Run';
$string['instructions'] = 'The Image Organizer plugin will find all image links in a course and download images to a directory called \'[COURSE ID]_[COURSE_SHORTNAME]\' within another specified directory. It is recommended to backup the following tables prior to using this plugin: book_chapters, quiz, assign, page.';
$string['directory'] = 'Directory';
$string['directory_help'] = 'Course directories containing images for each course will be created in this directory. Example: /_LOR/course_pics/ will add images to https://YOUR_DOMAIN.com/_LOR/course_pics/';
$string['pluginfile'] = 'Include Pluginfile images';
$string['pluginfile_help'] = 'These images are stored in Moodle\'s database. They were uploaded by users and reference pluginfile.php.';
$string['ignore'] = 'Image URLs to ignore';
$string['ignore_help'] = 'Comma separated (no spaces) strings. If an image URL contains any of these strings it will be skipped.';
$string['domains'] = 'Domains to search for';
$string['domains_help'] = 'Ex. wcln,bclearningnetwork. Do not include .com, .ca, etc... Only images with a URL of this domain will be organized. Leave empty to organize images of any domain.';

$string['backtoform'] = 'Back to Image Organizer form';
