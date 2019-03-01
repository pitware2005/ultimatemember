<?php $args['view_types'] = ! empty( $args['view_types'] ) ? $args['view_types'] : array();
if ( empty( $args['view_types'] ) ) {
	$single_view = true;
	$view_type = 'grid';
} else {
	if ( count( $args['view_types'] ) == 1 ) {
		$single_view = true;
		$view_type = $args['view_types'][0];
	} else {
		$single_view = false;
		$args['default_view'] = ! empty( $args['default_view'] ) ? $args['default_view'] : $args['view_types'][0];
		$view_type = ! empty( $_GET['view_type'] ) ? $_GET['view_type'] : $args['default_view'];
	}
}

$view_type_info = array(
	'list' => array(
		'title' => __( 'Change to List', 'ultimate-member' ),
		'icon'	=> 'um-faicon-list'
	),
	'grid' => array(
		'title' => __( 'Change to Grid', 'ultimate-member' ),
		'icon'	=> 'um-faicon-th'
	)
);

$delete_default = array_diff( array_keys( $view_type_info ), array_keys( array_flip( $args['view_types'] ) ) );

foreach ( $delete_default as $key => $value ) {
	unset( $view_type_info[ $value ] );
}

/*
 * Add view info
 */
$view_type_info = apply_filters( 'um_add_view_types_info', $view_type_info, $args['view_types'] );

$sorting_options = array();
if ( ! empty( $args['sorting_fields'] ) ) {
	$sorting_options = $args['sorting_fields'];
}
$all_sorting_options = UM()->members()->get_sorting_fields();
$sorting_options = array_intersect_key( $all_sorting_options, array_flip( $sorting_options ) );

$show_search = true;
if ( ! empty( $args['roles_can_search'] ) && ! in_array( um_user( 'role' ), $args['roles_can_search'] ) ) {
	$show_search = false;
}

$show_filters = true;
if ( ! empty( $args['roles_can_filter'] ) && ! in_array( um_user( 'role' ), $args['roles_can_filter'] ) ) {
	$show_filters = false;
}

$classes = '';
if ( ! empty( $sorting_options ) ) {
	$classes .= ' um-member-with-sorting';
}

if ( $filters && $show_filters ) {
	$classes .= ' um-member-with-filters';
}

if ( ! $single_view ) {
	$classes .= ' um-member-with-view';
}?>

