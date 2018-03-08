<?php
/**
 * xAPI Connect
 *
 * Description:
 * This tool provides methods to submit learning statements to
 * an LRS that adhere to the xAPI spec.
 *
 * Version: 1.0.0
 * Author: Paul Ryan <paul.ryan@hawaii.edu>
 */

/**
 * Configuration global constants.
 */
$GLOBALS['XAPI_CONNECT_CATALOG_URL'] = 'https://example.edu/xapi';
$GLOBALS['XAPI_CONNECT_PLATFORM'] = 'Your Platform v1.0.0';
$GLOBALS['XAPI_CONNECT_VERSION'] = '1.0.1';
$GLOBALS['XAPI_CONNECT_AUTHORITY_NAME'] = 'Your Authority Name';
$GLOBALS['XAPI_CONNECT_AUTHORITY_EMAIL'] = 'you@example.edu';


/**
 * Submits an xAPI statement to the LRS connected to this WordPress install (ACF Course Options field).
 *
 * @param  int $user_id   User that is the actor of this statement.
 * @param  str $verb_name [failed|completed|answered|rated|commented|viewed|rescored]
 * @param  int $page_id   ID of page that is the object of this statement (assessment or page).
 * @param  array $data extra data for the specific verb:
 *            failed: array(
 *                'points_earned' => [0-1],
 *                'points_available' => [0-999],
 *                'feedback' => 'What the instructor said.',
 *                'duration' => [0-99] (seconds spent on quiz),
 *            )
 *            completed: array(
 *                'points_earned' => [0-1],
 *                'points_available' => [0-999],
 *                'feedback' => 'What the instructor said.',
 *                'duration' => [0-99] (seconds spent on quiz),
 *            )
 *            answered: array(
 *                'question_text' => 'What is x?',
 *                'question_id' => 'aecf423541',
 *                'question_type' => 'mc',
 *                'question_bloom' => 'remember-recognize',
 *                'answer_text' => 'x is something.',
 *                'is_correct' => [true|false],
 *            )
 *            rated: array(
 *                'rating' => [1-5],
 *            )
 *            commented: array(
 *                'comment_text' => 'text of the page feedback',
 *            )
 *            viewed: array()
 *            rescored: array(
 *            	'student_id' => id of student getting rescored,
 *            	'old_score' => [0-999],
 *            	'new_score' => [0-999],
 *            )
 * @return bool true if the statement was sent to the LRS.
 */
