<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="um-admin-metabox">
	<?php $can_search_array = array();
	$can_filter_array = array();
	foreach ( UM()->roles()->get_roles() as $key => $value ) {
	    $_um_roles_can_search = UM()->query()->get_meta_value( '_um_roles_can_search', $key );
		if ( ! empty( $_um_roles_can_search ) ) {
			$can_search_array[] = $_um_roles_can_search;
		}

		$_um_roles_can_filter = UM()->query()->get_meta_value( '_um_roles_can_filter', $key );
		if ( ! empty( $_um_roles_can_filter ) ) {
			$can_filter_array[] = $_um_roles_can_filter;
		}
	}

	$user_fields = UM()->members()->get_filters_fields();

	$post_id = get_the_ID();
	$_um_search_fields = get_post_meta( $post_id, '_um_search_fields', true );

	UM()->admin_forms( array(
		'class'     => 'um-member-directory-search um-half-column',
		'prefix_id' => 'um_metadata',
		'fields'    => array(
			array(
				'id'        => '_um_search',
				'type'      => 'checkbox',
				'label'     => __( 'Enable Search feature', 'ultimate-member' ),
				'tooltip'   => __( 'If turned on, users will be able to search members in this directory', 'ultimate-member' ),
				'value'     => UM()->query()->get_meta_value( '_um_search' ),
			),
			array(
				'id'            => '_um_roles_can_search',
				'type'          => 'select',
				'multi'         => true,
				'label'         => __( 'User Roles that can use search', 'ultimate-member' ),
				'tooltip'       => __( 'If you want to allow specific user roles to be able to search only', 'ultimate-member' ),
				'value'         => $can_search_array,
				'options'       => UM()->roles()->get_roles(),
				'conditional'   => array( '_um_search', '=', 1 )
			),
			array(
				'id'        => '_um_filters',
				'type'      => 'checkbox',
				'label'     => __( 'Enable Filters feature', 'ultimate-member' ),
				'tooltip'   => __( 'If turned on, users will be able to filter members in this directory', 'ultimate-member' ),
				'value'     => UM()->query()->get_meta_value( '_um_filters' ),
			),
			array(
				'id'            => '_um_roles_can_filter',
				'type'          => 'select',
				'multi'         => true,
				'label'         => __( 'User Roles that can use filters', 'ultimate-member' ),
				'tooltip'       => __( 'If you want to allow specific user roles to be able to filter only', 'ultimate-member' ),
				'value'         => $can_filter_array,
				'options'       => UM()->roles()->get_roles(),
				'conditional'   => array( '_um_filters', '=', 1 )
			),
			array(
				'id'                    => '_um_search_fields',
				'type'                  => 'multi_selects',
				'label'                 => __( 'Choose filter(s) meta to enable', 'ultimate-member' ),
				'value'                 => $_um_search_fields,
				'conditional'           => array( '_um_filters', '=', 1 ),
				'options'               => $user_fields,
				'add_text'              => __( 'Add New Custom Field', 'ultimate-member' ),
				'show_default_number'   => 1,
			),
			array(
				'id'            => '_um_search_filters',
				'type'          => 'text',
				'label'         => __( 'Additional search filters', 'ultimate-member' ),
				'tooltip'       => __( 'Additional search filters like URL parameters', 'ultimate-member' ),
				'value'         => UM()->query()->get_meta_value('_um_search_filters', null, 'na' ),
				'conditional'   => array( '_um_filters', '=', 1 ),
				'placeholder'   => 'field1=val1&field2=val2'
			),
		)
	) )->render_form(); ?>

	<div class="um-admin-clear"></div>
</div>