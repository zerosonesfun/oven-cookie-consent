/**
 * Cookie setter tracing for detection mode (administrators).
 *
 * @package Oven
 */
( function () {
	'use strict';

	var cfg = window.ovenCookieTracer || {};
	var skipName = cfg.consentCookieName || 'oven_cc';

	window._ovenCookieScriptMap = {};
	try {
		var d = document;
		var origDesc =
			Object.getOwnPropertyDescriptor( Document.prototype, 'cookie' ) ||
			Object.getOwnPropertyDescriptor( d, 'cookie' );
		if ( ! origDesc || ! origDesc.set ) {
			return;
		}
		var set = origDesc.set;
		Object.defineProperty( d, 'cookie', {
			set: function ( v ) {
				if ( typeof v === 'string' ) {
					var name = v.split( '=' )[ 0 ].trim();
					if ( name && name !== skipName ) {
						try {
							var err = new Error();
							var stack = err.stack || '';
							var lines = stack.split( '\n' );
							for ( var i = 0; i < lines.length; i++ ) {
								var line = lines[ i ];
								var match = line.match( /(https?:\/\/[^\s\)]+\.js)/ );
								if ( match ) {
									window._ovenCookieScriptMap[ name ] = match[ 1 ];
									break;
								}
							}
						} catch ( e ) {
							/* ignore */
						}
					}
				}
				return set.call( this, v );
			},
			get: origDesc.get,
			configurable: true,
			enumerable: origDesc.enumerable,
		} );
	} catch ( e ) {
		/* ignore */
	}
} )();