function xapi_connect_statement( $user_id, $verb_name, $page_id, $data = array(), &$containing_loop = null, $xapi_connect_lrs_username = '', $xapi_connect_lrs_password = '' ) {
	// Set constant xAPI attributes.
	$xapi_catalog_url = $GLOBALS['XAPI_CONNECT_CATALOG_URL'];

	// Fail if unrecognized verb.
	if ( ! in_array( $verb_name, array( 'failed', 'completed', 'answered', 'rated', 'commented', 'viewed', 'rescored' ) ) ) {
		return false;
	}

	// Fail if no page_id provided
	$page = get_post( intval( $page_id ) );
	if ( is_null( $page ) ) {
		return false;
	}

	// Get verb id, object type, and object description.
	$categories = get_the_category( $page_id );
	$category_id = count( $categories ) > 0 ? $categories[0]->term_id : 0;
	$parent_level_permalink = '';
	if ( $verb_name === 'failed' ) {
		$verb_id = 'activity/assessment/failed';
		$object_type = 'activity/assessment';

		// Get the parent level's objectives to include in this object description.
		$objectives = array();
		$parent_level = new WP_Query( array(
			'post_type' => 'post',
			'cat' => $category_id,
			'meta_key' => 'level',
			'meta_value' => get_field( 'level', $page_id ),
		) );
		while ( $parent_level->have_posts() ) : $parent_level->the_post();
			while ( have_rows( 'level_objectives' ) ) : the_row();
				$parent_level_permalink = get_permalink();
				$objectives[] = get_sub_field( 'level_objective' );
			endwhile;
		endwhile;
		// Reset postdata to containing loop (if provided).
		if ( $containing_loop instanceof WP_Query ) {
			$containing_loop->reset_postdata();
		} else {
			wp_reset_postdata();
		}
		// Build the object description from the level objectives
		$object_description = 'Learning objectives: ' . implode( '; ', $objectives );
	} else if ( $verb_name === 'completed' ) {
		$verb_id = 'activity/assessment/completed';
		$object_type = 'activity/assessment';

		// Get the parent level's objectives to include in this object description.
		$objectives = array();
		$parent_level = new WP_Query( array(
			'post_type' => 'post',
			'cat' => $category_id,
			'meta_key' => 'level',
			'meta_value' => get_field( 'level', $page_id ),
		) );
		while ( $parent_level->have_posts() ) : $parent_level->the_post();
			while ( have_rows( 'level_objectives' ) ) : the_row();
				$parent_level_permalink = get_permalink();
				$objectives[] = get_sub_field( 'level_objective' );
			endwhile;
		endwhile;
		// Reset postdata to containing loop (if provided).
		if ( $containing_loop instanceof WP_Query ) {
			$containing_loop->reset_postdata();
		} else {
			wp_reset_postdata();
		}
		// Build the object description from the level objectives
		$object_description = 'Learning objectives: ' . implode( '; ', $objectives );
	} else if ( $verb_name === 'answered' ) {
		$verb_id = 'activity/assessment/answered';
		$object_type = 'activity/assessment/' . $data['question_type'];
		$object_description = $data['question_text'];
	} else if ( $verb_name === 'rated' ) {
		$verb_id = 'activity/course-feedback/rated';
		$object_type = '';
		$object_description = '';
	} else if ( $verb_name === 'commented' ) {
		$verb_id = 'activity/course-feedback/commented';
		$object_type = '';
		$object_description = '';
	} else if ( $verb_name === 'viewed' ) {
		$verb_id = 'viewed';
		$object_type = '';
		$object_description = '';
	} else if ( $verb_name === 'rescored' ) {
		$verb_id = 'activity/assessment/rescored';
		$object_type = 'activity/assessment';
		$object_description = '';
	}

	// Get verb data.
	$verb = array(
		'display' => $verb_name,
		'id' => $verb_id,
	);

	// Get object data.
	$object = array(
		'object_type' => 'Activity',
		'id' => get_permalink( $page ),
		'name' => get_the_title( $page ),
		'type' => $object_type,
		'description' => $object_description,
	);

	// Add object extensions if this is a question being answered (save the Bloom's Taxonomy data)
	if ( $verb_name === 'answered' ) {
		$bloom = explode( '-', $data['question_bloom'] );
		if ( count( $bloom ) > 1 ) {
			$object['extensions'] = array(
				$xapi_catalog_url . 'framework/blooms/primary' => array(
					'name' => $bloom[0],
				),
				$xapi_catalog_url . 'framework/blooms/secondary' => array(
					'name' => $bloom[1],
				),
			);
		}
		$object['name'] .= ', Question ' . $data['question_id'];
	}

	// Get timestamp (now or when assessment was completed).
	$timestamp = date( 'c', time() ); // ISO 8601 formatted time
	if ( array_key_exists( 'timestamp', $data ) && intval( $data['timestamp'] ) > 0 ) {
		$timestamp = date( 'c', $data['timestamp'] );
	}

	// Get results data.
	$result = array();
	if ( $verb_name === 'failed' ) {
		$result = array(
			'success' => false,
			'score' => array(
				'scaled' => $data['points_available'] > 0 ? floatval( $data['points_earned'] / $data['points_available'] ) : 0,
				'raw' => floatval( $data['points_earned'] ),
				'min' => 0,
				'max' => floatval( $data['points_available'] ),
			),
			'response' => $data['feedback'],
			'duration' => seconds_to_iso8601_duration( intval( $data['duration'] ) ),
		);
	} else if ( $verb_name === 'completed' ) {
		$result = array(
			'success' => true,
			'score' => array(
				'scaled' => $data['points_available'] > 0 ? floatval( $data['points_earned'] / $data['points_available'] ) : 0,
				'raw' => floatval( $data['points_earned'] ),
				'min' => 0,
				'max' => floatval( $data['points_available'] ),
			),
			'response' => $data['feedback'],
			'duration' => seconds_to_iso8601_duration( intval( $data['duration'] ) ),
		);
	} else if ( $verb_name === 'answered' ) {
		$result = array(
			'success' => $data['is_correct'],
			'response' => $data['answer_text'],
		);
	} else if ( $verb_name === 'rated' ) {
		$result = array(
			'score' => array(
				'raw' => floatval( $data['rating'] ),
				'min' => 1,
				'max' => 5,
			),
		);
	} else if ( $verb_name === 'commented' ) {
		$result = array(
			'response' => $data['comment_text'],
		);
	} else if ( $verb_name === 'viewed' ) {
	} else if ( $verb_name === 'rescored' ) {
		$student = get_user_by( 'id', intval( $data['student_id'] ) );
		$email = $student ? $student->user_email : '(unknown)';
		$result = array(
			'success' => true,
			'score' => array(
				'scaled' => $data['points_available'] > 0 ? floatval( $data['new_points'] / $data['points_available'] ) : 0,
				'raw' => floatval( $data['new_points'] ),
				'min' => 0,
				'max' => floatval( $data['points_available'] ),
			),
			'response' => 'Old score for ' . $email . ': ' . $data['old_points'],
		);
	}

	// Get context data.
	$context = array();
	if ( $verb_name === 'failed' ) {
		// Get parent level (post), topic (category), and theme (category) of this Assessment.
		$context['parents'] = array();
		if ( $parent_level_permalink ) {
			$context['parents'][] = array(
				'id' => $parent_level_permalink,
				'objectType' => 'Activity',
			);
		}
		foreach ( $categories as $category ) {
			$context['parents'][] = array(
				'id' => get_category_link( $category->term_id ),
				'objectType' => 'Activity',
			);
			if ( intval( $category->parent ) > 0 ) {
				$context['parents'][] = array(
					'id' => get_category_link( $category->parent ),
					'objectType' => 'Activity',
				);
			}
		}
	} else if ( $verb_name === 'completed' ) {
		// Get parent level (post), topic (category), and theme (category) of this Assessment.
		$context['parents'] = array();
		if ( $parent_level_permalink ) {
			$context['parents'][] = array(
				'id' => $parent_level_permalink,
				'objectType' => 'Activity',
			);
		}
		foreach ( $categories as $category ) {
			$context['parents'][] = array(
				'id' => get_category_link( $category->term_id ),
				'objectType' => 'Activity',
			);
			if ( intval( $category->parent ) > 0 ) {
				$context['parents'][] = array(
					'id' => get_category_link( $category->parent ),
					'objectType' => 'Activity',
				);
			}
		}
	} else if ( $verb_name === 'answered' ) {
		// Get parent assessment, topic (category), and theme (category) of this question being answered.
		$context['parents'] = array();
		$context['parents'][] = array(
			'id' => get_permalink( $page ),
			'objectType' => 'Activity',
		);
		foreach ( $categories as $category ) {
			$context['parents'][] = array(
				'id' => get_category_link( $category->term_id ),
				'objectType' => 'Activity',
			);
			if ( intval( $category->parent ) > 0 ) {
				$context['parents'][] = array(
					'id' => get_category_link( $category->parent ),
					'objectType' => 'Activity',
				);
			}
		}
	} else if ( $verb_name === 'rated' ) {
		// Get parent level (post), topic (category), and theme (category) of this page being rated.
		$context['parents'] = array();
		foreach ( $categories as $category ) {
			$context['parents'][] = array(
				'id' => get_category_link( $category->term_id ),
				'objectType' => 'Activity',
			);
			if ( intval( $category->parent ) > 0 ) {
				$context['parents'][] = array(
					'id' => get_category_link( $category->parent ),
					'objectType' => 'Activity',
				);
			}
		}
	} else if ( $verb_name === 'commented' ) {
	} else if ( $verb_name === 'viewed' ) {
	}

	return xapi_connect_build_statement( $user_id, $verb, $object, $result, $context, $timestamp, $xapi_connect_lrs_username, $xapi_connect_lrs_password );
}


