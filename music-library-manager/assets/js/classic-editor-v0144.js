(function ($) {
  'use strict';
  const library = window.mlmClassicEditor?.library || { tracks: [], playlists: [] };
  const escapeHtml = value => $('<div>').text($('<textarea>').html(value || '').text()).html();
  let mode = 'track';
	let autoplay = false;

  function findItem(kind, id) {
    const list = kind === 'playlist' ? library.playlists : library.tracks;
    return list.find(item => Number(item.id) === Number(id)) || {};
  }

  function marker(kind, id, lyrics, autoPlayValue) {
    const item = findItem(kind, id);
    const title = kind === 'playlist' ? (item.name || '音乐播放列表') : (item.title || '音乐');
    const meta = kind === 'playlist' ? '' : (item.artist || '未知歌手');
    const cover = item.cover || item.tracks?.[0]?.cover || '';
    return '<span class="mlm-classic-preview" style="display:inline-flex;width:360px;height:72px;box-sizing:border-box;align-items:center;gap:10px;padding:8px 12px;" contenteditable="false" data-mlm-kind="' + kind + '" data-mlm-id="' + Number(id) + '" data-mlm-lyrics="' + (lyrics === '0' ? '0' : '1') + '" data-mlm-autoplay="' + (/^(1|yes|true|on)$/i.test(autoPlayValue || '') ? '1' : '0') + '">' +
      (cover ? '<img src="' + escapeHtml(cover) + '" alt="" width="48" height="48" style="width:48px;height:48px;object-fit:cover;border-radius:9px;display:block;">' : '<span class="mlm-classic-preview-cover" style="width:48px;height:48px;display:grid;place-items:center;">♫</span>') +
      '<span class="mlm-classic-preview-info"><strong>' + escapeHtml(title) + '</strong>' + (meta ? '<small>' + escapeHtml(meta) + '</small>' : '') + '</span></span>';
  }

  function visualize(html) {
    if (!html) return html;
    const attribute = function (source, name) { const match = source.match(new RegExp('(?:^|\\s)' + name + '=["\\\']?([^"\\\'\\s]+)', 'i')); return match ? match[1] : ''; };
    html = html.replace(/\[music_playlist\b([^\]]*)\]/gi, function (shortcode, attrs) { const id = attribute(attrs, 'id'); return id ? marker('playlist', id, '1', attribute(attrs, 'autoplay')) : shortcode; });
    return html.replace(/\[music(?!_playlist)\b([^\]]*)\]/gi, function (shortcode, attrs) { const id = attribute(attrs, 'id'); return id ? marker('track', id, attribute(attrs, 'lyrics'), attribute(attrs, 'autoplay')) : shortcode; });
  }

  function restore(html) {
    if (!html) return html;
    const root = document.createElement('div');
    root.innerHTML = html;
    root.querySelectorAll('span.mlm-classic-preview').forEach(card => {
      const kind = card.dataset.mlmKind;
      const id = Number.parseInt(card.dataset.mlmId || '', 10);
      if (!id || !['track', 'playlist'].includes(kind)) return;
      const autoPlayValue = /^(1|yes|true|on)$/i.test(card.dataset.mlmAutoplay || '') ? 'yes' : 'no';
      const lyrics = /^(0|no|false|off)$/i.test(card.dataset.mlmLyrics || '') ? 'no' : 'yes';
      const shortcode = kind === 'playlist'
        ? '[music_playlist id="' + id + '" autoplay="' + autoPlayValue + '"]'
        : '[music id="' + id + '" lyrics="' + lyrics + '" autoplay="' + autoPlayValue + '"]';
      card.replaceWith(document.createTextNode(shortcode));
    });
    return root.innerHTML;
  }

  function setupPreview(editor) {
    if (!editor || editor.__mlmPreviewSetup) return;
    editor.__mlmPreviewSetup = true;
    let busy = false;
    editor.on('BeforeSetContent', function (event) { if (!busy && event.content) event.content = visualize(event.content); });
    editor.on('BeforeGetContent', function (event) { if (!busy && event.content) event.content = restore(event.content); });
    editor.on('PostProcess', function (event) { if (!busy && event.get && event.content) event.content = restore(event.content); });
    editor.on('SaveContent', function (event) { if (!busy && event.content) event.content = restore(event.content); });
    const apply = function () {
      if (busy || !editor.initialized) return;
      const raw = editor.getContent({ format: 'raw' });
      const visual = visualize(restore(raw));
      if (visual !== raw) { busy = true; editor.setContent(visual, { format: 'raw', no_events: true }); busy = false; }
    };
    if (editor.initialized) apply(); else editor.on('init', apply);
  }

  function watchEditors() {
    if (!window.tinyMCE) return;
    Object.keys(window.tinyMCE.editors || {}).forEach(key => setupPreview(window.tinyMCE.editors[key]));
  }

  function insertShortcode(shortcode) {
    if (window.tinyMCE?.activeEditor && !window.tinyMCE.activeEditor.isHidden()) {
      window.tinyMCE.activeEditor.execCommand('mceInsertContent', false, shortcode);
    } else if (window.QTags?.insertContent) {
      window.QTags.insertContent(shortcode);
    } else {
      const field = document.getElementById('content');
      if (field) field.value += shortcode;
    }
    closeModal();
  }

  function render() {
    const term = ($('#mlm-classic-search').val() || '').toLowerCase();
    const source = mode === 'playlist' ? library.playlists : library.tracks;
    const filtered = source.filter(item => {
      const text = mode === 'playlist' ? item.name + ' ' + (item.tracks || []).map(track => track.title + ' ' + track.artist).join(' ') : item.title + ' ' + item.artist + ' ' + item.album;
      return text.toLowerCase().includes(term);
    });
    $('#mlm-classic-count').text('共 ' + filtered.length + ' 项');
    $('#mlm-classic-results').html(filtered.map(item => {
      const title = mode === 'playlist' ? item.name : item.title;
      const meta = mode === 'playlist' ? item.count + ' 首歌曲' : (item.artist || '未知歌手');
      const detail = mode === 'playlist' ? (item.tracks || []).slice(0, 5).map(track => track.title).join(' · ') : (item.album || '暂无专辑');
      const cover = item.cover || item.tracks?.[0]?.cover || '';
      return '<article class="mlm-classic-card">' + (cover ? '<img src="' + escapeHtml(cover) + '" alt="" loading="lazy">' : '<span class="mlm-classic-cover-empty">♫</span>') + '<div><strong>' + escapeHtml(title) + '</strong><span>' + escapeHtml(meta) + '</span><small>' + escapeHtml(detail) + '</small><button type="button" class="button button-primary mlm-classic-choose" data-id="' + Number(item.id) + '">插入文章</button></div></article>';
    }).join('') || '<p class="mlm-classic-empty">没有找到可用内容。</p>');
  }

  function openModal() {
    if (!$('#mlm-classic-modal').length) {
      $('body').append('<div id="mlm-classic-modal" class="mlm-classic-modal" role="dialog" aria-modal="true" aria-labelledby="mlm-classic-title"><div class="mlm-classic-dialog"><header><h2 id="mlm-classic-title">插入音乐</h2><button type="button" class="mlm-classic-close" aria-label="关闭">×</button></header><div class="mlm-classic-toolbar"><div><button type="button" class="button button-primary mlm-classic-mode" data-mode="track">单曲</button><button type="button" class="button mlm-classic-mode" data-mode="playlist">播放列表</button></div><label><input type="checkbox" id="mlm-classic-autoplay"> 页面打开后自动播放</label><input type="search" id="mlm-classic-search" placeholder="搜索歌曲、作者、专辑或歌单…"><span id="mlm-classic-count"></span></div><div id="mlm-classic-results" class="mlm-classic-results"></div></div></div>');
    }
    autoplay = false;
    $('#mlm-classic-autoplay').prop('checked', false);
    $('#mlm-classic-modal').show(); $('body').addClass('mlm-classic-open'); render(); $('#mlm-classic-search').trigger('focus');
  }
  function closeModal() { $('#mlm-classic-modal').hide(); $('body').removeClass('mlm-classic-open'); }

  $(document).on('click', '#mlm-classic-insert', openModal)
    .on('click', '.mlm-classic-close', closeModal)
    .on('click', '#mlm-classic-modal', event => { if (event.target.id === 'mlm-classic-modal') closeModal(); })
    .on('input', '#mlm-classic-search', render)
		.on('change', '#mlm-classic-autoplay', function () { autoplay = this.checked; })
    .on('click', '.mlm-classic-mode', function () { mode = $(this).data('mode'); $('.mlm-classic-mode').removeClass('button-primary'); $(this).addClass('button-primary'); render(); })
    .on('click', '.mlm-classic-choose', function () { const id = Number($(this).data('id')); const value = autoplay ? 'yes' : 'no'; insertShortcode(mode === 'playlist' ? '[music_playlist id="' + id + '" autoplay="' + value + '"]' : '[music id="' + id + '" autoplay="' + value + '"]'); })
    .on('keydown', event => { if (event.key === 'Escape') closeModal(); });
  $(watchEditors);
  $('#post').on('submit.mlmClassicPreview', function () {
    if (window.tinyMCE?.get('content') && !window.tinyMCE.get('content').isHidden()) {
      window.tinyMCE.get('content').save();
    }
    const field = document.getElementById('content');
    if (field) field.value = restore(field.value);
  });
  setInterval(watchEditors, 500);
})(jQuery);
