// public/archive.js

const API_BASE = '../api';

let archiveId = null;
let foundItems = [];

// Helper für i18n-Messages (Fallback = übergebener Default-Text)
function amsg(key, fallback) {
	if (window.archiveMessages && Object.prototype.hasOwnProperty.call(window.archiveMessages, key)) {
		return window.archiveMessages[key];
	}
	return fallback;
}

document.addEventListener('DOMContentLoaded', () => {
	const uploadBtn        = document.getElementById('upload-btn');
	const importBtn        = document.getElementById('import-selected');
	const importBtnTop     = document.getElementById('import-selected-top');
	const selectAllBtn     = document.getElementById('select-all-btn');
	const selectNoneBtn    = document.getElementById('select-none-btn');
	const selectAllBtnTop  = document.getElementById('select-all-btn-top');
	const selectNoneBtnTop = document.getElementById('select-none-btn-top');

	if (uploadBtn)      uploadBtn.addEventListener('click', uploadArchive);
	if (importBtn)      importBtn.addEventListener('click', importSelected);
	if (importBtnTop)   importBtnTop.addEventListener('click', importSelected);
	if (selectAllBtn)   selectAllBtn.addEventListener('click', selectAllRecipes);
	if (selectNoneBtn)  selectNoneBtn.addEventListener('click', selectNoneRecipes);
	if (selectAllBtnTop)  selectAllBtnTop.addEventListener('click', selectAllRecipes);
	if (selectNoneBtnTop) selectNoneBtnTop.addEventListener('click', selectNoneRecipes);

	// falls archive_id in der URL steht → vorherige Liste wieder aufbauen
	const params = new URLSearchParams(window.location.search);
	const archiveIdParam = params.get('archive_id');
	if (archiveIdParam) {
		// zuerst versuchen, aus dem SessionStorage zu laden
		const cacheKey = 'broccoliArchive_' + archiveIdParam;
		let cached = null;
		try {
			cached = sessionStorage.getItem(cacheKey);
		} catch (e) {
			cached = null;
		}

		if (cached) {
			archiveId = archiveIdParam;
			try {
				foundItems = JSON.parse(cached) || [];
			} catch (e) {
				foundItems = [];
			}
			if (foundItems.length > 0) {
				const results = document.getElementById('archive-results');
				if (results) results.classList.remove('hidden');
				renderArchiveList();
			} else {
				loadExistingArchive(archiveIdParam);
			}
		} else {
			loadExistingArchive(archiveIdParam);
		}
	}
});

/**
 * Upload einer .broccoli oder .broccoli-archive Datei.
 * Beide werden an archive_upload.php geschickt.
 */
async function uploadArchive() {
	const input = document.getElementById('archive-file');
	const file = input && input.files ? input.files[0] : null;

	if (!file) {
		alert(amsg(
			'select_file_required',
			'Bitte eine .broccoli- oder .broccoli-archive-Datei auswählen.'
		));
		return;
	}

	const form = new FormData();
	form.append('file', file);

	try {
		const res = await fetch(`${API_BASE}/archive_upload.php`, {
			method: 'POST',
			body: form
		});
		if (!res.ok) {
			throw new Error(`HTTP ${res.status}`);
		}
		const data = await res.json();
		handleArchiveData(data);
	} catch (err) {
		console.error(err);
		alert(amsg('upload_error', 'Fehler beim Upload!'));
	}
}

async function loadExistingArchive(id) {
	try {
		const res = await fetch(`${API_BASE}/archive_items.php?archive_id=${encodeURIComponent(id)}`, {
			headers: { 'Accept': 'application/json' }
		});
		if (!res.ok) {
			throw new Error(`HTTP ${res.status}`);
		}
		const data = await res.json();
		handleArchiveData(data);
	} catch (err) {
		console.error(err);
		// kein Alert nötig, wenn es schiefgeht, ist die Seite halt leer
	}
}

/**
 * Anzeige der Liste nach dem Upload
 */
function handleArchiveData(data) {
	archiveId = data.archive_id;
	foundItems = Array.isArray(data.items) ? data.items : [];

	if (!archiveId) {
		alert(amsg('analyze_error', 'Archiv / Datei konnte nicht analysiert werden.'));
		return;
	}
	const url = new URL(window.location.href);
	url.searchParams.set('archive_id', archiveId);
	window.history.replaceState({}, '', url.toString());

	// Zustand für diese archive_id im SessionStorage zwischenspeichern
	try {
		sessionStorage.setItem(
			'broccoliArchive_' + archiveId,
			JSON.stringify(foundItems)
		);
	} catch (e) {
		// Ignorieren, wenn Storage nicht verfügbar ist
	}

	const results = document.getElementById('archive-results');
	if (results) results.classList.remove('hidden');
	renderArchiveList();
}

/**
 * Liste der Rezepte aus dem Archiv / Einzeldatei darstellen
 */
