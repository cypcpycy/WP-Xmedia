(function ($) {

  $(document).on('submit', 'form:has(input[name="action"][value="mlm_import_hermit_json"])', async function (event) {
    event.preventDefault();
    const $form = $(this), file = $form.find('input[type="file"]')[0]?.files?.[0], $button = $form.find('input[type="submit"]');
    if (!file) return;
    let records;
    try { records = JSON.parse(await file.text()); } catch (error) { window.alert('JSON 文件格式无效。'); return; }
    if (!Array.isArray(records)) { window.alert('JSON 文件必须包含歌曲记录数组。'); return; }
	$button.prop('disabled', true).val('正在检查音乐库…');
	let filterResult;
	try {
	  filterResult = await $.post(mlmAdmin.ajaxUrl, { action: 'mlm_filter_hermit_records', nonce: $form.find('input[name="_wpnonce"]').val(), records: JSON.stringify(records) });
	} catch (error) {
	  $button.prop('disabled', false).val('重新读取文件'); window.alert('检查音乐库现有记录失败，请稍后重试。'); return;
	}
	if (!filterResult?.success) { $button.prop('disabled', false).val('重新读取文件'); window.alert(filterResult?.data?.message || '检查音乐库现有记录失败。'); return; }
	const availableIndexes = filterResult.data.available_indexes || [], filtered = Number(filterResult.data.filtered || 0);
	$form.data('mlmHermitRecords', records);
	let $selector = $form.find('.mlm-hermit-selector');
	if (!$selector.length) {
	  $selector = $('<section class="mlm-hermit-selector" style="max-width:760px;margin-top:16px"><h3>选择需要导入的歌曲</h3><p class="mlm-hermit-filter-summary"></p><p><label><input type="checkbox" class="mlm-hermit-select-all"> 全选 / 取消全选</label>　<span class="mlm-hermit-selected-count">已选择 0 首</span></p><div class="mlm-hermit-song-list" style="max-height:420px;overflow:auto;border:1px solid #ccd0d4;background:#fff;padding:8px"></div><p><button type="button" class="button button-primary mlm-hermit-import-selected">导入所选歌曲</button></p></section>').appendTo($form);
	}
	const $list = $selector.find('.mlm-hermit-song-list').empty();
	availableIndexes.forEach(index => { const item = records[index] || {}; $('<label style="display:flex;gap:8px;padding:7px;border-bottom:1px solid #eee"><input type="checkbox" class="mlm-hermit-song-check" data-index="' + index + '"><span><strong></strong><br><small></small></span></label>').find('strong').text(item.title || '未命名歌曲').end().find('small').text((item.artist || '') + (item.album ? ' — ' + item.album : '')).end().appendTo($list); });
	if (!availableIndexes.length) { $list.append('<p style="padding:8px">文件中的歌曲均已存在于音乐库，无需重复导入。</p>'); }
	$selector.find('.mlm-hermit-filter-summary').text('文件共 ' + records.length + ' 首；已过滤音乐库现有歌曲 ' + filtered + ' 首；待导入 ' + availableIndexes.length + ' 首。');
	$selector.find('.mlm-hermit-import-selected').prop('disabled', availableIndexes.length === 0);
	$selector.find('.mlm-hermit-select-all').prop('checked', false);
	$selector.find('.mlm-hermit-selected-count').text('已选择 0 首');
	$button.prop('disabled', false).val('重新读取文件');
	return;
  });

  $(document).on('change', '.mlm-hermit-select-all', function () {
	const $selector = $(this).closest('.mlm-hermit-selector');
	$selector.find('.mlm-hermit-song-check').prop('checked', this.checked).trigger('change');
  });

  $(document).on('change', '.mlm-hermit-song-check', function () {
	const $selector = $(this).closest('.mlm-hermit-selector');
	const total = $selector.find('.mlm-hermit-song-check').length, selected = $selector.find('.mlm-hermit-song-check:checked').length;
	$selector.find('.mlm-hermit-selected-count').text('已选择 ' + selected + ' 首');
	$selector.find('.mlm-hermit-select-all').prop('checked', total > 0 && selected === total);
  });

  $(document).on('click', '.mlm-hermit-import-selected', async function () {
	const $selector = $(this).closest('.mlm-hermit-selector'), $form = $selector.closest('form'), allRecords = $form.data('mlmHermitRecords') || [];
	const records = $selector.find('.mlm-hermit-song-check:checked').map(function () { return allRecords[Number($(this).data('index'))]; }).get();
	if (!records.length) { window.alert('请至少选择一首歌曲。'); return; }
	const $button = $(this).prop('disabled', true).text('正在导入…');
    let created = 0, repaired = 0, skipped = 0, failed = 0, mediaFailed = 0;
    let $progress = $form.find('.mlm-import-progress');
    if (!$progress.length) {
      $progress = $('<div class="mlm-import-progress" style="margin-top:12px;max-width:620px"><progress max="100" value="0" style="width:100%"></progress><p class="mlm-import-summary"></p><p class="mlm-import-current"></p></div>').appendTo($form);
    }
    const nonce = $form.find('input[name="_wpnonce"]').val();
    for (let index = 0; index < records.length; index++) {
      const item = records[index], processed = index + 1;
      $progress.find('progress').val(Math.round(processed / records.length * 100));
      $progress.find('.mlm-import-current').text('正在处理：' + (item.title || '未命名歌曲'));
      try {
        const response = await $.post(mlmAdmin.ajaxUrl, { action: 'mlm_import_hermit_item', nonce: nonce, item: JSON.stringify(item) });
        if (response?.success && response.data?.status === 'skipped') skipped++;
        else if (response?.success && response.data?.status === 'repaired') repaired++;
        else if (response?.success) created++; else failed++;
        if (response?.success && response.data?.asset_errors) mediaFailed += Object.keys(response.data.asset_errors).length;
      } catch (error) { failed++; }
      $progress.find('.mlm-import-summary').text('总计 ' + records.length + ' 条｜已处理 ' + processed + ' 条｜新增 ' + created + ' 条｜补齐 ' + repaired + ' 条｜跳过 ' + skipped + ' 条｜记录失败 ' + failed + ' 条｜媒体失败 ' + mediaFailed + ' 个');
      localStorage.setItem('mlmHermitImportProgress', JSON.stringify({ total: records.length, processed: processed, created: created, repaired: repaired, skipped: skipped, failed: failed, mediaFailed: mediaFailed }));
    }
    $progress.find('.mlm-import-current').text('导入完成。');
    localStorage.removeItem('mlmHermitImportProgress');
	$button.prop('disabled', false).text('再次导入所选歌曲');
  });
  'use strict';
  const $results = $('#mlm-search-results');
  const $message = $('#mlm-search-message');
  const escapeHtml = value => $('<div>').text(value || '').html();
  const request = data => $.post(mlmAdmin.ajaxUrl, Object.assign({ nonce: mlmAdmin.nonce }, data));
  const askDuplicate = (duplicate, details) => {
    const deferred = $.Deferred();
    let $dialog = $('#mlm-duplicate-dialog');
    if (!$dialog.length) {
      $('body').append('<div id="mlm-duplicate-dialog" class="mlm-duplicate-dialog" hidden><div class="mlm-duplicate-dialog__box" role="dialog" aria-modal="true" aria-labelledby="mlm-duplicate-title"><h2 id="mlm-duplicate-title">发现重复音乐文件</h2><p class="mlm-duplicate-dialog__message"></p><label class="mlm-duplicate-dialog__apply"><input type="checkbox" id="mlm-duplicate-apply"> 后续重复歌曲使用同一选择</label><div class="mlm-duplicate-dialog__actions"><button type="button" class="button button-primary" data-mlm-duplicate="reuse">引用已有文件</button><button type="button" class="button" data-mlm-duplicate="skip">取消导入</button></div></div></div>');
      $dialog = $('#mlm-duplicate-dialog');
    }
    if ($dialog.parent()[0] !== document.documentElement) { $dialog.detach().appendTo(document.documentElement); }
    $dialog.find('.mlm-duplicate-dialog__message').text((duplicate.message || '媒体库中已有完全相同的音频文件。') + (details || ''));
    $dialog.find('#mlm-duplicate-apply').prop('checked', false);
    $dialog.prop('hidden', false).css({ zIndex: 2147483647, pointerEvents: 'auto' });
    $dialog.off('click.mlmDuplicate').on('click.mlmDuplicate', '[data-mlm-duplicate]', function () {
      const reuse = $(this).data('mlm-duplicate') === 'reuse';
      const apply = $dialog.find('#mlm-duplicate-apply').prop('checked');
      $dialog.prop('hidden', true);
      deferred.resolve({ reuse: reuse, apply: apply });
    });
    return deferred.promise();
  };
  const importMusic = (item, options, resolveDuplicate) => {
    const deferred = $.Deferred();
    const send = reuseId => request(Object.assign({ action: 'mlm_import_music', track: JSON.stringify(item), quality: quality(), reuse_duplicate_id: reuseId || 0 }, options || {}))
      .done(response => deferred.resolve(response))
      .fail(xhr => {
        const duplicate = xhr.responseJSON?.data;
        if (!duplicate?.duplicate) { deferred.reject(xhr); return; }
        const details = duplicate.attachment_title ? '\n\n已有文件：' + duplicate.attachment_title : '';
        const decision = typeof resolveDuplicate === 'function' ? resolveDuplicate(duplicate, details) : { reuse: window.confirm((duplicate.message || '媒体库中已有相同文件，是否引用已有文件继续导入？') + details) };
        $.when(decision).done(function (result) {
          const reuse = result && typeof result === 'object' ? result.reuse : result;
          if (reuse) { send(duplicate.attachment_id); return; }
          xhr.mlmDuplicateCanceled = true; deferred.reject(xhr);
        });
      });
    send(0); return deferred.promise();
  };
  const quality = () => $('#mlm-quality').val() || 'standard';
  const streamUrl = (item, selected = quality()) => mlmAdmin.ajaxUrl + '?' + $.param({ action: 'mlm_qq_stream', nonce: mlmAdmin.nonce, track_id: item.id, quality: selected });
  let currentPage = 1;
	let adminPlayers = [];
	let albumPlayer = null;
	function destroyPlayers() {
	  adminPlayers.forEach(player => { try { player.destroy(); } catch (error) {} }); adminPlayers = [];
	  if (albumPlayer) { try { albumPlayer.destroy(); } catch (error) {} albumPlayer = null; }
	}
	function stopPlayers() {
	  adminPlayers.forEach(player => { try { player.pause(); player.seek(0); } catch (error) {} });
	  if (albumPlayer) { try { albumPlayer.pause(); albumPlayer.seek(0); } catch (error) {} }
	}
	function audioConfig(item, selected = quality(), lyrics = '') { return { name: item.title, artist: item.artist || '未知歌手', url: streamUrl(item, selected), cover: item.cover_url || '', theme: '#2271b1', lrc: lyrics || '[00:00.00]暂无歌词' }; }
	function initResultPlayers() {
	  if (typeof APlayer === 'undefined') return;
	  $results.find('.mlm-result-aplayer').each(function () {
		const container = this; const card = $(container).closest('.mlm-search-result'); const item = JSON.parse(decodeURIComponent(card.attr('data-track')));
		const player = new APlayer({ container, mini: false, autoplay: false, theme: '#2271b1', preload: 'none', mutex: true, lrcType: 1, audio: audioConfig(item, quality()) });
		adminPlayers.push(player);
	  });
	}

  function openSearchModal() {
    $('#mlm-search-modal').prop('hidden', false);
    $('body').addClass('mlm-modal-open');
    setTimeout(() => $('#mlm-search-term').trigger('focus'), 0);
  }
  function closeSearchModal() {
	stopPlayers();
    $('#mlm-search-modal').prop('hidden', true);
    $('body').removeClass('mlm-modal-open');
    $('#mlm-open-search').trigger('focus');
  }
  $('#mlm-open-search').on('click', openSearchModal);
  $('.mlm-modal-close').on('click', closeSearchModal);
  $('#mlm-search-modal').on('click', function (event) { if (event.target === this) closeSearchModal(); });
  $(document).on('keydown', function (event) { if (event.key === 'Escape' && !$('#mlm-search-modal').prop('hidden')) closeSearchModal(); });

  let qqLoggedIn = false;
  let loginPollTimer = null;
  function setLoginState(status) {
    const loggedIn = status === true || status?.logged_in === true;
    const credentialPresent = loggedIn || status?.credential_present === true;
    const credentialValid = loggedIn && status?.credential_valid !== false;
    qqLoggedIn = loggedIn;
    if (loggedIn && loginPollTimer) { clearInterval(loginPollTimer); loginPollTimer = null; }
    const stateText = credentialValid ? 'QQ 音乐已登录 · 凭证有效' : (credentialPresent ? (status?.message || 'QQ 音乐登录凭证无效') : 'QQ 音乐尚未登录 · 无有效凭证');
    $('#mlm-qq-state').text(stateText);
    $('#mlm-qq-login').prop('hidden', loggedIn).toggle(!loggedIn).prop('disabled', false).text('QQ 扫码登录');
    $('#mlm-qq-logout').prop('hidden', !credentialPresent).toggle(credentialPresent).prop('disabled', false).text('退出登录');
    if (loggedIn) $('#mlm-qq-panel').prop('hidden', true);
  }
  request({ action: 'mlm_qq_status' }).done(response => setLoginState(response.data || false)).fail(() => setLoginState({ credential_present: false, message: '暂时无法检查登录凭证' }));
  $('#mlm-qq-login').on('click', function () {
    if (qqLoggedIn) return;
    const $button = $(this).prop('disabled', true).text('正在生成二维码…');
    request({ action: 'mlm_qq_login_start' }).done(function (response) {
      if (!response.success) { $('#mlm-qq-state').text(response.data?.message || '二维码获取失败'); $button.prop('disabled', false).text('QQ 扫码登录'); return; }
      if (qqLoggedIn) return;
      $('#mlm-qq-qr').attr('src', response.data.img); $('#mlm-qq-panel').prop('hidden', false); $('#mlm-qq-state').text('等待扫码…');
      let attempts = 0;
      loginPollTimer = setInterval(function () {
        attempts += 1;
		request({ action: 'mlm_qq_login_poll', identifier: response.data.identifier }).done(function (poll) {
		  if (!poll.success) { clearInterval(loginPollTimer); loginPollTimer = null; $('#mlm-qq-state').text(poll.data?.message || 'QQ 登录失败，请重试'); $button.prop('disabled', false).text('QQ 扫码登录'); return; }
          if (poll.data?.logged_in) { setLoginState(poll.data); return; }
		  if (poll.data?.event === 67 || poll.data?.event === 404) $('#mlm-qq-state').text('已扫码，请在手机上确认…');
		  if (poll.data?.event === 65 || poll.data?.event === 402 || poll.data?.event === 68 || poll.data?.event === 403 || attempts >= 90) { clearInterval(loginPollTimer); loginPollTimer = null; $('#mlm-qq-state').text('二维码已失效或登录被取消，请重新登录'); $button.prop('disabled', false).text('QQ 扫码登录'); }
		}).fail(function (xhr) {
		  clearInterval(loginPollTimer); loginPollTimer = null; $('#mlm-qq-state').text(xhr.responseJSON?.data?.message || 'QQ 登录请求失败，请重新登录'); $button.prop('disabled', false).text('QQ 扫码登录');
		});
      }, 2000);
    }).fail(() => { $('#mlm-qq-state').text('二维码获取失败'); $button.prop('disabled', false).text('QQ 扫码登录'); });
  });
  $('#mlm-qq-logout').on('click', function () {
    if (!window.confirm('确定退出 QQ 音乐登录并删除已保存的凭证吗？')) return;
    const $button = $(this).prop('disabled', true).text('正在退出…');
    $('#mlm-qq-state').text('正在退出 QQ 音乐登录…');
    request({ action: 'mlm_qq_logout' }).done(function (response) {
      if (!response.success) { $('#mlm-qq-state').text(response.data?.message || '退出登录失败'); $button.prop('disabled', false).text('退出登录'); return; }
      setLoginState(response.data || false);
      $('#mlm-qq-panel').prop('hidden', true);
    }).fail(function (xhr) {
      $('#mlm-qq-state').text(xhr.responseJSON?.data?.message || '退出登录失败，请重试');
      $button.prop('disabled', false).text('退出登录');
    });
  });

  function searchMusic(page = 1) {
    const term = $('#mlm-search-term').val().trim();
    if (term.length < 2) { $message.text('请输入至少 2 个字符。'); return; }
	destroyPlayers(); $('#mlm-search-spinner').addClass('is-active'); $message.text('正在搜索…'); $results.empty();
    request({ action: 'mlm_search_music', term, page }).done(function (response) {
      if (!response.success || !response.data.results.length) {
        $message.text(page > 1 ? '这一页没有更多结果。' : '没有找到结果。');
        $('#mlm-pagination').prop('hidden', page <= 1);
        return;
      }
      currentPage = response.data.page;
      $message.text('第 ' + currentPage + ' 页，共显示 ' + response.data.results.length + ' 条结果');
      response.data.results.forEach(function (item) {
        const payload = encodeURIComponent(JSON.stringify(item));
        $results.append('<article class="mlm-search-result" data-track="' + payload + '">' +
          '<div class="mlm-result-aplayer"></div><div class="mlm-result-integrated-actions">' + (item.album && item.album_mid ? '<button type="button" class="button mlm-album-link" data-album-mid="' + escapeHtml(item.album_mid) + '" data-album-name="' + escapeHtml(item.album) + '" title="完整专辑名称：' + escapeHtml(item.album) + '">' + escapeHtml(item.album) + '</button>' : '<span class="mlm-album-unavailable">暂无专辑</span>') + '<button type="button" class="button button-primary mlm-add">添加到音乐库</button></div></article>');
      });
	  initResultPlayers();
      $('#mlm-current-page').text('第 ' + currentPage + ' 页');
      $('#mlm-prev-page').prop('disabled', currentPage <= 1);
      $('#mlm-next-page').prop('disabled', !response.data.has_more);
      $('#mlm-pagination').prop('hidden', false);
      $('.mlm-modal-body').animate({ scrollTop: Math.max(0, $results.position().top - 20) }, 160);
    }).fail(xhr => $message.text(xhr.responseJSON?.data?.message || '搜索失败，请检查音乐服务。')).always(() => $('#mlm-search-spinner').removeClass('is-active'));
  }
  $('#mlm-search-button').on('click', () => searchMusic(1));
  $('#mlm-search-term').on('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); searchMusic(1); } });
  $('#mlm-prev-page').on('click', () => { if (currentPage > 1) searchMusic(currentPage - 1); });
  $('#mlm-next-page').on('click', () => searchMusic(currentPage + 1));

  $('#mlm-quality').on('change', function () {
	if (albumPlayer) {
	  const items = $results.data('album-items') || []; renderAlbumTracks(items, $results.data('album-name') || '专辑');
	} else { destroyPlayers(); initResultPlayers(); }
  });

  function renderAlbumTracks(items, albumName) {
    destroyPlayers(); $results.empty().data('album-items', items).data('album-name', albumName).append('<section class="mlm-album-aplayer-panel"><div class="mlm-album-toolbar"><button type="button" class="button" id="mlm-back-search">← 返回搜索结果</button><strong>' + escapeHtml(albumName) + '（' + items.length + ' 首）</strong><div class="mlm-album-selection"><button type="button" class="button" id="mlm-select-all">全选</button><button type="button" class="button" id="mlm-select-none">取消全选</button><span id="mlm-selected-count">已选 ' + items.length + ' 首</span><button type="button" class="button button-primary" id="mlm-import-album">导入已选曲目</button></div></div><div id="mlm-album-aplayer"></div></section>');
	if (typeof APlayer !== 'undefined') {
	  $('#mlm-album-aplayer').html('<div class="mlm-player-loading">正在加载专辑播放器…</div>');
	  Promise.resolve(items.map(item => audioConfig(item, quality()))).then(function (albumAudio) {
		const container = document.getElementById('mlm-album-aplayer'); $(container).empty();
		albumPlayer = new APlayer({ container, autoplay: false, theme: '#2271b1', preload: 'none', mutex: true, lrcType: 1, listFolded: false, listMaxHeight: '360px', audio: albumAudio });
		$('#mlm-album-aplayer .aplayer-list li').each(function (index) {
		  $(this).prepend('<label class="mlm-track-selector" title="选择这首歌曲"><input type="checkbox" class="mlm-album-check" data-index="' + index + '" checked><span class="screen-reader-text">选择第 ' + (index + 1) + ' 首</span></label>');
		});
	  });
	}
	/* Keep track payloads available for the batch importer without duplicating the visible album list. */
    items.forEach(function (item) {
      const payload = encodeURIComponent(JSON.stringify(item));
	  $results.append('<span class="mlm-album-track-data" data-track="' + payload + '" hidden></span>');
    });
    $('#mlm-pagination').prop('hidden', true);
    $('#mlm-back-search').on('click', () => searchMusic(currentPage));
	function updateSelectedCount() { $('#mlm-selected-count').text('已选 ' + $('.mlm-album-check:checked').length + ' 首'); }
	$('#mlm-album-aplayer').on('click', '.mlm-track-selector, .mlm-album-check', event => event.stopPropagation()).on('change', '.mlm-album-check', updateSelectedCount);
	$('#mlm-select-all').on('click', () => { $('.mlm-album-check').prop('checked', true); updateSelectedCount(); });
	$('#mlm-select-none').on('click', () => { $('.mlm-album-check').prop('checked', false); updateSelectedCount(); });
    $('#mlm-import-album').on('click', function () {
	  const selectedItems = $('.mlm-album-check:checked').map(function () { return items[Number($(this).data('index'))]; }).get().filter(Boolean);
	  if (!selectedItems.length) { $message.text('请至少选择一首要导入的歌曲。'); return; }
	  const $button = $(this).prop('disabled', true).text('正在批量导入…'); let index = 0; let success = 0; let playlistUrl = ''; let duplicatePolicy = null;
	  function resolveAlbumDuplicate(duplicate, details) {
		if (duplicatePolicy === 'reuse') return true;
		if (duplicatePolicy === 'skip') return false;
		return askDuplicate(duplicate, details).then(function (result) {
			if (result.apply) duplicatePolicy = result.reuse ? 'reuse' : 'skip';
			return result;
		});
	  }
      function next() {
		if (index >= selectedItems.length) {
		  $message.text('专辑导入完成：成功 ' + success + ' 首，正在前往播放列表“' + albumName + '”…'); $button.text('导入完成');
		  if (playlistUrl) { window.location.assign(playlistUrl); } else { $message.text('专辑导入完成：成功 ' + success + ' 首。请从“音乐播放列表”打开该列表。'); }
		  return;
		}
		const item = selectedItems[index++]; $message.text('正在导入 ' + index + ' / ' + selectedItems.length + '：' + item.title);
        importMusic(item, { post_id: 0, bulk: 1, playlist: albumName }, resolveAlbumDuplicate)
		  .done(response => { if (response.success) { success += 1; playlistUrl = response.data?.playlist_edit_url || playlistUrl; } })
		  .fail(xhr => { if (xhr.mlmDuplicateCanceled) $message.text('已取消导入“' + item.title + '”，继续处理下一首。'); }).always(next);
      }
      next();
    });
  }

  $results.on('click', '.mlm-album-link', function () {
    const albumMid = $(this).data('album-mid'); const albumName = $(this).data('album-name');
	$message.text('正在读取专辑全部曲目，加载后可逐首试听…');
    request({ action: 'mlm_album_songs', album_mid: albumMid }).done(function (response) {
      if (!response.success || !response.data.results.length) { $message.text('没有读取到专辑曲目。'); return; }
      $message.text(''); renderAlbumTracks(response.data.results, albumName);
    }).fail(xhr => $message.text(xhr.responseJSON?.data?.message || '专辑读取失败。'));
  });

  $results.on('click', '.mlm-add', function () {
    const $button = $(this); const $card = $button.closest('.mlm-search-result'); const item = JSON.parse(decodeURIComponent($card.attr('data-track')));
    $button.prop('disabled', true).text('正在导入…'); $message.text('正在下载音频、封面和歌词，请稍候…');
    importMusic(item, { post_id: mlmAdmin.postId }).done(function (response) {
      const data = response.data;
      $('#title').val(item.title).trigger('input'); $('#mlm_artist').val(item.artist || ''); $('#mlm_album').val(item.album || '');
      $('#mlm_audio_url').val(data.audio_url || ''); $('#mlm_cover_url').val(data.cover_url || ''); $('#mlm_lyrics_url').val(data.lyrics_url || '');
	  $('#mlm_source_url').val(item.source_url || ''); $('#mlm_lyrics').val(data.lyrics || '');
      $('#mlm-cover-preview').html(data.cover_url ? '<img src="' + escapeHtml(data.cover_url) + '" alt="">' : '');
      $message.html('<span class="mlm-success">' + escapeHtml(data.message) + '</span>'); $button.text('已添加');
      setTimeout(function () {
        closeSearchModal();
        const $submit = $('#publish');
        if ($submit.length) { $submit.trigger('click'); }
        else { $('#post').trigger('submit'); }
      }, 500);
    }).fail(function (xhr) { $message.text(xhr.mlmDuplicateCanceled ? '已取消导入，你可以继续编辑当前歌曲。' : (xhr.responseJSON?.data?.message || '导入失败。')); $button.prop('disabled', false).text('添加到音乐库'); });
  });

  $('.mlm-media-button').on('click', function () {
    const target = $(this).data('target'); const kind = $(this).data('type'); const libraryType = kind === 'cover_url' ? 'image' : (kind === 'audio_url' ? 'audio' : undefined);
    const frame = wp.media({ title: '选择媒体文件', button: { text: '使用此文件' }, library: libraryType ? { type: libraryType } : {}, multiple: false });
    frame.on('select', function () { const attachment = frame.state().get('selection').first().toJSON(); let readableUrl = attachment.url; try { readableUrl = decodeURIComponent(attachment.url); } catch (error) {} $('#' + target).val(readableUrl).trigger('change'); if (kind === 'cover_url') $('#mlm-cover-preview').html('<img src="' + escapeHtml(attachment.url) + '" alt="">'); }); frame.open();
  });
})(jQuery);
