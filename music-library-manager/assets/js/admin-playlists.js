(function () {
  'use strict';
  var container = document.querySelector('.mlm-admin-album-player');
  if (!container || typeof window.APlayer !== 'function') return;
  var audio;
  try { audio = JSON.parse(container.getAttribute('data-playlist') || '[]'); } catch (error) { return; }
  if (!Array.isArray(audio) || !audio.length) return;
  new window.APlayer({
    container: container,
    autoplay: false,
    theme: '#2271b1',
    preload: 'none',
    mutex: true,
    lrcType: audio.some(function (item) { return item.lrc; }) ? 1 : 0,
    listFolded: false,
    listMaxHeight: '480px',
    audio: audio
  });
}());