// Helper: Builds the xAPI statement from the data submitted here from xapi_connect_statement().
// @ref https://github.com/adlnet/xAPI-Spec/blob/master/xAPI.md
function xapi_connect_build_statement( $user_id, $verb, $object, $result, $context, $timestamp, $xapi_connect_lrs_username = '', $xapi_connect_lrs_password = '' ) {
	// Set constant xAPI attributes.
	$platform = $GLOBALS['XAPI_CONNECT_PLATFORM'];
	$xapi_version = $GLOBALS['XAPI_CONNECT_VERSION'];
	$authority_name = $GLOBALS['XAPI_CONNECT_AUTHORITY_NAME'];
	$authority_email = $GLOBALS['XAPI_CONNECT_AUTHORITY_EMAIL'];
	$xapi_catalog_url = $GLOBALS['XAPI_CONNECT_CATALOG_URL'];

	// Get current user who is submitting this xAPI statement.
	$user = get_user_by( 'id', $user_id );

	// Get the UUID of this user's course section.
	$user_section = get_field( 'user_section', 'user_' . $user->ID );
	$user_section_uuid = '00000000-0000-4000-B000-000000000000'; // UUID  version 4 (random)
	while ( have_rows( 'course_sections', 'options' ) ) {
		the_row();
		$name = get_sub_field( 'course_section_name' );
		$semester = get_sub_field( 'course_section_semester' );
		$year = get_sub_field( 'course_section_year' );
		if ( $user_section === "$year-$semester-" . sanitize_title( $name ) ) {
			$user_section_uuid = get_sub_field( 'course_section_uuid' );
		}
	}

	// Get this user's instructor (if no instructor assigned to the user's section, just choose the first instructor.
	$user_instructor = null;
	foreach ( get_users( array( 'role' => 'instructor' ) ) as $instructor ) {
		if ( is_null( $user_instructor ) || get_field( 'user_section', 'user_' . $instructor->ID ) === $user_section ) {
			$user_instructor = $instructor;
		}
	}

	// Build xAPI statement.
	$statement = array(
		'actor' => array(
			'mbox' => 'mailto:' . $user->user_email,
			'name' => $user->display_name,
			'objectType' => 'Agent',
		),
		'verb' => array(
			'id' => $xapi_catalog_url . 'verbs/' . $verb['id'],
			'display' => array(
				'en-US' => $verb['display'],
			)
		),
		'object' => array(
			'id' => $object['id'],
			'definition' => array(
				'name' => array(
					'en-US' => $object['name'],
				),
				'description' => array(
					'en-US' => $object['description'],
				),
				'type' => $xapi_catalog_url . $object['type'],
				'extensions' => array_key_exists( 'extensions', $object ) ? $object['extensions'] : new stdClass(),
				//'moreInfo' => 'https://example.com',
			),
			'objectType' => $object['object_type'],
		),
		'context' => array(
			'registration' => $user_section_uuid,
			'contextActivities' => array_key_exists( 'parents', $context ) ? array(
				'parent' => $context['parents'],
				//'category' => array(),
				//'grouping' => array(),
				//'other' => array(),
			) : new stdClass(),
			'revision' => 'original',
			'instructor' => array(
				'mbox' => 'mailto:' . $user_instructor->user_email,
				'name' => $user_instructor->displayname,
				'objectType' => 'Agent',
			),
			'platform' => $platform,
			//'team' => array(),
			//'statement' => array(),
			//'extensions' => array(),
		),
		'result' => count( $result ) > 0 ? $result : new stdClass(),
		'version' => $xapi_version,
		'authority' => array(
			'mbox' => 'mailto:' . $authority_email,
			'name' => $authority_name,
			'objectType' => 'Agent',
		),
		'timestamp' => $timestamp,
		// 'stored' => LRS will record the submission time
		// 'id' => LRS will create a UUID upon submission
	);

	// Submit xAPI statement to LRS.
	return xapi_connect_send_statement( $statement, 'statements', $xapi_connect_lrs_username, $xapi_connect_lrs_password );
}


