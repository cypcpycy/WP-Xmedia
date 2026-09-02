(function () {
	'use strict';
	var warmups = Object.create(null);

	function preconnectTracks(tracks) {
		var origins = {};
		tracks.forEach(function (track) {
			['url', 'cover'].forEach(function (key) {
				if (!track[key]) { return; }
				try { origins[new URL(track[key], window.location.href).origin] = true; } catch (error) {}
			});
		});
		Object.keys(origins).forEach(function (origin) {
			if (document.querySelector('link[rel="preconnect"][href="' + origin + '"]')) { return; }
			var link = document.createElement('link');
			link.rel = 'preconnect'; link.href = origin; link.crossOrigin = 'anonymous';
			document.head.appendChild(link);
		});
	}

	function canWarmTracks() {
		var connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
		if (!connection) { return true; }
		if (connection.saveData) { return false; }
		return !/^(slow-)?2g$/i.test(connection.effectiveType || '');
	}

	/* Warm the browser HTTP cache used by APlayer instead of a separate Audio buffer. */
	function warmTrack(tracks, index) {
		if (!canWarmTracks() || !tracks.length) { return; }
		index = Number(index) % tracks.length;
		if (index < 0 || !tracks[index] || !tracks[index].url) { return; }
		var url = tracks[index].url;
		if (warmups[url]) { return; }
		warmups[url] = fetch(url, {
			method: 'GET', mode: 'no-cors', credentials: 'omit',
			cache: 'force-cache', priority: 'low'
		}).catch(function () { delete warmups[url]; });
	}

	function buildPlayer(container, tracks) {
		tracks = (Array.isArray(tracks) ? tracks : []).filter(function (item) { return item && item.url; });
		if (!tracks.length || typeof window.APlayer !== 'function') {
			container.setAttribute('data-mlm-ready', 'error');
			return;
		}
		preconnectTracks(tracks);
		container.setAttribute('data-mlm-ready', 'true');
		var player = new window.APlayer({
			container: container,
			autoplay: /^(1|yes|true|on)$/i.test(container.getAttribute('data-mlm-autoplay') || ''),
			theme: '#5895be', preload: 'auto', mutex: true,
			lrcType: tracks.some(function (item) { return item.lrc; }) ? 1 : 0,
			listFolded: false, listMaxHeight: '349px', audio: tracks
		});
		var pendingWarmIndex = tracks.length > 1 ? 1 : -1;
		var warmPendingTrack = function () {
			if (pendingWarmIndex < 0) { return; }
			var index = pendingWarmIndex;
			pendingWarmIndex = -1;
			window.setTimeout(function () { warmTrack(tracks, index); }, 250);
		};
		player.on('canplay', warmPendingTrack);
		player.on('playing', warmPendingTrack);
		player.on('listswitch', function (event) {
			var index = event && typeof event === 'object' ? Number(event.index) : Number(event);
			if (!Number.isNaN(index)) { pendingWarmIndex = (index + 1) % tracks.length; }
		});
	}

	function initializePlayers(root) {
		(root || document).querySelectorAll('.mlm-aplayer:not([data-mlm-ready])').forEach(function (container) {
			var token = container.getAttribute('data-mlm-token') || '';
			var endpoint = container.getAttribute('data-mlm-endpoint') || '';
			if (!token || !endpoint) { return; }
			container.setAttribute('data-mlm-ready', 'loading');
			var body = new URLSearchParams(); body.set('action', 'mlm_player_data'); body.set('token', token);
			var request = window.mlmPlayerRequests && window.mlmPlayerRequests[token];
			if (!request) {
				request = fetch(endpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() })
					.then(function (response) { if (!response.ok) { throw new Error('player request failed'); } return response.json(); });
			}
			request
				.then(function (result) { buildPlayer(container, result && result.success && result.data ? result.data.audio : []); })
				.catch(function () { container.setAttribute('data-mlm-ready', 'error'); });
		});
	}

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', function () { initializePlayers(document); }); }
	else { initializePlayers(document); }
}());
