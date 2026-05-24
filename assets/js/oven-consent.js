/**
 * Oven cookie consent – initializes CookieConsent and detection/save logic.
 *
 * @package Oven
 */

(function () {
	'use strict';

	var config = typeof window.ovenConsentConfig !== 'undefined' ? window.ovenConsentConfig : null;
	if (!config) {
		return;
	}

	var cookieName = config.cookieName || 'oven_cc';
	var revision = config.revision || 1;
	var categories = config.categories || { necessary: { readOnly: true, enabled: true }, nonessential: { readOnly: false, enabled: false } };
	var essentialTable = config.essentialTable || { headers: {}, body: [] };
	var nonessentialTable = config.nonessentialTable || { headers: {}, body: [] };

	function normalizeCookieTable(table) {
		if (!table || !table.headers || !table.body || !Array.isArray(table.body)) return null;
		var headers = table.headers;
		var body = table.body.map(function (row) {
			var out = {};
			for (var key in headers) {
				if (Object.prototype.hasOwnProperty.call(headers, key)) {
					out[key] = row && typeof row[key] !== 'undefined' ? String(row[key]) : '';
				}
			}
			return out;
		});
		return { headers: headers, body: body };
	}
	var normalizedEssential = normalizeCookieTable(essentialTable);
	var normalizedNonessential = normalizeCookieTable(nonessentialTable);
	var noNonessentialNote = config.noNonessentialNote || '';
	var t = config.translations || {};
	var locale = config.locale || 'en';
	var loggedIn = !!config.loggedIn;
	var detectionMode = !!config.detectionMode;
	var ajaxUrl = config.ajaxUrl || '';
	var nonceConsent = config.nonceConsent || '';
	var nonceDetect = config.nonceDetect || '';

	function runConsent() {
		// Library defaults to secure: true; on HTTP the browser will not set the cookie, so consent never persists.
		var isSecure = typeof location !== 'undefined' && location.protocol === 'https:';
		// Use both cookie and localStorage for guests so consent survives clearing one or the other; on login we can sync from either.
		var cookieConfig = {
			name: cookieName,
			path: '/',
			expiresAfterDays: 182,
			useLocalStorage: true,
			secure: isSecure,
			sameSite: 'Lax'
		};

		// Logged-in users: consent stored in DB; we inject cookie from PHP. Still set cookie on accept so library is happy until next reload.
		var lang = {
			default: locale,
			translations: {}
		};
		lang.translations[locale] = {
			consentModal: {
				title: t.title || 'We use cookies',
				description: (t.description || '') + ' <a href="#" data-cc="show-preferencesModal" class="cc__link">' + (t.managePreferences || 'Manage preferences') + '</a>',
				acceptAllBtn: t.acceptAll || 'Accept all',
				acceptNecessaryBtn: t.rejectAll || 'Essential only',
				revisionMessage: t.revisionMessage || ''
			},
			preferencesModal: (function () {
				var sections = [];
				if (noNonessentialNote) {
					sections.push({
						title: '',
						description: noNonessentialNote
					});
				}
				sections.push({
					title: t.necessaryTitle || 'Essential cookies',
					description: t.necessaryDesc || '',
					linkedCategory: 'necessary',
					cookieTable: normalizedEssential && normalizedEssential.body.length ? normalizedEssential : undefined
				});
				sections.push({
					title: t.nonessentialTitle || 'Non-essential cookies',
					description: t.nonessentialDesc || '',
					linkedCategory: 'nonessential',
					cookieTable: normalizedNonessential && normalizedNonessential.body.length ? normalizedNonessential : undefined
				});
				return {
					title: t.preferencesTitle || 'Cookie preferences',
					acceptAllBtn: t.acceptAll || 'Accept all',
					acceptNecessaryBtn: t.rejectAll || 'Essential only',
					savePreferencesBtn: t.savePreferences || 'Save preferences',
					closeIconLabel: t.close || 'Close',
					sections: sections
				};
			})()
		};

		var runOptions = {
			cookie: cookieConfig,
			revision: revision,
			categories: categories,
			language: lang,
			guiOptions: {
				consentModal: { layout: 'box inline', position: 'bottom right' },
				preferencesModal: { layout: 'box', position: 'right' }
			},
			onFirstConsent: function (payload) {
				if (loggedIn && nonceConsent && payload && payload.cookie) {
					saveConsentToServer(payload.cookie);
				}
			},
			onConsent: function (payload) {
				if (loggedIn && nonceConsent && payload && payload.cookie) {
					saveConsentToServer(payload.cookie);
				}
			},
			onChange: function (payload) {
				if (loggedIn && nonceConsent && payload && payload.cookie) {
					saveConsentToServer(payload.cookie);
				}
			}
		};

		// Dark mode: add .cc--darkmode when (prefers-color-scheme: dark) OR body has .dark-mode.
		var mq = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)');
		function isDarkMode() {
			return (mq && mq.matches) || (document.body && document.body.classList.contains('dark-mode'));
		}
		function applySystemTheme() {
			var el = document.getElementById('cc-main');
			if (el) {
				el.classList.toggle('cc--darkmode', isDarkMode());
			}
		}
		if (mq) {
			mq.addEventListener('change', applySystemTheme);
		}
		// React to body.dark-mode class changes (e.g. theme toggle).
		var bodyObserver = null;
		if (window.MutationObserver && document.body) {
			bodyObserver = new MutationObserver(applySystemTheme);
			bodyObserver.observe(document.body, { attributes: true, attributeFilter: ['class'] });
		}
		runOptions.onModalReady = function () {
			applySystemTheme();
			// Add a visible close button for the preferences modal (library close icon can be invisible in some themes).
			var header = document.querySelector('#cc-main .pm .pm__header');
			if (header && !document.querySelector('#cc-main .oven-pm-close')) {
				var closeBtn = document.createElement('button');
				closeBtn.type = 'button';
				closeBtn.className = 'oven-pm-close';
				closeBtn.setAttribute('aria-label', (t && t.close) ? t.close : 'Close');
				if (config.closeIconUrl) {
					var closeImg = document.createElement('img');
					closeImg.src = config.closeIconUrl;
					closeImg.alt = '';
					closeImg.setAttribute('aria-hidden', 'true');
					closeBtn.appendChild(closeImg);
				} else {
					closeBtn.innerHTML = '&times;';
				}
				closeBtn.addEventListener('click', function () {
					var nativeClose = document.querySelector('#cc-main .pm__close-btn');
					if (nativeClose) nativeClose.click();
				});
				header.appendChild(closeBtn);
			}
		};

		function trySyncConsentFromLocalStorage() {
			if (!loggedIn || !nonceConsent) return false;
			try {
				var hasCookie = document.cookie.indexOf(cookieName + '=') !== -1;
				if (hasCookie) return false;
				var raw = typeof localStorage !== 'undefined' && localStorage.getItem(cookieName);
				if (!raw || typeof raw !== 'string') return false;
				var data = JSON.parse(raw);
				if (!data || typeof data !== 'object' || Number(data.revision) !== Number(revision)) return false;
				saveConsentToServer(data);
				return true;
			} catch (e) {
				return false;
			}
		}

		if (typeof window.CookieConsent !== 'undefined' && typeof window.CookieConsent.run === 'function') {
			window.CookieConsent.run(runOptions);
		}
		applySystemTheme();

		// If we have consent in the cookie but it was not in user meta (e.g. save failed), sync it to the server so the banner does not keep reappearing.
		if (window._ovenSyncConsentFromCookie && loggedIn && nonceConsent) {
			var toSync = window._ovenSyncConsentFromCookie;
			delete window._ovenSyncConsentFromCookie;
			saveConsentToServer(toSync);
		} else if (loggedIn && nonceConsent) {
			// Logged in but no cookie sync from PHP; try localStorage (guest may have accepted with useLocalStorage so only localStorage has consent).
			trySyncConsentFromLocalStorage();
		}

		// Any element with class "cookie-settings" opens the preferences modal (e.g. block or custom links).
		document.addEventListener('click', function (ev) {
			var el = ev.target && ev.target.closest && ev.target.closest('.cookie-settings');
			if (!el) return;
			ev.preventDefault();
			if (typeof window.CookieConsent !== 'undefined' && typeof window.CookieConsent.showPreferences === 'function') {
				window.CookieConsent.showPreferences();
			}
		});
	}

	function saveConsentToServer(cookieData) {
		if (!ajaxUrl || !nonceConsent) return;
		var sessCookieName = config.sessionVerifiedCookieName || 'oven_cc_sess';
		var xhr = new XMLHttpRequest();
		xhr.open('POST', ajaxUrl, true);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
		xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
		xhr.onreadystatechange = function () {
			if (xhr.readyState !== 4) return;
			var ok = xhr.status >= 200 && xhr.status < 300;
			if (ok) {
				try {
					document.cookie = sessCookieName + '=; path=/; expires=Thu, 01 Jan 1970 00:00:01 GMT';
				} catch (e) {}
			}
		};
		var body = 'action=oven_save_consent&oven_nonce=' + encodeURIComponent(nonceConsent) + '&consent=' + encodeURIComponent(JSON.stringify(cookieData));
		xhr.send(body);
	}

	function getCookieNames() {
		var doc = document.cookie || '';
		var parts = doc.split(/;\s*/);
		var names = [];
		for (var i = 0; i < parts.length; i++) {
			var pair = parts[i].split('=');
			var name = pair[0] ? pair[0].trim() : '';
			if (name && name !== cookieName) {
				names.push(name);
			}
		}
		return names;
	}

	function sendDetectedCookies() {
		if (!detectionMode || !nonceDetect) return;
		var names = getCookieNames();
		if (names.length === 0 && !(window._ovenCookieScriptMap && Object.keys(window._ovenCookieScriptMap).length)) return;

		var body = 'action=oven_detect_cookies&oven_nonce=' + encodeURIComponent(nonceDetect);
		if (names.length > 0) {
			body += '&cookies[]=' + names.map(function (n) { return encodeURIComponent(n); }).join('&cookies[]=');
		}
		if (window._ovenCookieScriptMap && typeof window._ovenCookieScriptMap === 'object') {
			var map = window._ovenCookieScriptMap;
			body += '&script_mapping=' + encodeURIComponent(JSON.stringify(map));
		}

		var xhr = new XMLHttpRequest();
		xhr.open('POST', ajaxUrl, true);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
		xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
		xhr.send(body);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', runConsent);
	} else {
		runConsent();
	}

	if (detectionMode && nonceDetect) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', function () {
				sendDetectedCookies();
				setInterval(sendDetectedCookies, 15000);
			});
		} else {
			sendDetectedCookies();
			setInterval(sendDetectedCookies, 15000);
		}
	}
})();
