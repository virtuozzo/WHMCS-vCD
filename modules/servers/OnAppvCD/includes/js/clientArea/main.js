$( document ).ready( function() {
	// generate new password
	$( '#change-password' ).on( 'click', function() {
		$( '#gotocp .alert' ).hide();
		var btn = $( this );
		btn.prop( 'disabled', true );
		btn.text( btn.data( 'loading' ) );

		$.ajax( {
			url: document.location.href,
			data: {
				modop: 'custom',
				a: 'GeneratePassword'
			},
			error: function() {
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
			btn.prop( 'disabled', false );
			btn.text( btn.data( 'normal' ) );
		} );
	} );

	// datetime picker
	var opts = {
		format: 'YYYY-MM-DD HH:mm',
		allowInputToggle: true,
		maxDate: 'now',
		collapse: true
	};
	$( '#datetimepicker1' ).datetimepicker( opts );
	$( '#datetimepicker2' ).datetimepicker( opts );
	$( '#datetimepicker2 input' ).val( moment().format( 'YYYY-MM-DD HH:mm' ) );
	$( '#datetimepicker1 input' ).val( moment().subtract( 2, 'days' ).format( 'YYYY-MM-DD HH:mm' ) );

	// get stat
	$( '#stat_data button' ).click();
} );
