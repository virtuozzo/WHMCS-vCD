$( document ).ready(
function( ) {
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
	// convert trial
	$( '#convert-trial' ).on( 'click', function() {
		$( '#gotocp .alert' ).hide();
		var btn = $( this );
		btn.button( 'loading' );

		$.ajax( {
			url: document.location.href,
			data: {
				modop: 'custom',
				a: 'ConvertTrial'
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
					$( '#convert-trial' ).hide();
				}
				else {
					alert.addClass( 'alert-danger' ).show( 'fast' );
				}
			}
		} ).always( function() {
			btn.button( 'reset' );
		} );
	} );
} );

$( document ).ready( function() {
	// set datetime pickers
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

	// ajax
	$( '#stat_data button' ).on( 'click', function() {
		$( 'tr#error' ).hide();
		$( '#stat_data tbody' ).fadeTo( 'fast', 0.1 );
		var btn = $( this );
		btn.button( 'loading' );

		$.ajax( {
			url: document.location.href,
			data: {
				getstat: 1,
				modop: 'custom',
				a: 'OutstandingDetails',
				start: $( '#datetimepicker1 input' ).val(),
				end: $( '#datetimepicker2 input' ).val(),
				tz_offset: function() {
					var myDate = new Date();
					offset = myDate.getTimezoneOffset();
					return offset;
				}
			},
			error: function() {
				$( '#stat_data tbody' ).hide();
				$( 'tr#error' ).show();
			},
			success: function( data ) {
				data = JSON.parse( data );
				processData( data );
			}
		} ).always( function() {
			btn.button( 'reset' );
		} );
	} );
	//$( '#stat_data button' ).click();
} );

function processData( data ) {
	if( data ) {
		for( i in data ) {
			if( ( data.hideZeroEntries == 'on' ) && ( i != 'total_cost' ) ) {
				if( data[i] >= 0.01 ) {
					val = accounting.formatMoney( data[i], {symbol: data.currency_code, format: "%v %s"} );
					$( '#' + i ).text( val );
					$( '#' + i ).parent().show();
				}
				else {
					$( '#' + i ).parent().hide();
				}
			}
			else {
				val = accounting.formatMoney( data[i], {symbol: data.currency_code, format: "%v %s"} );
				$( '#' + i ).text( val );
				$( '#' + i ).parent().show();
			}
		}
		$( '#stat_data tbody' ).fadeTo( 'fast', 1 );
	}
	else {
		$( '#stat_data tbody' ).hide();
		$( 'tr#error' ).show();
	}
}