// Helper: Submits xAPI statement to the connected LRS.
function xapi_connect_send_statement( $statement, $endpoint = 'statements', $xapi_connect_lrs_username = '', $xapi_connect_lrs_password = '' ) {
	$response = '';

	if ( is_array( $statement ) || is_object( $statement ) ) {
		$statement = json_encode( $statement );
	}

	// Get LRS connection credentials.
	$xapi_connect_lrs_url = get_field( 'xapi_lrs_url', 'options' );
	$xapi_connect_url = $xapi_connect_lrs_url . $endpoint;
	if ( strlen( $xapi_connect_lrs_username ) < 1 ) {
		$xapi_connect_lrs_username = get_field( 'xapi_lrs_username', 'options' );
	}
	if ( strlen( $xapi_connect_lrs_password ) < 1 ) {
		$xapi_connect_lrs_password = get_field( 'xapi_lrs_password', 'options' );
	}

	// Quit if we don't have a valid LRS URL.
	if ( filter_var( $xapi_connect_url, FILTER_VALIDATE_URL ) === FALSE ) {
		return false;
	}

	$parts = parse_url( $xapi_connect_url );
	if ( $fp = fsockopen( 'ssl://' . $parts['host'], isset( $parts['port'] ) ? $parts['port'] : 443, $errno, $errstr, 30 ) ) {
		$out = "POST " . $parts['path'] . " HTTP/1.1\r\n";
		$out .= "Host: " . $parts['host'] . "\r\n";
		$out .= "Content-Type: application/json\r\n";
		$out .= "Authorization: Basic " . base64_encode( $xapi_connect_lrs_username . ':' . $xapi_connect_lrs_password ) . "\r\n";
		$out .= "X-Experience-API-Version: 1.0.1\r\n";
		$out .= "Content-Length: " . strlen( $statement ) . "\r\n";
		$out .= "Connection: Close\r\n\r\n";
		if ( isset( $statement ) ) {
			$out .= $statement;
		}

		fwrite( $fp, $out );

		// Get response from server (should be HTTP 200 OK) if debug mode is on.
		if ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) {
			while ( $fp && ! feof( $fp ) ) {
				$response .= @fgets( $fp, 128 );
			}
			// Log response from LRS if it rejected the submitted statement.
			if ( strpos( $response, '200 OK' ) === FALSE && strpos( $response, '404 Not Found' ) === FALSE ) {
				error_log( 'LRS rejected the submitted statement: ' . $statement );
				error_log( 'Response: ' . $response );
			}
		}

		fclose( $fp );
	}

	return $response;
}


