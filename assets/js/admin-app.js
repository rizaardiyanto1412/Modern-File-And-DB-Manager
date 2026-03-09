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
		const fileInputRef = useRef(null);
		const reqRef = useRef({ id: 0 });

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

		const selectedItem = selectedItems.length === 1 ? selectedItems[0] : null;

		return h(
			'div',
			{ className: 'mfm-shell' },
			h(
				'div',
				{ className: 'mfm-topbar' },
				h('div', { className: 'mfm-actions' },
					h('button', { className: 'button button-primary', onClick: handleCreateFolder }, __('New Folder', 'modern-file-manager')),
					h('button', { className: 'button', onClick: handleCreateFile }, __('New File', 'modern-file-manager')),
					h('button', { className: 'button', onClick: handleUploadClick }, __('Upload', 'modern-file-manager')),
					h('button', { className: 'button', onClick: handleRename }, __('Rename', 'modern-file-manager')),
					h('button', { className: 'button', onClick: () => handleMove(false) }, __('Move', 'modern-file-manager')),
					h('button', { className: 'button', onClick: () => handleMove(true) }, __('Copy', 'modern-file-manager')),
					h('button', { className: 'button', onClick: handleDelete }, __('Delete', 'modern-file-manager')),
					h('button', { className: 'button', onClick: handleDownload }, __('Download', 'modern-file-manager')),
					h('button', { className: 'button', onClick: () => refresh(path) }, __('Refresh', 'modern-file-manager'))
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
											onDoubleClick: () => (item.type === 'dir' ? refresh(item.path) : selectOnly(item.path)),
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
													item.type === 'dir' ? `[DIR] ${item.name}` : `[FILE] ${item.name}`
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