function renderArchiveList() {
	const list = document.getElementById('recipes-list');
	const importBtn = document.getElementById('import-selected');
	const importBtnTop = document.getElementById('import-selected-top');

	if (!list) return;

	list.innerHTML = '';

	if (foundItems.length === 0) {
		list.innerHTML = `<p class="empty-hint">${escapeHtml(
			amsg('empty_hint', 'Keine Rezepte gefunden.')
		)}</p>`;
		if (importBtn) importBtn.disabled = true;
		if (importBtnTop) importBtnTop.disabled = true;
		return;
	}

	let html = '<form id="archive-select-form">';

	const titleFallback = amsg('title_fallback', '(ohne Titel)');
	const hasImageLabel = amsg('thumb_has_image', 'Bild vorhanden');
	const noImageLabel  = amsg('thumb_no_image', 'Kein Bild');
	const catLabel      = amsg('categories_label', 'Kategorien:');
	const previewLabel  = amsg('preview_link', 'anzeigen');

	for (const item of foundItems) {
		const safeCats = Array.isArray(item.categories)
			? item.categories
				.map(c => (c && typeof c.name === 'string' ? c.name.trim() : ''))
				.filter(Boolean)
				.join(', ')
			: '';
		const safeTitle = item.title || titleFallback;

		let thumbHtml = '';
		if (item.image_url) {
			thumbHtml = `<div class="archive-thumb"><img src="${item.image_url}" alt="${escapeHtml(safeTitle)}"></div>`;
		} else if (item.has_image) {
			thumbHtml = `<div class="archive-thumb archive-thumb-placeholder">${escapeHtml(hasImageLabel)}</div>`;
		} else {
			thumbHtml = `<div class="archive-thumb archive-thumb-placeholder">${escapeHtml(noImageLabel)}</div>`;
		}

		html += `
		<label class="archive-item">
			<input type="checkbox" name="sel" value="${escapeHtml(item.filename)}">
			${thumbHtml}
			<div class="archive-item-body">
				<div class="archive-item-title">${escapeHtml(safeTitle)}</div>
				<div class="archive-item-meta">
					<span>${escapeHtml(catLabel)} ${escapeHtml(safeCats || '—')}</span>
				</div>
				<div class="archive-open" data-filename="${escapeHtml(item.filename)}">
					📝 ${escapeHtml(previewLabel)}
				</div>
			</div>
		</label>`;
	}

	html += '</form>';
	list.innerHTML = html;

	const form = document.getElementById('archive-select-form');
	if (!form) return;

	form.addEventListener('change', () => {
		const any = form.querySelectorAll('input[type=checkbox]:checked').length > 0;
		if (importBtn) importBtn.disabled = !any;
		if (importBtnTop) importBtnTop.disabled = !any;
	});

	// Klick auf "📝 anzeigen" → Einzel-View
	list.querySelectorAll('.archive-open').forEach(el => {
		el.addEventListener('click', (e) => {
			e.preventDefault();
			e.stopPropagation();
			const filename = el.dataset.filename;
			openSingleFromArchive(filename);
		});
	});
}

/**
 * einzelnes Rezept aus Archiv/Datei anzeigen.
 */
function openSingleFromArchive(filename) {
	if (!archiveId || !filename) return;
	const url = `archive_view.php?archive_id=${encodeURIComponent(archiveId)}&filename=${encodeURIComponent(filename)}`;
	window.location.href = url;
}

/**
 * Import der ausgewählten Dateien in die DB
 */
async function importSelected() {
	const form = document.getElementById('archive-select-form');
	if (!form) return;

	const boxes = form.querySelectorAll('input[type=checkbox]:checked');
	if (boxes.length === 0) return;

	const filenames = [];
	boxes.forEach(b => filenames.push(b.value));

	try {
		const res = await fetch(`${API_BASE}/archive_import.php`, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				archive_id: archiveId,
				filenames: filenames
			})
		});

		if (!res.ok) {
			throw new Error(`HTTP ${res.status}`);
		}

		const data = await res.json();

		// Fall: nur Duplikate, nichts importiert
		if (Array.isArray(data.imported_ids)
			&& data.imported_ids.length === 0
			&& Array.isArray(data.duplicate_ids)
			&& data.duplicate_ids.length > 0) {

			alert(amsg(
				'duplicate_only_message',
				'Rezept existiert bereits. Kein Rezept übernommen.'
			));
			return;
		}

		const count = Array.isArray(data.imported_ids) ? data.imported_ids.length : 0;
		const tpl = amsg(
			'import_done',
			'Import abgeschlossen. {count} Rezepte übernommen.'
		);
		alert(tpl.replace('{count}', String(count)));

	} catch (err) {
		console.error(err);
		alert(amsg('import_error', 'Fehler beim Importieren.'));
	}
}

/**
 * Alle markieren
 */
function selectAllRecipes() {
	const form = document.getElementById('archive-select-form');
	if (!form) return;

	const boxes = form.querySelectorAll('input[type=checkbox]');
	boxes.forEach(b => { b.checked = true; });

	const btn = document.getElementById('import-selected');
	const btnTop = document.getElementById('import-selected-top');
	const disabled = boxes.length === 0;
	if (btn) btn.disabled = disabled;
	if (btnTop) btnTop.disabled = disabled;
}

/**
 * Alle abwählen
 */
function selectNoneRecipes() {
	const form = document.getElementById('archive-select-form');
	if (!form) return;

	const boxes = form.querySelectorAll('input[type=checkbox]');
	boxes.forEach(b => { b.checked = false; });

	const btn = document.getElementById('import-selected');
	const btnTop = document.getElementById('import-selected-top');
	if (btn) btn.disabled = true;
	if (btnTop) btnTop.disabled = true;
}

/**
 * Minimales HTML-Escaping.
 */
function escapeHtml(str) {
	return String(str)
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#039;');
}
