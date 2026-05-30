/**
 * Clear rejected non-essential cookies early in document head.
 *
 * @package Oven
 */
( function () {
	'use strict';

	var cfg = window.ovenClearRejected || {};
	var names = cfg.names || [];
	var path = '; path=/';
	var exp = '; expires=Thu, 01 Jan 1970 00:00:01 GMT';
	var host = typeof location !== 'undefined' && location.hostname ? location.hostname : '';

	for ( var i = 0; i < names.length; i++ ) {
		var n = names[ i ];
		if ( typeof n !== 'string' || ! n ) {
			continue;
		}
		document.cookie = n + '=' + path + exp;
		if ( host && host.indexOf( '.' ) !== -1 ) {
			document.cookie = n + '=' + path + '; domain=' + host + exp;
			document.cookie = n + '=' + path + '; domain=.' + host + exp;
		}
	}
} )();
