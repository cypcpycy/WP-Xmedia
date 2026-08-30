(function () {
	'use strict';

	function buildPlayer(container, tracks) {
		tracks = (Array.isArray(tracks) ? tracks : []).filter(function (item) { return item && item.url; });
		if (!tracks.length || typeof window.APlayer !== 'function') {
			container.setAttribute('data-mlm-ready', 'error');
			return;
		}
		container.setAttribute('data-mlm-ready', 'true');
		new window.APlayer({
			container: container,
			autoplay: /^(1|yes|true|on)$/i.test(container.getAttribute('data-mlm-autoplay') || ''),
			theme: '#5895be', preload: 'none', mutex: true,
			lrcType: tracks.some(function (item) { return item.lrc; }) ? 1 : 0,
			listFolded: false, listMaxHeight: '349px', audio: tracks
		});
	}

	function initializePlayers(root) {
		(root || document).querySelectorAll('.mlm-aplayer:not([data-mlm-ready])').forEach(function (container) {
			var token = container.getAttribute('data-mlm-token') || '';
			var endpoint = container.getAttribute('data-mlm-endpoint') || '';
			if (!token || !endpoint) { return; }
			container.setAttribute('data-mlm-ready', 'loading');
			var body = new URLSearchParams(); body.set('action', 'mlm_player_data'); body.set('token', token);
			fetch(endpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() })
				.then(function (response) { if (!response.ok) { throw new Error('player request failed'); } return response.json(); })
				.then(function (result) { buildPlayer(container, result && result.success && result.data ? result.data.audio : []); })
				.catch(function () { container.setAttribute('data-mlm-ready', 'error'); });
		});
	}

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', function () { initializePlayers(document); }); }
	else { initializePlayers(document); }
}());
