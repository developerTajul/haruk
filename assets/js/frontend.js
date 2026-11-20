/**
 * Public frontend helper for the DLS Starter Plugin.
 *
 * Configuration is provided by wp_localize_script() as `window.dlsStarterFrontend`.
 * `dlsStarterFrontend.post( data )` resolves with the parsed AJAX response.
 */
(function (window) {
	'use strict';

	var config = window.dlsStarterFrontend || {};

	config.post = function (data) {
		var body = new URLSearchParams();
		body.append('action', config.action);
		body.append('security', config.nonce);
		body.append('data', data);

		return window
			.fetch(config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				},
				body: body.toString()
			})
			.then(function (response) {
				return response.json();
			});
	};

	window.dlsStarterFrontend = config;
})(window);
