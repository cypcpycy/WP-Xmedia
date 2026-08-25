(function () {
	'use strict';

	function initializePlayers(root) {
		if (typeof window.APlayer !== 'function') {
			return;
		}

		(root || document).querySelectorAll('.mlm-aplayer:not([data-mlm-ready])').forEach(function (container) {
			var audio;
			try {
				audio = JSON.parse(container.getAttribute('data-mlm-audio') || '{}');
			} catch (error) {
				return;
			}

			var tracks = Array.isArray(audio) ? audio.filter(function (item) { return item && item.url; }) : (audio.url ? [audio] : []);
			if (!tracks.length) {
				return;
			}

			container.setAttribute('data-mlm-ready', 'true');
			new window.APlayer({
				container: container,
				preload: 'none',
				mutex: true,
				lrcType: tracks.some(function (item) { return item.lrc; }) ? 1 : 0,
				listFolded: false,
				listMaxHeight: '420px',
				audio: tracks
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { initializePlayers(document); });
	} else {
		initializePlayers(document);
	}
}());
