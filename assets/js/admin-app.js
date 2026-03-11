(function (wp, config) {
	if (!wp || !wp.element || !config) {
		return;
	}

	const { createElement: h, Fragment, useEffect, useMemo, useState, useRef } = wp.element;
	const { __ } = wp.i18n;
	const endpoint = (config.restUrl || '').replace(/\/$/, '');
	const editorThemeStorageKey = 'mfmEditorTheme';
	const editorThemeOptions = ['light', 'one-dark', 'monokai', 'solarized-dark', 'tokyo-night-storm', 'nord'];

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

	function getParentPath(path) {
		const normalized = normalizePath(path);
		if (normalized === '/') return '/';
		const bits = normalized.split('/').filter(Boolean);
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
			let message = __('Request failed.', 'modern-file-db-manager');
			let code = '';
			try {
				const payload = await response.json();
				if (payload && payload.message) {
					message = payload.message;
				}
				if (payload && payload.code) {
					code = String(payload.code);
				}
			} catch (error) {
				// Keep default message.
			}
			const err = new Error(message);
			err.code = code;
			throw err;
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
		let path2 = '';

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
		} else if (icon === 'file-txt') {
			path2 = 'M8 11h8M8 14h8M8 17h5';
		} else if (icon === 'file-php') {
			path2 = 'M8 13.5h8M8 16.5h6';
		} else if (icon === 'file-js') {
			path2 = 'M8 13.5h4M14 13.5h2M8 16.5h8';
		} else if (icon === 'file-css') {
			path2 = 'M8 12.5h8M8 15.5h6';
		} else if (icon === 'file-json') {
			path2 = 'M9.5 12.5h.01M14.5 12.5h.01M10 15.5h4';
		} else if (icon === 'file-md') {
			path2 = 'M8 15.5v-3l2 2 2-2v3m1-3h3';
		} else if (icon === 'file-yml') {
			path2 = 'M8 12.5l2 2 2-2 2 2 2-2';
		} else if (icon === 'file-svg') {
			path2 = 'M8.5 15.5 12 10.5 15.5 15.5 12 18.5Z';
		} else if (icon === 'file-image') {
			path2 = 'M8 16l3-3 2 2 3-3 2 4';
		} else if (icon === 'file-zip') {
			path2 = 'M12 9v8m0-8h0m0 2h0m0 2h0m0 2h0';
		} else if (icon === 'chevron-right') {
			path = 'M9 6l6 6-6 6';
		} else if (icon === 'chevron-down') {
			path = 'M6 9l6 6 6-6';
		}

		return h(
			'span',
			{ className: `mfm-icon mfm-icon--${icon}`, 'aria-hidden': 'true' },
				h(
					'svg',
					{ viewBox: '0 0 24 24', width: '14', height: '14', fill: 'none', stroke: 'currentColor', 'stroke-width': '2', 'stroke-linecap': 'round', 'stroke-linejoin': 'round' },
					h('path', { d: path }),
					path2 ? h('path', { d: path2 }) : null
				)
			);
	}

	function getFileIconByExtension(item) {
		if (!item || item.type !== 'file') {
			return 'folder';
		}
		const ext = String(item.extension || '').toLowerCase();
		if (ext === 'txt') return 'file-txt';
		if (ext === 'php' || ext === 'phtml') return 'file-php';
		if (ext === 'js' || ext === 'mjs' || ext === 'cjs') return 'file-js';
		if (ext === 'css' || ext === 'scss' || ext === 'less') return 'file-css';
		if (ext === 'json') return 'file-json';
		if (ext === 'md' || ext === 'markdown') return 'file-md';
		if (ext === 'yml' || ext === 'yaml') return 'file-yml';
		if (ext === 'svg') return 'file-svg';
		if (ext === 'png' || ext === 'jpg' || ext === 'jpeg' || ext === 'gif' || ext === 'webp' || ext === 'bmp') return 'file-image';
		if (ext === 'zip' || ext === 'rar' || ext === 'tar' || ext === 'gz' || ext === '7z') return 'file-zip';
		return 'file';
	}

	let cmModulesPromise = null;

	async function loadCodeMirrorModules() {
		if (cmModulesPromise) {
			return cmModulesPromise;
		}

		cmModulesPromise = new Promise((resolve, reject) => {
			if (
				window.MFMCodeMirror &&
				window.MFMCodeMirror.EditorState &&
				window.MFMCodeMirror.Compartment &&
				window.MFMCodeMirror.oneDark &&
				window.MFMCodeMirror.monokai &&
				window.MFMCodeMirror.solarizedDark &&
				window.MFMCodeMirror.tokyoNightStorm &&
				window.MFMCodeMirror.nord
			) {
				resolve(window.MFMCodeMirror);
				return;
			}
			reject(new Error(__('Editor modules failed to load.', 'modern-file-db-manager')));
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

	function getInitialEditorTheme() {
		try {
			const stored = window.localStorage.getItem(editorThemeStorageKey);
			if (stored === 'dark') return 'one-dark';
			return editorThemeOptions.includes(stored) ? stored : 'light';
		} catch (error) {
			return 'light';
		}
	}

	function getEditorThemeExtensions(theme, cm) {
		if (theme === 'one-dark') return [cm.oneDark];
		if (theme === 'monokai') return [cm.monokai];
		if (theme === 'solarized-dark') return [cm.solarizedDark];
		if (theme === 'tokyo-night-storm') return [cm.tokyoNightStorm];
		if (theme === 'nord') return [cm.nord];
		return [cm.syntaxHighlighting(cm.defaultHighlightStyle, { fallback: true })];
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
			const [treeChildrenByPath, setTreeChildrenByPath] = useState({ '/': [] });
			const [treeExpanded, setTreeExpanded] = useState({ '/': true });
			const [treeLoadingByPath, setTreeLoadingByPath] = useState({});
			const [contextMenu, setContextMenu] = useState({ open: false, x: 0, y: 0, item: null, mode: 'item' });
			const [transferDialog, setTransferDialog] = useState({
				open: false,
				isCopy: false,
				item: null,
				destination: '/',
				submitting: false,
				treeExpanded: { '/': true },
			});
			const [renameDialog, setRenameDialog] = useState({
				open: false,
				item: null,
				name: '',
				submitting: false,
			});
			const [createDialog, setCreateDialog] = useState({
				open: false,
				kind: 'file',
				name: '',
				submitting: false,
			});
			const [deleteDialog, setDeleteDialog] = useState({
				open: false,
				items: [],
				submitting: false,
			});
			const [editorOpen, setEditorOpen] = useState(false);
			const [editorPath, setEditorPath] = useState('');
			const [editorLoading, setEditorLoading] = useState(false);
			const [editorSaving, setEditorSaving] = useState(false);
			const [editorTheme, setEditorTheme] = useState(getInitialEditorTheme);
			const fileInputRef = useRef(null);
			const reqRef = useRef({ id: 0 });
			const editorHostRef = useRef(null);
			const editorViewRef = useRef(null);
			const editorThemeCompartmentRef = useRef(null);
			const renameInputRef = useRef(null);
			const createInputRef = useRef(null);

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

		function getAncestorPaths(targetPath) {
			const normalized = normalizePath(targetPath);
			if (normalized === '/') return ['/'];
			const bits = normalized.split('/').filter(Boolean);
			const ancestors = ['/'];
			let running = '';
			bits.forEach((segment) => {
				running += `/${segment}`;
				ancestors.push(running);
			});
			return ancestors;
		}

		async function ensureTreeChildren(folderPath, forceReload) {
			const target = normalizePath(folderPath);
			const hasCache = !!treeChildrenByPath[target];
			if (hasCache && !forceReload) {
				return;
			}
			if (treeLoadingByPath[target]) {
				return;
			}

			setTreeLoadingByPath((current) => ({ ...current, [target]: true }));
			try {
				const data = await apiFetch('/list', { query: { path: target } });
				const payload = data.data || {};
				const listedItems = payload.items || [];
				const onlyDirs = listedItems.filter((item) => item.type === 'dir');
				setTreeChildrenByPath((current) => ({ ...current, [target]: onlyDirs }));
			} catch (error) {
				toast('error', error.message);
			} finally {
				setTreeLoadingByPath((current) => ({ ...current, [target]: false }));
			}
		}

		function toast(type, message, options) {
			const opts = options && typeof options === 'object' ? options : {};
			const id = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
			setToasts((current) => current.concat([{ id, type, message }]));

			const defaultAutoCloseMs = type === 'error' ? 4800 : 2600;
			const shouldAutoClose = !opts.persistent;
			const autoCloseMs = Number(opts.autoCloseMs || defaultAutoCloseMs);

			if (shouldAutoClose && autoCloseMs > 0) {
				window.setTimeout(() => {
					setToasts((current) => current.filter((item) => item.id !== id));
				}, autoCloseMs);
			}
		}

		function removeToast(id) {
			setToasts((current) => current.filter((item) => item.id !== id));
		}

		async function copyToastMessage(message) {
			try {
				if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
					await navigator.clipboard.writeText(String(message || ''));
					toast('success', __('Error copied to clipboard.', 'modern-file-db-manager'), { autoCloseMs: 1800 });
					return;
				}

				const fallback = document.createElement('textarea');
				fallback.value = String(message || '');
				fallback.setAttribute('readonly', '');
				fallback.style.position = 'fixed';
				fallback.style.opacity = '0';
				document.body.appendChild(fallback);
				fallback.select();
				document.execCommand('copy');
				document.body.removeChild(fallback);
				toast('success', __('Error copied to clipboard.', 'modern-file-db-manager'), { autoCloseMs: 1800 });
			} catch (error) {
				toast('error', __('Unable to copy error message.', 'modern-file-db-manager'));
			}
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
					const onlyDirs = listedItems.filter((item) => item.type === 'dir');
					setItems(listedItems);
					setPath(payload.path || target);
					setSelected({});
					setTreeChildrenByPath((current) => ({ ...current, [target]: onlyDirs }));
					setTreeExpanded((current) => {
						const next = { ...current };
						getAncestorPaths(target).forEach((ancestorPath) => {
							next[ancestorPath] = true;
						});
						return next;
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
				if (event.key === 'Escape' && deleteDialog.open) {
					event.preventDefault();
					closeDeleteDialog();
					return;
				}
				if (event.key === 'Enter' && deleteDialog.open) {
					event.preventDefault();
					confirmDeleteDialog();
					return;
				}
				if (event.key === 'Escape' && createDialog.open) {
					event.preventDefault();
					closeCreateDialog();
					return;
				}
				if (event.key === 'Escape' && renameDialog.open) {
					event.preventDefault();
					closeRenameDialog();
					return;
				}
				if (event.key === 'Escape' && transferDialog.open) {
					event.preventDefault();
					closeTransferDialog();
					return;
				}
				if (deleteDialog.open || createDialog.open || renameDialog.open || transferDialog.open || editorOpen) {
					return;
				}
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
		}, [createDialog.open, deleteDialog.open, editorOpen, path, renameDialog.open, selectedItems, transferDialog.open]);

		useEffect(() => {
			if (!renameDialog.open || !renameInputRef.current) {
				return;
			}
			renameInputRef.current.focus();
			renameInputRef.current.select();
		}, [renameDialog.open]);

		useEffect(() => {
			if (!createDialog.open || !createInputRef.current) {
				return;
			}
			createInputRef.current.focus();
		}, [createDialog.open]);

		useEffect(() => () => {
			if (editorViewRef.current) {
				editorViewRef.current.destroy();
				editorViewRef.current = null;
			}
		}, []);

			function closeContextMenu() {
				setContextMenu({ open: false, x: 0, y: 0, item: null, mode: 'item' });
			}

			useEffect(() => {
				if (!contextMenu.open) {
					return undefined;
				}

				function closeMenuOnOutside() {
					closeContextMenu();
				}

			function closeOnEscape(event) {
				if (event.key === 'Escape') {
					closeMenuOnOutside();
				}
			}

			window.addEventListener('click', closeMenuOnOutside);
			window.addEventListener('contextmenu', closeMenuOnOutside);
			window.addEventListener('keydown', closeOnEscape);
			window.addEventListener('resize', closeMenuOnOutside);
			return () => {
				window.removeEventListener('click', closeMenuOnOutside);
				window.removeEventListener('contextmenu', closeMenuOnOutside);
				window.removeEventListener('keydown', closeOnEscape);
				window.removeEventListener('resize', closeMenuOnOutside);
			};
		}, [contextMenu.open]);

		useEffect(() => {
			try {
				window.localStorage.setItem(editorThemeStorageKey, editorTheme);
			} catch (error) {
				// Ignore storage errors.
			}

			const editorView = editorViewRef.current;
			const cm = window.MFMCodeMirror;
			const compartment = editorThemeCompartmentRef.current;
			if (!editorView || !cm || !compartment) {
				return;
			}

			editorView.dispatch({
				effects: compartment.reconfigure(getEditorThemeExtensions(editorTheme, cm)),
			});
		}, [editorTheme]);

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

		function openCreateDialog(kind) {
			setCreateDialog({
				open: true,
				kind: kind === 'folder' ? 'folder' : 'file',
				name: '',
				submitting: false,
			});
		}

		function closeCreateDialog() {
			setCreateDialog({
				open: false,
				kind: 'file',
				name: '',
				submitting: false,
			});
		}

		function getCreateInvalidReason(name) {
			const trimmed = String(name || '').trim();
			if (!trimmed) {
				return __('Please provide a valid file or folder name.', 'modern-file-db-manager');
			}
			return '';
		}

		function handleCreateFolder() {
			openCreateDialog('folder');
		}

		function handleCreateFile() {
			openCreateDialog('file');
		}

		async function confirmCreate() {
			if (createDialog.submitting) {
				return;
			}

			const invalidReason = getCreateInvalidReason(createDialog.name);
			if (invalidReason) {
				toast('error', invalidReason);
				return;
			}

			const name = String(createDialog.name || '').trim();
			const route = createDialog.kind === 'folder' ? '/mkdir' : '/create-file';
			setCreateDialog((current) => ({ ...current, submitting: true }));
			try {
				await apiFetch(route, { method: 'POST', body: { path, name } });
				toast('success', createDialog.kind === 'folder' ? __('Folder created.', 'modern-file-db-manager') : __('File created.', 'modern-file-db-manager'));
				closeCreateDialog();
				refresh(path);
			} catch (error) {
				toast('error', error.message);
				setCreateDialog((current) => ({ ...current, submitting: false }));
			}
		}

		async function handleRename(targetItem) {
			const current = targetItem || (selectedItems.length === 1 ? selectedItems[0] : null);
			if (!current) {
				toast('error', __('Select exactly one item to rename.', 'modern-file-db-manager'));
				return;
			}
			openRenameDialog(current);
		}

		function getRenameInvalidReason(item, name) {
			if (!item) {
				return __('Select exactly one item to rename.', 'modern-file-db-manager');
			}
			const trimmed = String(name || '').trim();
			if (!trimmed) {
				return __('Please provide a valid file or folder name.', 'modern-file-db-manager');
			}
			if (trimmed === item.name) {
				return __('New name must be different from current name.', 'modern-file-db-manager');
			}
			return '';
		}

		function openRenameDialog(item) {
			setRenameDialog({
				open: true,
				item,
				name: item ? item.name : '',
				submitting: false,
			});
		}

		function closeRenameDialog() {
			setRenameDialog({
				open: false,
				item: null,
				name: '',
				submitting: false,
			});
		}

		async function confirmRename() {
			if (!renameDialog.item || renameDialog.submitting) {
				return;
			}

			const invalidReason = getRenameInvalidReason(renameDialog.item, renameDialog.name);
			if (invalidReason) {
				toast('error', invalidReason);
				return;
			}

			const newName = String(renameDialog.name || '').trim();
			setRenameDialog((current) => ({ ...current, submitting: true }));
			try {
				await apiFetch('/rename', { method: 'POST', body: { path: renameDialog.item.path, newName } });
				toast('success', __('Item renamed.', 'modern-file-db-manager'));
				closeRenameDialog();
				refresh(path);
			} catch (error) {
				toast('error', error.message);
				setRenameDialog((current) => ({ ...current, submitting: false }));
			}
		}

		async function handleDelete(targetItems) {
			const itemsToDelete = Array.isArray(targetItems) ? targetItems : selectedItems;
			if (!itemsToDelete.length) {
				toast('error', __('Select at least one item.', 'modern-file-db-manager'));
				return;
			}
			openDeleteDialog(itemsToDelete);
		}

		function openDeleteDialog(items) {
			setDeleteDialog({
				open: true,
				items: Array.isArray(items) ? items : [],
				submitting: false,
			});
		}

		function closeDeleteDialog() {
			setDeleteDialog({
				open: false,
				items: [],
				submitting: false,
			});
		}

		async function confirmDeleteDialog() {
			if (!deleteDialog.items.length || deleteDialog.submitting) {
				return;
			}

			setDeleteDialog((current) => ({ ...current, submitting: true }));
			try {
				await apiFetch('/delete', {
					method: 'POST',
					body: { paths: deleteDialog.items.map((item) => item.path) },
				});
				toast('success', __('Items deleted.', 'modern-file-db-manager'));
				closeDeleteDialog();
				refresh(path);
			} catch (error) {
				toast('error', error.message);
				setDeleteDialog((current) => ({ ...current, submitting: false }));
			}
		}

		async function handleMove(isCopy, targetItem) {
			const itemToMove = targetItem || (selectedItems.length === 1 ? selectedItems[0] : null);
			if (!itemToMove) {
				toast('error', __('Select exactly one item.', 'modern-file-db-manager'));
				return;
			}
			openTransferDialog(isCopy, itemToMove);
		}

		function getTransferInvalidReason(item, destinationPath) {
			if (!item) {
				return __('Select exactly one item.', 'modern-file-db-manager');
			}
			const destination = normalizePath(destinationPath);
			if (item.type === 'dir') {
				const source = normalizePath(item.path);
				if (destination === source || destination.startsWith(`${source}/`)) {
					return __('Cannot place a folder inside itself.', 'modern-file-db-manager');
				}
			}
			return '';
		}

		function closeTransferDialog() {
			setTransferDialog({
				open: false,
				isCopy: false,
				item: null,
				destination: '/',
				submitting: false,
				treeExpanded: { '/': true },
			});
		}

		async function ensureTransferTreeReady(targetPath) {
			const ancestors = getAncestorPaths(targetPath);
			const nextExpanded = { '/': true };
			ancestors.forEach((ancestorPath) => {
				nextExpanded[ancestorPath] = true;
			});
			setTransferDialog((current) => ({ ...current, treeExpanded: nextExpanded }));
			await ensureTreeChildren('/', false);
			for (let idx = 0; idx < ancestors.length; idx += 1) {
				await ensureTreeChildren(ancestors[idx], false);
			}
		}

		async function openTransferDialog(isCopy, itemToMove) {
			const fallbackDestination = normalizePath(path || '/');
			const safeDestination = itemToMove && itemToMove.type === 'dir' && fallbackDestination.startsWith(`${normalizePath(itemToMove.path)}/`)
				? getParentPath(itemToMove.path)
				: fallbackDestination;

			setTransferDialog({
				open: true,
				isCopy,
				item: itemToMove,
				destination: safeDestination,
				submitting: false,
				treeExpanded: { '/': true },
			});

			await ensureTransferTreeReady(safeDestination);
		}

		async function confirmTransfer() {
			if (!transferDialog.item || transferDialog.submitting) {
				return;
			}

			const invalidReason = getTransferInvalidReason(transferDialog.item, transferDialog.destination);
			if (invalidReason) {
				toast('error', invalidReason);
				return;
			}

			setTransferDialog((current) => ({ ...current, submitting: true }));
			try {
				await apiFetch(transferDialog.isCopy ? '/copy' : '/move', {
					method: 'POST',
					body: {
						source: transferDialog.item.path,
						destination: normalizePath(transferDialog.destination),
					},
				});
				toast('success', transferDialog.isCopy ? __('Item copied.', 'modern-file-db-manager') : __('Item moved.', 'modern-file-db-manager'));
				closeTransferDialog();
				refresh(path);
			} catch (error) {
				toast('error', error.message);
				setTransferDialog((current) => ({ ...current, submitting: false }));
			}
		}

		async function toggleTransferTreeNode(folderPath) {
			const target = normalizePath(folderPath);
			const isExpanded = !!transferDialog.treeExpanded[target];
			if (isExpanded) {
				setTransferDialog((current) => ({
					...current,
					treeExpanded: { ...current.treeExpanded, [target]: false },
				}));
				return;
			}
			setTransferDialog((current) => ({
				...current,
				treeExpanded: { ...current.treeExpanded, [target]: true },
			}));
			await ensureTreeChildren(target, false);
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
				toast('success', __('File uploaded.', 'modern-file-db-manager'));
				refresh(path);
			} catch (error) {
				toast('error', error.message);
			} finally {
				input.value = '';
			}
		}

		function handleDownload(targetItem) {
			const itemToDownload = targetItem || (selectedItems.length === 1 ? selectedItems[0] : null);
			if (!itemToDownload || itemToDownload.type !== 'file') {
				toast('error', __('Select one file to download.', 'modern-file-db-manager'));
				return;
			}
			const target = buildUrl('/download', {
				path: itemToDownload.path,
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
					throw new Error(__('Editor container is not ready.', 'modern-file-db-manager'));
				}

				if (editorViewRef.current) {
					editorViewRef.current.destroy();
					editorViewRef.current = null;
				}

				const themeCompartment = new cm.Compartment();
				editorThemeCompartmentRef.current = themeCompartment;

				const extensions = [
					cm.lineNumbers(),
					cm.highlightActiveLineGutter(),
					cm.history(),
					cm.indentOnInput(),
					cm.bracketMatching(),
					cm.codeFolding(),
					cm.foldGutter(),
					cm.autocompletion(),
					themeCompartment.of(getEditorThemeExtensions(editorTheme, cm)),
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
			editorThemeCompartmentRef.current = null;
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
				toast('success', __('File saved.', 'modern-file-db-manager'));
				refresh(path);
			} catch (error) {
				const isFatalSyntaxBlock = error && error.code === 'php_lint_failed';
				toast('error', error.message, isFatalSyntaxBlock ? { persistent: true } : undefined);
			} finally {
				setEditorSaving(false);
			}
		}

		function handleEdit(targetItem) {
			const itemToEdit = targetItem || (selectedItems.length === 1 ? selectedItems[0] : null);
			if (!itemToEdit || itemToEdit.type !== 'file') {
				toast('error', __('Select one file to edit.', 'modern-file-db-manager'));
				return;
			}
			openEditorForPath(itemToEdit.path);
		}

		function openItem(item) {
			if (!item) return;
			if (item.type === 'dir') {
				refresh(item.path);
				return;
			}
			openEditorForPath(item.path);
		}

			function openContextMenu(event, item) {
				event.preventDefault();
				event.stopPropagation();
				selectOnly(item.path);
				setContextMenu({
					open: true,
					x: event.clientX,
					y: event.clientY,
					item,
					mode: 'item',
				});
			}

			function openWorkspaceContextMenu(event) {
				const target = event.target;
				if (target && typeof target.closest === 'function' && target.closest('.mfm-item-row')) {
					return;
				}
				event.preventDefault();
				event.stopPropagation();
				setContextMenu({
					open: true,
					x: event.clientX,
					y: event.clientY,
					item: null,
					mode: 'workspace',
				});
			}

			function runContextAction(action) {
				const item = contextMenu.item;
				const mode = contextMenu.mode;
				closeContextMenu();

				if (mode === 'workspace') {
					if (action === 'new-folder') handleCreateFolder();
					if (action === 'new-file') handleCreateFile();
					if (action === 'upload') handleUploadClick();
					if (action === 'refresh') refresh(path);
					return;
				}

				if (!item) return;

			if (action === 'edit') handleEdit(item);
			if (action === 'rename') handleRename(item);
			if (action === 'move') handleMove(false, item);
			if (action === 'copy') handleMove(true, item);
			if (action === 'download') handleDownload(item);
			if (action === 'delete') handleDelete([item]);
			if (action === 'open-folder' && item.type === 'dir') openItem(item);
		}

		async function toggleTreeNode(folderPath) {
			const target = normalizePath(folderPath);
			const isExpanded = !!treeExpanded[target];
			if (isExpanded) {
				setTreeExpanded((current) => ({ ...current, [target]: false }));
				return;
			}
			setTreeExpanded((current) => ({ ...current, [target]: true }));
			await ensureTreeChildren(target, false);
		}

		function renderTreeNodes(parentPath, depth) {
			const children = treeChildrenByPath[parentPath] || [];
			if (!children.length) {
				if (treeLoadingByPath[parentPath]) {
					return h('div', { className: 'mfm-tree-empty' }, __('Loading...', 'modern-file-db-manager'));
				}
				return null;
			}

			return children.map((node) => {
				const expanded = !!treeExpanded[node.path];
				const hasKnownChildren = Object.prototype.hasOwnProperty.call(treeChildrenByPath, node.path);
				const isLoading = !!treeLoadingByPath[node.path];
				const mayHaveChildren = !hasKnownChildren || (treeChildrenByPath[node.path] && treeChildrenByPath[node.path].length > 0);

				return h(
					'div',
					{ key: node.path, className: 'mfm-tree-node-wrap' },
					h(
						'div',
						{ className: `mfm-tree-node ${path === node.path ? 'is-active' : ''}`, style: { paddingLeft: `${depth * 14}px` } },
						h(
							'button',
							{
								type: 'button',
								className: 'mfm-tree-toggle',
								onClick: () => toggleTreeNode(node.path),
								'aria-label': expanded ? __('Collapse folder', 'modern-file-db-manager') : __('Expand folder', 'modern-file-db-manager'),
								disabled: !mayHaveChildren && !isLoading,
							},
							mayHaveChildren || isLoading ? h(Icon, { name: expanded ? 'chevron-down' : 'chevron-right' }) : null
						),
						h(
							'button',
							{
								type: 'button',
								className: 'mfm-tree-link',
								onClick: () => refresh(node.path),
							},
							h(Icon, { name: 'folder' }),
							h('span', null, node.name)
						)
					),
					expanded ? renderTreeNodes(node.path, depth + 1) : null
				);
			});
		}

		function renderTransferTreeNodes(parentPath, depth) {
			const children = treeChildrenByPath[parentPath] || [];
			if (!children.length) {
				if (treeLoadingByPath[parentPath]) {
					return h('div', { className: 'mfm-tree-empty' }, __('Loading...', 'modern-file-db-manager'));
				}
				return null;
			}

			return children.map((node) => {
				const expanded = !!transferDialog.treeExpanded[node.path];
				const hasKnownChildren = Object.prototype.hasOwnProperty.call(treeChildrenByPath, node.path);
				const isLoading = !!treeLoadingByPath[node.path];
				const mayHaveChildren = !hasKnownChildren || (treeChildrenByPath[node.path] && treeChildrenByPath[node.path].length > 0);
				const isActive = transferDialog.destination === node.path;

				return h(
					'div',
					{ key: `transfer-${node.path}`, className: 'mfm-tree-node-wrap' },
					h(
						'div',
						{ className: `mfm-tree-node ${isActive ? 'is-active' : ''}`, style: { paddingLeft: `${depth * 14}px` } },
						h(
							'button',
								{
									type: 'button',
									className: 'mfm-tree-toggle',
									onClick: () => toggleTransferTreeNode(node.path),
									'aria-label': expanded ? __('Collapse folder', 'modern-file-db-manager') : __('Expand folder', 'modern-file-db-manager'),
									disabled: !mayHaveChildren && !isLoading,
								},
							mayHaveChildren || isLoading ? h(Icon, { name: expanded ? 'chevron-down' : 'chevron-right' }) : null
						),
						h(
							'button',
							{
								type: 'button',
								className: 'mfm-tree-link',
								onClick: () => setTransferDialog((current) => ({ ...current, destination: node.path })),
							},
							h(Icon, { name: 'folder' }),
							h('span', null, node.name)
						)
					),
					expanded ? renderTransferTreeNodes(node.path, depth + 1) : null
				);
			});
		}

		const selectedItem = selectedItems.length === 1 ? selectedItems[0] : null;
		const createInvalidReason = createDialog.open ? getCreateInvalidReason(createDialog.name) : '';
		const renameInvalidReason = renameDialog.open ? getRenameInvalidReason(renameDialog.item, renameDialog.name) : '';
		const transferInvalidReason = transferDialog.open ? getTransferInvalidReason(transferDialog.item, transferDialog.destination) : '';

		return h(
			'div',
			{ className: 'mfm-shell' },
			h(
				'div',
				{ className: 'mfm-topbar' },
					h('div', { className: 'mfm-actions' },
						h('button', { className: 'button button-primary mfm-btn', onClick: handleCreateFolder }, h(Icon, { name: 'folder-add' }), h('span', null, __('New Folder', 'modern-file-db-manager'))),
						h('button', { className: 'button mfm-btn', onClick: handleCreateFile }, h(Icon, { name: 'file-add' }), h('span', null, __('New File', 'modern-file-db-manager'))),
						h('button', { className: 'button mfm-btn', onClick: handleUploadClick }, h(Icon, { name: 'upload' }), h('span', null, __('Upload', 'modern-file-db-manager'))),
						h('button', { className: 'button mfm-btn', onClick: () => refresh(path) }, h(Icon, { name: 'refresh' }), h('span', null, __('Refresh', 'modern-file-db-manager')))
					),
				h('div', { className: 'mfm-search-wrap' },
					h('input', {
						type: 'search',
						className: 'mfm-search',
						placeholder: __('Search in current folder...', 'modern-file-db-manager'),
						value: search,
						onChange: (event) => setSearch(event.target.value),
						'aria-label': __('Search files and folders', 'modern-file-db-manager'),
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
					h('aside', { className: 'mfm-sidebar', 'aria-label': __('Directory shortcuts', 'modern-file-db-manager') },
						h('h2', null, __('Folders', 'modern-file-db-manager')),
						h(
							'div',
							{ className: 'mfm-tree-root' },
							h(
								'div',
								{ className: `mfm-tree-node is-root ${path === '/' ? 'is-active' : ''}` },
								h(
									'button',
									{
										type: 'button',
										className: 'mfm-tree-toggle',
										onClick: () => toggleTransferTreeNode('/'),
										'aria-label': transferDialog.treeExpanded['/'] ? __('Collapse root', 'modern-file-db-manager') : __('Expand root', 'modern-file-db-manager'),
									},
									h(Icon, { name: transferDialog.treeExpanded['/'] ? 'chevron-down' : 'chevron-right' })
								),
								h(
									'button',
									{ type: 'button', className: 'mfm-tree-link', onClick: () => refresh('/') },
									h(Icon, { name: 'folder' }),
									h('span', null, __('Root', 'modern-file-db-manager'))
								)
							),
							treeExpanded['/'] ? renderTreeNodes('/', 1) : null
						)
					),
					h('main', { className: 'mfm-main', onContextMenu: openWorkspaceContextMenu },
						h('table', { className: 'mfm-table' },
						h('thead', null,
							h('tr', null,
								h('th', { className: 'mfm-col-check' }, ''),
								h('th', null,
									h('button', { className: 'mfm-sort', onClick: () => toggleSort('name') }, __('Name', 'modern-file-db-manager'))
								),
								h('th', null,
									h('button', { className: 'mfm-sort', onClick: () => toggleSort('size') }, __('Size', 'modern-file-db-manager'))
								),
								h('th', null,
									h('button', { className: 'mfm-sort', onClick: () => toggleSort('modified') }, __('Modified', 'modern-file-db-manager'))
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
									h('tr', { className: 'mfm-empty-row' }, h('td', { colSpan: 4 }, __('No items in this folder.', 'modern-file-db-manager'))) :
										filteredSortedItems.map((item) =>
												h('tr', {
													key: item.path,
													className: `${selected[item.path] ? 'is-selected' : ''} mfm-item-row`,
													onDoubleClick: () => (item.type === 'dir' ? refresh(item.path) : openEditorForPath(item.path)),
													onContextMenu: (event) => openContextMenu(event, item),
												},
											h('td', { className: 'mfm-col-check' },
												h('input', {
													type: 'checkbox',
													checked: !!selected[item.path],
													onChange: () => toggleSelection(item.path),
													'aria-label': __('Select item', 'modern-file-db-manager'),
												})
											),
											h('td', null,
											h('button', {
												type: 'button',
												className: 'mfm-item-link',
												onClick: () => (item.type === 'dir' ? refresh(item.path) : selectOnly(item.path)),
											},
												h('span', { className: 'mfm-item-cell' },
													h(Icon, { name: item.type === 'dir' ? 'folder' : getFileIconByExtension(item) }),
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
					h('h2', null, __('Details', 'modern-file-db-manager')),
					selectedItem ?
						h('div', { className: 'mfm-meta' },
							h('p', null, h('strong', null, __('Name:', 'modern-file-db-manager')), ` ${selectedItem.name}`),
							h('p', null, h('strong', null, __('Path:', 'modern-file-db-manager')), ` ${selectedItem.path}`),
							h('p', null, h('strong', null, __('Type:', 'modern-file-db-manager')), ` ${selectedItem.type}`),
							h('p', null, h('strong', null, __('Size:', 'modern-file-db-manager')), ` ${selectedItem.type === 'dir' ? '-' : formatBytes(selectedItem.size)}`),
							h('p', null, h('strong', null, __('Modified:', 'modern-file-db-manager')), ` ${formatDate(selectedItem.modified)}`),
							h('p', { className: 'mfm-badge-wrap' },
								h('span', { className: `mfm-badge ${selectedItem.readable ? 'is-ok' : ''}` }, selectedItem.readable ? __('Readable', 'modern-file-db-manager') : __('Not readable', 'modern-file-db-manager')),
								h('span', { className: `mfm-badge ${selectedItem.writable ? 'is-ok' : ''}` }, selectedItem.writable ? __('Writable', 'modern-file-db-manager') : __('Not writable', 'modern-file-db-manager'))
							)
						)
						: h('p', { className: 'mfm-empty-note' }, __('Select one item to view details.', 'modern-file-db-manager'))
				)
				)
				,
					contextMenu.open ? h('div', {
						className: 'mfm-context-menu',
						style: { left: `${contextMenu.x}px`, top: `${contextMenu.y}px` },
						onClick: (event) => event.stopPropagation(),
					},
						contextMenu.mode === 'workspace' ? [
							h('button', { key: 'new-folder', type: 'button', className: 'mfm-context-item', onClick: () => runContextAction('new-folder') }, __('New Folder', 'modern-file-db-manager')),
							h('button', { key: 'new-file', type: 'button', className: 'mfm-context-item', onClick: () => runContextAction('new-file') }, __('New File', 'modern-file-db-manager')),
							h('button', { key: 'upload', type: 'button', className: 'mfm-context-item', onClick: () => runContextAction('upload') }, __('Upload', 'modern-file-db-manager')),
							h('button', { key: 'refresh', type: 'button', className: 'mfm-context-item', onClick: () => runContextAction('refresh') }, __('Refresh', 'modern-file-db-manager')),
						] : [
							contextMenu.item && contextMenu.item.type === 'dir' ? h('button', { key: 'open-folder', type: 'button', className: 'mfm-context-item', onClick: () => runContextAction('open-folder') }, __('Open Folder', 'modern-file-db-manager')) : null,
							contextMenu.item && contextMenu.item.type === 'file' ? h('button', { key: 'edit', type: 'button', className: 'mfm-context-item', onClick: () => runContextAction('edit') }, __('Edit File', 'modern-file-db-manager')) : null,
							h('button', { key: 'rename', type: 'button', className: 'mfm-context-item', onClick: () => runContextAction('rename') }, __('Rename', 'modern-file-db-manager')),
							h('button', { key: 'move', type: 'button', className: 'mfm-context-item', onClick: () => runContextAction('move') }, __('Move', 'modern-file-db-manager')),
							h('button', { key: 'copy', type: 'button', className: 'mfm-context-item', onClick: () => runContextAction('copy') }, __('Copy', 'modern-file-db-manager')),
							contextMenu.item && contextMenu.item.type === 'file' ? h('button', { key: 'download', type: 'button', className: 'mfm-context-item', onClick: () => runContextAction('download') }, __('Download', 'modern-file-db-manager')) : null,
							h('button', { key: 'delete', type: 'button', className: 'mfm-context-item is-danger', onClick: () => runContextAction('delete') }, __('Delete', 'modern-file-db-manager')),
						]
					) : null,
				editorOpen ? h('div', {
					className: 'mfm-editor-modal',
					role: 'dialog',
					'aria-modal': 'true',
					'aria-label': __('File editor', 'modern-file-db-manager'),
					onClick: closeEditor,
				},
					h('div', {
						className: 'mfm-editor-window',
						onClick: (event) => event.stopPropagation(),
						},
							h('div', { className: 'mfm-editor-topbar' },
								h('strong', { className: 'mfm-editor-path' }, editorPath || __('Editor', 'modern-file-db-manager')),
								h('div', { className: 'mfm-editor-actions' },
									h(
										'label',
										{ className: 'mfm-editor-theme' },
										h('span', null, __('Theme', 'modern-file-db-manager')),
										h(
											'select',
											{
												value: editorTheme,
												onChange: (event) => setEditorTheme(editorThemeOptions.includes(event.target.value) ? event.target.value : 'light'),
												'aria-label': __('Editor theme', 'modern-file-db-manager'),
											},
											h('option', { value: 'light' }, __('Light', 'modern-file-db-manager')),
											h('option', { value: 'one-dark' }, __('One Dark', 'modern-file-db-manager')),
											h('option', { value: 'monokai' }, __('Monokai', 'modern-file-db-manager')),
											h('option', { value: 'solarized-dark' }, __('Solarized Dark', 'modern-file-db-manager')),
											h('option', { value: 'tokyo-night-storm' }, __('Tokyo Night', 'modern-file-db-manager')),
											h('option', { value: 'nord' }, __('Nord', 'modern-file-db-manager'))
										)
									),
									h('button', { type: 'button', className: 'button', onClick: closeEditor }, __('Close', 'modern-file-db-manager')),
									h('button', { type: 'button', className: 'button button-primary', onClick: saveEditor, disabled: editorSaving || editorLoading }, editorSaving ? __('Saving...', 'modern-file-db-manager') : __('Save', 'modern-file-db-manager'))
								)
							),
						h('div', { className: `mfm-editor-host ${editorLoading ? 'is-loading' : ''}` },
							editorLoading ? h('div', { className: 'mfm-editor-loading' }, __('Loading editor...', 'modern-file-db-manager')) : null,
							h('div', { ref: editorHostRef, className: 'mfm-editor-cm-root' })
						)
					)
				) : null,
				deleteDialog.open ? h('div', {
					className: 'mfm-editor-modal mfm-transfer-modal',
					role: 'dialog',
					'aria-modal': 'true',
					'aria-label': __('Delete items', 'modern-file-db-manager'),
					onClick: closeDeleteDialog,
				},
					h('div', {
						className: 'mfm-rename-window',
						onClick: (event) => event.stopPropagation(),
					},
						h('div', { className: 'mfm-transfer-header' },
							h('strong', null, __('Confirm Delete', 'modern-file-db-manager')),
							h('button', { type: 'button', className: 'button', onClick: closeDeleteDialog, disabled: deleteDialog.submitting }, __('Cancel', 'modern-file-db-manager'))
						),
						h('p', { className: 'mfm-transfer-source' }, __('Delete selected items permanently?', 'modern-file-db-manager')),
						h('p', { className: 'mfm-delete-summary' },
							`${deleteDialog.items.length} ${deleteDialog.items.length === 1 ? __('item selected', 'modern-file-db-manager') : __('items selected', 'modern-file-db-manager')}`
						),
						h('ul', { className: 'mfm-delete-list' },
							deleteDialog.items.map((item) => h('li', { key: `delete-${item.path}` }, item.path))
						),
						h('div', { className: 'mfm-transfer-actions' },
							h('button', { type: 'button', className: 'button', onClick: closeDeleteDialog, disabled: deleteDialog.submitting }, __('Cancel', 'modern-file-db-manager')),
							h(
								'button',
								{
									type: 'button',
									className: 'button button-primary mfm-delete-confirm',
									onClick: confirmDeleteDialog,
									disabled: deleteDialog.submitting,
								},
								deleteDialog.submitting ? __('Deleting...', 'modern-file-db-manager') : __('Delete Permanently', 'modern-file-db-manager')
							)
						)
					)
				) : null,
				createDialog.open ? h('div', {
					className: 'mfm-editor-modal mfm-transfer-modal',
					role: 'dialog',
					'aria-modal': 'true',
					'aria-label': createDialog.kind === 'folder' ? __('Create folder', 'modern-file-db-manager') : __('Create file', 'modern-file-db-manager'),
					onClick: closeCreateDialog,
				},
					h('div', {
						className: 'mfm-rename-window',
						onClick: (event) => event.stopPropagation(),
					},
						h('div', { className: 'mfm-transfer-header' },
							h('strong', null, createDialog.kind === 'folder' ? __('New Folder', 'modern-file-db-manager') : __('New File', 'modern-file-db-manager')),
							h('button', { type: 'button', className: 'button', onClick: closeCreateDialog, disabled: createDialog.submitting }, __('Cancel', 'modern-file-db-manager'))
						),
						h('p', { className: 'mfm-transfer-source' },
							`${__('Path:', 'modern-file-db-manager')} ${path}`
						),
						h('label', { className: 'mfm-rename-field' },
							h('span', null, createDialog.kind === 'folder' ? __('Folder name', 'modern-file-db-manager') : __('File name', 'modern-file-db-manager')),
							h('input', {
								ref: createInputRef,
								type: 'text',
								value: createDialog.name,
								onChange: (event) => setCreateDialog((current) => ({ ...current, name: event.target.value })),
								onKeyDown: (event) => {
									if (event.key === 'Enter') {
										event.preventDefault();
										confirmCreate();
									}
								},
								disabled: createDialog.submitting,
							})
						),
						createInvalidReason ? h('p', { className: 'mfm-transfer-error' }, createInvalidReason) : null,
						h('div', { className: 'mfm-transfer-actions' },
							h('button', { type: 'button', className: 'button', onClick: closeCreateDialog, disabled: createDialog.submitting }, __('Cancel', 'modern-file-db-manager')),
							h(
								'button',
								{
									type: 'button',
									className: 'button button-primary',
									onClick: confirmCreate,
									disabled: !!createInvalidReason || createDialog.submitting,
								},
								createDialog.submitting
									? __('Working...', 'modern-file-db-manager')
									: (createDialog.kind === 'folder' ? __('Create Folder', 'modern-file-db-manager') : __('Create File', 'modern-file-db-manager'))
							)
						)
					)
				) : null,
				renameDialog.open ? h('div', {
					className: 'mfm-editor-modal mfm-transfer-modal',
					role: 'dialog',
					'aria-modal': 'true',
					'aria-label': __('Rename item', 'modern-file-db-manager'),
					onClick: closeRenameDialog,
				},
					h('div', {
						className: 'mfm-rename-window',
						onClick: (event) => event.stopPropagation(),
					},
						h('div', { className: 'mfm-transfer-header' },
							h('strong', null, __('Rename Item', 'modern-file-db-manager')),
							h('button', { type: 'button', className: 'button', onClick: closeRenameDialog, disabled: renameDialog.submitting }, __('Cancel', 'modern-file-db-manager'))
						),
						h('p', { className: 'mfm-transfer-source' },
							`${__('Path:', 'modern-file-db-manager')} ${renameDialog.item ? renameDialog.item.path : ''}`
						),
						h('label', { className: 'mfm-rename-field' },
							h('span', null, __('New name', 'modern-file-db-manager')),
							h('input', {
								ref: renameInputRef,
								type: 'text',
								value: renameDialog.name,
								onChange: (event) => setRenameDialog((current) => ({ ...current, name: event.target.value })),
								onKeyDown: (event) => {
									if (event.key === 'Enter') {
										event.preventDefault();
										confirmRename();
									}
								},
								disabled: renameDialog.submitting,
							})
						),
						renameInvalidReason ? h('p', { className: 'mfm-transfer-error' }, renameInvalidReason) : null,
						h('div', { className: 'mfm-transfer-actions' },
							h('button', { type: 'button', className: 'button', onClick: closeRenameDialog, disabled: renameDialog.submitting }, __('Cancel', 'modern-file-db-manager')),
							h(
								'button',
								{
									type: 'button',
									className: 'button button-primary',
									onClick: confirmRename,
									disabled: !!renameInvalidReason || renameDialog.submitting,
								},
								renameDialog.submitting ? __('Working...', 'modern-file-db-manager') : __('Rename', 'modern-file-db-manager')
							)
						)
					)
				) : null,
				transferDialog.open ? h('div', {
					className: 'mfm-editor-modal mfm-transfer-modal',
					role: 'dialog',
					'aria-modal': 'true',
					'aria-label': transferDialog.isCopy ? __('Copy item', 'modern-file-db-manager') : __('Move item', 'modern-file-db-manager'),
					onClick: closeTransferDialog,
				},
					h('div', {
						className: 'mfm-transfer-window',
						onClick: (event) => event.stopPropagation(),
					},
						h('div', { className: 'mfm-transfer-header' },
							h('strong', null, transferDialog.isCopy ? __('Copy To...', 'modern-file-db-manager') : __('Move To...', 'modern-file-db-manager')),
							h('button', { type: 'button', className: 'button', onClick: closeTransferDialog, disabled: transferDialog.submitting }, __('Cancel', 'modern-file-db-manager'))
						),
						h('p', { className: 'mfm-transfer-source' },
							`${__('Item:', 'modern-file-db-manager')} ${transferDialog.item ? transferDialog.item.path : ''}`
						),
						h('div', { className: 'mfm-transfer-tree' },
							h(
								'div',
								{ className: `mfm-tree-node is-root ${transferDialog.destination === '/' ? 'is-active' : ''}` },
								h(
									'button',
									{
										type: 'button',
										className: 'mfm-tree-toggle',
										onClick: () => toggleTreeNode('/'),
										'aria-label': treeExpanded['/'] ? __('Collapse root', 'modern-file-db-manager') : __('Expand root', 'modern-file-db-manager'),
									},
									h(Icon, { name: treeExpanded['/'] ? 'chevron-down' : 'chevron-right' })
								),
								h(
									'button',
									{
										type: 'button',
										className: 'mfm-tree-link',
										onClick: () => setTransferDialog((current) => ({ ...current, destination: '/' })),
									},
									h(Icon, { name: 'folder' }),
									h('span', null, __('Root', 'modern-file-db-manager'))
								)
							),
							transferDialog.treeExpanded['/'] ? renderTransferTreeNodes('/', 1) : null
						),
						h('div', { className: 'mfm-transfer-path' },
							h('span', null, __('Destination:', 'modern-file-db-manager')),
							h('code', null, transferDialog.destination)
						),
						transferInvalidReason ? h('p', { className: 'mfm-transfer-error' }, transferInvalidReason) : null,
						h('div', { className: 'mfm-transfer-actions' },
							h('button', { type: 'button', className: 'button', onClick: closeTransferDialog, disabled: transferDialog.submitting }, __('Cancel', 'modern-file-db-manager')),
							h(
								'button',
								{
									type: 'button',
									className: 'button button-primary',
									onClick: confirmTransfer,
									disabled: !!transferInvalidReason || transferDialog.submitting,
								},
								transferDialog.submitting
									? __('Working...', 'modern-file-db-manager')
									: (transferDialog.isCopy ? __('Copy Here', 'modern-file-db-manager') : __('Move Here', 'modern-file-db-manager'))
							)
						)
					)
				) : null,
				h('div', { className: 'mfm-toast-stack', 'aria-live': 'polite' },
					toasts.map((item) =>
						h('div', { key: item.id, className: `mfm-toast is-${item.type}` },
							h('div', { className: 'mfm-toast-message' }, item.message),
							h('div', { className: 'mfm-toast-actions' },
								item.type === 'error'
									? h(
										'button',
										{
											type: 'button',
											className: 'mfm-toast-action',
											onClick: () => copyToastMessage(item.message),
											title: __('Copy error', 'modern-file-db-manager'),
											'aria-label': __('Copy error', 'modern-file-db-manager'),
										},
										h(Icon, { name: 'copy' })
									)
									: null,
								h(
									'button',
									{
										type: 'button',
										className: 'mfm-toast-action',
										onClick: () => removeToast(item.id),
										title: __('Close', 'modern-file-db-manager'),
										'aria-label': __('Close', 'modern-file-db-manager'),
									},
									h('span', { 'aria-hidden': 'true' }, '\u00d7')
								)
							)
						)
					)
				)
		);
	}

	const root = document.getElementById('mfm-app');
	if (!root) {
		return;
	}

	wp.element.render(h(App), root);
})(window.wp, window.mfmConfig);
