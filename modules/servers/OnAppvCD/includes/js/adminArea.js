$( document ).ready( function() {
	var tables = $( 'table.form' );
	// remove old table
	var newTable = $( 'span.oeu-container' );
	tables.eq( 3 ).after( newTable )
	tables.eq( 3 ).remove()

	// apply chosen
	var chzn = $( 'table.oeu select' );
	if( chzn.length ) {
		var opts = {
			width: '40%',
			disable_search_threshold: 5,
			placeholder_text_multiple: 'Select Some Options'
		};
		tables.eq( 2 ).find( 'select' ).chosen( opts );
		chzn.chosen( opts );
	}

	// remove validation style
	chzn.on( 'change', function( e ) {
		$( this ).next().find( 'a' ).css( 'border', '' );
		$( this ).next().find( 'ul' ).css( 'border', '' );
	} );

	// reinit chosen
	$( '#tabLink3' ).click( function() {
		setTimeout( function() {
			$( 'table.oeu select' ).trigger( 'chosen:updated' );
		}, 100 );
	} );

	// reset cache handler
	$( '#oeu-reset-cache' ).click( function() {
		$( '.oeu-reset-cache' ).val( 1 );
		$( '#' + OnAppModuleName + '_Skip' ).val( 1 );
		$( 'form[name="packagefrm"]' ).submit();
	} );

	// change server handler
	$( 'table.oeu select:first' ).on( 'change', function() {
		if( $( 'table.oeu select' ).length > 1 ) {
			// todo localize
			var r = confirm( 'Save current settings?' );
			if( r == false ) {
				$( '#' + OnAppModuleName + '_Skip' ).val( 1 );
				$( 'form[name="packagefrm"]' ).submit();
				return;
			}
		}
		if( OnAppvCD_Validate() ) {
			$( 'form[name="packagefrm"]' ).submit();
		}
	} );

	// organization type handler
	$( 'table.oeu #org-type' ).on( 'change', function() {
		if( this.value == 1 ) {
			$( 'table.oeu #group-row select' ).prop( 'disabled', false );
			$( 'table.oeu #group-row' ).show();

			$('#billing-plan').html( $('#bp-regular').html() );

			$( 'table.oeu #group-bp-row' ).hide();
			$( 'table.oeu #group-bp-row select' ).prop( 'disabled', true );
		}
		else {
			$( 'table.oeu #group-row' ).hide();
			$( 'table.oeu #group-row select' ).prop( 'disabled', true );

			$( '#billing-plan' ).html( $( '#bp-company' ).html() );

			$( 'table.oeu #group-bp-row' ).show();
			$( 'table.oeu #group-bp-row select' ).prop( 'disabled', false );
		}
		$( 'table.oeu select' ).trigger( 'chosen:updated' );
	} );

	$( 'form[name="packagefrm"] input[type="submit"]' ).on( 'click', OnAppvCD_Validate );
} );

function OnAppvCD_Validate() {
	var submit = true;
	$.each( $( 'table.oeu select[required]:enabled' ), function( i, el ) {
		if( el.value == '' ) {
			// #a94442
			$( el ).next().find( 'a' ).css( 'border', '1px solid red' );
			$( el ).next().find( 'ul' ).css( 'border', '1px solid red' );
			submit = false;
		}
		else {
			$( el ).next().find( 'a' ).css( 'border', '' );
			$( el ).next().find( 'ul' ).css( 'border', '' );
		}
	} );
	return submit;
}