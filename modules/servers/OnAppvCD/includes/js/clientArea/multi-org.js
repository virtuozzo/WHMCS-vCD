// app
var OnAppvCDApp = new Vue( {
	el:   '#app',
	data: {
		pools:      [],
		cost:     '',
	}
} );

function OnAppModule_render( data ) {
	for( i in data ) {
		OnAppvCDApp[i] = data[i];
	}
}