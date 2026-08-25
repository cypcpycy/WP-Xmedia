(function (blocks, element, components, blockEditor, i18n) {
  'use strict';
  const el = element.createElement;
  const Fragment = element.Fragment;
  const useState = element.useState;
  const InspectorControls = blockEditor.InspectorControls;
  const BlockControls = blockEditor.BlockControls;
  const useBlockProps = blockEditor.useBlockProps;
  const Button = components.Button;
  const Modal = components.Modal;
  const PanelBody = components.PanelBody;
  const ToggleControl = components.ToggleControl;
  const TextControl = components.TextControl;
  const ToolbarGroup = components.ToolbarGroup;
  const ToolbarButton = components.ToolbarButton;
  const library = window.mlmBlockData || { tracks: [], playlists: [] };

  function cover(item) {
    return item && item.cover ? el('img', { className: 'mlm-library-cover', src: item.cover, alt: '', loading: 'lazy', decoding: 'async' }) : el('div', { className: 'mlm-library-cover mlm-library-cover-empty' }, '♫');
  }
  function trackCard(track, selectedId, onSelect) {
    return el('article', { className: 'mlm-library-card' + (selectedId === track.id ? ' is-selected' : ''), key: track.id }, cover(track),
      el('div', { className: 'mlm-library-card-body' }, el('strong', { title: track.title }, track.title), el('span', null, track.artist || '未知歌手'), el('small', { title: track.album || '' }, track.album || '暂无专辑'),
        track.audio ? el('audio', { controls: true, preload: 'none', src: track.audio }) : el('em', null, '暂无可试听音频'),
        el(Button, { variant: selectedId === track.id ? 'secondary' : 'primary', onClick: function () { onSelect(track.id); } }, selectedId === track.id ? '已加入文章' : '加入文章')));
  }
  function playlistCard(playlist, selectedId, onSelect) {
    const first = playlist.tracks[0] || {};
    return el('article', { className: 'mlm-library-card mlm-playlist-card' + (selectedId === playlist.id ? ' is-selected' : ''), key: playlist.id }, cover(first),
      el('div', { className: 'mlm-library-card-body' }, el('strong', { title: playlist.name }, playlist.name), el('span', null, playlist.count + ' 首歌曲'),
        el('small', { title: playlist.tracks.map(function (track) { return track.title; }).join('、') }, playlist.tracks.slice(0, 4).map(function (track) { return track.title; }).join(' · ') || '暂无歌曲'),
        first.audio ? el('audio', { controls: true, preload: 'none', src: first.audio }) : el('em', null, '暂无可试听音频'),
        el(Button, { variant: selectedId === playlist.id ? 'secondary' : 'primary', onClick: function () { onSelect(playlist.id); } }, selectedId === playlist.id ? '已加入文章' : '加入文章')));
  }
  function LibraryModal(props) {
    const state = useState(''); const term = state[0]; const setTerm = state[1];
    const source = props.mode === 'playlist' ? library.playlists : library.tracks;
    const filtered = source.filter(function (item) {
      const text = props.mode === 'playlist' ? item.name + ' ' + item.tracks.map(function (track) { return track.title + ' ' + track.artist; }).join(' ') : item.title + ' ' + item.artist + ' ' + item.album;
      return text.toLowerCase().indexOf(term.toLowerCase()) !== -1;
    });
    return el(Modal, { title: props.mode === 'playlist' ? '选择音乐播放列表' : '选择歌曲', className: 'mlm-library-modal', onRequestClose: props.onClose },
      el('div', { className: 'mlm-library-toolbar' }, el(TextControl, { label: '搜索音乐库', hideLabelFromVision: true, placeholder: props.mode === 'playlist' ? '搜索播放列表或歌曲…' : '搜索歌曲、作者或专辑…', value: term, onChange: setTerm }), el('span', null, '共 ' + filtered.length + ' 项')),
      filtered.length ? el('div', { className: 'mlm-library-grid' }, filtered.map(function (item) { return props.mode === 'playlist' ? playlistCard(item, props.selectedId, props.onSelect) : trackCard(item, props.selectedId, props.onSelect); })) : el('div', { className: 'mlm-library-empty' }, '没有找到可用内容。请先在音乐库中发布歌曲。'));
  }
  function SelectedPreview(props) {
    const item = props.item;
    if (!item) return el('div', { className: 'mlm-block-empty' }, el('span', { className: 'dashicons dashicons-format-audio' }), el('strong', null, props.mode === 'playlist' ? '还没有选择播放列表' : '还没有选择歌曲'), el('div', { className: 'mlm-block-actions' }, el(Button, { variant: 'primary', onClick: props.onOpen }, '打开可视化音乐库'), el(Button, { variant: 'secondary', isDestructive: true, onClick: props.onRemove }, '删除区块')));
    const first = props.mode === 'playlist' ? (item.tracks[0] || {}) : item;
    return el('div', { className: 'mlm-selected-preview' }, cover(first), el('div', { className: 'mlm-selected-info' },
      el('strong', null, props.mode === 'playlist' ? item.name : item.title), el('span', null, props.mode === 'playlist' ? item.count + ' 首歌曲' : (item.artist || '未知歌手')),
      el('small', null, props.mode === 'playlist' ? item.tracks.slice(0, 5).map(function (track) { return track.title; }).join(' · ') : (item.album || '暂无专辑')), first.audio && el('audio', { controls: true, preload: 'none', src: first.audio })),
      el('div', { className: 'mlm-block-actions' }, el(Button, { variant: 'secondary', onClick: props.onOpen }, '重新选择'), el(Button, { variant: 'secondary', isDestructive: true, onClick: props.onRemove }, '删除区块')));
  }
  function blockEdit(mode) {
    return function (props) {
      const idKey = mode === 'playlist' ? 'playlistId' : 'trackId'; const selectedId = props.attributes[idKey] || 0;
      const modalState = useState(false); const isOpen = modalState[0]; const setOpen = modalState[1];
      const source = mode === 'playlist' ? library.playlists : library.tracks; const selected = source.find(function (item) { return item.id === selectedId; });
      const blockProps = useBlockProps({ className: 'mlm-block-preview' });
      function choose(id) { const value = {}; value[idKey] = id; props.setAttributes(value); setOpen(false); }
      function removeBlock() { window.wp.data.dispatch('core/block-editor').removeBlock(props.clientId); }
      return el(Fragment, {}, mode === 'track' && el(InspectorControls, {}, el(PanelBody, { title: '播放器设置', initialOpen: true }, el(ToggleControl, { label: '显示歌词', checked: props.attributes.showLyrics, onChange: function (value) { props.setAttributes({ showLyrics: value }); } }))),
        el(BlockControls, {}, el(ToolbarGroup, {}, el(ToolbarButton, { icon: 'trash', label: '删除音乐区块', onClick: removeBlock }))),
        el('div', blockProps, el(SelectedPreview, { mode: mode, item: selected, onOpen: function () { setOpen(true); }, onRemove: removeBlock })),
        isOpen && el(LibraryModal, { mode: mode, selectedId: selectedId, onSelect: choose, onClose: function () { setOpen(false); } }));
    };
  }
  blocks.registerBlockType('mlm/music-player', { apiVersion: 3, title: '音乐播放器', description: '预览并选择一首歌曲插入文章。', icon: 'format-audio', category: 'media', keywords: ['音乐', '歌曲', '播放器', 'music', 'audio'], attributes: { trackId: { type: 'integer', default: 0 }, showLyrics: { type: 'boolean', default: true } }, edit: blockEdit('track'), save: function () { return null; } });
  blocks.registerBlockType('mlm/music-playlist', { apiVersion: 3, title: '音乐播放列表', description: '预览并选择一个播放列表插入文章。', icon: 'playlist-audio', category: 'media', keywords: ['音乐', '播放列表', '歌单', 'playlist'], attributes: { playlistId: { type: 'integer', default: 0 } }, edit: blockEdit('playlist'), save: function () { return null; } });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.i18n);
