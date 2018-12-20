var um_members_directory_busy = [];


jQuery(document).ready(function() {

	//slider filter
	var slider = jQuery( ".um-slider" );
	slider.slider({
		range: true,
		min: parseInt( slider.data('min') ),
		max: parseInt( slider.data('max') ),
		values: [ parseInt( slider.data('min') ), parseInt( slider.data('max') ) ],
		create: function( event, ui ) {
			console.log( ui );
		},
		slide: function( event, ui ) {
			jQuery( this ).siblings('.um-slider-range').html( ui.values[ 0 ] + ' - ' + ui.values[ 1 ] + ' y.o' );
			jQuery( this ).siblings('.um_range_min').val( ui.values[ 0 ] );
			jQuery( this ).siblings('.um_range_max').val( ui.values[ 1 ] );
		},
		stop: function( event, ui ) {
			var directory = jQuery(this).parents('.um-directory');
			if ( ! um_is_directory_busy( directory ) ) {
				um_set_directory_storage( directory, 'filter_' + jQuery(this).data('field_name'), ui.values, true );
				um_set_directory_storage( directory, 'page', 1, true );
				um_ajax_get_members( directory );
			}
		}
	});

	jQuery( ".um-slider-range" ).each( function() {
		jQuery( this ).html( jQuery( this ).siblings( ".um-slider" ).slider( "values", 0 ) + ' - ' +
			jQuery( this ).siblings( ".um-slider" ).slider( "values", 1 ) + ' y.o' );


		jQuery( this ).siblings( ".um_range_min" ).val( jQuery( this ).siblings( ".um-slider" ).slider( "values", 0 ) );
		jQuery( this ).siblings( ".um_range_max" ).val( jQuery( this ).siblings( ".um-slider" ).slider( "values", 1 ) );
	});


	jQuery('.um-datepicker-filter').each( function(){
		elem = jQuery(this);
		var years_n = elem.attr('data-years');

		var min = elem.data('date_min');
		var max = elem.data('date_max');

		elem.pickadate({
			selectYears: years_n,
			min: min,
			max: max,
			formatSubmit: 'yyyy/mm/dd',
			hiddenName: true,
			onOpen: function() { elem.blur(); },
			onClose: function() { elem.blur(); },
			onSet: function(context) {
				if ( jQuery(this).val() === '' ) {
					return;
				}

				var directory = jQuery(this).parents('.um-directory');

				if ( um_is_directory_busy( directory ) ) {
					return;
				}

				var filter_name = jQuery(this).prop('name');

				var current_value = um_get_directory_storage( directory, 'filter_' + filter_name );
				if ( current_value === null ) {
					current_value = [];
				}
				if ( -1 === jQuery.inArray( jQuery(this).val(), current_value ) ) {
					current_value.push( jQuery(this).val() );
					um_set_directory_storage( directory, 'filter_' + filter_name, current_value, true );

					//set 1st page after filtration
					um_set_directory_storage( directory, 'page', 1, true );
				}

				jQuery(this).val('').trigger('change');

				um_ajax_get_members( directory );


				var unique_id = um_members_get_unique_id( directory );
				var filters_data = [];

				directory.find('.um-search-filter').each( function() {
					var filter = jQuery(this);
					var filter_name = filter.find('select').attr('name');
					var query_value = um_get_directory_storage( directory, 'filter_' + filter_name );

					var filter_title = filter.find('select').data('placeholder');
					var filter_value_title;

					if ( typeof( query_value ) != 'undefined' ) {
						if ( typeof( query_value ) == 'string' ) {
							filter_value_title = filter.find('select option[value="' + query_value + '"]').data('value_label');
							filters_data.push( {'name':filter_name, 'label':filter_title, 'value_label':filter_value_title, 'value':query_value, 'unique_id':unique_id} );
						} else {
							jQuery.each( query_value, function(e) {
								filter_value_title = filter.find('select option[value="' + query_value[e] + '"]').data('value_label');
								filters_data.push( {'name': filter_name, 'label':filter_title, 'value_label':filter_value_title, 'value':query_value[e], 'unique_id':unique_id} );
							});
						}
					}
				});

				directory.find('.um-members-filter-tag').remove();

				var filters_template = wp.template( 'um-members-filtered-line' );
				directory.find('.um-filtered-line').prepend( filters_template( {'filters': filters_data} ) );

				if ( filters_data.length > 0 ) {
					directory.find('.um-filtered-line').show();
				} else {
					directory.find('.um-filtered-line').hide();
				}
			}
		});
	});


	//first page loading
	jQuery( '.um-directory' ).each( function() {
		var directory = jQuery(this);


		//show preloader at first
		um_members_show_preloader( directory );

		var layout = directory.data( 'view_type' );
		//set default directory view type
		layout = um_set_directory_storage( directory, 'layout', layout );

		if ( directory.find('.um-member-directory-view-type-a').length ) {
			var button_class = ( layout === 'list' ) ? 'um-faicon-list' : 'um-faicon-th';
			directory.find('.um-member-directory-view-type-a i').attr( 'class', button_class );

			var tooltip_title = ( layout === 'list' ) ? 'Change to Grid' : 'Change to List';
			directory.find('.um-member-directory-view-type-a').attr( 'original-title', tooltip_title );
			directory.find('.um-member-directory-view-type-a').tipsy('hide').tipsy('show');
		}


		//set first page if it's null
		um_set_directory_storage( directory, 'page', 1 );

		if ( directory.find('.um-member-directory-sorting-options').length ) {
			var sorting = um_set_directory_storage( directory, 'sorting', directory.find('.um-member-directory-sorting-options').val() );
			directory.find('.um-member-directory-sorting-options').val( sorting );
		}


		var show_after_search = false;
		//set search line from session storage
		if ( directory.find('.um-search-line').length ) {
			var general_search = um_get_directory_storage( directory, 'general_search' );
			if ( general_search !== null ) {
				directory.find('.um-search-line').val( general_search );
			} else {
				//show results after search
				show_after_search = true;
			}
		}

		if ( directory.find('.um-member-directory-sorting-options').length ) {
			var sorting = um_get_directory_storage( directory, 'sorting' );
			if ( sorting !== null ) {
				directory.find('.um-member-directory-sorting-options').val( sorting ).trigger( 'change' );
			}
		}

		if ( jQuery('#tmpl-um-members-filtered-line').length ) {
			var unique_id = um_members_get_unique_id( directory );
			var filters_data = [];

			directory.find('.um-search-filter').each( function() {
				var filter = jQuery(this);
				var filter_name = filter.find('select').attr('name');
				var query_value = um_get_directory_storage( directory, 'filter_' + filter_name );

				var filter_title = filter.find('select').data('placeholder');
				var filter_value_title;

				if ( typeof( query_value ) != 'undefined' ) {
					if ( typeof( query_value ) == 'string' ) {
						filter_value_title = filter.find('select option[value="' + query_value + '"]').data('value_label');
						filters_data.push( {'name':filter_name, 'label':filter_title, 'value_label':filter_value_title, 'value':query_value, 'unique_id':unique_id} );
					} else {
						jQuery.each( query_value, function(e) {
							filter_value_title = filter.find('select option[value="' + query_value[e] + '"]').data('value_label');
							filters_data.push( {'name': filter_name, 'label':filter_title, 'value_label':filter_value_title, 'value':query_value[e], 'unique_id':unique_id} );
						});
					}
				}

				if ( filter.find( '.um-slider' ).length ) {
					var age_query_value = um_get_directory_storage( directory, 'filter_birth_date' );

					if ( age_query_value !== null ) {

						filter.find( ".um-slider" ).slider( "option", "values", age_query_value );

						filter.find( ".um_range_min" ).val( age_query_value[0] );
						filter.find( ".um_range_max" ).val( age_query_value[1] );

						filter.find( ".um-slider-range" ).html( filter.find( ".um-slider" ).slider( "values", 0 ) + ' - ' +
							filter.find( ".um-slider" ).slider( "values", 1 ) + ' y.o' );
					}
				}
			});

			directory.find('.um-members-filter-tag').remove();

			var filters_template = wp.template( 'um-members-filtered-line' );
			directory.find('.um-filtered-line').prepend( filters_template( {'filters': filters_data} ) );

			if ( filters_data.length > 0 ) {
				directory.find('.um-filtered-line').show();
				show_after_search = false;
			} else {
				directory.find('.um-filtered-line').hide();
				show_after_search = true;
			}
		}

		if ( parseInt( directory.data( 'only_search' ) ) === 1 && show_after_search ) {
			um_members_hide_preloader( directory );
			return;
		}

		um_ajax_get_members( directory );
	});


	//change layout
	jQuery( document.body ).on( 'click', '.um-member-directory-view-type-a', function() {
		var directory = jQuery(this).parents('.um-directory');

		if ( um_is_directory_busy( directory ) ) {
			return false;
		}

		var layout = um_get_directory_storage( directory, 'layout' );
		layout = ( layout === 'grid' ) ? 'list' : 'grid';

		um_set_directory_storage( directory, 'layout', layout, true );
		directory.data( 'view_type', layout );

		var button_class = ( layout === 'list' ) ? 'um-faicon-list' : 'um-faicon-th';
		directory.find('.um-member-directory-view-type-a i').attr( 'class', button_class );

		var tooltip_title = ( layout === 'list' ) ? 'Change to Grid' : 'Change to List';
		directory.find('.um-member-directory-view-type-a').attr( 'original-title', tooltip_title );
		directory.find('.um-member-directory-view-type-a').tipsy('hide').tipsy('show');

		var data = um_get_directory_storage( directory, 'last_data' );
		if ( data !== null ) {
			um_build_template( directory, data );
		} else {

			var show_after_search = false;
			//set search line from session storage
			if ( directory.find('.um-search-line').length ) {
				var general_search = um_get_directory_storage( directory, 'general_search' );
				if ( general_search === null ) {
					//show results after search
					show_after_search = true;
				}
			}

			if ( jQuery('#tmpl-um-members-filtered-line').length ) {
				var filters_data = [];
				directory.find('.um-search-filter').each( function() {
					var filter = jQuery(this);
					var filter_name = filter.find('select').attr('name');
					var query_value = um_get_directory_storage( directory, 'filter_' + filter_name );

					if ( typeof( query_value ) != 'undefined' ) {
						if ( typeof( query_value ) == 'string' ) {
							filters_data.push( {'name':filter_name} );
						} else {
							jQuery.each( query_value, function() {
								filters_data.push( {'name': filter_name} );
							});
						}
					}
				});

				show_after_search = ( filters_data.length <= 0 );
			}

			if ( parseInt( directory.data( 'only_search' ) ) === 1 && show_after_search ) {
				return;
			}

			um_members_show_preloader( directory );
			um_ajax_get_members( directory );
		}
	});


	//pagination
	jQuery( document.body ).on( 'click', '.um-directory .pagi:not(.current)', function() {
		if ( jQuery(this).hasClass('disabled') ) {
			return;
		}

		var directory = jQuery(this).parents('.um-directory');
		if ( um_is_directory_busy( directory ) ) {
			return;
		}

		um_members_show_preloader( directory );

		var page;
		if ( 'first' === jQuery(this).data('page') ) {
			page = 1;
		} else if ( 'prev' === jQuery(this).data('page') ) {
			page = directory.data( 'page' )*1 - 1;
		} else if ( 'next' === jQuery(this).data('page') ) {
			page = directory.data( 'page' )*1 + 1;
		} else if ( 'last' === jQuery(this).data('page') ) {
			page = parseInt( directory.data( 'total_pages' ) );
		} else {
			page = parseInt( jQuery(this).data('page') );
		}

		if ( page === 1 ) {
			directory.find('.pagi[data-page="first"], .pagi[data-page="prev"]').addClass('disabled');
			directory.find('.pagi[data-page="prev"], .pagi[data-page="last"]').removeClass('disabled');
		} else if ( page === parseInt( directory.data( 'total_pages' ) ) ) {
			directory.find('.pagi[data-page="prev"], .pagi[data-page="last"]').addClass('disabled');
			directory.find('.pagi[data-page="first"], .pagi[data-page="prev"]').removeClass('disabled');
		} else {
			directory.find('.pagi[data-page="prev"], .pagi[data-page="last"]').removeClass('disabled');
			directory.find('.pagi[data-page="first"], .pagi[data-page="prev"]').removeClass('disabled');
		}

		directory.find('.pagi').removeClass('current');
		directory.find('.pagi[data-page="' + page + '"]').addClass('current');

		directory.data( 'page', page );
		um_set_directory_storage( directory, 'page', page, true );

		um_ajax_get_members( directory );
	});


	//mobile pagination
	jQuery( document.body ).on( 'change', '.um-directory .um-members-pagi-dropdown', function() {
		var directory = jQuery(this).parents('.um-directory');

		if ( um_is_directory_busy( directory ) ) {
			return;
		}

		var page = jQuery(this).val();

		directory.find('.pagi').removeClass('current');
		directory.find('.pagi[data-page="' + page + '"]').addClass('current');

		directory.data( 'page', page );
		um_set_directory_storage( directory, 'page', page, true );

		um_ajax_get_members( directory );
	});


	//sorting
	jQuery( document.body ).on( 'change', '.um-member-directory-sorting-options', function() {
		var directory = jQuery(this).parents('.um-directory');

		if ( um_is_directory_busy( directory ) ) {
			return;
		}

		var show_after_search = false;
		//set search line from session storage
		if ( directory.find('.um-search-line').length ) {
			var general_search = um_get_directory_storage( directory, 'general_search' );
			if ( general_search === null ) {
				//show results after search
				show_after_search = true;
			}
		}

		if ( jQuery('#tmpl-um-members-filtered-line').length ) {
			var filters_data = [];
			directory.find('.um-search-filter').each( function() {
				var filter = jQuery(this);
				var filter_name = filter.find('select').attr('name');
				var query_value = um_get_directory_storage( directory, 'filter_' + filter_name );

				if ( typeof( query_value ) != 'undefined' ) {
					if ( typeof( query_value ) == 'string' ) {
						filters_data.push( {'name':filter_name} );
					} else {
						jQuery.each( query_value, function() {
							filters_data.push( {'name': filter_name} );
						});
					}
				}
			});

			show_after_search = ( filters_data.length <= 0 );
		}

		if ( parseInt( directory.data( 'only_search' ) ) === 1 && show_after_search ) {
			return;
		}

		directory.data( 'sorting', jQuery(this).val() );
		um_set_directory_storage( directory, 'sorting', jQuery(this).val(), true );

		um_ajax_get_members( directory );
	});


	//searching
	jQuery( document.body ).on( 'click', '.um-do-search', function() {
		var directory = jQuery(this).parents('.um-directory');

		if ( um_is_directory_busy( directory ) ) {
			return;
		}

		var search = directory.find('.um-search-line').val();
		if ( search === um_get_directory_storage( directory, 'general_search' ) ||
			 ( search === '' && null === um_get_directory_storage( directory, 'general_search' ) ) ) {
			return;
		}

		directory.data( 'general_search', search );
		um_set_directory_storage( directory, 'general_search', search, true );

		//set 1st page after search
		um_set_directory_storage( directory, 'page', 1, true );

		um_ajax_get_members( directory );
	});


	//make search on Enter click
	jQuery( document.body ).on( 'keypress', '.um-search-line', function(e) {
		if ( e.which === 13 ) {
			var directory = jQuery(this).parents('.um-directory');
			directory.find('.um-do-search').trigger('click');
		}
	});






	//filters controls
	jQuery('.um-member-directory-filters').click( function() {
		var search_bar = jQuery(this).parents('.um-directory').find('.um-search');

		if ( search_bar.is(':visible') ) {
			search_bar.slideUp(750);
		} else {
			search_bar.slideDown(750);
		}
	});


	jQuery('.um-close-filter').click( function() {
		var search_bar = jQuery(this).parents('.um-directory').find('.um-search');
		search_bar.slideUp(750);
	});




	//filtration process
	jQuery( document.body ).on( 'change', '.um-search-filter select', function() {
		if ( jQuery(this).val() === '' ) {
			return;
		}

		var directory = jQuery(this).parents('.um-directory');

		if ( um_is_directory_busy( directory ) ) {
			return;
		}

		var filter_name = jQuery(this).prop('name');

		var current_value = um_get_directory_storage( directory, 'filter_' + filter_name );
		if ( current_value === null ) {
			current_value = [];
		}
		if ( -1 === jQuery.inArray( jQuery(this).val(), current_value ) ) {
			current_value.push( jQuery(this).val() );
			um_set_directory_storage( directory, 'filter_' + filter_name, current_value, true );

			//set 1st page after filtration
			um_set_directory_storage( directory, 'page', 1, true );
		}

		jQuery(this).val('').trigger('change');

		um_ajax_get_members( directory );


		var unique_id = um_members_get_unique_id( directory );
		var filters_data = [];

		directory.find('.um-search-filter').each( function() {
			var filter = jQuery(this);
			var filter_name = filter.find('select').attr('name');
			var query_value = um_get_directory_storage( directory, 'filter_' + filter_name );

			var filter_title = filter.find('select').data('placeholder');
			var filter_value_title;

			if ( typeof( query_value ) != 'undefined' ) {
				if ( typeof( query_value ) == 'string' ) {
					filter_value_title = filter.find('select option[value="' + query_value + '"]').data('value_label');
					filters_data.push( {'name':filter_name, 'label':filter_title, 'value_label':filter_value_title, 'value':query_value, 'unique_id':unique_id} );
				} else {
					jQuery.each( query_value, function(e) {
						filter_value_title = filter.find('select option[value="' + query_value[e] + '"]').data('value_label');
						filters_data.push( {'name': filter_name, 'label':filter_title, 'value_label':filter_value_title, 'value':query_value[e], 'unique_id':unique_id} );
					});
				}
			}

			if ( filter.find( '.um-slider' ).length ) {
				var age_query_value = um_get_directory_storage( directory, 'filter_birth_date' );

				if ( typeof age_query_value != 'undefined' ) {

					filter.find( ".um-slider" ).slider( "option", "values", age_query_value );

					filter.find( ".um_range_min" ).val( age_query_value[0] );
					filter.find( ".um_range_max" ).val( age_query_value[1] );

					filter.find( ".um-slider-range" ).html( filter.find( ".um-slider" ).slider( "values", 0 ) + ' - ' +
						filter.find( ".um-slider" ).slider( "values", 1 ) + ' y.o' );
				}
			}
		});

		directory.find('.um-members-filter-tag').remove();

		var filters_template = wp.template( 'um-members-filtered-line' );
		directory.find('.um-filtered-line').prepend( filters_template( {'filters': filters_data} ) );

		if ( filters_data.length > 0 ) {
			directory.find('.um-filtered-line').show();
		} else {
			directory.find('.um-filtered-line').hide();
		}
	});


	jQuery( document.body ).on( 'click', '.um-members-filter-remove', function() {
		var directory = jQuery(this).parents('.um-directory');

		if ( um_is_directory_busy( directory ) ) {
			return;
		}

		var removeItem = jQuery(this).data('value');
		var filter_name = jQuery(this).data('name');

		var current_value = um_get_directory_storage( directory, 'filter_' + filter_name );
		if ( current_value !== null && -1 !== jQuery.inArray( removeItem, current_value ) ) {
			current_value = jQuery.grep( current_value, function( value ) {
				return value !== removeItem;
			});

			if ( current_value.length > 0 ) {
				um_set_directory_storage( directory, 'filter_' + filter_name, current_value, true );
			} else {
				um_delete_directory_storage( directory, 'filter_' + filter_name );
			}

			//set 1st page after filtration
			um_set_directory_storage( directory, 'page', 1, true );

			jQuery(this).parents('.um-members-filter-tag').remove();
		}

		um_ajax_get_members( directory );

		if ( directory.find( '.um-members-filter-remove' ).length === 0 ) {
			directory.find('.um-clear-filters-a').remove();
		}
	});


	jQuery( document.body ).on( 'click', '.um-clear-filters-a', function() {
		var directory = jQuery(this).parents('.um-directory');
		if ( um_is_directory_busy( directory ) ) {
			return;
		}

		directory.find( '.um-members-filter-remove' ).each( function() {
			var removeItem = jQuery(this).data('value');
			var filter_name = jQuery(this).data('name');

			var current_value = um_get_directory_storage( directory, 'filter_' + filter_name );
			if ( current_value !== null && -1 !== jQuery.inArray( removeItem, current_value ) ) {
				current_value = jQuery.grep( current_value, function( value ) {
					return value !== removeItem;
				});

				if ( current_value.length > 0 ) {
					um_set_directory_storage( directory, 'filter_' + filter_name, current_value, true );
				} else {
					um_delete_directory_storage( directory, 'filter_' + filter_name );
				}

				directory.find('.um-members-filter-tag').remove();
			}
		});

		//set 1st page after filtration
		um_set_directory_storage( directory, 'page', 1, true );
		directory.find('.um-members-filter-tag').remove();

		um_ajax_get_members( directory );

		jQuery(this).remove();
	});





	//grid controls
	jQuery( document.body ).on( 'click', '.um-member-more a', function() {
		var directory = jQuery(this).parents('.um-directory');

		var block = jQuery(this).parents('.um-member');
		block.find('.um-member-more').hide();
		block.find('.um-member-meta').slideDown( function() {
			block.find('.um-member-less').fadeIn();
			if ( directory.find('.um-members').length ) {
				UM_Member_Grid( directory.find('.um-members') );
			}
		});

		setTimeout( function() {
			if ( directory.find('.um-members').length ) {
				UM_Member_Grid( directory.find('.um-members') );
			}
		}, 100 );
	});


	jQuery( document.body ).on( 'click', '.um-member-less', function() {
		var directory = jQuery(this).parents('.um-directory');

		var block = jQuery(this).parents('.um-member');
		block.find('.um-member-less').hide();
		block.find('.um-member-meta').slideUp( function() {
			block.find('.um-member-more').fadeIn();
			if ( directory.find('.um-members').length ) {
				UM_Member_Grid( directory.find('.um-members') );
			}
		});
	});
});


