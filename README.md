# xAPI Connect

## Description:

This WordPress theme library provides methods to submit learning statements to an LRS that adhere to the xAPI spec. It implements the following verbs related to consuming educational content:

* failed
* completed
* answered
* rated
* commented
* viewed
* rescored

## Installation:

This library is meant to be included in your custom WordPress theme. Place it in a subdirectory of your theme, e.g., /lib/xapi-connector/xapi-connector.php and include it wherever you bootstrap theme code (typically functions.php):

```
 function your_theme_setup() {
   // xAPI Connector submits learning statements to an LRS.
  require_once( get_stylesheet_directory() . '/lib/xapi-connector/xapi-connector.php' );
}
add_action( 'after_setup_theme', 'your_theme_setup' );
```

## Dependencies:

LRS connection details are pulled from the following ACF options values:

```
$xapi_lrs_url = get_field( 'xapi_lrs_url', 'options' );
$xapi_lrs_username = get_field( 'xapi_lrs_username', 'options' );
$xapi_lrs_password = get_field( 'xapi_lrs_password', 'options' );
```

Student sections are defined in ACF options, and saved in usermeta for individual students:

```
$user_section = get_field( 'user_section', 'user_' . $user->ID );
```

```
while ( have_rows( 'course_sections', 'options' ) ) {
  the_row();
  $name = get_sub_field( 'course_section_name' );
  $semester = get_sub_field( 'course_section_semester' );
  $year = get_sub_field( 'course_section_year' );
  if ( $user_section === "$year-$semester-" . sanitize_title( $name ) ) {
    $user_section_uuid = get_sub_field( 'course_section_uuid' );
  }
}
```

xAPI statement metadata is pulled from various ACF fields. These can be replaced in the codebase if desired:

**Verb(s)**| ACF Field Name| ACF Field Type| Comments
:-----:|:-----:|:-----:|:-----:
failed completed| `level`| Radio Button| Type/difficulty of content
failed completed| `level_objectives`| Repeater| List of learning objectives
failed completed| -`level_objective`| Text| Learning objective

## Example usage:

You can fire xAPI statements anywhere in your theme; typical places include in the header template (for firing "viewed" statements) and in AJAX handlers for specific actions (such as clicking an input field on a rendered assessment).

Example "viewed" statement in theme's header.php:

```
// Send xAPI statement: "student viewed page."
if ( is_user_logged_in() ) {
  xapi_connect_statement( get_current_user_id(), 'viewed', get_the_ID() );
}
```

Example AJAX handler for a student clicking the "Submit" button on an assessment:

```
/**
 * AJAX: Update assessment state and submit final versions of all answers to questions.
 *
 * POST @param str assessments_nonce Security nonce.
 * POST @param int assessment_id Assessment ID to mark as finished.
 * POST @param json_str answers Array(question_id=>'', question=>'', answer_id=>'', answer=>'') for each question
 */
function assessments_finish() {
  ...
  foreach ( $assessment_data['answers'] as $question_id => $answer ) {
    xapi_connect_statement( get_current_user_id(), 'answered', $assessment_id, array(
      'question_text' => $answer['question'],
      'question_id' => str_replace( $question_id, 'question-', '' ),
      'question_type' => $answer['question_type'],
      'question_bloom' => $answer['bloom'],
      'answer_text' => $answer['answer'],
      'is_correct' => $answer['is_correct'],
    ));
  }
  ...
}
add_action( 'wp_ajax_assessments_finish', 'assessments_finish' );
```

## Version:

1.0.0

## Author:

Paul Ryan <paul.ryan@hawaii.edu>

## License:

MIT License

Copyright 2016 University of Hawaii

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