<div class="um <?php echo $this->get_class( $mode ); ?> um-<?php echo esc_attr( $form_id ); ?> um-visible"
     data-unique_id="um-<?php echo esc_attr( $form_id ) ?>"
     data-view_type="<?php echo $view_type ?>"
     data-only_search="<?php echo ( $search && $show_search && ! empty( $must_search ) ) ? 1 : 0 ?>">

	<div class="um-form">
		<div class="um-member-directory-header">
			<div class="um-clear"></div>

			<?php if ( $search && $show_search ) { ?>
				<div class="um-member-directory-search-line <?php echo esc_attr( $classes ) ?>">
					<input type="text" class="um-search-line" placeholder="<?php esc_attr_e( 'Search', 'ultimate-member' ) ?>"  value="" />
					<div class="uimob340-show uimob500-show">
						<a href="javascript:void(0);" class="um-button um-do-search um-tip-n" original-title="<?php esc_attr_e( 'Search', 'ultimate-member' ); ?>">
							<i class="um-faicon-search"></i>
						</a>
					</div>
					<div class="uimob340-hide uimob500-hide">
						<a href="javascript:void(0);" class="um-button um-do-search"><?php _e( 'Search', 'ultimate-member' ); ?></a>
					</div>
				</div>
			<?php } ?>

			<div class="um-member-directory-actions <?php echo esc_attr( $classes ) ?>">
				<?php if ( ! empty( $sorting_options ) ) { ?>
					<div class="um-member-directory-sorting <?php if ( ! $filters || ! $show_filters ) { ?>hidden_filter<?php } ?> <?php if ( $single_view ) { ?>hidden_type<?php } ?>">
						<select class="um-s3 um-member-directory-sorting-options" id="um-member-directory-sorting-select-<?php echo esc_attr( $form_id ) ?>" data-placeholder="<?php esc_attr_e( 'Sort By', 'ultimate-member' ); ?>">
							<option value=""></option>
							<?php foreach ( $sorting_options as $value => $title ) { ?>
								<option value="<?php echo $value ?>"><?php echo $title ?></option>
							<?php } ?>
						</select>
					</div>
				<?php }

				if ( $filters && $show_filters ) { ?>
					<div class="um-member-directory-filters">
						<a href="javascript:void(0);" class="um-member-directory-filters-a um-tip-n" original-title="<?php esc_attr_e( 'Filters', 'ultimate-member' ); ?>">
							<i class="um-faicon-sliders"></i>
						</a>
					</div>
				<?php }

				if ( ! $single_view ) { ?>
					<div class="um-member-directory-view-type">
						<!-- <a href="javascript:void(0);" class="um-member-directory-view-type-a um-tip-n" original-title="<?php if ( 'list' == $view_type ) { ?>Change to Grid<?php } else { ?>Change to List<?php } ?>">
							<i class="<?php if ( 'list' == $view_type ) { ?>um-faicon-list<?php } else { ?>um-faicon-th<?php } ?>"></i>
						</a> -->

						<?php foreach ( $view_type_info as $key => $type ) { ?>
							<a href="javascript:void(0)"
								class="um-member-directory-view-type-a um-tip-n"
								data-type="<?php echo $key; ?>"
								original-title="<?php echo $type['title']; ?>"
								default-title="<?php echo $type['title']; ?>"
								next-item="" >
								<i class="<?php echo $type['icon']; ?>"></i>
							</a>
						<?php } ?>

					</div>
				<?php } ?>
			</div>
			<div class="um-clear"></div>
		</div>

		<?php if ( $filters && $show_filters ) {

			$search_filters = array();
			if ( isset( $args['search_fields'] ) ) {
				foreach ( $args['search_fields'] as $k => $testfilter ) {
					if ( $testfilter && ! in_array( $testfilter, (array) $search_filters ) ) {
						$search_filters[] = $testfilter;
					}
				}
			}
			$search_filters = apply_filters( 'um_frontend_member_search_filters', $search_filters );

			if ( $args['filters'] == 1 && is_array( $search_filters ) ) { ?>
				<script type="text/template" id="tmpl-um-members-filtered-line">
					<# if ( data.filters.length > 0 ) { #>
						<# _.each( data.filters, function( filter, key, list ) { #>
							<div class="um-members-filter-tag">
								<strong>{{{filter.label}}}</strong>: {{{filter.value_label}}}
								<div class="um-members-filter-remove" data-name="{{{filter.name}}}" data-value="{{{filter.value}}}" data-range="{{{filter.range}}}">&times;</div></div>
						<# }); #>
					<# } #>
				</script>

				<div class="um-search um-search-<?php echo count( $search_filters ) ?>">
					<?php $i = 0;
					foreach ( $search_filters as $filter ) {
						$filter_content = UM()->members()->show_filter( $filter );
						if ( empty( $filter_content ) ) {
							continue;
						}

						$add_class = ( $i % 2 !== 0 ) ? 'um-search-filter-2' : ''; ?>

						<div class="um-search-filter <?php echo $add_class ?>">
							<?php echo $filter_content; ?>
						</div>

						<?php $i++;
					} ?>

					<div class="um-clear"></div>

					<div class="um-search-submit">
						<a href="javascript:void(0);" class="um-close-filter"><?php esc_attr_e( 'Close Filters', 'ultimate-member' ); ?></a>
					</div>
					<div class="um-clear"></div>
				</div>

				<div class="um-filtered-line">
					<div class="um-clear-filters"><a href="javascript:void(0);" class="um-clear-filters-a"><?php esc_attr_e( 'Clear All Filters', 'ultimate-member' ); ?></a></div>
				</div>
			<?php }

			do_action( 'um_members_directory_head', $args );
		} ?>

		<div class="um-members-wrapper">
			<?php $args['view_type'] = $view_type;

			include UM()->templates()->get_template( 'members-grid' );
			include UM()->templates()->get_template( 'members-list' );
			do_action( 'um_member_directory_map', $args ); ?>

			<div class="um-members-overlay"><div class="um-ajax-loading"></div></div>
		</div>
		<div class="um-clear"></div>


		<div class="um-members-pagination-box"></div>

		<script type="text/template" id="tmpl-um-members-pagination">
			<# if ( data.pagination.pages_to_show.length > 0 ) { #>
				<div class="um-members-pagidrop uimob340-show uimob500-show">
					<?php _e( 'Jump to page:','ultimate-member' ); ?>
					<select class="um-s2 um-members-pagi-dropdown" style="width: 100px;display:inline-block;">
						<# _.each( data.pagination.pages_to_show, function( page, key, list ) { #>
							<option value="{{{page}}}" <# if ( page == data.pagination.current_page ) { #>selected<# } #>>{{{page}}} <?php _e( 'of','ultimate-member' ) ?> {{{data.pagination.total_pages}}}</option>
						<# }); #>
					</select>
				</div>

				<div class="um-members-pagi uimob340-hide uimob500-hide">
					<span class="pagi pagi-arrow <# if ( data.pagination.current_page == 1 ) { #>disabled<# } #>" data-page="first"><i class="um-faicon-angle-double-left"></i></span>
					<span class="pagi pagi-arrow <# if ( data.pagination.current_page == 1 ) { #>disabled<# } #>" data-page="prev"><i class="um-faicon-angle-left"></i></span>

					<# _.each( data.pagination.pages_to_show, function( page, key, list ) { #>
						<span class="pagi <# if ( page == data.pagination.current_page ) { #>current<# } #>" data-page="{{{page}}}">{{{page}}}</span>
					<# }); #>

					<span class="pagi pagi-arrow <# if ( data.pagination.current_page == data.pagination.total_pages ) { #>disabled<# } #>" data-page="next"><i class="um-faicon-angle-right"></i></span>
					<span class="pagi pagi-arrow <# if ( data.pagination.current_page == data.pagination.total_pages ) { #>disabled<# } #>" data-page="last"><i class="um-faicon-angle-double-right"></i></span>
				</div>
			<# } #>
		</script>

		<?php
		/**
		* UM hook
		*
		* @type action
		* @title um_members_directory_footer
		* @description Member directory display footer
		* @input_vars
		* [{"var":"$args","type":"array","desc":"Member directory shortcode arguments"}]
		* @change_log
		* ["Since: 2.0"]
		* @usage add_action( 'um_members_directory_footer', 'function_name', 10, 1 );
		* @example
		* <?php
		 * add_action( 'um_members_directory_footer', 'my_members_directory_footer', 10, 1 );
		 * function my_members_directory_footer( $args ) {
		 *     // your code here
		 * }
		 * ?>
		*/
		do_action( 'um_members_directory_footer', $args ); ?>

		<div class="um-clear"></div>
	</div>
</div>
