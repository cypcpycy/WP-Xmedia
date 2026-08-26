(function () {
  'use strict';
  document.querySelectorAll('.mlm-delete-playlist-form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (form.dataset.confirmed === '1') return;
      event.preventDefault();
      var withTracks = window.confirm('是否同时永久删除这个播放列表中的所有歌曲记录和相关媒体文件？\n\n确定：删除列表、歌曲和文件\n取消：只删除列表');
      form.querySelector('[name="delete_mode"]').value = withTracks ? 'with_tracks' : 'playlist_only';
      var finalMessage = withTracks
        ? '此操作会永久删除歌单内的歌曲记录及其独占媒体文件，无法恢复。确定继续吗？'
        : '将只删除播放列表，歌曲记录和媒体文件都会保留。确定继续吗？';
      if (!window.confirm(finalMessage)) return;
      form.dataset.confirmed = '1';
      form.submit();
    });
  });
}());
