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

			if (!audio.url) {
				return;
			}

			container.setAttribute('data-mlm-ready', 'true');
			new window.APlayer({
				container: container,
				preload: 'none',
				lrcType: audio.lrc ? 1 : 0,
				audio: [audio]
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { initializePlayers(document); });
	} else {
		initializePlayers(document);
	}
}());