// Helper: convert seconds to ISO 8601 duration.
function seconds_to_iso8601_duration( $seconds ) {
	$units = array(
		'Y' => 365 * 24 * 3600,
		'D' => 24 * 3600,
		'H' => 3600,
		'M' => 60,
		'S' => 1,
	);
	$str = 'P';
	$is_time = false;
	foreach ( $units as $unitName => $unit ) {
		$quotient  = intval( $seconds / $unit );
		$seconds -= $quotient * $unit;
		$unit  = $quotient;
		if ( $unit > 0 ) {
			if ( ! $is_time && in_array( $unitName, array( 'H', 'M', 'S' ) ) ) { // There may be a better way to do this
				$str .= 'T';
				$is_time = true;
			}
			$str .= strval( $unit ) . $unitName;
		}
	}
	return $str;
}




// AJAX: Wrapper for xapi_connect_get_aggregate_data().
// POST @param pipeline: see http://docs.learninglocker.net/statements_api/
// POST @param section: section label
function ajax_xapi_connect_get_aggregate_data() {
	// Nonce check.
	$nonce = $_REQUEST['nonce'];
	if ( ! wp_verify_nonce( $nonce, 'ltec112_nonce' ) ) {
		die( __( 'Busted.' ) );
	}

	// Default response.
	$success = true;
	$message = 'Aggregate LRS data retrieved.';
	$data = '';
	$extra = '';

	// Fail if required parameters don't exist.
	if ( ! array_key_exists( 'section', $_REQUEST ) || strlen( $_REQUEST['section'] ) < 1 ) {
		$success = false;
		$message = 'Missing parameter (section).';
	}

	// Fail if current user doesn't have the required permissions.
	if ( $success && ! current_user_can( 'edit_users' ) ) {
		$success = false;
		$message = 'No permissions.';
	}

	// Get the aggregate LRS data.
	if ( $success ) {

		// Get students in current section.
		$args = array( 'role' => 'student' );
		if ( strlen( $_REQUEST['section'] ) > 0 ) {
			$args['meta_key'] = 'user_section';
			$args['meta_value'] = $_REQUEST['section'];
		}
		$grading_students = get_users( $args );

		// Get student IDs and emails.
		$student_ids = array();
		$student_emails = array();
		foreach ( $grading_students as $grading_student ) {
			$student_ids[$grading_student->data->user_email] = $grading_student->data->ID;
			array_push( $student_emails, $grading_student->get( 'user_email' ) );
		}

		// Get LRS data of student progress.
		$data = json_decode( xapi_connect_get_aggregate_data( $student_emails ) );

		// Add WordPress ID of each student (for generated a link to that student's
		// grading dashboard page from the d3 visualization).
		foreach ( (array)$data->result as $key => $student) {
			$student_email = str_replace( 'mailto:', '', $student->email );
			$data->result[$key]->id = $student_ids[$student_email];
		}

		// Get current section and points needed for an A in that section.
		$section_uuid = '00000000-0000-4000-B000-000000000000';
		while ( have_rows( 'course_sections', 'options' ) ) : the_row();
			$name = get_sub_field( 'course_section_name' );
			$semester = get_sub_field( 'course_section_semester' );
			$year = get_sub_field( 'course_section_year' );
			if ( $_REQUEST['section'] === "$year-$semester-" . sanitize_title( $name ) ) {
				$section_uuid = get_sub_field( 'course_section_uuid' );
				// Get course-specific grade settings (points needed for an A).
				$extra = array(
					'course_grade_a_points' => get_sub_field( 'course_grade_a_points' ),
					'course_grade_a_project_points' => get_sub_field( 'course_grade_a_project_points' ),
				);
				break;
			}
		endwhile;
	}

	$response = json_encode( array(
		'success' => $success,
		'message' => $message,
		'data' => $data,
		'extra' => $extra,
	));

	header( 'content-type: application/json' );
	echo $response;
	exit;
}
add_action( 'wp_ajax_xapi_connect_get_aggregate_data', 'ajax_xapi_connect_get_aggregate_data' );