function IsValidJSONString( str ) {
	try {
		JSON.parse( str );
	} catch ( e ) {
		return false;
	}
	return true;
}


function um_set_directory_storage( directory, key, value, hard ) {
	if ( hard || sessionStorage.getItem( 'um_directory_' + um_members_get_unique_id( directory ) + '_' + key ) === null ) {
		var val = value;
		if ( typeof( value ) === 'object' ) {
			val = JSON.stringify( value );
		}

		sessionStorage.setItem( 'um_directory_' + um_members_get_unique_id( directory ) + '_' + key, val );
		return value;
	} else {
		return um_get_directory_storage( directory, key );
	}
}


function um_get_directory_storage( directory, key ) {
	var value = sessionStorage.getItem( 'um_directory_' + um_members_get_unique_id( directory ) + '_' + key );
	if ( IsValidJSONString( value ) ) {
		return JSON.parse( value );
	} else {
		return value;
	}
}


function um_delete_directory_storage( directory, key ) {
	sessionStorage.removeItem( 'um_directory_' + um_members_get_unique_id( directory ) + '_' + key );
}


function um_members_get_unique_id( directory ) {
	return directory.data( 'unique_id' );
}







function um_is_directory_busy( directory ) {
	var unique_id = um_members_get_unique_id( directory );
	return typeof um_members_directory_busy[ unique_id ] != 'undefined' && um_members_directory_busy[ unique_id ];
}


