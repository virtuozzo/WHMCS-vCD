$( document ).ready( function() {
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

	// getting stat handler
	$( '#stat_data button' ).on( 'click', function() {
		$( 'tr#error' ).hide();
		var btn = $( this );
		OnAppModule_toggleButton( btn, 'process', true );

		//$( '#stat_data tbody' ).hide('fast');
		$( '#app' ).addClass( 'app-hidden' );
		var outstandingDetailsData = {
			getstat:   1,
			modop:     'custom',
			a:         'OutstandingDetails',
			start:     $( '#datetimepicker1 input' ).val(),
			end:       $( '#datetimepicker2 input' ).val(),
			tz_offset: function() {
				var myDate = new Date();
				offset = myDate.getTimezoneOffset();
				return offset;
			}
		};
		if (typeof window.onappvcd_serviceid !== 'undefined') {
			outstandingDetailsData.id = window.onappvcd_serviceid;
		}
		$.ajax( {
			url:     document.location.href,
			data:    outstandingDetailsData,
			error:   function() {
				$( '#stat_data tbody' ).addClass( 'app-hidden' );
				$( 'tr#error' ).show();
			},
			success: function( data ) {
				OnAppModule_render( data );
				$( '#app' ).toggleClass( 'app-hidden' );
			}
		} ).always( function() {
			OnAppModule_toggleButton( btn, 'reset', true );
		} );
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