// Helper: Builds the xAPI statement to get aggregate data back from LRS.
// @ref: http://docs.learninglocker.net/statements_api/
function xapi_connect_get_aggregate_data( $student_emails = array(), $verb = 'completed', $project_id = 0 ) {
	// Fail if no students provided to gather data from.
	if ( ! is_array( $student_emails ) || count( $student_emails ) < 1 ) {
		return json_encode( array( 'result' => array() ) );
	}

	// Build the pipeline match mongodb selector.
	$match = array(
		// Match statements from any of the students in this section.
		'$or' => array_map(
			function ( $email ) { return array( 'statement.actor.mbox' => "mailto:$email" ); },
			$student_emails
		),
		// Match only the verb we're inspecting.
		'statement.verb.display.en-US' => $verb,
		// Exclude voided statements.
		'voided' => false,
	);

	// Exclude the following activity URLs if flag is set.
	$should_exclude = false;
	if ( $should_exclude ) {
		$match['statement.object.id'] = array(
			'$nin' => array(
				// Add any statement IDs here that you want to exclude
			),
		);
	}

	// Build xAPI statement.
	$statement = array(
		'pipeline' => json_encode( array(
			array(
				'$match' => $match,
			),
			array(
				'$sort' => array(
					'statement.timestamp' => 1,
				),
			),
			array(
				'$project' => array(
					'_id' => $project_id,
					'name' => '$statement.actor.name',
					'date' => '$statement.timestamp',
					'email' => '$statement.actor.mbox',
					'competency' => '$statement.object.definition.name.en-US',
					'score' => '$statement.result.score.raw',
				),
			),
		) ),
	);

	// Submit xAPI statement to the LRS requesting aggregate data.
	$xapi_connect_lrs_url = get_field( 'xapi_lrs_url', 'options' );
	$xapi_connect_lrs_username = get_field( 'xapi_lrs_username', 'options' );
	$xapi_connect_lrs_password = get_field( 'xapi_lrs_password', 'options' );
	$custom_headers = array(
		'Authorization' => 'Basic ' . base64_encode( $xapi_connect_lrs_username . ':' . $xapi_connect_lrs_password ),
		'Cache-Control' => 'no-cache',
		'Connection' => 'close',
		'Content-Type' => 'application/json',
		'X-Experience-API-Version' => '1.0.1',
	);

	$parts = parse_url( $xapi_connect_lrs_url );

	$response = http_request( 'GET', $parts['host'], 443, '/api/v1/statements/aggregate', $statement, array(), array(), $custom_headers );

	return $response;
}


