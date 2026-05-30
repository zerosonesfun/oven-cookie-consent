/**
 * Logged-in consent cookie bootstrap (set, sync from request, or clear).
 *
 * @package Oven
 */
( function () {
	'use strict';

	var cfg = window.ovenLoggedInConsent || {};
	var mode = cfg.mode || 'clear';
	var cookieName = cfg.cookieName || 'oven_cc';
	var sessionCookieName = cfg.sessionCookieName || 'oven_cc_sess';
	var secureSuffix = cfg.secureSuffix || '';

	function setCookie( name, value, expiresSec ) {
		var exp = expiresSec
			? '; expires=' + new Date( expiresSec * 1000 ).toUTCString()
			: '';
		document.cookie =
			name +
			'=' +
			encodeURIComponent( value ) +
			'; path=/; SameSite=Lax' +
			exp +
			secureSuffix;
	}

	function clearCookies() {
		var path = '; path=/';
		var exp = '; expires=Thu, 01 Jan 1970 00:00:01 GMT';
		document.cookie = cookieName + '=' + path + exp;
		document.cookie = sessionCookieName + '=' + path + exp;
		var host = typeof location !== 'undefined' && location.hostname ? location.hostname : '';
		if ( host && host.indexOf( '.' ) !== -1 ) {
			document.cookie = cookieName + '=' + path + '; domain=' + host + exp;
			document.cookie = cookieName + '=' + path + '; domain=.' + host + exp;
			document.cookie = sessionCookieName + '=' + path + '; domain=' + host + exp;
			document.cookie = sessionCookieName + '=' + path + '; domain=.' + host + exp;
		}
	}

	if ( mode === 'set' ) {
		var cookieValue = cfg.cookieValue || '';
		var expiresSec = parseInt( cfg.expiresSec, 10 ) || 0;
		setCookie( cookieName, cookieValue, expiresSec );
		if ( cfg.sessionPayload ) {
			setCookie( sessionCookieName, cfg.sessionPayload, 0 );
		}
		return;
	}

	if ( mode === 'sync' ) {
		var syncValue = cfg.cookieValue || '';
		var syncExpires = parseInt( cfg.expiresSec, 10 ) || 0;
		setCookie( cookieName, syncValue, syncExpires );
		if ( cfg.syncPayload && typeof cfg.syncPayload === 'object' ) {
			window._ovenSyncConsentFromCookie = cfg.syncPayload;
		}
		return;
	}

	clearCookies();
} )();
