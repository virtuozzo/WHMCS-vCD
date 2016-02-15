$( document ).ready( function() {
	// app
	var OnAppvCDApp = new Vue( {
		el: '#app',
		data: {
			vms: [],
			cost: '',
			currency: ''
		}
	} );

	// ajax
	$( '#stat_data button' ).on( 'click', function() {
		$( 'tr#error' ).hide();
		//$( '#stat_data tbody' ).fadeTo( 'fast', 0.1 );
		var btn = $( this );
		btn.button( 'loading' );

		$( '#stat_data tbody' ).hide();
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
				data.cost = accounting.formatMoney( data.cost, {
					symbol: data.currency[LANG.MoneySymbol],
					format: LANG.MoneyFormat,
					precision: LANG.MoneyPrecision
				} );
				for( i in data.vms ) {
					data.vms[i].cost = accounting.formatMoney( data.vms[i].cost, {
						symbol: data.currency[LANG.MoneySymbol],
						format: LANG.MoneyFormat,
						precision: LANG.MoneyPrecision
					} );
				}
				for( i in data ) {
					OnAppvCDApp[i] = data[i];
				}

				$( '#stat_data tbody' ).show();
			}
		} ).always( function() {
			btn.button( 'reset' );
		} );
	} );
} );