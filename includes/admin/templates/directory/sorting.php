<?php if ( ! defined( 'ABSPATH' ) ) exit;

$sorting_fields = UM()->members()->get_sorting_fields();

$post_id = get_the_ID();
$_um_sorting_fields = get_post_meta( $post_id, '_um_sorting_fields', true );
$_um_sorting_fields = empty( $_um_sorting_fields ) ? array() : $_um_sorting_fields; ?>

<div class="um-admin-metabox">

	<?php
	/**
	 * UM hook
	 *
	 * @type filter
	 * @title um_admin_directory_sort_users_select
	 * @description Extend Sort Types for Member Directory
	 * @input_vars
	 * [{"var":"$sort_types","type":"array","desc":"Sort Types"}]
	 * @change_log
	 * ["Since: 2.0"]
	 * @usage add_filter( 'um_admin_directory_sort_users_select', 'function_name', 10, 1 );
	 * @example
	 * <?php
	 * add_filter( 'um_admin_directory_sort_users_select', 'my_directory_sort_users_select', 10, 1 );
	 * function my_directory_sort_users_select( $sort_types ) {
	 *     // your code here
	 *     return $sort_types;
	 * }
	 * ?>
	 */
	$sort_options = apply_filters( 'um_admin_directory_sort_users_select', array(
		'user_registered_desc'  => __( 'New Users First', 'ultimate-member' ),
		'user_registered_asc'   => __( 'Old Users First', 'ultimate-member' ),
		'username'              => __( 'Username', 'ultimate-member' ),
		'last_login'            => __( 'Last Login', 'ultimate-member' ),
		'display_name'          => __( 'Display Name', 'ultimate-member' ),
		'first_name'            => __( 'First Name', 'ultimate-member' ),
		'last_name'             => __( 'Last Name', 'ultimate-member' ),
		'random'                => __( 'Random', 'ultimate-member' ),
		'other'                 => __( 'Other (Custom Field)', 'ultimate-member' ),
	) );

	asort( $sort_options );

	$fields = array(
		array(
			'id'            => '_um_sortby',
			'type'          => 'select',
			'label'         => __( 'Default sort users by', 'ultimate-member' ),
			'tooltip'       => __( 'Default sorting users by a specific parameter in the directory', 'ultimate-member' ),
			'options'       => $sort_options,
			'value'         => UM()->query()->get_meta_value( '_um_sortby' ),
			'conditional'   => array( '_um_sorting_fields', '!=', '' )
		),
		array(
			'id'            => '_um_sortby_custom',
			'type'          => 'text',
			'label'         => __( 'Meta key', 'ultimate-member' ),
			'tooltip'       => __( 'To sort by a custom field, enter the meta key of field here', 'ultimate-member' ),
			'value'         => UM()->query()->get_meta_value( '_um_sortby_custom', null, 'na' ),
			'conditional'   => array( '_um_sortby', '=', 'other' )
		),
		array(
			'id'                    => '_um_sorting_fields',
			'type'                  => 'multi_selects',
			'label'                 => __( 'Choose field(s) to enable in sorting', 'ultimate-member' ),
			'value'                 => $_um_sorting_fields,
			'options'               => $sorting_fields,
			'add_text'              => __( 'Add New Field','ultimate-member' ),
			'show_default_number'   => 0,
		)
	);

	UM()->admin_forms( array(
		'class'     => 'um-member-directory-sorting um-half-column',
		'prefix_id' => 'um_metadata',
		'fields'    => $fields
	) )->render_form(); ?>

	<div class="um-admin-clear"></div>
</div>