(function () {
	'use strict';
	var preloaders = [];

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

	function warmTrack(tracks, index) {
		if (!tracks.length) { return; }
		index = Number(index) % tracks.length;
		if (index < 0 || !tracks[index] || !tracks[index].url) { return; }
		if (preloaders.some(function (item) { return item.url === tracks[index].url; })) { return; }
		var audio = new Audio();
		audio.preload = 'auto'; audio.src = tracks[index].url; audio.load();
		var entry = { url: tracks[index].url, audio: audio };
		preloaders.push(entry);
		window.setTimeout(function () {
			var position = preloaders.indexOf(entry);
			if (position !== -1) { preloaders.splice(position, 1); }
			audio.removeAttribute('src'); audio.load();
		}, 120000);
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
		if (tracks.length > 1) { warmTrack(tracks, 1); }
		player.on('listswitch', function (event) {
			var index = event && typeof event === 'object' ? Number(event.index) : Number(event);
			if (!Number.isNaN(index)) { warmTrack(tracks, index + 1); }
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
