<?php
/**
 * Event surveys: one on the application, and follow-ups issued later.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Survey storage, application rendering, and follow-up issuance.
 */
class Remember_Surveys {

	const PLACEMENT_APPLICATION = 'application';
	const PLACEMENT_FOLLOWUP    = 'followup';

	/**
	 * Field types a question may use.
	 *
	 * @return string[]
	 */
	public static function field_types() {
		return array( 'text', 'textarea', 'select', 'multiselect', 'boolean' );
	}

	/**
	 * Application survey for an event, or null.
	 *
	 * @param int $event_id Event.
	 * @return object|null
	 */
	public static function application_survey( $event_id ) {
		global $wpdb;
		$event_id = absint( $event_id );
		if ( $event_id < 1 ) {
			return null;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::surveys_table() . ' WHERE event_id = %d AND placement = %s ORDER BY survey_id ASC LIMIT 1',
				$event_id,
				self::PLACEMENT_APPLICATION
			)
		);
		return $row ? $row : null;
	}

	/**
	 * Survey row.
	 *
	 * @param int $survey_id Survey.
	 * @return object|null
	 */
	public static function get( $survey_id ) {
		global $wpdb;
		$survey_id = absint( $survey_id );
		if ( $survey_id < 1 ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::surveys_table() . ' WHERE survey_id = %d', $survey_id ) );
		return $row ? $row : null;
	}

	/**
	 * Follow-up surveys for an event.
	 *
	 * @param int $event_id Event.
	 * @return object[]
	 */
	public static function followups_for_event( $event_id ) {
		global $wpdb;
		$event_id = absint( $event_id );
		if ( $event_id < 1 ) {
			return array();
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::surveys_table() . ' WHERE event_id = %d AND placement = %s ORDER BY survey_id ASC',
				$event_id,
				self::PLACEMENT_FOLLOWUP
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Every survey, with its event name and question count, for the admin list.
	 *
	 * @param int $event_id Limit to one event, or 0 for all.
	 * @return object[]
	 */
	public static function list_for_admin( $event_id = 0 ) {
		global $wpdb;
		$surveys   = self::surveys_table();
		$questions = self::questions_table();
		$events    = $wpdb->prefix . 'remember_events';
		$event_id  = absint( $event_id );
		$where     = $event_id > 0 ? $wpdb->prepare( 'WHERE s.event_id = %d', $event_id ) : '';
		$rows      = $wpdb->get_results(
			"SELECT s.survey_id, s.event_id, s.title, s.placement, s.timing, s.status, e.event_name,
				(SELECT COUNT(*) FROM {$questions} q WHERE q.survey_id = s.survey_id) AS question_count
			FROM {$surveys} s
			LEFT JOIN {$events} e ON e.event_id = s.event_id
			{$where}
			ORDER BY e.start_date DESC, e.event_name ASC, s.title ASC, s.survey_id ASC"
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Questions in sort order.
	 *
	 * @param int $survey_id Survey.
	 * @return object[]
	 */
	public static function questions( $survey_id ) {
		global $wpdb;
		$survey_id = absint( $survey_id );
		if ( $survey_id < 1 ) {
			return array();
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::questions_table() . ' WHERE survey_id = %d ORDER BY sort_order ASC, question_id ASC',
				$survey_id
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Markup for the apply form, or an empty string when the event has no application survey.
	 *
	 * @param int $event_id Event.
	 * @return string
	 */
	public static function render_apply_html( $event_id ) {
		$survey = self::application_survey( $event_id );
		if ( ! $survey ) {
			return '';
		}
		$questions = self::questions( $survey->survey_id );
		if ( empty( $questions ) ) {
			return '';
		}
		return self::render_fields( $survey, $questions, false );
	}

	/**
	 * Error when the posted application survey is incomplete. Empty when there is nothing to answer.
	 *
	 * @param int $event_id Event.
	 * @return string
	 */
	public static function validate_apply( $event_id ) {
		$survey = self::application_survey( $event_id );
		if ( ! $survey ) {
			return '';
		}
		$questions = self::questions( $survey->survey_id );
		if ( empty( $questions ) ) {
			return '';
		}
		return self::validate_posted( $questions );
	}

	/**
	 * Store the application survey once the application row exists.
	 *
	 * @param int $application_id Application.
	 * @param int $event_id       Event.
	 * @param int $member_id      Member.
	 * @return void
	 */
	public static function save_apply_response( $application_id, $event_id, $member_id ) {
		$survey = self::application_survey( $event_id );
		if ( ! $survey ) {
			return;
		}
		self::save_response( $survey, $member_id, $application_id );
	}

	/**
	 * Whether this member was accepted to the event.
	 *
	 * @param int $event_id  Event.
	 * @param int $member_id Member.
	 * @return bool
	 */
	public static function member_is_accepted( $event_id, $member_id ) {
		global $wpdb;
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT application_id FROM {$wpdb->prefix}remember_event_applications WHERE event_id = %d AND member_id = %d AND status = 'accepted' LIMIT 1",
				absint( $event_id ),
				absint( $member_id )
			)
		);
		return (bool) $found;
	}

	/**
	 * Follow-up response page for a logged-in accepted member.
	 *
	 * @param int $survey_id Survey.
	 * @param int $member_id Member.
	 * @return string
	 */
	public static function render_followup_page( $survey_id, $member_id ) {
		$survey = self::get( $survey_id );
		if ( ! $survey || self::PLACEMENT_FOLLOWUP !== $survey->placement || 'issued' !== $survey->status ) {
			return '<p class="remember-notice remember-error">' . esc_html__( 'That survey is not open.', 'remember' ) . '</p>';
		}
		if ( ! self::member_is_accepted( $survey->event_id, $member_id ) ) {
			return '<p class="remember-notice remember-error">' . esc_html__( 'This survey is for accepted participants of the event.', 'remember' ) . '</p>';
		}
		$questions = self::questions( $survey->survey_id );
		if ( empty( $questions ) ) {
			return '<p class="remember-notice remember-error">' . esc_html__( 'This survey has no questions.', 'remember' ) . '</p>';
		}

		$notice = '';
		if ( isset( $_POST['remember_survey_response'] ) && check_admin_referer( 'remember_survey_response_' . $survey->survey_id, 'remember_survey_response_nonce' ) ) {
			$error = self::validate_posted( $questions );
			if ( $error ) {
				$notice = '<p class="remember-notice remember-error">' . esc_html( $error ) . '</p>';
			} else {
				self::save_response( $survey, $member_id, 0 );
				$notice = '<p class="remember-notice remember-success">' . esc_html__( 'Your response was saved.', 'remember' ) . '</p>';
			}
		}

		$existing = self::response_for_member( $survey->survey_id, $member_id );
		$html     = $notice;
		$html    .= '<form class="remember-form" method="post">';
		$html    .= wp_nonce_field( 'remember_survey_response_' . $survey->survey_id, 'remember_survey_response_nonce', true, false );
		$html    .= '<input type="hidden" name="remember_survey_response" value="1">';
		$html    .= self::render_fields( $survey, $questions, true, $existing );
		$html    .= '<p><button type="submit" class="remember-button remember-button-primary">' . esc_html__( 'Submit survey', 'remember' ) . '</button></p>';
		$html    .= '</form>';
		return $html;
	}

	/**
	 * Save the survey posted from the admin editor.
	 *
	 * @param int $event_id Event.
	 * @return int|WP_Error Survey id.
	 */
	public static function save_from_admin( $event_id ) {
		$event_id  = absint( $event_id );
		$placement = isset( $_POST['survey_placement'] ) ? sanitize_key( wp_unslash( $_POST['survey_placement'] ) ) : self::PLACEMENT_FOLLOWUP;
		if ( ! in_array( $placement, array( self::PLACEMENT_APPLICATION, self::PLACEMENT_FOLLOWUP ), true ) ) {
			$placement = self::PLACEMENT_FOLLOWUP;
		}
		$survey_id = isset( $_POST['survey_id'] ) ? absint( $_POST['survey_id'] ) : 0;
		$title     = isset( $_POST['survey_title'] ) ? sanitize_text_field( wp_unslash( $_POST['survey_title'] ) ) : '';
		$timing    = isset( $_POST['survey_timing'] ) ? sanitize_key( wp_unslash( $_POST['survey_timing'] ) ) : '';
		if ( ! in_array( $timing, array( 'before', 'after' ), true ) ) {
			$timing = '';
		}
		if ( self::PLACEMENT_APPLICATION === $placement ) {
			$timing = '';
			$existing = self::application_survey( $event_id );
			if ( $existing ) {
				$survey_id = (int) $existing->survey_id;
			}
			if ( '' === $title ) {
				$title = __( 'Application survey', 'remember' );
			}
		}
		$rows = self::posted_question_rows();
		if ( empty( $rows ) ) {
			if ( self::PLACEMENT_APPLICATION === $placement ) {
				$existing = self::application_survey( $event_id );
				if ( ! $existing ) {
					return 0;
				}
				$removed = self::delete_survey( (int) $existing->survey_id );
				if ( is_wp_error( $removed ) ) {
					return $removed;
				}
				require_once plugin_dir_path( __FILE__ ) . 'class-remember-logger.php';
				Remember_Logger::info( 'Application survey removed', array( 'event_id' => $event_id, 'survey_id' => (int) $existing->survey_id ) );
				return 0;
			}
			return new WP_Error( 'remember_survey_empty', __( 'Add at least one question.', 'remember' ) );
		}
		if ( '' === $title ) {
			return new WP_Error( 'remember_survey_title', __( 'A survey needs a title.', 'remember' ) );
		}

		global $wpdb;
		$now  = current_time( 'mysql' );
		$data = array(
			'event_id'   => $event_id,
			'title'      => $title,
			'placement'  => $placement,
			'timing'     => self::PLACEMENT_FOLLOWUP === $placement ? ( $timing ? $timing : 'after' ) : '',
			'updated_at' => $now,
		);
		if ( $survey_id > 0 ) {
			$current = self::get( $survey_id );
			if ( ! $current || (int) $current->event_id !== $event_id ) {
				return new WP_Error( 'remember_survey_missing', __( 'Survey not found for this event.', 'remember' ) );
			}
			if ( 'issued' === $current->status && self::response_count( $survey_id ) > 0 ) {
				return new WP_Error( 'remember_survey_locked', __( 'This survey already has responses, so its questions stay as they were issued.', 'remember' ) );
			}
			$wpdb->update( self::surveys_table(), $data, array( 'survey_id' => $survey_id ) );
		} else {
			$data['status']     = self::PLACEMENT_APPLICATION === $placement ? 'open' : 'draft';
			$data['created_by'] = get_current_user_id();
			$data['created_at'] = $now;
			$wpdb->insert( self::surveys_table(), $data );
			$survey_id = (int) $wpdb->insert_id;
		}
		if ( $survey_id < 1 ) {
			return new WP_Error( 'remember_survey_save', __( 'The survey could not be saved.', 'remember' ) );
		}
		self::sync_questions( $survey_id, $rows );
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-logger.php';
		Remember_Logger::info(
			'Survey saved',
			array(
				'survey_id'  => $survey_id,
				'event_id'   => $event_id,
				'placement'  => $placement,
			)
		);
		Remember_Logger::debug(
			'Survey saved',
			array(
				'survey_id'      => $survey_id,
				'event_id'       => $event_id,
				'question_count' => count( $rows ),
			)
		);
		return $survey_id;
	}

	/**
	 * Email accepted participants and open a follow-up survey.
	 *
	 * @param int $survey_id Survey.
	 * @return true|WP_Error
	 */
	public static function issue( $survey_id ) {
		$survey = self::get( $survey_id );
		if ( ! $survey || self::PLACEMENT_FOLLOWUP !== $survey->placement ) {
			return new WP_Error( 'remember_survey_issue', __( 'Only a follow-on survey can be issued.', 'remember' ) );
		}
		if ( empty( self::questions( $survey_id ) ) ) {
			return new WP_Error( 'remember_survey_empty', __( 'Add at least one question before issuing the survey.', 'remember' ) );
		}
		global $wpdb;
		$member_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT member_id FROM {$wpdb->prefix}remember_event_applications WHERE event_id = %d AND status = 'accepted'",
				(int) $survey->event_id
			)
		);
		if ( ! is_array( $member_ids ) ) {
			$member_ids = array();
		}
		$wpdb->update(
			self::surveys_table(),
			array(
				'status'     => 'issued',
				'issued_at'  => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'survey_id' => (int) $survey->survey_id )
		);
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-logger.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-page-creator.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-notifications.php';
		Remember_Page_Creator::create_pages( array( 'survey' ) );
		if ( empty( $member_ids ) ) {
			Remember_Logger::warning( 'Survey issued with no accepted participants', array( 'survey_id' => (int) $survey->survey_id, 'event_id' => (int) $survey->event_id ) );
			return true;
		}
		$event_name = $wpdb->get_var( $wpdb->prepare( "SELECT event_name FROM {$wpdb->prefix}remember_events WHERE event_id = %d", (int) $survey->event_id ) );
		$url        = self::response_url( $survey->survey_id );
		$sent       = 0;
		foreach ( $member_ids as $member_id ) {
			$user = get_user_by( 'ID', absint( $member_id ) );
			if ( ! $user || ! is_email( $user->user_email ) ) {
				continue;
			}
			$result = Remember_Notifications::send(
				'survey_issued',
				array(
					'member_name'  => $user->display_name,
					'event_name'   => (string) $event_name,
					'survey_title' => (string) $survey->title,
					'survey_url'   => $url,
				),
				$user->user_email
			);
			if ( true === $result ) {
				++$sent;
			} elseif ( is_wp_error( $result ) ) {
				Remember_Logger::warning(
					'Survey email failed',
					array(
						'survey_id' => (int) $survey->survey_id,
						'user_id'   => (int) $user->ID,
						'error'     => $result->get_error_message(),
					)
				);
			}
		}
		Remember_Logger::info(
			'Survey issued',
			array(
				'survey_id'  => (int) $survey->survey_id,
				'event_id'   => (int) $survey->event_id,
				'recipients' => $sent,
			)
		);
		return true;
	}

	/**
	 * Public URL for a follow-up survey.
	 *
	 * @param int $survey_id Survey.
	 * @return string
	 */
	public static function response_url( $survey_id ) {
		$pages = get_option( 'remember_created_pages', array() );
		$page_id = isset( $pages['survey'] ) ? absint( $pages['survey'] ) : 0;
		$url     = $page_id > 0 ? get_permalink( $page_id ) : '';
		if ( ! $url ) {
			$page = get_page_by_path( 'survey' );
			$url  = $page ? get_permalink( $page ) : home_url( '/survey/' );
		}
		return add_query_arg( 'survey_id', absint( $survey_id ), $url );
	}

	/**
	 * Delete a survey that has no responses.
	 *
	 * @param int $survey_id Survey.
	 * @return true|WP_Error
	 */
	public static function delete_survey( $survey_id ) {
		$survey_id = absint( $survey_id );
		if ( self::response_count( $survey_id ) > 0 ) {
			return new WP_Error( 'remember_survey_locked', __( 'This survey has responses and cannot be deleted.', 'remember' ) );
		}
		global $wpdb;
		$question_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT question_id FROM ' . self::questions_table() . ' WHERE survey_id = %d', $survey_id ) );
		if ( $question_ids ) {
			$in = implode( ',', array_map( 'absint', $question_ids ) );
			$wpdb->query( "DELETE FROM " . self::answers_table() . " WHERE question_id IN ({$in})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$wpdb->delete( self::questions_table(), array( 'survey_id' => $survey_id ), array( '%d' ) );
		$wpdb->delete( self::surveys_table(), array( 'survey_id' => $survey_id ), array( '%d' ) );
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-logger.php';
		Remember_Logger::info( 'Survey deleted', array( 'survey_id' => $survey_id ) );
		return true;
	}

	/**
	 * One question row.
	 *
	 * @param int $question_id Question.
	 * @return object|null
	 */
	public static function get_question( $question_id ) {
		global $wpdb;
		$question_id = absint( $question_id );
		if ( $question_id < 1 ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::questions_table() . ' WHERE question_id = %d', $question_id ) );
		return $row ? $row : null;
	}

	/**
	 * Create or update one question, and the survey title it belongs to.
	 *
	 * @param int $event_id Event.
	 * @return int|WP_Error Question id.
	 */
	public static function save_question_from_admin( $event_id ) {
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-profile-questions.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-logger.php';

		$event_id = absint( $event_id );
		$survey   = self::existing_survey_for_question( $event_id );
		if ( is_wp_error( $survey ) ) {
			return $survey;
		}

		$question_id = isset( $_POST['question_id'] ) ? absint( $_POST['question_id'] ) : 0;
		$current     = $question_id > 0 ? self::get_question( $question_id ) : null;
		if ( $question_id > 0 && ( ! $current || (int) $current->survey_id !== (int) $survey->survey_id ) ) {
			return new WP_Error( 'remember_survey_question', __( 'That question is not on this survey.', 'remember' ) );
		}

		$label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$type  = isset( $_POST['field_type'] ) ? sanitize_key( wp_unslash( $_POST['field_type'] ) ) : 'text';
		if ( ! in_array( $type, self::field_types(), true ) ) {
			$type = 'text';
		}
		$field_key = isset( $_POST['field_key'] ) ? Remember_Profile_Questions::sanitize_field_key( wp_unslash( $_POST['field_key'] ) ) : '';
		if ( '' === $field_key && '' !== $label ) {
			$field_key = Remember_Profile_Questions::suggest_field_key_from_label( $label );
		}
		$req_mode = isset( $_POST['required_mode'] ) ? sanitize_key( wp_unslash( $_POST['required_mode'] ) ) : 'optional';
		if ( ! in_array( $req_mode, array( 'optional', 'always', 'when' ), true ) ) {
			$req_mode = 'optional';
		}
		$sort_order = isset( $_POST['sort_order'] ) ? absint( $_POST['sort_order'] ) : 0;

		$option_pairs = array();
		if ( self::type_uses_options( $type ) && isset( $_POST['option_label'] ) && is_array( $_POST['option_label'] ) ) {
			$labels_in = wp_unslash( $_POST['option_label'] );
			$keys_in   = isset( $_POST['option_key'] ) && is_array( $_POST['option_key'] ) ? wp_unslash( $_POST['option_key'] ) : array();
			foreach ( $labels_in as $i => $opt_label ) {
				$opt_label = sanitize_text_field( (string) $opt_label );
				$opt_key   = isset( $keys_in[ $i ] ) ? Remember_Profile_Questions::sanitize_field_key( (string) $keys_in[ $i ] ) : '';
				if ( '' === $opt_label && '' === $opt_key ) {
					continue;
				}
				if ( '' === $opt_key ) {
					$opt_key = Remember_Profile_Questions::sanitize_field_key( $opt_label );
				}
				if ( '' === $opt_key ) {
					continue;
				}
				if ( '' === $opt_label ) {
					$opt_label = $opt_key;
				}
				$option_pairs[] = array(
					'key'   => $opt_key,
					'label' => $opt_label,
				);
			}
		}

		$required_when_json = null;
		if ( 'when' === $req_mode ) {
			$when_key = isset( $_POST['required_when_field'] ) ? Remember_Profile_Questions::sanitize_field_key( wp_unslash( $_POST['required_when_field'] ) ) : '';
			$when_raw = isset( $_POST['required_when_values'] ) && is_array( $_POST['required_when_values'] ) ? wp_unslash( $_POST['required_when_values'] ) : array();
			$when_vals = array();
			foreach ( $when_raw as $value ) {
				$when_vals[] = Remember_Profile_Questions::sanitize_field_key( (string) $value );
			}
			$required_when_json = Remember_Profile_Questions::encode_required_when( $when_key, $when_vals );
		}

		$error = '';
		if ( '' === $label ) {
			$error = __( 'Please enter the question members will see.', 'remember' );
		} elseif ( '' === $field_key ) {
			$error = __( 'Please enter a short name (for example ice_cream_flavor).', 'remember' );
		} elseif ( self::field_key_exists( $survey->survey_id, $field_key, $question_id ) ) {
			$error = __( 'That short name is already used by another question on this survey.', 'remember' );
		} elseif ( self::type_uses_options( $type ) && empty( $option_pairs ) ) {
			$error = __( 'Add at least one choice with a label and a key (for example Vanilla / vanilla).', 'remember' );
		} elseif ( 'when' === $req_mode && null === $required_when_json ) {
			$error = __( 'Choose an earlier pick-one or pick-several question and at least one choice that makes this question required.', 'remember' );
		}

		if ( '' === $error && 'when' === $req_mode ) {
			$rule = Remember_Profile_Questions::parse_required_when( $required_when_json );
			$gate = $rule ? self::question_by_field_key( $survey->survey_id, $rule['field_key'] ) : null;
			if ( ! $gate || (int) $gate->question_id === $question_id ) {
				$error = __( 'The “required when” question must be a different question on this survey.', 'remember' );
			} elseif ( ! self::type_can_gate( $gate->field_type ) ) {
				$error = __( 'The “required when” question must be a pick-one, pick-several, or yes/no question.', 'remember' );
			} else {
				$allowed = wp_list_pluck( self::option_pairs( $gate ), 'key' );
				foreach ( $rule['values'] as $value ) {
					if ( ! in_array( $value, $allowed, true ) ) {
						$error = __( 'One of the “required when” choices is not valid for that question.', 'remember' );
						break;
					}
				}
			}
		}

		if ( '' !== $error ) {
			return new WP_Error( 'remember_survey_question', $error );
		}

		global $wpdb;
		$data = array(
			'survey_id'           => (int) $survey->survey_id,
			'sort_order'          => $sort_order,
			'label'               => $label,
			'field_key'           => $field_key,
			'field_type'          => $type,
			'options_text'        => self::type_uses_options( $type ) ? Remember_Profile_Questions::encode_options( $option_pairs ) : '',
			'is_required'         => ( 'always' === $req_mode ) ? 1 : 0,
			'required_when_json'  => $required_when_json,
			'show_if_question_id' => 0,
			'show_if_value'       => '',
		);
		if ( $question_id > 0 ) {
			$wpdb->update( self::questions_table(), $data, array( 'question_id' => $question_id ) );
		} else {
			$wpdb->insert( self::questions_table(), $data );
			$question_id = (int) $wpdb->insert_id;
		}
		if ( $question_id < 1 ) {
			return new WP_Error( 'remember_survey_question', __( 'The question could not be saved.', 'remember' ) );
		}
		Remember_Logger::info(
			'Survey question saved',
			array(
				'survey_id'   => (int) $survey->survey_id,
				'question_id' => $question_id,
				'event_id'    => $event_id,
			)
		);
		Remember_Logger::debug(
			'Survey question saved',
			array(
				'survey_id'   => (int) $survey->survey_id,
				'question_id' => $question_id,
				'field_key'   => $field_key,
				'field_type'  => $type,
			)
		);
		return $question_id;
	}

	/**
	 * Delete one question and its answers.
	 *
	 * @param int $question_id Question.
	 * @return true|WP_Error
	 */
	public static function delete_question( $question_id ) {
		$question = self::get_question( $question_id );
		if ( ! $question ) {
			return new WP_Error( 'remember_survey_question', __( 'Question not found.', 'remember' ) );
		}
		global $wpdb;
		$wpdb->delete( self::answers_table(), array( 'question_id' => (int) $question->question_id ), array( '%d' ) );
		$wpdb->delete( self::questions_table(), array( 'question_id' => (int) $question->question_id ), array( '%d' ) );
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-logger.php';
		Remember_Logger::info(
			'Survey question deleted',
			array(
				'survey_id'   => (int) $question->survey_id,
				'question_id' => (int) $question->question_id,
			)
		);
		return true;
	}

	/**
	 * @param object   $survey    Survey row.
	 * @param object[] $questions Questions.
	 * @param bool     $standalone Kept for callers. The block is the same on the apply form and the follow-up page.
	 * @param object|null $existing Response row.
	 * @return string
	 */
	private static function render_fields( $survey, $questions, $standalone, $existing = null ) {
		$answers = $existing ? self::answers_for_response( $existing->response_id ) : array();
		$html         = '<div class="remember-survey" data-remember-survey="1">';
		$html        .= '<div class="remember-form-label">' . esc_html( $survey->title ) . '</div>';
		$instructions = isset( $survey->instructions ) ? trim( (string) $survey->instructions ) : '';
		if ( '' !== $instructions ) {
			$html .= '<p class="remember-form-help">' . nl2br( esc_html( $instructions ) ) . '</p>';
		}
		foreach ( $questions as $question ) {
			$qid      = (int) $question->question_id;
			$key      = self::question_field_key( $question );
			$rule     = self::when_rule( $question );
			$required = ! $rule && ! empty( $question->is_required );
			$html    .= '<div class="remember-form-group remember-survey-question" data-remember-survey-question="1" data-question-id="' . esc_attr( (string) $qid ) . '" data-field-type="' . esc_attr( $question->field_type ) . '" data-remember-pq-key="' . esc_attr( $key ) . '"';
			if ( $rule ) {
				$html .= ' data-remember-pq-when="' . esc_attr( wp_json_encode( $rule ) ) . '" hidden';
			}
			$html .= '>';
			$html .= '<label class="remember-form-label" for="remember_survey_' . esc_attr( (string) $qid ) . '">' . esc_html( $question->label );
			$html .= ' <span class="remember-required"' . ( $required ? '' : ' hidden' ) . '>*</span></label>';
			$html .= self::render_input( $question, isset( $answers[ $qid ] ) ? $answers[ $qid ] : '' );
			$html .= '</div>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * @param object $question Question.
	 * @param string $value    Stored value.
	 * @return string
	 */
	private static function render_input( $question, $value ) {
		$qid   = (int) $question->question_id;
		$name  = 'remember_survey[' . $qid . ']';
		$id    = 'remember_survey_' . $qid;
		$type    = (string) $question->field_type;
		$options = self::option_pairs( $question );
		if ( 'textarea' === $type ) {
			return '<textarea class="remember-form-control" name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '" rows="4">' . esc_textarea( $value ) . '</textarea>';
		}
		if ( 'select' === $type ) {
			$html = '<select class="remember-form-control" name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '"><option value="">' . esc_html__( '— Select —', 'remember' ) . '</option>';
			foreach ( $options as $option ) {
				$html .= '<option value="' . esc_attr( $option['key'] ) . '"' . selected( $value, $option['key'], false ) . '>' . esc_html( $option['label'] ) . '</option>';
			}
			return $html . '</select>';
		}
		if ( 'multiselect' === $type ) {
			$selected = self::decode_list( $value );
			$html     = '<div class="remember-pq-checkboxes" id="' . esc_attr( $id ) . '">';
			foreach ( $options as $option ) {
				$html .= '<label class="remember-checkbox-label"><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $option['key'] ) . '"' . checked( in_array( $option['key'], $selected, true ), true, false ) . '> ' . esc_html( $option['label'] ) . '</label>';
			}
			return $html . '</div>';
		}
		if ( 'boolean' === $type ) {
			return '<label><input type="radio" name="' . esc_attr( $name ) . '" value="yes"' . checked( $value, 'yes', false ) . '> ' . esc_html__( 'Yes', 'remember' ) . '</label> '
				. '<label><input type="radio" name="' . esc_attr( $name ) . '" value="no"' . checked( $value, 'no', false ) . '> ' . esc_html__( 'No', 'remember' ) . '</label>';
		}
		return '<input class="remember-form-control" type="text" name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '" value="' . esc_attr( $value ) . '">';
	}

	/**
	 * @param object[] $questions Questions.
	 * @return string
	 */
	private static function validate_posted( $questions ) {
		$posted  = isset( $_POST['remember_survey'] ) && is_array( $_POST['remember_survey'] ) ? wp_unslash( $_POST['remember_survey'] ) : array();
		$visible = self::visibility_map( $questions, $posted );
		foreach ( $questions as $question ) {
			$qid = (int) $question->question_id;
			if ( empty( $visible[ $qid ] ) ) {
				continue;
			}
			$raw = isset( $posted[ $qid ] ) ? $posted[ $qid ] : '';
			if ( self::question_is_required( $question, $visible ) && self::posted_is_empty( $question, $raw ) ) {
				return sprintf(
					/* translators: %s: question label */
					__( 'Please answer: %s', 'remember' ),
					$question->label
				);
			}
		}
		return '';
	}

	/**
	 * @param object $survey         Survey.
	 * @param int    $member_id      Member.
	 * @param int    $application_id Application, or 0.
	 * @return void
	 */
	private static function save_response( $survey, $member_id, $application_id ) {
		global $wpdb;
		$questions = self::questions( $survey->survey_id );
		$posted    = isset( $_POST['remember_survey'] ) && is_array( $_POST['remember_survey'] ) ? wp_unslash( $_POST['remember_survey'] ) : array();
		$existing = self::response_for_member( $survey->survey_id, $member_id );
		$now      = current_time( 'mysql' );
		if ( $existing ) {
			$response_id = (int) $existing->response_id;
			$wpdb->update(
				self::responses_table(),
				array(
					'submitted_at'   => $now,
					'application_id' => $application_id > 0 ? $application_id : $existing->application_id,
				),
				array( 'response_id' => $response_id )
			);
		} else {
			$wpdb->insert(
				self::responses_table(),
				array(
					'survey_id'      => (int) $survey->survey_id,
					'event_id'       => (int) $survey->event_id,
					'member_id'      => absint( $member_id ),
					'application_id' => $application_id > 0 ? absint( $application_id ) : null,
					'submitted_at'   => $now,
				)
			);
			$response_id = (int) $wpdb->insert_id;
		}
		if ( $response_id < 1 ) {
			return;
		}
		$visible = self::visibility_map( $questions, $posted );
		foreach ( $questions as $question ) {
			$qid   = (int) $question->question_id;
			$show  = ! empty( $visible[ $qid ] );
			$value = $show ? self::sanitize_answer( $question, isset( $posted[ $qid ] ) ? $posted[ $qid ] : '' ) : '';
			$wpdb->query(
				$wpdb->prepare(
					'INSERT INTO ' . self::answers_table() . ' (response_id, question_id, value_text) VALUES (%d, %d, %s) ON DUPLICATE KEY UPDATE value_text = VALUES(value_text)',
					$response_id,
					$qid,
					$value
				)
			);
		}
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-logger.php';
		Remember_Logger::info(
			'Survey response saved',
			array(
				'survey_id'      => (int) $survey->survey_id,
				'event_id'       => (int) $survey->event_id,
				'member_id'      => absint( $member_id ),
				'application_id' => absint( $application_id ),
			)
		);
	}

	/**
	 * @return array<int,array{label:string,field_type:string,options:string,show_if_row:int,show_if_value:string,question_id:int}>
	 */
	private static function posted_question_rows() {
		if ( empty( $_POST['survey_questions'] ) || ! is_array( $_POST['survey_questions'] ) ) {
			return array();
		}
		$rows = array();
		foreach ( wp_unslash( $_POST['survey_questions'] ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}
			$type = isset( $row['field_type'] ) ? sanitize_key( $row['field_type'] ) : 'text';
			if ( ! in_array( $type, self::field_types(), true ) ) {
				$type = 'text';
			}
			$show_row = isset( $row['show_if_row'] ) ? (int) $row['show_if_row'] : -1;
			$rows[]   = array(
				'question_id'   => isset( $row['question_id'] ) ? absint( $row['question_id'] ) : 0,
				'label'         => $label,
				'field_type'    => $type,
				'options'       => isset( $row['options'] ) ? sanitize_textarea_field( $row['options'] ) : '',
				'show_if_row'   => $show_row,
				'show_if_value' => isset( $row['show_if_value'] ) ? sanitize_text_field( $row['show_if_value'] ) : '',
			);
		}
		return $rows;
	}

	/**
	 * @param int   $survey_id Survey.
	 * @param array $rows      Posted questions.
	 * @return void
	 */
	private static function sync_questions( $survey_id, $rows ) {
		global $wpdb;
		$table    = self::questions_table();
		$existing = self::questions( $survey_id );
		$owned    = array();
		foreach ( $existing as $question ) {
			$owned[ (int) $question->question_id ] = true;
		}
		$ids_by_row = array();
		$keep       = array();
		foreach ( $rows as $index => $row ) {
			$data = array(
				'survey_id'    => $survey_id,
				'sort_order'   => $index,
				'label'        => $row['label'],
				'field_type'   => $row['field_type'],
				'options_text' => $row['options'],
			);
			$qid = (int) $row['question_id'];
			if ( $qid > 0 && isset( $owned[ $qid ] ) ) {
				$wpdb->update( $table, $data, array( 'question_id' => $qid ) );
			} else {
				$wpdb->insert( $table, $data );
				$qid = (int) $wpdb->insert_id;
			}
			$ids_by_row[ $index ] = $qid;
			$keep[]               = $qid;
		}
		foreach ( $rows as $index => $row ) {
			$parent_row = (int) $row['show_if_row'];
			$parent_id  = ( $parent_row >= 0 && $parent_row < $index && isset( $ids_by_row[ $parent_row ] ) ) ? $ids_by_row[ $parent_row ] : 0;
			$value      = $parent_id > 0 ? $row['show_if_value'] : '';
			$wpdb->update(
				$table,
				array(
					'show_if_question_id' => $parent_id > 0 ? $parent_id : 0,
					'show_if_value'       => $value,
				),
				array( 'question_id' => $ids_by_row[ $index ] )
			);
		}
		foreach ( $existing as $question ) {
			$qid = (int) $question->question_id;
			if ( in_array( $qid, $keep, true ) ) {
				continue;
			}
			$wpdb->delete( self::answers_table(), array( 'question_id' => $qid ), array( '%d' ) );
			$wpdb->delete( $table, array( 'question_id' => $qid ), array( '%d' ) );
		}
	}

	/**
	 * @param object|null  $question Parent question.
	 * @param mixed        $raw      Posted parent value.
	 * @param string       $expected Expected value.
	 * @return bool
	 */
	private static function answer_matches( $question, $raw, $expected ) {
		$expected = strtolower( trim( (string) $expected ) );
		if ( '' === $expected || ! $question ) {
			return false;
		}
		if ( 'multiselect' === $question->field_type ) {
			$list = is_array( $raw ) ? $raw : array();
			foreach ( $list as $item ) {
				if ( strtolower( trim( sanitize_text_field( (string) $item ) ) ) === $expected ) {
					return true;
				}
			}
			return false;
		}
		return strtolower( trim( sanitize_text_field( is_array( $raw ) ? '' : (string) $raw ) ) ) === $expected;
	}

	/**
	 * @param object $question Question.
	 * @param mixed  $raw      Posted value.
	 * @return bool
	 */
	private static function posted_is_empty( $question, $raw ) {
		if ( 'multiselect' === $question->field_type ) {
			return ! is_array( $raw ) || empty( $raw );
		}
		return '' === trim( is_array( $raw ) ? '' : (string) $raw );
	}

	/**
	 * @param object $question Question.
	 * @param mixed  $raw      Posted value.
	 * @return string
	 */
	private static function sanitize_answer( $question, $raw ) {
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-profile-questions.php';
		if ( 'multiselect' === $question->field_type ) {
			$clean   = array();
			$allowed = wp_list_pluck( self::option_pairs( $question ), 'key' );
			foreach ( (array) $raw as $item ) {
				$item = Remember_Profile_Questions::sanitize_field_key( (string) $item );
				if ( in_array( $item, $allowed, true ) ) {
					$clean[] = $item;
				}
			}
			return implode( ' | ', array_values( $clean ) );
		}
		if ( 'boolean' === $question->field_type ) {
			$value = sanitize_key( is_array( $raw ) ? '' : (string) $raw );
			return in_array( $value, array( 'yes', 'no' ), true ) ? $value : '';
		}
		if ( 'select' === $question->field_type || 'boolean' === $question->field_type ) {
			$value   = Remember_Profile_Questions::sanitize_field_key( is_array( $raw ) ? '' : (string) $raw );
			$allowed = wp_list_pluck( self::option_pairs( $question ), 'key' );
			return in_array( $value, $allowed, true ) ? $value : '';
		}
		if ( 'textarea' === $question->field_type ) {
			return sanitize_textarea_field( is_array( $raw ) ? '' : (string) $raw );
		}
		return sanitize_text_field( is_array( $raw ) ? '' : (string) $raw );
	}

	/**
	 * Save the survey title, instructions, and follow-up timing. Questions are saved separately.
	 *
	 * @param int $event_id Event.
	 * @return object|WP_Error Saved survey.
	 */
	public static function save_header_from_admin( $event_id ) {
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-logger.php';
		$event_id  = absint( $event_id );
		$placement = ! empty( $_POST['on_application'] ) ? self::PLACEMENT_APPLICATION : self::PLACEMENT_FOLLOWUP;
		$survey_id    = isset( $_POST['survey_id'] ) ? absint( $_POST['survey_id'] ) : 0;
		$title        = isset( $_POST['survey_title'] ) ? sanitize_text_field( wp_unslash( $_POST['survey_title'] ) ) : '';
		$instructions = isset( $_POST['survey_instructions'] ) ? sanitize_textarea_field( wp_unslash( $_POST['survey_instructions'] ) ) : '';
		$timing       = isset( $_POST['survey_timing'] ) ? sanitize_key( wp_unslash( $_POST['survey_timing'] ) ) : '';
		if ( ! in_array( $timing, array( 'before', 'after' ), true ) ) {
			$timing = '';
		}
		if ( $event_id < 1 ) {
			return new WP_Error( 'remember_survey_event', __( 'Choose an event.', 'remember' ) );
		}
		if ( '' === $title ) {
			return new WP_Error( 'remember_survey_title', __( 'A survey needs a title.', 'remember' ) );
		}
		$existing_application = self::application_survey( $event_id );
		if ( self::PLACEMENT_APPLICATION === $placement && $existing_application && (int) $existing_application->survey_id !== $survey_id ) {
			return new WP_Error( 'remember_survey_application', __( 'This event already has a survey on the application.', 'remember' ) );
		}
		global $wpdb;
		$now  = current_time( 'mysql' );
		$data = array(
			'event_id'     => $event_id,
			'title'        => $title,
			'instructions' => $instructions,
			'placement'    => $placement,
			'timing'       => self::PLACEMENT_FOLLOWUP === $placement ? ( $timing ? $timing : 'after' ) : '',
			'updated_at'   => $now,
		);
		if ( $survey_id > 0 ) {
			$current = self::get( $survey_id );
			if ( ! $current || (int) $current->event_id !== $event_id ) {
				return new WP_Error( 'remember_survey_missing', __( 'Survey not found for this event.', 'remember' ) );
			}
			if ( self::PLACEMENT_APPLICATION === $placement ) {
				$data['status'] = 'open';
			} elseif ( 'open' === $current->status ) {
				$data['status'] = 'draft';
			}
			$wpdb->update( self::surveys_table(), $data, array( 'survey_id' => $survey_id ) );
		} else {
			$data['status']     = self::PLACEMENT_APPLICATION === $placement ? 'open' : 'draft';
			$data['created_by'] = get_current_user_id();
			$data['created_at'] = $now;
			$wpdb->insert( self::surveys_table(), $data );
			$survey_id = (int) $wpdb->insert_id;
		}
		if ( $survey_id < 1 ) {
			return new WP_Error( 'remember_survey_save', __( 'The survey could not be saved.', 'remember' ) );
		}
		Remember_Logger::info(
			'Survey saved',
			array(
				'survey_id' => $survey_id,
				'event_id'  => $event_id,
				'placement' => $placement,
			)
		);
		$survey = self::get( $survey_id );
		return $survey ? $survey : new WP_Error( 'remember_survey_save', __( 'The survey could not be saved.', 'remember' ) );
	}

	/**
	 * Survey a question save belongs to. The header is saved on its own.
	 *
	 * @param int $event_id Event.
	 * @return object|WP_Error
	 */
	private static function existing_survey_for_question( $event_id ) {
		$survey = self::get( isset( $_POST['survey_id'] ) ? absint( $_POST['survey_id'] ) : 0 );
		if ( ! $survey || (int) $survey->event_id !== absint( $event_id ) ) {
			return new WP_Error( 'remember_survey_missing', __( 'Save the survey title before adding questions.', 'remember' ) );
		}
		return $survey;
	}

	/**
	 * @param int    $survey_id   Survey.
	 * @param string $field_key   Short name.
	 * @param int    $except_id   Question to ignore.
	 * @return bool
	 */
	private static function field_key_exists( $survey_id, $field_key, $except_id ) {
		global $wpdb;
		$found = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT question_id FROM ' . self::questions_table() . ' WHERE survey_id = %d AND field_key = %s AND question_id != %d LIMIT 1',
				absint( $survey_id ),
				$field_key,
				absint( $except_id )
			)
		);
		return (bool) $found;
	}

	/**
	 * @param int    $survey_id Survey.
	 * @param string $field_key Short name.
	 * @return object|null
	 */
	private static function question_by_field_key( $survey_id, $field_key ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::questions_table() . ' WHERE survey_id = %d AND field_key = %s LIMIT 1',
				absint( $survey_id ),
				$field_key
			)
		);
		return $row ? $row : null;
	}

	/**
	 * @param string $field_type Type.
	 * @return bool
	 */
	private static function type_uses_options( $field_type ) {
		return in_array( (string) $field_type, array( 'select', 'multiselect' ), true );
	}

	/**
	 * @param string $field_type Type.
	 * @return bool
	 */
	private static function type_can_gate( $field_type ) {
		return in_array( (string) $field_type, array( 'select', 'multiselect', 'boolean' ), true );
	}

	/**
	 * @param object $question Question.
	 * @return string
	 */
	private static function question_field_key( $question ) {
		$key = isset( $question->field_key ) ? (string) $question->field_key : '';
		if ( '' === $key ) {
			$key = 'q' . (int) $question->question_id;
		}
		return $key;
	}

	/**
	 * @param object $question Question.
	 * @return array{field_key:string,values:array<int,string>}|null
	 */
	private static function when_rule( $question ) {
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-profile-questions.php';
		if ( empty( $question->required_when_json ) ) {
			return null;
		}
		return Remember_Profile_Questions::parse_required_when( $question->required_when_json );
	}

	/**
	 * @param object[] $questions Questions in sort order.
	 * @param array    $posted    question_id => raw posted value.
	 * @return array<int,bool>
	 */
	private static function visibility_map( $questions, $posted ) {
		$by_key  = array();
		$visible = array();
		foreach ( $questions as $question ) {
			$by_key[ self::question_field_key( $question ) ] = $question;
		}
		for ( $pass = 0; $pass < 2; $pass++ ) {
			foreach ( $questions as $question ) {
				$qid  = (int) $question->question_id;
				$rule = self::when_rule( $question );
				if ( ! $rule ) {
					$visible[ $qid ] = true;
					continue;
				}
				$gate    = isset( $by_key[ $rule['field_key'] ] ) ? $by_key[ $rule['field_key'] ] : null;
				$gate_id = $gate ? (int) $gate->question_id : 0;
				$gate_on = $gate_id > 0 && ( ! isset( $visible[ $gate_id ] ) || ! empty( $visible[ $gate_id ] ) );
				$raw     = ( $gate_id && isset( $posted[ $gate_id ] ) ) ? $posted[ $gate_id ] : '';
				$visible[ $qid ] = $gate_on && self::posted_matches_values( $raw, $rule['values'] );
			}
		}
		return $visible;
	}

	/**
	 * @param object        $question Question.
	 * @param array<int,bool> $visible Visibility map.
	 * @return bool
	 */
	private static function question_is_required( $question, $visible ) {
		$qid = (int) $question->question_id;
		if ( empty( $visible[ $qid ] ) ) {
			return false;
		}
		if ( self::when_rule( $question ) ) {
			return true;
		}
		return ! empty( $question->is_required );
	}

	/**
	 * @param mixed    $raw    Posted value.
	 * @param string[] $values Choice keys.
	 * @return bool
	 */
	private static function posted_matches_values( $raw, $values ) {
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-profile-questions.php';
		$picked = array();
		$items  = is_array( $raw ) ? $raw : array( $raw );
		foreach ( $items as $item ) {
			$key = Remember_Profile_Questions::sanitize_field_key( (string) $item );
			if ( '' !== $key ) {
				$picked[] = $key;
			}
		}
		return ! empty( array_intersect( $picked, $values ) );
	}

	/**
	 * @param object $question Question.
	 * @return array<int,array{key:string,label:string}>
	 */
	private static function option_pairs( $question ) {
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-profile-questions.php';
		if ( 'boolean' === $question->field_type ) {
			return array(
				array(
					'key'   => 'yes',
					'label' => __( 'Yes', 'remember' ),
				),
				array(
					'key'   => 'no',
					'label' => __( 'No', 'remember' ),
				),
			);
		}
		$parsed = Remember_Profile_Questions::parse_options( isset( $question->options_text ) ? $question->options_text : '' );
		if ( ! empty( $parsed ) ) {
			return $parsed;
		}
		$out = array();
		foreach ( self::option_lines( isset( $question->options_text ) ? $question->options_text : '' ) as $line ) {
			$key = Remember_Profile_Questions::sanitize_field_key( $line );
			if ( '' === $key ) {
				continue;
			}
			$out[] = array(
				'key'   => $key,
				'label' => $line,
			);
		}
		return $out;
	}

	/**
	 * @param string $text Options, one per line.
	 * @return string[]
	 */
	private static function option_lines( $text ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $text );
		$out   = array();
		foreach ( (array) $lines as $line ) {
			$line = trim( sanitize_text_field( $line ) );
			if ( '' !== $line ) {
				$out[] = $line;
			}
		}
		return $out;
	}

	/**
	 * @param string $value Stored multiselect JSON.
	 * @return string[]
	 */
	private static function decode_list( $value ) {
		$out = array();
		foreach ( explode( ' | ', (string) $value ) as $item ) {
			$item = trim( $item );
			if ( '' !== $item ) {
				$out[] = $item;
			}
		}
		return $out;
	}

	/**
	 * @param int $survey_id Survey.
	 * @param int $member_id Member.
	 * @return object|null
	 */
	private static function response_for_member( $survey_id, $member_id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::responses_table() . ' WHERE survey_id = %d AND member_id = %d',
				absint( $survey_id ),
				absint( $member_id )
			)
		);
		return $row ? $row : null;
	}

	/**
	 * @param int $response_id Response.
	 * @return array<int,string>
	 */
	private static function answers_for_response( $response_id ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT question_id, value_text FROM ' . self::answers_table() . ' WHERE response_id = %d',
				absint( $response_id )
			)
		);
		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row->question_id ] = (string) $row->value_text;
		}
		return $out;
	}

	/**
	 * @param int $survey_id Survey.
	 * @return int
	 */
	private static function response_count( $survey_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::responses_table() . ' WHERE survey_id = %d', absint( $survey_id ) ) );
	}

	/**
	 * @return string
	 */
	private static function surveys_table() {
		global $wpdb;
		return $wpdb->prefix . 'remember_surveys';
	}

	/**
	 * @return string
	 */
	private static function questions_table() {
		global $wpdb;
		return $wpdb->prefix . 'remember_survey_questions';
	}

	/**
	 * @return string
	 */
	private static function responses_table() {
		global $wpdb;
		return $wpdb->prefix . 'remember_survey_responses';
	}

	/**
	 * @return string
	 */
	private static function answers_table() {
		global $wpdb;
		return $wpdb->prefix . 'remember_survey_answers';
	}
}
