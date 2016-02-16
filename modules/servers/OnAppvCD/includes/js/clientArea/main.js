$( document ).ready( function() {
	// generate new password
	$( '#change-password' ).on( 'click', function() {
		$( '#gotocp .alert' ).hide();
		var btn = $( this );
		OnAppModule_toggleButton( btn, 'process', true );

		$.ajax( {
			url:     document.location.href,
			data:    {
				modop: 'custom',
				a:     'GeneratePassword'
			},
			error:   function() {
				$( '#gotocp .alert span' ).html( LANG.GeneralIssue );
				var alert = $( '#gotocp .alert' );
				alert.removeClass().addClass( 'alert alert-danger' ).show( 'fast' );
			},
			success: function( data ) {
				data = JSON.parse( data );
				$( '#gotocp .alert span' ).html( data.message );
				var alert = $( '#gotocp .alert' );
				alert.removeClass().addClass( 'alert' );
				if( data.status ) {
					alert.addClass( 'alert-success' ).show( 'fast' );
					setTimeout( function() {
						location.reload();
					}, 1500 );
				}
				else {
					alert.addClass( 'alert-danger' ).show( 'fast' );
				}
			}
		} ).always( function() {
			OnAppModule_toggleButton( btn, 'reset', true );
		} );
	} );

	// datetime picker
	var opts = {
		format:           'YYYY-MM-DD HH:mm',
		allowInputToggle: true,
		maxDate:          'now',
		collapse:         true
	};
	$( '#datetimepicker1' ).datetimepicker( opts );
	$( '#datetimepicker2' ).datetimepicker( opts );
	$( '#datetimepicker2 input' ).val( moment().format( 'YYYY-MM-DD HH:mm' ) );
	$( '#datetimepicker1 input' ).val( moment().subtract( 2, 'days' ).format( 'YYYY-MM-DD HH:mm' ) );

	// password handler
	$( '.preview-password' ).prevue( {
		offsetX: $( '.preview-password' ).width() + 5
	} );

	// get stat
	$( '#stat_data button' ).click();
} );

function OnAppModule_toggleButton( btn, mode, disable ) {
	if( !btn.data( 'reset' ) ) {
		btn.data( 'reset', btn.text() );
	}
	if( disable ) {
		btn.prop( 'disabled', function( _, val ) {
			return !val;
		} );
	}

	btn.text( btn.data( mode ) );
}