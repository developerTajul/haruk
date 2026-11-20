/**
 * Admin behaviour for the DLS Starter Plugin settings screen.
 *
 * Configuration is provided by wp_localize_script() as `window.dlsStarterAdmin`.
 */
(function (window, document) {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var button = document.getElementById('dls-starter-test-ajax');
		var output = document.getElementById('dls-starter-ajax-result');
		var config = window.dlsStarterAdmin;

		if (!button || !output || !config) {
			return;
		}

		button.addEventListener('click', function () {
			button.disabled = true;
			output.classList.remove('is-success', 'is-error');
			output.textContent = config.i18n.running;

			var body = new URLSearchParams();
			body.append('action', config.action);
			body.append('security', config.nonce);
			body.append('data', 'health-check');

			window.fetch(config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				},
				body: body.toString()
			})
				.then(function (response) {
					return response.json();
				})
				.then(function (payload) {
					var succeeded = Boolean(payload && payload.success);
					output.textContent = succeeded ? config.i18n.success : config.i18n.failure;
					output.classList.add(succeeded ? 'is-success' : 'is-error');
				})
				.catch(function () {
					output.textContent = config.i18n.failure;
					output.classList.add('is-error');
				})
				.finally(function () {
					button.disabled = false;
				});
		});
	});
})(window, document);
