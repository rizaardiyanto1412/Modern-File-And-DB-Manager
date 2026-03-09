(function (wp, config) {
	if (!wp || !wp.element || !config) {
		return;
	}

	const { createElement: h, Fragment, useEffect, useMemo, useState, useRef } = wp.element;
	const { __ } = wp.i18n;
	const endpoint = (config.restUrl || '').replace(/\/$/, '');

	function buildUrl(route, queryParams) {
		const cleanRoute = String(route || '').replace(/^\/+/, '');
		const url = new URL(endpoint, window.location.origin);

		if (url.searchParams.has('rest_route')) {
			const baseRoute = String(url.searchParams.get('rest_route') || '').replace(/\/+$/, '');
			url.searchParams.set('rest_route', `${baseRoute}/${cleanRoute}`);
		} else {
			const basePath = url.pathname.replace(/\/+$/, '');
			url.pathname = `${basePath}/${cleanRoute}`;
		}

		if (queryParams && typeof queryParams === 'object') {
			Object.keys(queryParams).forEach((key) => {
				const value = queryParams[key];
				if (value === undefined || value === null || value === '') {
					return;
				}
				url.searchParams.set(key, String(value));
			});
		}

		return url.toString();
	}

	function formatBytes(bytes) {
		if (!bytes || bytes < 1) return '-';
		const units = ['B', 'KB', 'MB', 'GB', 'TB'];
		const exp = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
		const val = bytes / Math.pow(1024, exp);
		return `${val.toFixed(val >= 10 || exp === 0 ? 0 : 1)} ${units[exp]}`;
	}

	function formatDate(unixTs) {
		if (!unixTs) return '-';
		return new Date(unixTs * 1000).toLocaleString();
	}

	function normalizePath(path) {
		if (!path || path === '/') return '/';
		return `/${String(path).replace(/^\/+/, '').replace(/\/+$/, '')}`;
	}

	function parentPath(path) {
		const current = normalizePath(path);
		if (current === '/') return '/';
		const bits = current.split('/').filter(Boolean);
		bits.pop();
		return bits.length ? `/${bits.join('/')}` : '/';
	}

	async function apiFetch(route, options) {
		const opts = options || {};
		const isFormData = opts.body instanceof FormData;
		const headers = {
			'X-WP-Nonce': config.nonce,
		};
		if (!isFormData) {
			headers['Content-Type'] = 'application/json';
		}

		const response = await fetch(buildUrl(route, opts.query), {
			method: opts.method || 'GET',
			headers,
			body: opts.body ? (isFormData ? opts.body : JSON.stringify(opts.body)) : undefined,
			credentials: 'same-origin',
		});

		if (!response.ok) {
			let message = __('Request failed.', 'modern-file-manager');
			try {
				const payload = await response.json();
				if (payload && payload.message) {
					message = payload.message;
				}
			} catch (error) {
				// Keep default message.
			}
			throw new Error(message);
		}

		const contentType = response.headers.get('content-type') || '';
		if (contentType.includes('application/json')) {
			return response.json();
		}

		return response;
	}

	function Icon(props) {
		const icon = props && props.name ? props.name : 'file';
		let path = 'M6 3h7l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z';

		if (icon === 'folder') {
			path = 'M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V7Z';
		} else if (icon === 'upload') {
			path = 'M12 16V6m0 0-4 4m4-4 4 4M4 18h16';
		} else if (icon === 'download') {
			path = 'M12 6v10m0 0-4-4m4 4 4-4M4 18h16';
		} else if (icon === 'refresh') {
			path = 'M20 12a8 8 0 1 1-2.34-5.66M20 4v6h-6';
		} else if (icon === 'edit') {
			path = 'm4 20 4.5-1 9-9a2 2 0 0 0 0-3l-.5-.5a2 2 0 0 0-3 0l-9 9L4 20Z';
		} else if (icon === 'move') {
			path = 'M12 3v18m0-18 4 4m-4-4-4 4m4 14 4-4m-4 4-4-4';
		} else if (icon === 'copy') {
			path = 'M9 9h11v11H9zM4 4h11v11H4z';
		} else if (icon === 'trash') {
			path = 'M4 7h16M9 7V4h6v3m-8 0v12m4-12v12m4-12v12';
		} else if (icon === 'file-add') {
			path = 'M6 3h7l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm6 6v6m-3-3h6';
		} else if (icon === 'folder-add') {
			path = 'M3 8a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v7a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V8Zm9 2v6m-3-3h6';
		}

		return h(
			'span',
			{ className: `mfm-icon mfm-icon--${icon}`, 'aria-hidden': 'true' },
			h(
				'svg',
				{ viewBox: '0 0 24 24', width: '14', height: '14', fill: 'none', stroke: 'currentColor', 'stroke-width': '2', 'stroke-linecap': 'round', 'stroke-linejoin': 'round' },
				h('path', { d: path })
			)
		);
	}

	let cmModulesPromise = null;

	async function loadCodeMirrorModules() {
		if (cmModulesPromise) {
			return cmModulesPromise;
		}

		cmModulesPromise = new Promise((resolve, reject) => {
			if (window.MFMCodeMirror && window.MFMCodeMirror.EditorState) {
				resolve(window.MFMCodeMirror);
				return;
			}
			reject(new Error(__('Editor modules failed to load.', 'modern-file-manager')));
		});

		return cmModulesPromise;
	}

	function getLanguageExtension(path, cm) {
		const ext = String(path || '').toLowerCase().split('.').pop();
		if (ext === 'php' || ext === 'phtml') return cm.php();
		if (ext === 'js' || ext === 'mjs' || ext === 'cjs' || ext === 'ts') return cm.javascript();
		if (ext === 'css' || ext === 'scss' || ext === 'less') return cm.css();
		if (ext === 'html' || ext === 'htm' || ext === 'xml') return cm.html();
		return cm.php();
	}

	function App() {
		const [path, setPath] = useState(normalizePath(config.initialPath || '/'));
		const [items, setItems] = useState([]);
		const [loading, setLoading] = useState(false);
		const [selected, setSelected] = useState({});
		const [search, setSearch] = useState('');
		const [sortKey, setSortKey] = useState('name');
		const [sortDir, setSortDir] = useState('asc');
		const [toasts, setToasts] = useState([]);
		const [directoriesByPath, setDirectoriesByPath] = useState({ '/': [] });
		const [pathHistory, setPathHistory] = useState(['/']);
		const [editorOpen, setEditorOpen] = useState(false);
		const [editorPath, setEditorPath] = useState('');
		const [editorLoading, setEditorLoading] = useState(false);
		const [editorSaving, setEditorSaving] = useState(false);
		const fileInputRef = useRef(null);
		const reqRef = useRef({ id: 0 });
		const editorHostRef = useRef(null);
		const editorViewRef = useRef(null);

		const selectedItems = useMemo(
			() => items.filter((item) => selected[item.path]),
			[items, selected]
		);

		const filteredSortedItems = useMemo(() => {
			const term = search.trim().toLowerCase();
			const filtered = term
				? items.filter((item) => item.name.toLowerCase().includes(term))
				: items.slice();

			const sorted = filtered.sort((a, b) => {
				if (a.type !== b.type) {
					return a.type === 'dir' ? -1 : 1;
				}
				let valA = a[sortKey];
				let valB = b[sortKey];
				if (sortKey === 'name') {
					valA = a.name.toLowerCase();
					valB = b.name.toLowerCase();
				}
				if (valA < valB) return sortDir === 'asc' ? -1 : 1;
				if (valA > valB) return sortDir === 'asc' ? 1 : -1;
				return 0;
			});

			return sorted;
		}, [items, search, sortKey, sortDir]);

		const directoryShortcuts = useMemo(() => {
			const current = directoriesByPath[path] || [];
			const staticNodes = [
				{ name: __('Root', 'modern-file-manager'), path: '/', type: 'dir' },
				{ name: __('Parent', 'modern-file-manager'), path: parentPath(path), type: 'dir' },
			];
			const merged = staticNodes.concat(current.map((dir) => ({ name: dir.name, path: dir.path, type: 'dir' })));
			const seen = new Set();
			return merged.filter((item) => {
				if (seen.has(item.path)) return false;
				seen.add(item.path);
				return true;
			});
		}, [directoriesByPath, path]);

		const breadcrumbs = useMemo(() => {
			const bits = path.split('/').filter(Boolean);
			const list = [{ name: 'root', path: '/' }];
			let running = '';
			bits.forEach((segment) => {
				running += `/${segment}`;
				list.push({ name: segment, path: running });
			});
			return list;
		}, [path]);

		function toast(type, message) {
			const id = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
			setToasts((current) => current.concat([{ id, type, message }]));
			setTimeout(() => {
				setToasts((current) => current.filter((item) => item.id !== id));
			}, 3800);
		}

		async function refresh(nextPath) {
			const target = normalizePath(nextPath || path);
			const reqId = Date.now();
			reqRef.current.id = reqId;
			setLoading(true);
			try {
				const data = await apiFetch('/list', { query: { path: target } });
				if (reqRef.current.id !== reqId) return;
				const payload = data.data || {};
				const listedItems = payload.items || [];
				setItems(listedItems);
				setPath(payload.path || target);
				setSelected({});
				setDirectoriesByPath((current) => {
					const next = { ...current };
					next[target] = listedItems.filter((item) => item.type === 'dir');
					return next;
				});
				setPathHistory((current) => {
					if (current[current.length - 1] === target) return current;
					return current.concat([target]).slice(-18);
				});
			} catch (error) {
				toast('error', error.message);
			} finally {
				if (reqRef.current.id === reqId) {
					setLoading(false);
				}
			}
		}

		useEffect(() => {
			refresh(path);
			// eslint-disable-next-line react-hooks/exhaustive-deps
		}, []);

		useEffect(() => {
			function onKeyDown(event) {
				if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'r') {
					event.preventDefault();
					refresh(path);
				}
				if (event.key === 'Delete' && selectedItems.length > 0) {
					event.preventDefault();
					handleDelete();
				}
				if (event.key === 'F2' && selectedItems.length === 1) {
					event.preventDefault();
					handleRename();
				}
			}
			window.addEventListener('keydown', onKeyDown);
			return () => window.removeEventListener('keydown', onKeyDown);
		});

		useEffect(() => () => {
			if (editorViewRef.current) {
				editorViewRef.current.destroy();
				editorViewRef.current = null;
			}
		}, []);

		function toggleSort(nextKey) {
			if (sortKey === nextKey) {
				setSortDir((current) => (current === 'asc' ? 'desc' : 'asc'));
				return;
			}
			setSortKey(nextKey);
			setSortDir('asc');
		}

		function toggleSelection(filePath) {
			setSelected((current) => ({ ...current, [filePath]: !current[filePath] }));
		}

		function selectOnly(filePath) {
			setSelected({ [filePath]: true });
		}

		async function handleCreateFolder() {
			const name = window.prompt(__('Folder name:', 'modern-file-manager'));
			if (!name) return;
			try {
				await apiFetch('/mkdir', { method: 'POST', body: { path, name } });
				toast('success', __('Folder created.', 'modern-file-manager'));
				refresh(path);
			} catch (error) {
				toast('error', error.message);
			}
		}

		async function handleCreateFile() {
			const name = window.prompt(__('File name:', 'modern-file-manager'));
			if (!name) return;
			try {
				await apiFetch('/create-file', { method: 'POST', body: { path, name } });
				toast('success', __('File created.', 'modern-file-manager'));
				refresh(path);
			} catch (error) {
				toast('error', error.message);
			}
		}

		async function handleRename() {
			if (selectedItems.length !== 1) {
				toast('error', __('Select exactly one item to rename.', 'modern-file-manager'));
				return;
			}
			const current = selectedItems[0];
			const newName = window.prompt(__('New name:', 'modern-file-manager'), current.name);
			if (!newName || newName === current.name) return;
			try {
				await apiFetch('/rename', { method: 'POST', body: { path: current.path, newName } });
				toast('success', __('Item renamed.', 'modern-file-manager'));
				refresh(path);
			} catch (error) {
				toast('error', error.message);
			}
		}

		async function handleDelete() {
			if (!selectedItems.length) {
				toast('error', __('Select at least one item.', 'modern-file-manager'));
				return;
			}
			const confirmDelete = window.confirm(
				__('Delete selected items permanently?', 'modern-file-manager')
			);
			if (!confirmDelete) return;

			try {
				await apiFetch('/delete', {
					method: 'POST',
					body: { paths: selectedItems.map((item) => item.path) },
				});
				toast('success', __('Items deleted.', 'modern-file-manager'));
				refresh(path);
			} catch (error) {
				toast('error', error.message);
			}
		}

		async function handleMove(isCopy) {
			if (selectedItems.length !== 1) {
				toast('error', __('Select exactly one item.', 'modern-file-manager'));
				return;
			}
			const destination = window.prompt(
				isCopy ? __('Copy to directory:', 'modern-file-manager') : __('Move to directory:', 'modern-file-manager'),
				path
			);
			if (!destination) return;

			try {
				await apiFetch(isCopy ? '/copy' : '/move', {
					method: 'POST',
					body: {
						source: selectedItems[0].path,
						destination: normalizePath(destination),
					},
				});
				toast('success', isCopy ? __('Item copied.', 'modern-file-manager') : __('Item moved.', 'modern-file-manager'));
				refresh(path);
			} catch (error) {
				toast('error', error.message);
			}
		}

		function handleUploadClick() {
			if (fileInputRef.current) {
				fileInputRef.current.click();
			}
		}

		async function handleUploadChange(event) {
			const input = event.target;
			const file = input.files && input.files[0];
			if (!file) return;
			const form = new FormData();
			form.append('path', path);
			form.append('file', file);
			try {
				await apiFetch('/upload', { method: 'POST', body: form });
				toast('success', __('File uploaded.', 'modern-file-manager'));
				refresh(path);
			} catch (error) {
				toast('error', error.message);
			} finally {
				input.value = '';
			}
		}

		function handleDownload() {
			if (selectedItems.length !== 1 || selectedItems[0].type !== 'file') {
				toast('error', __('Select one file to download.', 'modern-file-manager'));
				return;
			}
			const target = buildUrl('/download', {
				path: selectedItems[0].path,
				_wpnonce: config.nonce,
			});
			window.open(target, '_blank', 'noopener');
		}

		async function openEditorForPath(filePath) {
			const target = normalizePath(filePath);
			setEditorOpen(true);
			setEditorPath(target);
			setEditorLoading(true);
			try {
				const [fileResponse, cm] = await Promise.all([
					apiFetch('/read-file', { query: { path: target } }),
					loadCodeMirrorModules(),
				]);

				const fileData = fileResponse && fileResponse.data ? fileResponse.data : {};
				const content = String(fileData.content || '');
				const mountNode = editorHostRef.current;
				if (!mountNode) {
					throw new Error(__('Editor container is not ready.', 'modern-file-manager'));
				}

				if (editorViewRef.current) {
					editorViewRef.current.destroy();
					editorViewRef.current = null;
				}

				const extensions = [
					cm.lineNumbers(),
					cm.highlightActiveLineGutter(),
					cm.history(),
					cm.indentOnInput(),
					cm.bracketMatching(),
					cm.codeFolding(),
					cm.foldGutter(),
					cm.autocompletion(),
					cm.syntaxHighlighting(cm.defaultHighlightStyle, { fallback: true }),
					cm.keymap.of([
						cm.indentWithTab,
						...cm.defaultKeymap,
						...cm.historyKeymap,
						...cm.searchKeymap,
						...cm.completionKeymap,
					]),
					cm.EditorView.lineWrapping,
					getLanguageExtension(target, cm),
				];

				const state = cm.EditorState.create({
					doc: content,
					extensions,
				});
				editorViewRef.current = new cm.EditorView({
					state,
					parent: mountNode,
				});
			} catch (error) {
				toast('error', error.message);
				setEditorOpen(false);
			} finally {
				setEditorLoading(false);
			}
		}

		function closeEditor() {
			if (editorViewRef.current) {
				editorViewRef.current.destroy();
				editorViewRef.current = null;
			}
			setEditorOpen(false);
			setEditorPath('');
		}

		async function saveEditor() {
			if (!editorViewRef.current || !editorPath) {
				return;
			}
			setEditorSaving(true);
			try {
				const content = editorViewRef.current.state.doc.toString();
				await apiFetch('/save-file', {
					method: 'POST',
					body: {
						path: editorPath,
						content,
					},
				});
				toast('success', __('File saved.', 'modern-file-manager'));
				refresh(path);
			} catch (error) {
				toast('error', error.message);
			} finally {
				setEditorSaving(false);
			}
		}

		function handleEditSelected() {
			if (selectedItems.length !== 1 || selectedItems[0].type !== 'file') {
				toast('error', __('Select one file to edit.', 'modern-file-manager'));
				return;
			}
			openEditorForPath(selectedItems[0].path);
		}

		const selectedItem = selectedItems.length === 1 ? selectedItems[0] : null;

		return h(
			'div',
			{ className: 'mfm-shell' },
			h(
				'div',
				{ className: 'mfm-topbar' },
				h('div', { className: 'mfm-actions' },
					h('button', { className: 'button button-primary mfm-btn', onClick: handleCreateFolder }, h(Icon, { name: 'folder-add' }), h('span', null, __('New Folder', 'modern-file-manager'))),
					h('button', { className: 'button mfm-btn', onClick: handleCreateFile }, h(Icon, { name: 'file-add' }), h('span', null, __('New File', 'modern-file-manager'))),
					h('button', { className: 'button mfm-btn', onClick: handleUploadClick }, h(Icon, { name: 'upload' }), h('span', null, __('Upload', 'modern-file-manager'))),
						h('button', { className: 'button mfm-btn', onClick: handleRename }, h(Icon, { name: 'edit' }), h('span', null, __('Rename', 'modern-file-manager'))),
						h('button', { className: 'button mfm-btn', onClick: handleEditSelected }, h(Icon, { name: 'edit' }), h('span', null, __('Edit', 'modern-file-manager'))),
						h('button', { className: 'button mfm-btn', onClick: () => handleMove(false) }, h(Icon, { name: 'move' }), h('span', null, __('Move', 'modern-file-manager'))),
					h('button', { className: 'button mfm-btn', onClick: () => handleMove(true) }, h(Icon, { name: 'copy' }), h('span', null, __('Copy', 'modern-file-manager'))),
					h('button', { className: 'button mfm-btn', onClick: handleDelete }, h(Icon, { name: 'trash' }), h('span', null, __('Delete', 'modern-file-manager'))),
					h('button', { className: 'button mfm-btn', onClick: handleDownload }, h(Icon, { name: 'download' }), h('span', null, __('Download', 'modern-file-manager'))),
					h('button', { className: 'button mfm-btn', onClick: () => refresh(path) }, h(Icon, { name: 'refresh' }), h('span', null, __('Refresh', 'modern-file-manager')))
				),
				h('div', { className: 'mfm-search-wrap' },
					h('input', {
						type: 'search',
						className: 'mfm-search',
						placeholder: __('Search in current folder...', 'modern-file-manager'),
						value: search,
						onChange: (event) => setSearch(event.target.value),
						'aria-label': __('Search files and folders', 'modern-file-manager'),
					})
				),
				h('input', {
					ref: fileInputRef,
					type: 'file',
					className: 'mfm-hidden-file',
					onChange: handleUploadChange,
				})
			),
			h('div', { className: 'mfm-breadcrumb' },
				breadcrumbs.map((crumb, idx) =>
					h(Fragment, { key: crumb.path },
						h('button', {
							type: 'button',
							className: `mfm-crumb ${crumb.path === path ? 'is-active' : ''}`,
							onClick: () => refresh(crumb.path),
						}, crumb.name),
						idx < breadcrumbs.length - 1 ? h('span', { className: 'mfm-crumb-sep' }, '/') : null
					)
				)
			),
			h('div', { className: 'mfm-layout' },
				h('aside', { className: 'mfm-sidebar', 'aria-label': __('Directory shortcuts', 'modern-file-manager') },
					h('h2', null, __('Folders', 'modern-file-manager')),
					directoryShortcuts.map((item) =>
						h('button', {
							key: item.path,
							type: 'button',
							className: `mfm-nav-item ${item.path === path ? 'is-active' : ''}`,
							onClick: () => refresh(item.path),
						}, item.name)
					),
					h('h3', null, __('Recent', 'modern-file-manager')),
					pathHistory.slice().reverse().map((recentPath) =>
						h('button', {
							key: recentPath,
							type: 'button',
							className: 'mfm-nav-item mfm-nav-item--recent',
							onClick: () => refresh(recentPath),
						}, recentPath)
					)
				),
				h('main', { className: 'mfm-main' },
					h('table', { className: 'mfm-table' },
						h('thead', null,
							h('tr', null,
								h('th', { className: 'mfm-col-check' }, ''),
								h('th', null,
									h('button', { className: 'mfm-sort', onClick: () => toggleSort('name') }, __('Name', 'modern-file-manager'))
								),
								h('th', null,
									h('button', { className: 'mfm-sort', onClick: () => toggleSort('size') }, __('Size', 'modern-file-manager'))
								),
								h('th', null,
									h('button', { className: 'mfm-sort', onClick: () => toggleSort('modified') }, __('Modified', 'modern-file-manager'))
								)
							)
						),
						h('tbody', null,
							loading ?
								[1, 2, 3, 4, 5, 6].map((row) => h('tr', { key: row, className: 'is-skeleton' },
									h('td', null, h('span', { className: 'mfm-skeleton-line' })),
									h('td', null, h('span', { className: 'mfm-skeleton-line' })),
									h('td', null, h('span', { className: 'mfm-skeleton-line' })),
									h('td', null, h('span', { className: 'mfm-skeleton-line' }))
								)) :
								(filteredSortedItems.length === 0 ?
									h('tr', { className: 'mfm-empty-row' }, h('td', { colSpan: 4 }, __('No items in this folder.', 'modern-file-manager'))) :
									filteredSortedItems.map((item) =>
											h('tr', {
												key: item.path,
												className: selected[item.path] ? 'is-selected' : '',
												onDoubleClick: () => (item.type === 'dir' ? refresh(item.path) : openEditorForPath(item.path)),
											},
											h('td', { className: 'mfm-col-check' },
												h('input', {
													type: 'checkbox',
													checked: !!selected[item.path],
													onChange: () => toggleSelection(item.path),
													'aria-label': __('Select item', 'modern-file-manager'),
												})
											),
											h('td', null,
											h('button', {
												type: 'button',
												className: 'mfm-item-link',
												onClick: () => (item.type === 'dir' ? refresh(item.path) : selectOnly(item.path)),
											},
												h('span', { className: 'mfm-item-cell' },
													h(Icon, { name: item.type === 'dir' ? 'folder' : 'file' }),
													h('span', { className: 'mfm-item-name' }, item.name)
												)
											)
										),
											h('td', null, item.type === 'dir' ? '-' : formatBytes(item.size)),
											h('td', null, formatDate(item.modified))
										)
									)
								)
						)
					)
				),
				h('aside', { className: 'mfm-detail' },
					h('h2', null, __('Details', 'modern-file-manager')),
					selectedItem ?
						h('div', { className: 'mfm-meta' },
							h('p', null, h('strong', null, __('Name:', 'modern-file-manager')), ` ${selectedItem.name}`),
							h('p', null, h('strong', null, __('Path:', 'modern-file-manager')), ` ${selectedItem.path}`),
							h('p', null, h('strong', null, __('Type:', 'modern-file-manager')), ` ${selectedItem.type}`),
							h('p', null, h('strong', null, __('Size:', 'modern-file-manager')), ` ${selectedItem.type === 'dir' ? '-' : formatBytes(selectedItem.size)}`),
							h('p', null, h('strong', null, __('Modified:', 'modern-file-manager')), ` ${formatDate(selectedItem.modified)}`),
							h('p', { className: 'mfm-badge-wrap' },
								h('span', { className: `mfm-badge ${selectedItem.readable ? 'is-ok' : ''}` }, selectedItem.readable ? __('Readable', 'modern-file-manager') : __('Not readable', 'modern-file-manager')),
								h('span', { className: `mfm-badge ${selectedItem.writable ? 'is-ok' : ''}` }, selectedItem.writable ? __('Writable', 'modern-file-manager') : __('Not writable', 'modern-file-manager'))
							)
						)
						: h('p', { className: 'mfm-empty-note' }, __('Select one item to view details.', 'modern-file-manager'))
				)
				)
				,
				editorOpen ? h('div', { className: 'mfm-editor-modal', role: 'dialog', 'aria-modal': 'true', 'aria-label': __('File editor', 'modern-file-manager') },
					h('div', { className: 'mfm-editor-window' },
						h('div', { className: 'mfm-editor-topbar' },
							h('strong', { className: 'mfm-editor-path' }, editorPath || __('Editor', 'modern-file-manager')),
							h('div', { className: 'mfm-editor-actions' },
								h('button', { type: 'button', className: 'button', onClick: closeEditor }, __('Close', 'modern-file-manager')),
								h('button', { type: 'button', className: 'button button-primary', onClick: saveEditor, disabled: editorSaving || editorLoading }, editorSaving ? __('Saving...', 'modern-file-manager') : __('Save', 'modern-file-manager'))
							)
						),
						h('div', { className: `mfm-editor-host ${editorLoading ? 'is-loading' : ''}` },
							editorLoading ? h('div', { className: 'mfm-editor-loading' }, __('Loading editor...', 'modern-file-manager')) : null,
							h('div', { ref: editorHostRef, className: 'mfm-editor-cm-root' })
						)
					)
				) : null,
				h('div', { className: 'mfm-toast-stack', 'aria-live': 'polite' },
					toasts.map((item) => h('div', { key: item.id, className: `mfm-toast is-${item.type}` }, item.message))
				)
		);
	}

	const root = document.getElementById('mfm-app');
	if (!root) {
		return;
	}

	wp.element.render(h(App), root);
})(window.wp, window.mfmConfig);
