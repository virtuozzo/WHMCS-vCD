$( document ).ready( function( ) {
	// workaround bootstrap.min.js issue
	$.getScript( '/assets/js/bootstrap.min.js', function() {
		$( '#stat_data button' ).click();
	} );

	// generate new password
	$( '#change-password' ).on( 'click', function() {
		$( '#gotocp .alert' ).hide();
		var btn = $( this );
		btn.button( 'loading' );

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
			btn.button( 'reset' );
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
} );