function um_members_show_preloader( directory ) {
	um_members_directory_busy[ um_members_get_unique_id( directory ) ] = true;
	directory.find('.um-members-overlay').show();
}


function um_members_hide_preloader( directory ) {
	um_members_directory_busy[ um_members_get_unique_id( directory ) ] = false;
	directory.find('.um-members-overlay').hide();
}






function um_ajax_get_members( directory ) {
	var request = {
		page: um_get_directory_storage( directory, 'page' ),
		args: um_members_args,
		nonce: um_scripts.nonce,
		referrer_url: window.location.href
	};

	if ( directory.find('.um-member-directory-sorting-options').length ) {
		request.sorting = um_get_directory_storage( directory, 'sorting' );
	}

	if ( directory.find('.um-search-line').length ) {
		request.general_search = um_get_directory_storage( directory, 'general_search' );
	}


	if ( directory.find('.um-search-filter').length ) {
		directory.find('.um-search-filter').each( function() {
			var filter = jQuery(this);

			if ( filter.find( '.um-slider' ).length ) {
				var value = um_get_directory_storage( directory, 'filter_birth_date' );
				if ( value !== null ) {
					request[ 'birth_date' ] = value;
					request['is_filters'] = true;
				}
			} else {
				var filter_name = filter.find('select').attr('name');
				var value = um_get_directory_storage( directory, 'filter_' + filter_name );
				if ( value !== null ) {
					request[ filter_name ] = value;
					request['is_filters'] = true;
				}
			}
		});
	}


	wp.ajax.send( 'um_get_members', {
		data: request,
		success: function( answer ) {
			//set last data hard for using on layouts reloading
			um_set_directory_storage( directory, 'last_data', answer, true );

			um_build_template( directory, answer );

			um_members_hide_preloader( directory );

			var pagination_template = wp.template( 'um-members-pagination' );
			directory.find('.um-members-pagination-box').html( pagination_template( answer ) );

			directory.data( 'total_pages', answer.pagination.total_pages );
		},
		error: function( data ) {
			console.log( data );
		}
	});
}


function um_build_template( directory, data ) {
	var layout = um_get_directory_storage( directory, 'layout' );
	var template = wp.template( 'um-member-' + layout );

	directory.find('.um-members-grid, .um-members-list').remove();
	directory.find('.um-members-wrapper').prepend( template( data ) );
	directory.addClass('um-loaded');
	if ( directory.find('.um-members').length ) {
		UM_Member_Grid( directory.find('.um-members') );
		jQuery( window ).trigger( 'resize' );
	}

	jQuery( document ).trigger( "um_build_template", [ directory ] );
}


function UM_Member_Grid( container ) {
	if ( container.find( '.um-member' ).length ) {
		container.imagesLoaded( function() {
			var $grid = container.masonry({
				itemSelector: '.um-member',
				columnWidth: '.um-member',
				gutter: '.um-gutter-sizer'
			});

			$grid.on( 'layoutComplete', function( event, laidOutItems ) {
				jQuery( document ).trigger( "um_grid_initialized", [ event, laidOutItems ] );
			});
		});
	}
}