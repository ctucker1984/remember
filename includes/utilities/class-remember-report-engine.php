<?php
/**
 * Compile a report definition into parameterized SQL.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Cap-aware report runner.
 */
class Remember_Report_Engine {

	const PAGE_SIZE  = 50;
	const MAX_EXPORT = 10000;

	/**
	 * Run a report definition.
	 *
	 * @param array $definition Builder JSON.
	 * @param int   $page       1-based page.
	 * @param int   $per_page   Page size.
	 * @param bool  $export     Full export (capped).
	 * @param int   $event_id   Optional run-time event scope.
	 * @return array|\WP_Error
	 */
	public static function run( $definition, $page = 1, $per_page = self::PAGE_SIZE, $export = false, $event_id = 0 ) {
		global $wpdb;

		require_once plugin_dir_path( __FILE__ ) . 'class-remember-report-catalog.php';

		$event_id = Remember_Report_Catalog::sanitize_event_id( $event_id );
		$compiled = self::compile( $definition, $event_id );
		if ( is_wp_error( $compiled ) ) {
			return $compiled;
		}

		$count_sql = $compiled['count_sql'];
		$count     = empty( $compiled['count_params'] )
			? (int) $wpdb->get_var( $count_sql ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			: (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$compiled['count_params'] ) );

		$page     = max( 1, absint( $page ) );
		$per_page = min( 100, max( 1, absint( $per_page ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$sql    = $compiled['sql'];
		$params = $compiled['params'];
		if ( $export ) {
			$sql     .= ' LIMIT %d';
			$params[] = self::MAX_EXPORT;
		} else {
			$sql     .= ' LIMIT %d OFFSET %d';
			$params[] = $per_page;
			$params[] = $offset;
		}

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$out_rows = array();
		foreach ( $rows as $row ) {
			$line = array();
			foreach ( $compiled['select_ids'] as $id ) {
				$key          = self::alias( $id );
				$line[ $id ] = self::format_list_cell( isset( $row[ $key ] ) ? $row[ $key ] : '' );
			}
			$out_rows[] = $line;
		}

		return array(
			'columns' => $compiled['columns'],
			'rows'    => $out_rows,
			'total'   => $count,
			'page'    => $page,
			'pages'   => $export ? 1 : (int) max( 1, ceil( $count / $per_page ) ),
			'mode'    => $compiled['mode'],
			'subject' => $compiled['subject'],
			'event_id'    => $event_id,
			'event_label' => $event_id ? Remember_Report_Catalog::event_label( $event_id ) : '',
		);
	}

	/**
	 * Compile definition to SQL fragments.
	 *
	 * @param array $definition Definition.
	 * @param int   $event_id   Optional run-time event scope.
	 * @return array|\WP_Error
	 */
	public static function compile( $definition, $event_id = 0 ) {
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-report-catalog.php';
		if ( ! is_array( $definition ) ) {
			return new WP_Error( 'invalid', __( 'Invalid report.', 'remember' ) );
		}
		$event_id = absint( $event_id );
		$subject = isset( $definition['subject'] ) ? sanitize_key( $definition['subject'] ) : '';
		$fields  = Remember_Report_Catalog::fields_for_current_user( $subject );
		if ( empty( $fields ) ) {
			return new WP_Error( 'no_subject', __( 'You cannot report on that data.', 'remember' ) );
		}

		$group_by = self::sanitize_id_list( isset( $definition['group_by'] ) ? $definition['group_by'] : array(), $fields );
		$mode     = ( ! empty( $group_by ) || ( isset( $definition['mode'] ) && 'summary' === $definition['mode'] ) ) ? 'summary' : 'detail';

		$aggregations = array();
		if ( isset( $definition['aggregations'] ) && is_array( $definition['aggregations'] ) ) {
			foreach ( $definition['aggregations'] as $agg ) {
				if ( ! is_array( $agg ) ) {
					continue;
				}
				$fn = isset( $agg['fn'] ) ? sanitize_key( $agg['fn'] ) : '';
				if ( ! in_array( $fn, array( 'count', 'count_distinct', 'sum', 'min', 'max' ), true ) ) {
					continue;
				}
				$fid = isset( $agg['field'] ) ? (string) $agg['field'] : '';
				if ( 'count' === $fn && ( '' === $fid || '*' === $fid ) ) {
					$aggregations[] = array(
						'fn'    => 'count',
						'field' => '*',
						'label' => __( 'Count', 'remember' ),
						'sql'   => 'COUNT(*)',
					);
					continue;
				}
				if ( ! isset( $fields[ $fid ] ) ) {
					continue;
				}
				if ( in_array( $fn, array( 'sum' ), true ) && empty( $fields[ $fid ]['measure'] ) ) {
					continue;
				}
				$aggregations[] = array(
					'fn'    => $fn,
					'field' => $fid,
					'label' => self::agg_label( $fn, $fields[ $fid ]['label'] ),
					'sql'   => self::agg_sql( $fn, $fields[ $fid ]['sql'] ),
				);
			}
		}

		if ( 'summary' === $mode && empty( $group_by ) && empty( $aggregations ) ) {
			$aggregations[] = array(
				'fn'    => 'count',
				'field' => '*',
				'label' => __( 'Count', 'remember' ),
				'sql'   => 'COUNT(*)',
			);
		}

		$columns = self::sanitize_id_list( isset( $definition['columns'] ) ? $definition['columns'] : array(), $fields );
		if ( 'summary' === $mode ) {
			$columns = $group_by;
		} elseif ( empty( $columns ) ) {
			$columns = array();
			foreach ( Remember_Report_Catalog::default_columns( $subject ) as $id ) {
				if ( isset( $fields[ $id ] ) ) {
					$columns[] = $id;
				}
			}
		}
		if ( 'detail' === $mode && empty( $columns ) ) {
			return new WP_Error( 'no_columns', __( 'Pick at least one column.', 'remember' ) );
		}

		$filters = isset( $definition['filters'] ) && is_array( $definition['filters'] ) ? $definition['filters'] : array();
		$sort    = isset( $definition['sort'] ) && is_array( $definition['sort'] ) ? $definition['sort'] : array();

		$needed = array_merge( $columns, $group_by );
		foreach ( $aggregations as $agg ) {
			if ( '*' !== $agg['field'] && isset( $fields[ $agg['field'] ] ) ) {
				$needed[] = $agg['field'];
			}
		}
		foreach ( $filters as $filter ) {
			if ( is_array( $filter ) && ! empty( $filter['field'] ) ) {
				$needed[] = (string) $filter['field'];
			}
		}
		if ( ! empty( $sort['field'] ) ) {
			$needed[] = (string) $sort['field'];
		}

		$joins   = self::collect_joins( $subject, $fields, $needed );
		$from    = self::from_sql( $subject );
		$where   = array( '1=1' );
		$params  = array();
		$scope   = self::scope_sql( $subject, $event_id );
		if ( $scope['sql'] ) {
			$where[] = $scope['sql'];
			$params  = array_merge( $params, $scope['params'] );
		}

		foreach ( $filters as $filter ) {
			$clause = self::filter_clause( $filter, $fields );
			if ( is_wp_error( $clause ) ) {
				continue;
			}
			if ( $clause ) {
				$where[] = $clause['sql'];
				$params  = array_merge( $params, $clause['params'] );
			}
		}

		$where_sql = ' WHERE ' . implode( ' AND ', $where );

		$select_ids = array();
		$select_sql = array();
		$headers    = array();
		if ( 'summary' === $mode ) {
			foreach ( $group_by as $id ) {
				$select_ids[] = $id;
				$select_sql[] = $fields[ $id ]['sql'] . ' AS `' . self::alias( $id ) . '`';
				$headers[]    = array( 'id' => $id, 'label' => $fields[ $id ]['label'] );
			}
			$i = 0;
			foreach ( $aggregations as $agg ) {
				++$i;
				$aid          = 'agg_' . $i;
				$select_ids[] = $aid;
				$select_sql[] = $agg['sql'] . ' AS `' . self::alias( $aid ) . '`';
				$headers[]    = array( 'id' => $aid, 'label' => $agg['label'] );
			}
			if ( empty( $select_sql ) ) {
				return new WP_Error( 'no_columns', __( 'Pick a grouping or a count.', 'remember' ) );
			}
		} else {
			foreach ( $columns as $id ) {
				$select_ids[] = $id;
				$select_sql[] = $fields[ $id ]['sql'] . ' AS `' . self::alias( $id ) . '`';
				$headers[]    = array( 'id' => $id, 'label' => $fields[ $id ]['label'] );
			}
		}

		$sql = 'SELECT ' . implode( ', ', $select_sql ) . ' ' . $from . ' ' . $joins . $where_sql;

		if ( 'summary' === $mode && ! empty( $group_by ) ) {
			$gb = array();
			foreach ( $group_by as $id ) {
				$gb[] = $fields[ $id ]['sql'];
			}
			$sql .= ' GROUP BY ' . implode( ', ', $gb );
		}

		$order_id = isset( $sort['field'] ) ? (string) $sort['field'] : '';
		$dir      = ( isset( $sort['dir'] ) && 'desc' === strtolower( (string) $sort['dir'] ) ) ? 'DESC' : 'ASC';
		if ( 'summary' === $mode && isset( $fields[ $order_id ] ) && in_array( $order_id, $group_by, true ) ) {
			$sql .= ' ORDER BY ' . $fields[ $order_id ]['sql'] . ' ' . $dir;
		} elseif ( 'summary' !== $mode && isset( $fields[ $order_id ] ) ) {
			$sql .= ' ORDER BY ' . $fields[ $order_id ]['sql'] . ' ' . $dir;
		} elseif ( 'summary' === $mode && ! empty( $aggregations ) ) {
			$sql .= ' ORDER BY `' . self::alias( 'agg_1' ) . '` DESC';
		} elseif ( ! empty( $select_ids ) ) {
			$sql .= ' ORDER BY `' . self::alias( $select_ids[0] ) . '` ASC';
		}

		$count_sql    = 'SELECT COUNT(*) FROM (SELECT 1 ' . $from . ' ' . $joins . $where_sql;
		$count_params = $params;
		if ( 'summary' === $mode && empty( $group_by ) ) {
			$count_sql    = 'SELECT 1';
			$count_params = array();
		} elseif ( 'summary' === $mode && ! empty( $group_by ) ) {
			$gb = array();
			foreach ( $group_by as $id ) {
				$gb[] = $fields[ $id ]['sql'];
			}
			$count_sql .= ' GROUP BY ' . implode( ', ', $gb );
			$count_sql .= ') remember_report_count';
		} else {
			$count_sql .= ') remember_report_count';
		}

		return array(
			'sql'          => $sql,
			'params'       => $params,
			'count_sql'    => $count_sql,
			'count_params' => $count_params,
			'columns'     => $headers,
			'select_ids'  => $select_ids,
			'mode'        => $mode,
			'subject'     => $subject,
		);
	}

	/**
	 * FROM clause for a subject.
	 *
	 * @param string $subject Subject.
	 * @return string
	 */
	private static function from_sql( $subject ) {
		global $wpdb;
		$p = $wpdb->prefix;
		$map = array(
			'members'      => "FROM {$p}remember_members m",
			'applications' => "FROM {$p}remember_event_applications a",
			'payments'     => "FROM {$p}remember_payments pay",
			'vetting'      => "FROM {$p}remember_vetting v",
			'events'       => "FROM {$p}remember_events e",
		);
		return isset( $map[ $subject ] ) ? $map[ $subject ] : $map['members'];
	}

	/**
	 * Join SQL for needed keys.
	 *
	 * @param string $subject Subject.
	 * @param array  $fields  Catalog.
	 * @param array  $needed  Field ids.
	 * @return string
	 */
	private static function collect_joins( $subject, $fields, $needed ) {
		global $wpdb;
		$p     = $wpdb->prefix;
		$users = $wpdb->users;
		$keys  = array();
		foreach ( array_unique( $needed ) as $id ) {
			if ( isset( $fields[ $id ]['join'] ) && $fields[ $id ]['join'] ) {
				$keys[] = $fields[ $id ]['join'];
			}
		}
		$keys = array_unique( $keys );
		if ( in_array( $subject, array( 'members', 'applications', 'payments', 'vetting' ), true ) ) {
			$keys[] = 'user';
			$keys   = array_unique( $keys );
		}
		$sql  = '';

		$built = array(
			'members'      => array(
				'user'    => "INNER JOIN {$users} u ON u.ID = m.member_id",
				'profile' => "LEFT JOIN {$p}remember_member_profiles p ON p.member_id = m.member_id",
			),
			'applications' => array(
				'user'    => "INNER JOIN {$users} u ON u.ID = a.member_id",
				'member'  => "LEFT JOIN {$p}remember_members m ON m.member_id = a.member_id",
				'event'   => "LEFT JOIN {$p}remember_events e ON e.event_id = a.event_id",
				'role'    => "LEFT JOIN {$p}remember_event_roles er ON er.event_role_id = a.event_role_id LEFT JOIN {$p}remember_roles r ON r.role_id = er.role_id",
				'profile' => "LEFT JOIN {$p}remember_member_profiles p ON p.member_id = a.member_id",
			),
			'payments'     => array(
				'user'  => "INNER JOIN {$users} u ON u.ID = pay.member_id",
				'event' => "LEFT JOIN {$p}remember_event_applications a ON a.application_id = pay.event_application_id LEFT JOIN {$p}remember_events e ON e.event_id = a.event_id",
			),
			'vetting'      => array(
				'user'   => "INNER JOIN {$users} u ON u.ID = v.member_id",
				'member' => "LEFT JOIN {$p}remember_members m ON m.member_id = v.member_id",
				'vetter' => "LEFT JOIN {$users} vu ON vu.ID = v.primary_vetter_id",
			),
			'events'       => array(
				'location' => "LEFT JOIN {$p}remember_locations loc ON loc.location_id = e.location_id",
			),
		);

		$set = isset( $built[ $subject ] ) ? $built[ $subject ] : array();
		foreach ( $keys as $key ) {
			if ( isset( $set[ $key ] ) ) {
				$sql .= ' ' . $set[ $key ];
				continue;
			}
			if ( preg_match( '/^pq_\d+$/', $key ) && isset( $fields[ $key ]['question_id'] ) ) {
				$qid   = (int) $fields[ $key ]['question_id'];
				$mexp  = isset( $fields[ $key ]['member_expr'] ) ? $fields[ $key ]['member_expr'] : 'm.member_id';
				if ( ! in_array( $mexp, array( 'm.member_id', 'a.member_id' ), true ) ) {
					$mexp = 'm.member_id';
				}
				$alias = $key;
				$join  = $wpdb->prepare(
					" LEFT JOIN {$p}remember_profile_question_responses {$alias} ON {$alias}.member_id = {$mexp} AND {$alias}.question_id = %d",
					$qid
				);
				if ( is_string( $join ) && '' !== $join ) {
					$sql .= $join;
				}
			}
		}
		return $sql;
	}

	/**
	 * Row-scope SQL (merged exclusion, attendees-only, optional event).
	 *
	 * @param string $subject  Subject.
	 * @param int    $event_id Event scope.
	 * @return array{sql:string,params:array}
	 */
	private static function scope_sql( $subject, $event_id = 0 ) {
		global $wpdb;
		$p      = $wpdb->prefix;
		$sql    = '';
		$params = array();
		$event_id = absint( $event_id );

		if ( 'members' === $subject ) {
			$sql = "(m.status IS NULL OR m.status != 'merged')";
		}

		if ( $event_id > 0 ) {
			$event_scope = self::event_scope_sql( $subject, $event_id );
			if ( $event_scope['sql'] ) {
				$sql      = $sql ? $sql . ' AND ' . $event_scope['sql'] : $event_scope['sql'];
				$params   = array_merge( $params, $event_scope['params'] );
			}
		}

		if ( ! Remember_Report_Catalog::is_attendees_only() ) {
			return array( 'sql' => $sql, 'params' => $params );
		}

		$uid = get_current_user_id();
		$att = "EXISTS (
			SELECT 1 FROM {$p}remember_event_applications g
			INNER JOIN {$p}remember_event_applications t ON g.event_id = t.event_id
			WHERE g.member_id = %d AND g.status = 'accepted'
			AND t.status = 'accepted' AND t.member_id != %d AND t.member_id = {T}
		)";

		if ( 'members' === $subject ) {
			$extra    = str_replace( '{T}', 'm.member_id', $att );
			$sql      = $sql ? $sql . ' AND ' . $extra : $extra;
			$params[] = $uid;
			$params[] = $uid;
		} elseif ( 'applications' === $subject ) {
			$extra    = str_replace( '{T}', 'a.member_id', $att );
			$sql      = $sql ? $sql . ' AND ' . $extra : $extra;
			$params[] = $uid;
			$params[] = $uid;
		} elseif ( 'payments' === $subject ) {
			$extra    = str_replace( '{T}', 'pay.member_id', $att );
			$sql      = $sql ? $sql . ' AND ' . $extra : $extra;
			$params[] = $uid;
			$params[] = $uid;
		} elseif ( 'vetting' === $subject ) {
			$extra    = str_replace( '{T}', 'v.member_id', $att );
			$sql      = $sql ? $sql . ' AND ' . $extra : $extra;
			$params[] = $uid;
			$params[] = $uid;
		} elseif ( 'events' === $subject ) {
			$sql      = "e.event_id IN (SELECT DISTINCT event_id FROM {$p}remember_event_applications WHERE member_id = %d AND status = 'accepted')";
			$params[] = $uid;
		}

		return array( 'sql' => $sql, 'params' => $params );
	}

	/**
	 * Event grain for a subject.
	 *
	 * Members and vetting: accepted, current applications.
	 * Applications and payments: that event, any application status.
	 *
	 * @param string $subject  Subject.
	 * @param int    $event_id Event.
	 * @return array{sql:string,params:array}
	 */
	private static function event_scope_sql( $subject, $event_id ) {
		global $wpdb;
		$p        = $wpdb->prefix;
		$event_id = absint( $event_id );
		$accepted = "ea.status = 'accepted' AND (ea.superseded_at IS NULL OR ea.superseded_at = '0000-00-00 00:00:00')";

		if ( 'members' === $subject ) {
			return array(
				'sql'    => "EXISTS (SELECT 1 FROM {$p}remember_event_applications ea WHERE ea.member_id = m.member_id AND ea.event_id = %d AND {$accepted})",
				'params' => array( $event_id ),
			);
		}
		if ( 'vetting' === $subject ) {
			return array(
				'sql'    => "EXISTS (SELECT 1 FROM {$p}remember_event_applications ea WHERE ea.member_id = v.member_id AND ea.event_id = %d AND {$accepted})",
				'params' => array( $event_id ),
			);
		}
		if ( 'applications' === $subject ) {
			return array(
				'sql'    => 'a.event_id = %d',
				'params' => array( $event_id ),
			);
		}
		if ( 'payments' === $subject ) {
			return array(
				'sql'    => "EXISTS (SELECT 1 FROM {$p}remember_event_applications ea WHERE ea.application_id = pay.event_application_id AND ea.event_id = %d)",
				'params' => array( $event_id ),
			);
		}
		if ( 'events' === $subject ) {
			return array(
				'sql'    => 'e.event_id = %d',
				'params' => array( $event_id ),
			);
		}
		return array( 'sql' => '', 'params' => array() );
	}

	/**
	 * One filter to SQL.
	 *
	 * @param mixed $filter Filter.
	 * @param array $fields Catalog.
	 * @return array{sql:string,params:array}|null|\WP_Error
	 */
	private static function filter_clause( $filter, $fields ) {
		global $wpdb;
		if ( ! is_array( $filter ) || empty( $filter['field'] ) ) {
			return null;
		}
		$fid = (string) $filter['field'];
		if ( ! isset( $fields[ $fid ] ) ) {
			return null;
		}
		$op  = isset( $filter['op'] ) ? sanitize_key( $filter['op'] ) : 'eq';
		$sql = $fields[ $fid ]['sql'];
		$val = isset( $filter['value'] ) ? $filter['value'] : '';
		$list_field = ! empty( $fields[ $fid ]['list'] );

		if ( 'empty' === $op ) {
			if ( $list_field ) {
				return array( 'sql' => "({$sql} IS NULL OR {$sql} = '' OR {$sql} = '[]')", 'params' => array() );
			}
			return array( 'sql' => "({$sql} IS NULL OR {$sql} = '')", 'params' => array() );
		}
		if ( 'not_empty' === $op ) {
			if ( $list_field ) {
				return array( 'sql' => "({$sql} IS NOT NULL AND {$sql} != '' AND {$sql} != '[]')", 'params' => array() );
			}
			return array( 'sql' => "({$sql} IS NOT NULL AND {$sql} != '')", 'params' => array() );
		}
		if ( $list_field && in_array( $op, array( 'eq', 'neq', 'in', 'not_in' ), true ) ) {
			$keys = self::choice_keys( $val, $fields[ $fid ] );
			if ( empty( $keys ) ) {
				return null;
			}
			$match  = isset( $fields[ $fid ]['list_match'] ) ? (string) $fields[ $fid ]['list_match'] : 'json';
			$ors    = array();
			$params = array();
			if ( 'csv' === $match ) {
				$csv = "REPLACE({$sql}, ', ', ',')";
				foreach ( $keys as $key ) {
					$ors[]    = "FIND_IN_SET(%s, {$csv}) > 0";
					$params[] = $key;
				}
			} else {
				foreach ( $keys as $key ) {
					$ors[]    = "{$sql} LIKE %s";
					$params[] = '%"' . $wpdb->esc_like( $key ) . '"%';
				}
			}
			$clause = '(' . implode( ' OR ', $ors ) . ')';
			if ( in_array( $op, array( 'neq', 'not_in' ), true ) ) {
				$blank = 'csv' === $match
					? "({$sql} IS NULL OR {$sql} = '')"
					: "({$sql} IS NULL OR {$sql} = '' OR {$sql} = '[]')";
				$clause = "(NOT {$clause} OR {$blank})";
			}
			return array( 'sql' => $clause, 'params' => $params );
		}
		if ( 'in' === $op ) {
			$list = self::choice_keys( $val, $fields[ $fid ] );
			if ( empty( $list ) ) {
				$list = is_array( $val ) ? $val : explode( ',', (string) $val );
				$list = array_values( array_filter( array_map( 'strval', $list ), 'strlen' ) );
				$list = array_slice( $list, 0, 50 );
			}
			if ( empty( $list ) ) {
				return null;
			}
			$ph = implode( ',', array_fill( 0, count( $list ), '%s' ) );
			return array( 'sql' => "{$sql} IN ({$ph})", 'params' => $list );
		}
		if ( 'contains' === $op ) {
			$like = '%' . $wpdb->esc_like( (string) $val ) . '%';
			return array( 'sql' => "{$sql} LIKE %s", 'params' => array( $like ) );
		}
		if ( 'between' === $op ) {
			$a = is_array( $val ) && isset( $val[0] ) ? (string) $val[0] : ( isset( $filter['value_from'] ) ? (string) $filter['value_from'] : '' );
			$b = is_array( $val ) && isset( $val[1] ) ? (string) $val[1] : ( isset( $filter['value_to'] ) ? (string) $filter['value_to'] : '' );
			if ( '' === $a || '' === $b ) {
				return null;
			}
			return array( 'sql' => "{$sql} BETWEEN %s AND %s", 'params' => array( $a, $b ) );
		}
		$map = array(
			'eq'  => '=',
			'neq' => '!=',
			'gt'  => '>',
			'gte' => '>=',
			'lt'  => '<',
			'lte' => '<=',
		);
		if ( ! isset( $map[ $op ] ) ) {
			return null;
		}
		return array( 'sql' => "{$sql} {$map[ $op ]} %s", 'params' => array( (string) $val ) );
	}

	/**
	 * Keep submitted choice keys that exist on the field.
	 *
	 * @param mixed $val   Raw value.
	 * @param array $field Catalog field.
	 * @return string[]
	 */
	private static function choice_keys( $val, $field ) {
		$list = is_array( $val ) ? $val : explode( ',', (string) $val );
		$list = array_values( array_filter( array_map( 'strval', $list ), 'strlen' ) );
		$list = array_slice( $list, 0, 50 );
		if ( empty( $field['options'] ) || ! is_array( $field['options'] ) ) {
			return $list;
		}
		$allowed = array();
		foreach ( $field['options'] as $opt ) {
			if ( is_array( $opt ) ) {
				$id = isset( $opt['id'] ) ? $opt['id'] : ( isset( $opt['key'] ) ? $opt['key'] : '' );
			} elseif ( is_object( $opt ) ) {
				$id = isset( $opt->id ) ? $opt->id : ( isset( $opt->key ) ? $opt->key : '' );
			} else {
				$id = $opt;
			}
			$id = (string) $id;
			if ( '' !== $id ) {
				$allowed[] = $id;
			}
		}
		if ( empty( $allowed ) ) {
			return $list;
		}
		return array_values( array_intersect( $list, $allowed ) );
	}

	/**
	 * Keep field ids that exist in the catalog.
	 *
	 * @param mixed $ids    Raw.
	 * @param array $fields Catalog.
	 * @return string[]
	 */
	private static function sanitize_id_list( $ids, $fields ) {
		if ( ! is_array( $ids ) ) {
			return array();
		}
		$out = array();
		foreach ( $ids as $id ) {
			$id = (string) $id;
			if ( isset( $fields[ $id ] ) ) {
				$out[] = $id;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Safe SELECT alias.
	 *
	 * @param string $id Field id.
	 * @return string
	 */
	private static function alias( $id ) {
		$alias = preg_replace( '/[^a-zA-Z0-9_]/', '_', $id );
		return is_string( $alias ) && '' !== $alias ? $alias : 'col';
	}

	/**
	 * Aggregation SQL.
	 *
	 * @param string $fn  Function.
	 * @param string $sql Expression.
	 * @return string
	 */
	private static function agg_sql( $fn, $sql ) {
		if ( 'count' === $fn ) {
			return 'COUNT(*)';
		}
		if ( 'count_distinct' === $fn ) {
			return "COUNT(DISTINCT {$sql})";
		}
		if ( 'sum' === $fn ) {
			return "SUM({$sql})";
		}
		if ( 'min' === $fn ) {
			return "MIN({$sql})";
		}
		return "MAX({$sql})";
	}

	/**
	 * Aggregation header.
	 *
	 * @param string $fn    Function.
	 * @param string $label Field label.
	 * @return string
	 */
	private static function agg_label( $fn, $label ) {
		$map = array(
			'count'          => __( 'Count', 'remember' ),
			'count_distinct' => sprintf( __( 'Distinct %s', 'remember' ), $label ),
			'sum'            => sprintf( __( 'Sum of %s', 'remember' ), $label ),
			'min'            => sprintf( __( 'Min %s', 'remember' ), $label ),
			'max'            => sprintf( __( 'Max %s', 'remember' ), $label ),
		);
		return isset( $map[ $fn ] ) ? $map[ $fn ] : $label;
	}

	/**
	 * Turn a JSON list of option keys into a comma-separated cell.
	 *
	 * @param mixed $value Raw cell.
	 * @return string
	 */
	public static function format_list_cell( $value ) {
		$value = (string) $value;
		$trim  = trim( $value );
		if ( '' === $trim || '[' !== substr( $trim, 0, 1 ) ) {
			return $value;
		}
		$decoded = json_decode( $trim, true );
		if ( ! is_array( $decoded ) ) {
			return $value;
		}
		if ( $decoded && array_values( $decoded ) !== $decoded ) {
			return $value;
		}
		$keys = array();
		foreach ( $decoded as $item ) {
			if ( is_array( $item ) || is_object( $item ) ) {
				return $value;
			}
			$keys[] = (string) $item;
		}
		return implode( ',', $keys );
	}

	/**
	 * Formula-safe CSV cell.
	 *
	 * @param mixed $value Cell.
	 * @return string
	 */
	public static function csv_cell( $value ) {
		$value = (string) $value;
		if ( '' === $value ) {
			return $value;
		}
		$first = substr( $value, 0, 1 );
		if ( in_array( $first, array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $value;
		}
		return $value;
	}
}