// Helper: HTTP Request function (supports GET and POST).
// @see http://php.net/manual/en/function.fsockopen.php#101872
function http_request(
	$verb = 'GET',		// HTTP Request Method (GET and POST supported)
	$host,				// Target IP/Hostname
	$port = 80,			// Target TCP port
	$uri = '/',			// Target URI
	$getdata = array(),	// HTTP GET Data ie. array('var1' => 'val1', 'var2' => 'val2')
	$postdata = array(),	// HTTP POST Data ie. array('var1' => 'val1', 'var2' => 'val2')
	$cookie = array(),	// HTTP Cookie Data ie. array('var1' => 'val1', 'var2' => 'val2')
	$custom_headers = array(), // Custom HTTP headers ie. array('Referer: http://localhost/
	$timeout = 5,		// Socket timeout in seconds
	$req_hdr = false,		// Include HTTP request headers
	$res_hdr = false		// Include HTTP response headers
) {
	$ret = '';
	$verb = strtoupper( $verb );

	$getdata_str = count( $getdata ) ? '?' : '';
	foreach ( $getdata as $k => $v ) {
		$getdata_str .= urlencode( $k ) .'='. urlencode( $v ) . '&';
	}

	$postdata_str = '';
	foreach ( $postdata as $k => $v ) {
		$postdata_str .= urlencode( $k ) .'='. urlencode( $v ) .'&';
	}

	$cookie_str = '';
	foreach ( $cookie as $k => $v ) {
		$cookie_str .= urlencode( $k ) .'='. urlencode( $v ) .'; ';
	}

	$crlf = "\r\n";
	$req = $verb .' '. $uri . $getdata_str .' HTTP/1.1' . $crlf;
	$req .= 'Host: '. $host . $crlf;

	foreach ( $custom_headers as $k => $v ) {
		$req .= $k .': '. $v . $crlf;
	}

	if ( ! empty( $cookie_str ) ) {
		$req .= 'Cookie: '. substr( $cookie_str, 0, -2 ) . $crlf;
	}

	if ( $verb == 'POST' && ! empty( $postdata_str ) ) {
		$postdata_str = substr( $postdata_str, 0, -1 );
		$req .= 'Content-Type: application/x-www-form-urlencoded' . $crlf;
		$req .= 'Content-Length: '. strlen( $postdata_str ) . $crlf . $crlf;
		$req .= $postdata_str;
	} else {
		$req .= $crlf;
	}

	if ( $req_hdr ) {
		$ret .= $req;
	}

	if ( $port === 443 ) {
		$host = 'ssl://' . $host;
	}

	if ( ( $fp = @fsockopen( $host, $port, $errno, $errstr ) ) == false ) {
		return "Error $errno: $errstr\n";
	}

	stream_set_timeout( $fp, 0, $timeout * 1000 );

	fputs( $fp, $req );
	while ( ! feof( $fp ) ) {
		$ret .= @fgets( $fp, 128 );
	}
	fclose( $fp );

	if ( ! $res_hdr ) {
		$ret = substr( $ret, strpos( $ret, $crlf . $crlf ) + strlen( $crlf . $crlf ) );
	}

	return decode_chunked( $ret );
}

// Parse "Transfer-Encoding: chunked" data received from an HTTP request.
function decode_chunked( $str ) {
	// Nothing to decode if no line breaks exist.
	if ( strpos( $str, "\r\n" ) === FALSE ) {
		return $str;
	}
	// Remove line breaks from data.
	for ($res = ''; !empty($str); $str = trim($str)) {
		$pos = strpos($str, "\r\n");
		$len = hexdec(substr($str, 0, $pos));
		$res.= substr($str, $pos + 2, $len);
		$str = substr($str, $pos + 2 + $len);
	}
	return $res;
}


// Get UUID from raw http response from LRS after submitting a statement.
function get_uuid_from_lrs_response( $response ) {
	$uuid = '';

	$parsed_response = parse_http_response( $response );
	$json_content = json_decode( $parsed_response['content'] );

	foreach ( (array)$json_content as $value ) {
		$uuid = $value;
	}

	return $uuid;
}


// Parse raw HTTP response into headers and content.
// @return array(
//     'headers' => array( 'header1' => '', ... ),
//     'content' => ''
// )
function parse_http_response( $string ) {
	$headers = array();
	$content = '';
	$str = strtok( $string, "\n" );
	$h = null;
	while ( $str !== false ) {
		if ( $h && trim( $str ) === '' ) {
			$h = false;
			continue;
		}
		if ( $h !== false && false !== strpos( $str, ':' ) ) {
			$h = true;
			list( $headername, $headervalue ) = explode( ':', trim( $str ), 2 );
			$headername = strtolower( $headername );
			$headervalue = ltrim( $headervalue );
			if ( isset( $headers[$headername] ) ) {
				$headers[$headername] .= ',' . $headervalue;
			} else {
				$headers[$headername] = $headervalue;
			}
		}
		if ( $h === false ) {
			$content .= $str . "\n";
		}
		$str = strtok( "\n" );
	}
	return array(
		'headers' => $headers,
		'content' => trim( $content ),
	);
}
