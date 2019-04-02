<?php
namespace um\core;


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;


if ( ! class_exists( 'um\core\Members' ) ) {


	/**
	 * Class Members
	 * @package um\core
	 */
	class Members {


		/**
		 * @var
		 */
		var $results;


		/**
		 * @var
		 */
		var $query_args;


		/**
		 * Members constructor.
		 */
		function __construct() {

			$this->core_search_fields = array(
				'user_login',
				'username',
				'display_name',
				'user_email',
			);

			add_action( 'template_redirect', array( &$this, 'access_members' ), 555 );
			add_action( 'um_pre_directory_shortcode', array( &$this, 'pre_directory_shortcode' ) );

			add_filter( 'pre_user_query', array( &$this, 'sortby_randomly' ), 10, 1 );
		}


		/**
		 * Members page allowed?
		 */
		function access_members() {
			if ( UM()->options()->get('members_page') == 0 && um_is_core_page( 'members' ) ) {
				um_redirect_home();
			}
		}


		/**
		 * Pre-display Member Directory
		 *
		 * @param $args
		 */
		function pre_directory_shortcode( $args ) {
			wp_localize_script( 'um_members', 'um_members_args_' . $args['form_id'], $args );
		}


		/**
		 * Sorting random
		 *
		 * @param $query
		 *
		 * @return mixed
		 */
		function sortby_randomly( $query ) {

			if ( 'random' == $query->query_vars["orderby"] ) {

				if ( um_is_session_started() === false ) {
					@session_start();
				}

				// Reset seed on load of initial
				if ( ! isset( $_REQUEST['members_page'] ) || $_REQUEST['members_page'] == 0 || $_REQUEST['members_page'] == 1 ) {
					if ( isset( $_SESSION['seed'] ) ) {
						unset( $_SESSION['seed'] );
					}
				}

				// Get seed from session variable if it exists
				$seed = false;
				if ( isset( $_SESSION['seed'] ) ) {
					$seed = $_SESSION['seed'];
				}

				// Set new seed if none exists
				if ( ! $seed ) {
					$seed = rand();
					$_SESSION['seed'] = $seed;
				}

				$query->query_orderby = 'ORDER by RAND(' . $seed . ')';
			}

			return $query;
		}


		/**
		 * Change mySQL meta query join attribute
		 * for search only by UM user meta fields
		 *
		 * @param array $sql Array containing the query's JOIN and WHERE clauses.
		 * @return mixed
		 */
		function change_meta_sql( $sql ) {

			if ( ! empty( $_POST['general_search'] ) ) {
				global $wpdb;

				preg_match(
					'/^(.*).meta_value LIKE \'%' . esc_attr( $_POST['general_search'] ) . '%\' [^\)]/im',
					$sql['where'],
					$join_matches
				);

				$meta_join_for_search = trim( $join_matches[1] );

				$sql['join'] = preg_replace(
					'/(' . $meta_join_for_search . ' ON \( ' . $wpdb->users . '\.ID = ' . $meta_join_for_search . '\.user_id )(\))/im',
					"$1 AND " . $meta_join_for_search . ".meta_key IN( '" . implode( "','", array_keys( UM()->builtin()->all_user_fields ) ) . "' ) $2",
					$sql['join']
				);
			}

			return $sql;
		}


		/**
		 * Tag conversion for member directory
		 *
		 * @param $string
		 * @param $array
		 *
		 * @return mixed
		 */
		function convert_tags( $string, $array ) {

			$search = array(
				'{total_users}',
			);

			$replace = array(
				$array['total_users'],
			);

			return str_replace( $search, $replace, stripcslashes( $string ) );
		}


		/**
		 * Get prepared search text for sql request
		 *
		 * @global object $wpdb
		 * @param string $text
		 * @param array $sql_fields
		 * @return string
		 */
		function prepare_search( $text, $sql_fields ) {
			$text = strtolower( trim( $text ) );

			$string = '';
			foreach ( $sql_fields as $field ) {
				if ( ! is_array( $field ) ) {
					$string .= 'LOWER(' . $field . ') LIKE %s OR ';
				} else {
					if ( strpos( $field['meta_key'], '%' ) !== false ) {
						$field['meta_key'] = str_replace( '%', '%%', $field['meta_key'] );
						$string .= '( ' . $field['table'] . '.meta_key LIKE \'' . $field['meta_key'] . '\' AND LOWER(' . $field['meta_value'] . ') LIKE %s ) OR ';
					} else {
						$string .= '( ' . $field['table'] . '.meta_key = \'' . $field['meta_key'] . '\' AND LOWER(' . $field['meta_value'] . ') LIKE %s ) OR ';
					}
				}
			}

			$string = substr( $string, 0, -4 );

			if ( UM()->options()->get( 'members_page' ) == 0 && um_is_core_page( 'members' ) ) {
				um_redirect_home();
			}

			if ( empty( $string ) ) {
				return '';
			}

			global $wpdb;
			return $wpdb->prepare( ' AND ( ' . $string . ' )', array_fill( 0, count( $sql_fields ), '%' . $text . '%' ) );
		}


		/**
		 * Render member's directory
		 * filters selectboxes
		 *
		 * @param string $filter
		 * @return string $filter
		 */
		function show_filter( $filter ) {
			$filter_types = apply_filters( 'um_members_directory_filter_types', array(
				'country'           => 'select',
				'gender'            => 'select',
				'languages'         => 'select',
				'role'              => 'select',
				'birth_date'        => 'slider',
				'last_login'        => 'datepicker',
				'user_registered'   => 'datepicker',
			) );

			$fields = UM()->builtin()->all_user_fields;
			//var_dump( $fields );
			if ( isset( $fields[ $filter ] ) ) {
				$attrs = $fields[ $filter ];
			} else {
				/**
				 * UM hook
				 *
				 * @type filter
				 * @title um_custom_search_field_{$filter}
				 * @description Custom search settings by $filter
				 * @input_vars
				 * [{"var":"$settings","type":"array","desc":"Search Settings"}]
				 * @change_log
				 * ["Since: 2.0"]
				 * @usage
				 * <?php add_filter( 'um_custom_search_field_{$filter}', 'function_name', 10, 1 ); ?>
				 * @example
				 * <?php
				 * add_filter( 'um_custom_search_field_{$filter}', 'my_custom_search_field', 10, 1 );
				 * function my_change_email_template_file( $settings ) {
				 *     // your code here
				 *     return $settings;
				 * }
				 * ?>
				 */
				$attrs = apply_filters( "um_custom_search_field_{$filter}", array() );
			}

			/**
			 * UM hook
			 *
			 * @type filter
			 * @title um_search_fields
			 * @description Filter all search fields
			 * @input_vars
			 * [{"var":"$settings","type":"array","desc":"Search Fields"}]
			 * @change_log
			 * ["Since: 2.0"]
			 * @usage
			 * <?php add_filter( 'um_search_fields', 'function_name', 10, 1 ); ?>
			 * @example
			 * <?php
			 * add_filter( 'um_search_fields', 'my_search_fields', 10, 1 );
			 * function my_search_fields( $settings ) {
			 *     // your code here
			 *     return $settings;
			 * }
			 * ?>
			 */
			$attrs = apply_filters( 'um_search_fields', $attrs );
			ob_start();

			switch ( $filter_types[ $filter ] ) {
				default: {

					do_action( "um_member_directory_filter_type_{$filter_types[ $filter ]}", $filter, $filter_types );

					break;
				}
				case 'select': {

					if ( isset( $attrs['metakey'] ) && strstr( $attrs['metakey'], 'role_' ) ) {
						$shortcode_roles = get_post_meta( UM()->shortcodes()->form_id, '_um_roles', true );
						$um_roles = UM()->roles()->get_roles( false );

						if ( ! empty( $shortcode_roles ) && is_array( $shortcode_roles ) ) {
							$attrs['options'] = array();

							foreach ( $um_roles as $key => $value ) {
								if ( in_array( $key, $shortcode_roles ) ) {
									$attrs['options'][ $key ] = $value;
								}
							}
						}
					}

					if ( ! empty( $attrs['custom_dropdown_options_source'] ) ) {
						$attrs['custom'] = true;
						$attrs['options'] = UM()->fields()->get_options_from_callback( $attrs, $attrs['type'] );
					}

					if ( isset( $attrs['label'] ) ) {
						$attrs['label'] = strip_tags( $attrs['label'] );
					}

					if ( isset( $attrs['options'] ) && is_array( $attrs['options'] ) ) {
						asort( $attrs['options'] );
					}

					$custom_dropdown = ! empty( $attrs['custom_dropdown_options_source'] ) ? ' data-um-ajax-source="' . $attrs['custom_dropdown_options_source'] . '"' : '';

					if ( ! empty( $attrs['options'] ) || ! empty( $custom_dropdown ) ) { ?>

						<select class="um-s1" id="<?php echo $filter; ?>" name="<?php echo $filter; ?>"
						        data-placeholder="<?php esc_attr_e( stripslashes( $attrs['label'] ), 'ultimate-member' ); ?>"
								<?php echo $custom_dropdown; ?>>

							<option></option>

							<?php if ( ! empty( $attrs['options'] ) ) {
								foreach ( $attrs['options'] as $k => $v ) {

									$v = stripslashes( $v );

									$opt = $v;

									if ( strstr( $filter, 'role_' ) ) {
										$opt = $k;
									}

									if ( isset( $attrs['custom'] ) ) {
										$opt = $k;
									} ?>

									<option value="<?php echo $opt; ?>" data-value_label="<?php esc_attr_e( $v, 'ultimate-member' ); ?>">
										<?php _e( $v, 'ultimate-member' ); ?>
									</option>

								<?php }
							} ?>

						</select>

					<?php }

					break;
				}
				case 'slider': {
					$range = $this->slider_filters_range( $filter );
					$placeholder = $this->slider_range_placeholder( $filter );

					if ( $range ) { ?>
						<input type="hidden" id="<?php echo $filter; ?>_min" name="<?php echo $filter; ?>[]" class="um_range_min" />
						<input type="hidden" id="<?php echo $filter; ?>_max" name="<?php echo $filter; ?>[]" class="um_range_max" />
						<div class="um-slider" data-field_name="<?php echo $filter; ?>" data-min="<?php echo $range[0] ?>" data-max="<?php echo $range[1] ?>"></div>
						<div class="um-slider-range" data-placeholder="<?php echo esc_attr( $placeholder ); ?>" data-label="<?php esc_attr_e( stripslashes( $attrs['label'] ), 'ultimate-member' ); ?>"></div>
					<?php }

					break;
				}
				case 'datepicker': {

					$range = $this->datepicker_filters_range( $filter );

					//var_dump( $range );
					//var_dump( date( 'Y-m-d', $range[0] ) );
					//var_dump( date( 'Y-m-d', $range[1] ) );

					if ( $range ) { ?>

						<input type="text" id="<?php echo $filter; ?>_from" name="<?php echo $filter; ?>_from" class="um-half-filter um-datepicker-filter"
						       placeholder="<?php esc_attr_e( sprintf( '%s From', stripslashes( $attrs['label'] ) ), 'ultimate-member' ); ?>"
						       data-date_min="<?php echo $range[0] ?>" data-date_max="<?php echo $range[1] ?>"
						       data-filter_name="<?php echo $filter; ?>" data-range="from" />
						<input type="text" id="<?php echo $filter; ?>_to" name="<?php echo $filter; ?>_to" class="um-half-filter um-datepicker-filter"
						       placeholder="<?php esc_attr_e( sprintf( '%s To', stripslashes( $attrs['label'] ) ), 'ultimate-member' ); ?>"
						       data-date_min="<?php echo $range[0] ?>" data-date_max="<?php echo $range[1] ?>"
						       data-filter_name="<?php echo $filter; ?>" data-range="to" />

					<?php }

					break;
				}
			}

			$filter = ob_get_clean();
			return $filter;
		}


		/**
		 * @param $filter
		 *
		 * @return mixed
		 */
		function slider_filters_range( $filter ) {


			switch ( $filter ) {

				default: {
					$range = apply_filters( "um_member_directory_filter_{$filter}_slider", false );

					break;
				}
				case 'birth_date': {
					global $wpdb;
					$meta = $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->usermeta} WHERE meta_key='birth_date' ORDER BY meta_value DESC" );

					if ( empty( $meta ) || count( $meta ) === 1 ) {
						$range = false;
					} elseif ( ! empty( $meta ) ) {
						$range = array( $this->borndate( strtotime( $meta[0] ) ), $this->borndate( strtotime( $meta[ count( $meta ) - 1 ] ) ) );
					}

					break;
				}

			}

			return $range;
		}



		/**
		 * @param $filter
		 *
		 * @return mixed
		 */
		function slider_range_placeholder( $filter ) {
			switch ( $filter ) {
				default: {
					$label = ucwords( str_replace(array('um_', '_'), array('', ' '), $filter) );
					$placeholder = apply_filters( "um_member_directory_filter_{$filter}_slider_range_placeholder", "$label: {min_range} - {max_range}" );
					break;
				}
				case 'birth_date': {
					$placeholder = __( 'Age: {min_range} - {max_range} years old', 'ultimate-member' );
					break;
				}
				case 'user_rating': {
					$placeholder = __( 'User Rating: {min_range} - {max_range} points', 'ultimate-member' );
					break;
				}
			}

			return $placeholder;
		}


		/**
		 * @param $filter
		 *
		 * @return mixed
		 */
		function datepicker_filters_range( $filter ) {
			global $wpdb;

			switch ( $filter ) {

				default: {
					$range = apply_filters( "um_member_directory_filter_{$filter}_datepicker", false );

					break;
				}
				case 'last_login': {
					$meta = $wpdb->get_col( "SELECT DISTINCT meta_value
						FROM {$wpdb->usermeta}
						WHERE meta_key='_um_last_login'
						ORDER BY meta_value DESC" );

					if ( empty( $meta ) || count( $meta ) === 1 ) {
						$range = false;
					} elseif ( ! empty( $meta ) ) {
						$range = array( min( $meta ), max( $meta ) );
					}

					break;
				}
				case 'user_registered': {
					$meta = $wpdb->get_col(
					"SELECT DISTINCT user_registered
						FROM {$wpdb->users}
						ORDER BY user_registered DESC"
					);

					if ( empty( $meta ) || count( $meta ) === 1 ) {
						$range = false;
					} elseif ( ! empty( $meta ) ) {
						$range = array( strtotime( min( $meta ) ), strtotime( max( $meta ) ) );
					}

					break;
				}

			}

			return $range;
		}


		/**
		 * @param $borndate
		 *
		 * @return false|string
		 */
		function borndate( $borndate ) {
			if ( date('m', $borndate) > date('m') || date('m', $borndate) == date('m') && date('d', $borndate ) > date('d')) {
				return (date('Y') - date('Y', $borndate ) - 1);
			}
			return (date('Y') - date('Y', $borndate));
		}


		/**
		 * Must have a profile photo
		 *
		 * @param $args
		 */
		function profile_photo_query( $args ) {
			if ( $args['has_profile_photo'] == 1 ) {
				$meta_query = array(
					'relation'  => 'OR',
					array(
						'key'       => 'synced_profile_photo', // addons
						'value'     => '',
						'compare'   => '!='
					),
					array(
						'key'       => 'profile_photo', // from upload form
						'value'     => '',
						'compare'   => '!='
					)
				);

				if ( UM()->options()->get( 'use_gravatars' ) ) {
					$meta_query[] = array(
						'key'       => 'synced_gravatar_hashed_id', // gravatar
						'value'     => '',
						'compare'   => '!='
					);
				}

				$this->query_args['meta_query'] = array_merge( $this->query_args['meta_query'], array( $meta_query ) );
			}
		}


		/**
		 * Must have a cover photo
		 *
		 * @param $args
		 */
		function cover_photo_query( $args ) {
			if ( $args['has_cover_photo'] == 1 ) {
				$this->query_args['meta_query'] = array_merge( $this->query_args['meta_query'], array( array(
					'key'       => 'cover_photo',
					'value'     => '',
					'compare'   => '!='
				) ) );
			}
		}


		/**
		 *
		 */
		function hide_not_approved() {
			if ( UM()->roles()->um_user_can( 'can_edit_everyone' )  ) {
				return;
			}

			$this->query_args['meta_query'] = array_merge( $this->query_args['meta_query'], array( array(
				'key'       => 'account_status',
				'value'     => 'approved',
				'compare'   => '='
			) ) );
		}


		/**
		 *
		 */
		function hide_by_role() {
			$roles = um_user( 'can_view_roles' );
			$roles = maybe_unserialize( $roles );

			if ( empty( $roles ) || ! UM()->roles()->um_user_can( 'can_view_all' ) ) {
				return;
			}

			if ( ! empty( $this->query_args['role__in'] ) ) {
				$this->query_args['role__in'] = is_array( $this->query_args['role__in'] ) ? $this->query_args['role__in'] : array( $this->query_args['role__in'] );
				$this->query_args['role__in'] = array_intersect( $this->query_args['role__in'], $roles );
			} else {
				$this->query_args['role__in'] = $roles;
			}
		}


		/**
		 *
		 */
		function hide_by_account_settings() {
			if ( ! UM()->options()->get( 'account_hide_in_directory' ) ) {
				return;
			}

			if ( UM()->roles()->um_user_can( 'can_edit_everyone' ) ) {
				return;
			}

			$meta_query = array(
				"relation"  => "OR",
				array(
					'key'       => 'hide_in_members',
					'value'     => '',
					'compare'   => 'NOT EXISTS'
				),
				array(
					"relation"  => "AND",
					array(
						'key'       => 'hide_in_members',
						'value'     => __( 'Yes', 'ultimate-member' ),
						'compare'   => 'NOT LIKE'
					),
					array(
						'key'       => 'hide_in_members',
						'value'     => 'Yes',
						'compare'   => 'NOT LIKE'
					),
				),
			);

			$this->query_args['meta_query'] = array_merge( $this->query_args['meta_query'], array( $meta_query ) );
		}


		/**
		 * @param $args
		 */
		function sorting_query( $args ) {
			// sort members by
			$this->query_args['order'] = 'ASC';
			$sortby = ! empty( $_POST['sorting'] ) ? $_POST['sorting'] : $args['sortby'];

			if ( $sortby == 'other' && $args['sortby_custom'] ) {

				$this->query_args['meta_key'] = $args['sortby_custom'];
				$this->query_args['orderby'] = 'meta_value, display_name';

			} elseif ( in_array( $sortby, array( 'last_name', 'first_name' ) ) ) {

				$this->query_args['meta_key'] = $sortby;
				$this->query_args['orderby'] = 'meta_value';

			} elseif ( $sortby == 'last_login' ) {

				$this->query_args['meta_key'] = '_um_last_login';
				$this->query_args['orderby'] = 'meta_value_num';
				$this->query_args['order'] = 'desc';

			} else {

				if ( strstr( $sortby, '_desc' ) ) {
					$sortby = str_replace( '_desc','', $sortby );
					$order = 'DESC';
				}

				if ( strstr( $sortby, '_asc' ) ) {
					$sortby = str_replace('_asc','',$sortby);
					$order = 'ASC';
				}

				$this->query_args['orderby'] = $sortby;
				if ( isset( $order ) ) {
					$this->query_args['order'] = $order;
				}
			}


			/**
			 * UM hook
			 *
			 * @type filter
			 * @title um_modify_sortby_parameter
			 * @description Change query sort by attributes for search at Members Directory
			 * @input_vars
			 * [{"var":"$query_args","type":"array","desc":"Query Arguments"},
			 * {"var":"$sortby","type":"string","desc":"Sort by"}]
			 * @change_log
			 * ["Since: 2.0"]
			 * @usage
			 * <?php add_filter( 'um_modify_sortby_parameter', 'function_name', 10, 2 ); ?>
			 * @example
			 * <?php
			 * add_filter( 'um_modify_sortby_parameter', 'my_modify_sortby_parameter', 10, 2 );
			 * function my_modify_sortby_parameter( $query_args, $sortby ) {
			 *     // your code here
			 *     return $query_args;
			 * }
			 * ?>
			 */
			$this->query_args = apply_filters( 'um_modify_sortby_parameter', $this->query_args, $sortby );
		}


		function general_search() {
			//general search
			if ( ! empty( $_POST['general_search'] ) ) {
				$meta_query = array(
					array(
						'value'     => trim( $_POST['general_search'] ),
						'compare'   => '=',
					),
					array(
						'value'     => trim( $_POST['general_search'] ),
						'compare'   => 'LIKE',
					),
					array(
						'value'     => trim( serialize( strval( $_POST['general_search'] ) ) ),
						'compare'   => 'LIKE',
					),
					'relation' => 'OR',
				);

				$this->query_args['meta_query'] = array_merge( $this->query_args['meta_query'], array( $meta_query ) );
			}
		}


		/**
		 *
		 */
		function filters( $args ) {
			//filters
			$query = $_POST;
			if ( ! empty( $args['search_filters'] ) ) {
				parse_str( $args['search_filters'], $search_filters );
				if ( ! empty( $search_filters ) && is_array( $search_filters ) ) {
					$query = array_merge( $search_filters, $query );
				}
			}

			unset( $query['sorting'] );
			unset( $query['page'] );
			unset( $query['args'] );
			unset( $query['members_page'] );
			unset( $query['general_search'] );
			unset( $query['action'] );
			unset( $query['nonce'] );
			unset( $query['referrer_url'] );
			unset( $query['is_filters'] );

			if ( ! empty( $query ) && is_array( $query ) ) {
				foreach ( $query as $field => $value ) {

					//$filter_data = UM()->members()->prepare_filter( $field );

					if ( $value && $field != 'um_search' && $field != 'page_id' ) {

						if ( strstr( $field, 'role_' ) ) {
							$field = 'role';
						}

						if ( ! in_array( $field, UM()->members()->core_search_fields ) ) {

							if ( 'role' == $field ) {

								if ( ! empty( $this->query_args['role__in'] ) ) {
									$value = array_map('strtolower', $value);

									$this->query_args['role__in'] = is_array( $this->query_args['role__in'] ) ? $this->query_args['role__in'] : array( $this->query_args['role__in'] );
									$default_role = array_intersect( $this->query_args['role__in'], $value );
									$um_role = array_diff( $value, $default_role );

									foreach ($um_role as $key => &$val) {
										$val = 'um_' . str_replace(' ', '-', $val);
									}
									$this->query_args['role__in'] = array_merge( $default_role, $um_role );
								} else {
									$this->query_args['role__in'] = $value;
								};

							} elseif ( 'birth_date' == $field ) {
								$from_date = date( 'Y-m-d', mktime( 0,0,0, 1, 1, date('Y', time() - ($query['birth_date'][0] -1)*YEAR_IN_SECONDS ) ) );
								$to_date = date( 'Y-m-d', mktime( 0,0,0, 1, 1, date('Y', time() - ($query['birth_date'][1] +1)*YEAR_IN_SECONDS ) ) );

								$meta_query = array(
									array(
										'key'       => 'birth_date',
										'value'     => array( $to_date, $from_date ),
										'compare'   => 'BETWEEN',
										'type'      => 'DATE',
										'inclusive'	=> true,
									)
								);

								$this->query_args['meta_query'] = array_merge( $this->query_args['meta_query'], array( $meta_query ) );

							} elseif ( 'user_registered' == $field ) {

								$offset = 0;

								if( isset( $query['gmt_offset'] ) ) {
									$offset = (int)$query['gmt_offset'];
								}

								if( isset( $query['user_registered']['from'] ) ) {
									$from_date = date( 'Y-m-d', strtotime( date( 'Y-m-d H:s:i', $query['user_registered']['from'] ) . "+$offset hours" ) );
								}

								if( isset( $query['user_registered']['to'] ) ) {
									$to_date = date( 'Y-m-d', strtotime( date( 'Y-m-d H:s:i', $query['user_registered']['to'] ) . "+$offset hours" ) );
								}

								$date_query = array(
									array(
										'column'	=> 'user_registered',
										'before'	=> $to_date,
										'after'		=> $from_date,
										'inclusive'	=> true,
									),
								 );

								$this->query_args['date_query'] = array( $date_query );

							} elseif ( 'last_login' == $field ) {

								$meta_query = array();
								$offset		= 0;

								if( isset( $query['gmt_offset'] ) ) {
									$offset = (int)$query['gmt_offset'];
								}

								if( isset( $query['last_login']['from'] ) and isset( $query['last_login']['to'] ) ) {
									$from_date = (int)$query['last_login']['from'] + ( $offset * 60 * 60 ); // client time zone offset
									$to_date   = (int)$query['last_login']['to'] + ( $offset * 60 * 60 ) + (24 * 60 * 60 - 1); // time 23:59

									$meta_query[] = array(
										'key'       => '_um_last_login',
										'value'     =>  array( $from_date, $to_date ),
										'compare'   => 'BETWEEN',
									);

								} else {

									if( isset( $query['last_login']['from'] ) ) {
										$from_date = (int)$query['last_login']['from'] + ( $offset * 60 * 60 );

										$meta_query[] = array(
											'key'       => '_um_last_login',
											'value'     =>  $from_date,
											'compare'   => '>',
										);
									}

									if( isset( $query['last_login']['to'] ) ) {
										$to_date = (int)$query['last_login']['to'] + ( $offset * 60 * 60 ) + (24 * 60 * 60 - 1);

										$meta_query[] = array(
											'key'       => '_um_last_login',
											'value'     =>  $to_date,
											'compare'   => '<',
										);
									}
								}

								$this->query_args['meta_query'] = array_merge( $this->query_args['meta_query'], array( $meta_query ) );

							} elseif( 'gmt_offset' == $field ) {
								continue;
							} else {

								if ( is_array( $value ) ) {
									$field_query = array( 'relation' => 'OR' );

									foreach ( $value as $single_val ) {
										$field_query = array_merge( $field_query, array(
											array(
												'key' => $field,
												'value' => trim( $single_val ),
												'compare' => '=',
											)
										) );

										$types = apply_filters( 'um_search_field_types', array(
											'multiselect',
											'radio',
											'checkbox'
										) );

										//if ( in_array( $filter_data['attrs']['type'], $types ) ) {

											$arr_meta_query = array(
												array(
													'key' => $field,
													'value' => serialize( strval( trim( $single_val ) ) ),
													'compare' => 'LIKE',
												),
												array(
													'key' => $field,
													'value' => '"' . trim( $single_val ) . '"',
													'compare' => 'LIKE',
												)
											);

											if ( is_numeric( $single_val ) ) {

												$arr_meta_query[ ] = array(
													'key' => $field,
													'value' => serialize( intval( trim( $single_val ) ) ),
													'compare' => 'LIKE',
												);

											}

											$field_query = array_merge( $field_query, $arr_meta_query );
										//}
									}
								} else {
									$field_query = array(
										array(
											'key' => $field,
											'value' => trim( $value ),
											'compare' => '=',
										),
										'relation' => 'OR',
									);

									$types = apply_filters( 'um_search_field_types', array(
										'multiselect',
										'radio',
										'checkbox'
									) );

									//if ( in_array( $filter_data['attrs']['type'], $types ) ) {

										$arr_meta_query = array(
											array(
												'key' => $field,
												'value' => serialize( strval( trim( $value ) ) ),
												'compare' => 'LIKE',
											),
											array(
												'key' => $field,
												'value' => '"' . trim( $value ) . '"',
												'compare' => 'LIKE',
											)
										);

										if ( is_numeric( $value ) ) {

											$arr_meta_query[ ] = array(
												'key' => $field,
												'value' => serialize( intval( trim( $value ) ) ),
												'compare' => 'LIKE',
											);

										}

										$field_query = array_merge( $field_query, $arr_meta_query );
									//}
								}

								/**
								 * UM hook
								 *
								 * @type filter
								 * @title um_query_args_{$field}__filter
								 * @description Change field's query for search at Members Directory
								 * @input_vars
								 * [{"var":"$field_query","type":"array","desc":"Field query"}]
								 * @change_log
								 * ["Since: 2.0"]
								 * @usage
								 * <?php add_filter( 'um_query_args_{$field}__filter', 'function_name', 10, 1 ); ?>
								 * @example
								 * <?php
								 * add_filter( 'um_query_args_{$field}__filter', 'my_query_args_filter', 10, 1 );
								 * function my_query_args_filter( $field_query ) {
								 *     // your code here
								 *     return $field_query;
								 * }
								 * ?>
								 */
								$field_query = apply_filters( "um_query_args_{$field}__filter", $field_query );
								$this->query_args['meta_query'] = array_merge( $this->query_args['meta_query'], array( $field_query ) );
							}

						}

					}

				}
			}

			$query = UM()->permalinks()->get_query_array();
			$arr_columns = array();

			foreach ( UM()->members()->core_search_fields as $key ) {
				if ( ! empty( $query[ $key ]  ) ) {
					$arr_columns[] = $key;
					$this->query_args['search'] = '*' . $query[ $key ] .'*';
				}
			}

			if ( ! empty( $arr_columns ) ) {
				$this->query_args['search_columns'] = $arr_columns;
			}
		}


		/**
		 * get AJAX results members
		 */
		function ajax_get_members() {
			UM()->check_ajax_nonce();

			global $wpdb;

			$args = ! empty( $_POST['args'] ) ? $_POST['args'] : array();
			$args['page'] = ! empty( $_POST['page'] ) ? $_POST['page'] : ( isset( $args['page'] ) ? $args['page'] : 1 );

			/**
			 * @var $profiles_per_page
			 * @var $profiles_per_page_mobile
			 * @var $header
			 * @var $header_single
			 * @var $has_profile_photo
			 * @var $has_cover_photo
			 */
			extract( $args );

			$data_args = array(
				'show_count' => false
			);
			if ( ! empty( $_POST['general_search'] ) || ! empty( $_POST['is_filters'] ) || ! empty( $args['search_filters'] ) ) {
				$data_args['show_count'] = true;
			}

			$this->query_args = array(
				'fields' => 'ids',
				'number' => 0,
				'meta_query' => array(
					'relation' => 'AND'
				),
			);

			// add roles to appear in directory
			if ( ! empty( $args['roles'] ) ) {
				//since WP4.4 use 'role__in' argument
				$this->query_args['role__in'] = $args['roles'];
			}

			$this->profile_photo_query( $args );

			$this->cover_photo_query( $args );

			// show specific usernames
			if ( ! empty( $args['show_these_users'] ) && is_array( $args['show_these_users'] ) ) {
				foreach ( $args['show_these_users'] as $username ) {
					if ( false !== ( $exists_id = username_exists( $username ) ) ) {
						$users_array[] = $exists_id;
					}
				}

				$this->query_args['include'] = $users_array;
			}

			$this->hide_not_approved();

			$this->hide_by_role();

			$this->hide_by_account_settings();

			$this->sorting_query( $args );

			$this->general_search();

			$this->filters( $args );

			/**
			 * UM hook
			 *
			 * @type filter
			 * @title um_prepare_user_query_args
			 * @description Extend member directory query arguments
			 * @input_vars
			 * [{"var":"$query_args","type":"array","desc":"Members Query Arguments"},
			 * {"var":"$directory_settings","type":"array","desc":"Member Directory Settings"}]
			 * @change_log
			 * ["Since: 2.0"]
			 * @usage
			 * <?php add_filter( 'um_prepare_user_query_args', 'function_name', 10, 2 ); ?>
			 * @example
			 * <?php
			 * add_filter( 'um_prepare_user_query_args', 'my_prepare_user_query_args', 10, 2 );
			 * function my_prepare_user_query_args( $query_args, $directory_settings ) {
			 *     // your code here
			 *     return $query_args;
			 * }
			 * ?>
			 */
			$this->query_args = apply_filters( 'um_prepare_user_query_args', $this->query_args, $args );

			//unset empty meta_query attribute
			if ( isset( $this->query_args['meta_query']['relation'] ) && count( $this->query_args['meta_query'] ) == 1 ) {
				unset( $this->query_args['meta_query'] );
			}

			// Prepare for BIG SELECT query
			$wpdb->query( 'SET SQL_BIG_SELECTS=1' );

			// number of profiles for mobile
			$profiles_per_page = $args['profiles_per_page'];
			if ( UM()->mobile()->isMobile() && isset( $args['profiles_per_page_mobile'] ) ) {
				$profiles_per_page = $args['profiles_per_page_mobile'];
			}

			$this->query_args['number'] = isset( $args['number'] ) ? $args['number'] : $profiles_per_page;
			$this->query_args['number'] = ( ! empty( $args['max_users'] ) && $args['max_users'] <= $profiles_per_page ) ? $args['max_users'] : $this->query_args['number'];

			$current_page = isset( $args['page'] ) ? $args['page'] : 1;
			$this->query_args['paged'] = $current_page;

			if ( ! UM()->roles()->um_user_can( 'can_view_all' ) && is_user_logged_in() ) {
				$this->query_args = array();
			}

			/**
			 * UM hook
			 *
			 * @type action
			 * @title um_user_before_query
			 * @description Action before users query on member directory
			 * @input_vars
			 * [{"var":"$query_args","type":"array","desc":"Query arguments"}]
			 * @change_log
			 * ["Since: 2.0"]
			 * @usage add_action( 'um_user_before_query', 'function_name', 10, 1 );
			 * @example
			 * <?php
			 * add_action( 'um_user_before_query', 'my_user_before_query', 10, 1 );
			 * function my_user_before_query( $query_args ) {
			 *     // your code here
			 * }
			 * ?>
			 */
			do_action( 'um_user_before_query', $this->query_args );

			add_filter( 'get_meta_sql', array( &$this, 'change_meta_sql' ), 10 );

			$users = new \WP_User_Query( $this->query_args );

			remove_filter( 'get_meta_sql', array( &$this, 'change_meta_sql' ), 10 );

			/**
			 * UM hook
			 *
			 * @type action
			 * @title um_user_after_query
			 * @description Action before users query on member directory
			 * @input_vars
			 * [{"var":"$query_args","type":"array","desc":"Query arguments"},
			 * {"var":"$users","type":"array","desc":"Users"}]
			 * @change_log
			 * ["Since: 2.0"]
			 * @usage add_action( 'um_user_after_query', 'function_name', 10, 2 );
			 * @example
			 * <?php
			 * add_action( 'um_user_after_query', 'my_user_after_query', 10, 2 );
			 * function my_user_after_query( $query_args, $users ) {
			 *     // your code here
			 * }
			 * ?>
			 */
			do_action( 'um_user_after_query', $this->query_args, $users );

			$user_ids = ! empty( $users->results ) ? array_unique( $users->results ) : array();
			$total_users = ( ! empty( $max_users ) && $max_users <= $users->total_users ) ? $max_users : $users->total_users;
			$total_pages = ceil( $total_users / $profiles_per_page );

			if ( ! empty( $total_pages ) ) {
				$index1 = 0 - ( $current_page - 2 ) + 1;
				$to = $current_page + 2;
				if ( $index1 > 0 ) {
					$to += $index1;
				}

				$index2 = $total_pages - ( $current_page + 2 );
				$from = $current_page - 2;
				if ( $index2 < 0 ) {
					$from += $index2;
				}

				$pages_to_show = range(
					( $from > 0 ) ? $from : 1,
					( $to <= $total_pages ) ? $to : $total_pages
				);
			}

			/**
			 * UM hook
			 *
			 * @type filter
			 * @title um_prepare_user_results_array
			 * @description Extend member directory query result
			 * @input_vars
			 * [{"var":"$result","type":"array","desc":"Members Query Result"}]
			 * @change_log
			 * ["Since: 2.0"]
			 * @usage
			 * <?php add_filter( 'um_prepare_user_results_array', 'function_name', 10, 1 ); ?>
			 * @example
			 * <?php
			 * add_filter( 'um_prepare_user_results_array', 'my_prepare_user_results', 10, 1 );
			 * function my_prepare_user_results( $user_ids ) {
			 *     // your code here
			 *     return $user_ids;
			 * }
			 * ?>
			 */
			$users = apply_filters( 'um_prepare_user_results_array', $user_ids );

			$sizes = UM()->options()->get( 'cover_thumb_sizes' );
			$cover_size = UM()->mobile()->isTablet() ? $sizes[1] : $sizes[0];

			$users_data = array();
			foreach ( $users as $user_id ) {
				um_fetch_user( $user_id );

				$actions = array();
				if ( UM()->roles()->um_current_user_can( 'edit', $user_id ) || UM()->roles()->um_user_can( 'can_edit_everyone' ) ) {
					$actions[] = array(
						'title'         => __( 'Edit profile','ultimate-member' ),
						'url'           => um_edit_profile_url(),
						'wrapper_class' => 'um-members-edit-btn',
						'class'         => 'um-edit-profile-btn um-button um-alt',
					);
				}
				
				// Replace hook 'um_members_just_after_name'
				try {
					ob_start();
					do_action( 'um_members_just_after_name', $user_id, $args );
					$hook_just_after_name = ob_get_clean();					
				} catch ( Exception $exc ) {
					$hook_just_after_name = '';
				}
				
				// Replace hook 'um_members_after_user_name'
				try {					
					ob_start();
					do_action( 'um_members_after_user_name', $user_id, $args );
					$hook_after_user_name = ob_get_clean();
				} catch ( Exception $exc ) {
					$hook_after_user_name = '';
				}				

				$data_array = array(
					'id'                    => $user_id,
					'role'                  => um_user( 'role' ),
					'account_status'        => um_user( 'account_status' ),
					'account_status_name'   => um_user( 'account_status_name' ),
					'cover_photo'           => um_user( 'cover_photo', $cover_size ),
					'display_name'          => um_user( 'display_name' ),
					'profile_url'           => um_user_profile_url(),
					'can_edit'              => ( UM()->roles()->um_current_user_can( 'edit', $user_id ) || UM()->roles()->um_user_can( 'can_edit_everyone' ) ) ? true : false,
					'edit_profile_url'      => um_edit_profile_url(),
					'avatar'                => get_avatar( $user_id, str_replace( 'px', '', UM()->options()->get( 'profile_photosize' ) ) ),
					'display_name_html'     => um_user( 'display_name', 'html' ),
					'social_urls'           => UM()->fields()->show_social_urls( false ),
					'actions'               => $actions,
					'hook_just_after_name'  => $hook_just_after_name,
					'hook_after_user_name'  => $hook_after_user_name,
				);

				if ( $args['show_tagline'] && is_array( $args['tagline_fields'] ) ) {
					foreach ( $args['tagline_fields'] as $key ) {
						if ( $key && um_filtered_value( $key ) ) {
							$data_array[ $key ] = um_filtered_value( $key );
						}
					}
				}

				if ( $args['show_userinfo'] ) {
					foreach ( $args['reveal_fields'] as $key ) {
						if ( $key && um_filtered_value( $key ) ) {
							$label = strtr( UM()->fields()->get_label( $key ), array(
									' (Dropdown)' => '',
									' (Radio)' => ''
							) );
							$data_array[ "label_{$key}" ] = $label;
							$data_array[ $key ] = um_filtered_value( $key );
						}
					}
				}

				$users_data[] = apply_filters( 'um_ajax_get_members_data', $data_array, $user_id );
				um_reset_user_clean();
			}

			um_reset_user();

			$pagination_data = array(
				'pages_to_show' => ( ! empty( $pages_to_show ) && count( $pages_to_show ) > 1 ) ? array_values( $pages_to_show ) : array(),
				'current_page'  => $args['page'],
				'total_pages'   => $total_pages,
				'total_users'   => $total_users,
			);

			$pagination_data['header'] = $this->convert_tags( $args['header'], $pagination_data );
			$pagination_data['header_single'] = $this->convert_tags( $args['header_single'], $pagination_data );

			wp_send_json_success( array( 'users' => $users_data, 'pagination' => $pagination_data, 'args' => $data_args ) );
		}


		/**
		 * @return array
		 */
		function get_sorting_fields() {
			$sort_options = apply_filters( 'um_members_directory_sort_dropdown_options', array(
				'user_registered_desc'  => __( 'New Users First', 'ultimate-member' ),
				'user_registered_asc'   => __( 'Old Users First', 'ultimate-member' ),
				'username_asc'          => __( 'Username', 'ultimate-member' ),
				'first_name'            => __( 'First Name', 'ultimate-member' ),
				'last_name'             => __( 'Last Name', 'ultimate-member' ),
				'display_name'          => __( 'Display Name', 'ultimate-member' ),
				'last_login'            => __( 'Last Login', 'ultimate-member' ),
			) );

			asort( $sort_options );

			return $sort_options;
		}


		/**
		 * @return array
		 */
		function get_filters_fields() {
			$filters = apply_filters( 'um_members_directory_filter_dropdown_options', array(
				'country'           => __( 'Country', 'ultimate-member' ),
				'gender'            => __( 'Gender', 'ultimate-member' ),
				'languages'         => __( 'Languages', 'ultimate-member' ),
				'role'              => __( 'Roles', 'ultimate-member' ),
				'birth_date'        => __( 'Age', 'ultimate-member' ),
				'last_login'        => __( 'Last Login', 'ultimate-member' ),
				'user_registered'   => __( 'User Registered', 'ultimate-member' ),
			) );

			ksort( $filters );

			return $filters;
		}
	}
}
