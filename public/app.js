// public/app.js

function msg(key, fallback) {
	if (window.appMessages && Object.prototype.hasOwnProperty.call(window.appMessages, key)) {
		return window.appMessages[key];
	}
	return fallback;
}

const API_BASE = '../api';

let currentPage = 1;
let currentCategory = '';
let currentSearch = '';
let totalPages = 1;

document.addEventListener('DOMContentLoaded', () => {
	// --- vorhandene Elemente ---
	const searchInput        = document.getElementById('search-input');
	const categorySelect     = document.getElementById('category-select');
	const reloadButton       = document.getElementById('reload-button');
	const pagePrev           = document.getElementById('page-prev');
	const pageNext           = document.getElementById('page-next');

	// --- NEU (für Checkbox / Löschen) ---
	const btnSelectAll = document.getElementById('select-all');
	const btnSelectNone = document.getElementById('select-none');
	const btnDeleteSelected = document.getElementById('delete-selected');
	const btnSelectAllBottom = document.getElementById('select-all-bottom');
	const btnSelectNoneBottom = document.getElementById('select-none-bottom');
	const btnDeleteSelectedBottom = document.getElementById('delete-selected-bottom');
	const btnExportSelected = document.getElementById('export-selected');
	const btnExportSelectedBottom = document.getElementById('export-selected-bottom');
	const btnShareSelected = document.getElementById('share-selected');
	const btnShareSelectedBottom = document.getElementById('share-selected-bottom');



	// --- SUCHEN ---
	if (searchInput) {
		searchInput.addEventListener('input', debounce(() => {
			currentSearch = searchInput.value.trim();
			currentPage = 1;
			loadRecipes();
		}, 400));
	}

	// --- KATEGORIEN ---
	if (categorySelect) {
		categorySelect.addEventListener('change', () => {
			currentCategory = categorySelect.value;
			currentPage = 1;
			loadRecipes();
		});
	}

	// --- NEU LADEN ---
	if (reloadButton) {
		reloadButton.addEventListener('click', () => {
			loadRecipes();
		});
	}

	// --- PAGING PREV ---
	if (pagePrev) {
		pagePrev.addEventListener('click', () => {
			if (currentPage > 1) {
				currentPage--;
				loadRecipes();
			}
		});
	}

	// --- PAGING NEXT ---
	if (pageNext) {
		pageNext.addEventListener('click', () => {
			if (currentPage < totalPages) {
				currentPage++;
				loadRecipes();
			}
		});
	}

	// --- NEUE FUNKTIONEN (Checkbox / Löschen / Teilen) ---
	if (btnSelectAll) {
		btnSelectAll.addEventListener('click', selectAllRecipes);
	}

	if (btnSelectNone) {
		btnSelectNone.addEventListener('click', selectNoneRecipes);
	}

	if (btnDeleteSelected) {
		btnDeleteSelected.addEventListener('click', deleteSelectedRecipes);
	}

	if (btnShareSelected) {
		btnShareSelected.addEventListener('click', shareSelectedRecipes);
	}

	if (btnSelectAllBottom) {
		btnSelectAllBottom.addEventListener('click', selectAllRecipes);
	}

	if (btnSelectNoneBottom) {
		btnSelectNoneBottom.addEventListener('click', selectNoneRecipes);
	}

	if (btnDeleteSelectedBottom) {
		btnDeleteSelectedBottom.addEventListener('click', deleteSelectedRecipes);
	}

	if (btnShareSelectedBottom) {
		btnShareSelectedBottom.addEventListener('click', shareSelectedRecipes);
	}

	if (btnExportSelected) {
		btnExportSelected.addEventListener('click', exportSelectedRecipes);
	}
	if (btnExportSelectedBottom) {
		btnExportSelectedBottom.addEventListener('click', exportSelectedRecipes);
	}


	// --- ERSTER AUFRUF ---
	loadRecipes();
});


/**
 * Lädt die Rezeptliste aus der API und rendert die Übersicht.
 */
async function loadRecipes() {
	const limit = 30;
	const params = new URLSearchParams();
	params.set('page', String(currentPage));
	params.set('limit', String(limit));

	if (currentCategory) {
		params.set('category', currentCategory);
	}
	if (currentSearch) {
		params.set('q', currentSearch);
	}

	const url = `${API_BASE}/recipes_list.php?${params.toString()}`;

	try {
		const res = await fetch(url, {
			headers: {
				'Accept': 'application/json'
			}
		});

		if (!res.ok) {
			throw new Error(`HTTP ${res.status}`);
		}

		const data = await res.json();
		renderRecipes(data);
	} catch (err) {
		const logMsg = msg('load_error_log', 'Fehler beim Laden der Rezepte:');
		console.error(logMsg, err);
		renderError(msg('load_error', 'Fehler beim Laden der Rezepte.'));
	}
}

/**
 * Rendert die Rezeptkacheln, Toolbar und Pagination.
 */
function renderRecipes(data) {
	const container = document.getElementById('recipes-container');
	const pageInfo = document.getElementById('page-info');
	const pagePrev = document.getElementById('page-prev');
	const pageNext = document.getElementById('page-next');

	if (!container) {
		return;
	}

	container.innerHTML = '';

	const items = Array.isArray(data.items) ? data.items : [];
	const page = Number(data.page) || 1;
	const pages = Number(data.pages) || 1;

	currentPage = page;
	totalPages = pages;

	if (items.length === 0) {
		const empty = document.createElement('p');
		empty.className = 'empty-hint';
		empty.textContent = msg('no_recipes', 'Keine Rezepte gefunden.');
		container.appendChild(empty);

		if (pageInfo) {
			const pattern = msg('page_info_pattern', 'Seite {page} / {pages}');
			pageInfo.textContent = pattern
			.replace('{page}', page)
			.replace('{pages}', pages || 1);
		}
		if (pagePrev) {
			pagePrev.disabled = page <= 1;
		}
		if (pageNext) {
			pageNext.disabled = page >= pages;
		}
		updateDeleteButtonState();
		return;
	}

	// Grid für Karten
	const grid = container;

	for (const item of items) {
		const card = document.createElement('article');
		card.className = 'recipe-card';
		card.dataset.id = String(item.id);

		// Klick auf Karte → Detailansicht
		card.addEventListener('click', () => {
			openRecipeView(item.id);
		});

		// Checkbox
		const selectWrapper = document.createElement('div');
		selectWrapper.className = 'recipe-card-select';
		const checkbox = document.createElement('input');
		checkbox.type = 'checkbox';
		checkbox.className = 'recipe-select';
		checkbox.value = item.id;
		checkbox.addEventListener('click', (e) => {
			// Klick nicht als Kartenklick zählen
			e.stopPropagation();
			updateDeleteButtonState();
		});
		selectWrapper.appendChild(checkbox);

		// Bild
		const imgWrapper = document.createElement('div');
		imgWrapper.className = 'recipe-card-image';

		if (item.image_url) {
			const img = document.createElement('img');
			img.src = item.image_url;
			const altFallback = msg('card_image_alt_fallback', 'Rezeptbild');
			img.alt = item.title || altFallback;
			imgWrapper.appendChild(img);
		} else {
			const placeholder = document.createElement('div');
			placeholder.className = 'recipe-card-image-placeholder';
			placeholder.textContent = msg('card_image_placeholder', 'Kein Bild');
			imgWrapper.appendChild(placeholder);
		}

		// Textkörper
		const body = document.createElement('div');
		body.className = 'recipe-card-body';

		const titleEl = document.createElement('h2');
		titleEl.textContent = item.title || msg('card_title_fallback', 'Unbenanntes Rezept');

		const catEl = document.createElement('div');
		catEl.className = 'recipe-card-categories';

		let cats = [];

		if (Array.isArray(item.categories) && item.categories.length > 0) {
			cats = item.categories
				.map(c => (c && typeof c.name === 'string' ? c.name.trim() : ''))
				.filter(Boolean);
		}

		if (cats.length > 0) {
			catEl.textContent = cats.join(' · ');
		} else {
			catEl.textContent = msg('card_category_none', 'Keine Kategorie');
		}

		body.appendChild(titleEl);
		body.appendChild(catEl);

		card.appendChild(selectWrapper);
		card.appendChild(imgWrapper);
		card.appendChild(body);

		grid.appendChild(card);
	}

		if (pageInfo) {
			const pattern = msg('page_info_pattern', 'Seite {page} / {pages}');
			pageInfo.textContent = pattern
			.replace('{page}', page)
			.replace('{pages}', pages || 1);
		}

	if (pagePrev) {
		pagePrev.disabled = page <= 1;
	}
	if (pageNext) {
		pageNext.disabled = page >= pages;
	}

	updateCategoryFilterFromItems(items);
	updateDeleteButtonState();
}

/**
 * Füllt das Kategorie-Dropdown anhand der aktuell geladenen Items.
 */
function updateCategoryFilterFromItems(items) {
	const select = document.getElementById('category-select');
	if (!select) return;

	const currentValue = select.value;
	const categorySet = new Set();

for (const item of items) {
	if (Array.isArray(item.categories)) {
		for (const c of item.categories) {
			if (c && typeof c.name === 'string') {
				const name = c.name.trim();
				if (name !== '') {
					categorySet.add(name);
				}
			}
		}
	}
}


	const categories = Array.from(categorySet).sort((a, b) => a.localeCompare(b, 'de'));

	select.innerHTML = '';
	const optAll = document.createElement('option');
	optAll.value = '';
	optAll.textContent = msg('category_all', 'Alle Kategorien');
	select.appendChild(optAll);

	for (const c of categories) {
		const opt = document.createElement('option');
		opt.value = c;
		opt.textContent = c;
		select.appendChild(opt);
	}

	if (currentCategory && categories.includes(currentCategory)) {
		select.value = currentCategory;
	} else {
		select.value = '';
		currentCategory = '';
	}
}

/**
 * IDs der aktuell ausgewählten Rezepte holen.
 */
function getSelectedRecipeIds() {
	const boxes = document.querySelectorAll('.recipe-select:checked');
	const ids = [];
	boxes.forEach(b => {
		const v = parseInt(b.value, 10);
		if (!isNaN(v)) ids.push(v);
	});
	return ids;
}

/**
 * Delete-Button aktivieren/deaktivieren.
 */
function updateDeleteButtonState() {
	const ids = getSelectedRecipeIds();
	const disable = ids.length === 0;

	const btnDeleteTop = document.getElementById('delete-selected');
	const btnDeleteBottom = document.getElementById('delete-selected-bottom');
	const btnExportTop = document.getElementById('export-selected');
	const btnExportBottom = document.getElementById('export-selected-bottom');
	const btnShareTop = document.getElementById('share-selected');
	const btnShareBottom = document.getElementById('share-selected-bottom');

	if (btnDeleteTop) btnDeleteTop.disabled = disable;
	if (btnDeleteBottom) btnDeleteBottom.disabled = disable;
	if (btnExportTop) btnExportTop.disabled = disable;
	if (btnExportBottom) btnExportBottom.disabled = disable;
	if (btnShareTop) btnShareTop.disabled = disable;
	if (btnShareBottom) btnShareBottom.disabled = disable;
}


/**
 * Alle Rezepte der aktuellen Seite auswählen.
 */
function selectAllRecipes() {
	const boxes = document.querySelectorAll('.recipe-select');
	boxes.forEach(b => { b.checked = true; });
	updateDeleteButtonState();
}

/**
 * Auswahl komplett aufheben.
 */
function selectNoneRecipes() {
	const boxes = document.querySelectorAll('.recipe-select');
	boxes.forEach(b => { b.checked = false; });
	updateDeleteButtonState();
}

/**
 * Ausgewählte Rezepte löschen.
 */
async function deleteSelectedRecipes() {
	const ids = getSelectedRecipeIds();
	if (ids.length === 0) return;

	const pattern = msg('delete_confirm_pattern', 'Sollen wirklich {count} Rezept(e) gelöscht werden?');
	const ok = confirm(pattern.replace('{count}', ids.length));
	if (!ok) return;

	try {
		const res = await fetch(`${API_BASE}/recipes_delete.php`, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Accept': 'application/json'
			},
			body: JSON.stringify({ ids })
		});

		if (!res.ok) {
			throw new Error(`HTTP ${res.status}`);
		}

		const data = await res.json();
		// Nach dem Löschen einfach die aktuelle Seite neu laden
		await loadRecipes();
	} catch (err) {
		const logMsg = msg('delete_error_log', 'Fehler beim Löschen:');
		console.error(logMsg, err);
		alert(msg('delete_error', 'Fehler beim Löschen der Rezepte.'));
	}
}

/**
 * Ausgewählte Rezepte als Broccoli-Datei(en) exportieren.
 * 1 ID  -> .broccoli
 * >1 ID -> .broccoli-archive
 */
function exportSelectedRecipes() {
	const ids = getSelectedRecipeIds();
	if (ids.length === 0) {
		return;
	}

	// Hidden-Formular bauen, damit der Browser den Download handhabt
	const form = document.createElement('form');
	form.method = 'POST';
	form.action = `${API_BASE}/recipes_export.php`;
	form.style.display = 'none';

	const input = document.createElement('input');
	input.type = 'hidden';
	input.name = 'ids';
	input.value = ids.join(',');

	form.appendChild(input);
	document.body.appendChild(form);
	form.submit();
	document.body.removeChild(form);
}

/**
 * Markierte Rezepte als Sammlung teilen.
 */
async function shareSelectedRecipes() {
	const ids = getSelectedRecipeIds();
	if (ids.length === 0) {
		return;
	}

	// genau 1 Rezept: Direktlink auf view.php teilen, keine Sammlung anlegen
	if (ids.length === 1) {
		const id = ids[0];
		const origin = window.location.origin || (window.location.protocol + '//' + window.location.host);
		const url = origin + '/view.php?id=' + encodeURIComponent(id);

		try {
			if (navigator.clipboard && navigator.clipboard.writeText) {
				await navigator.clipboard.writeText(url);
			}
		} catch (e) {
			const logMsg = msg(
				'clipboard_collection_warn_log',
				'Konnte Link nicht in die Zwischenablage kopieren:'
			);
			console.warn(logMsg, e);
		}

		const pattern = msg(
			'share_single_copied',
			'Link zum Rezept wurde in die Zwischenablage kopiert:\n\n{url}'
		);
		alert(pattern.replace('{url}', url));
		return;
	}

	try {
		const res = await fetch(`${API_BASE}/collections_create.php`, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Accept': 'application/json'
			},
			body: JSON.stringify({ ids })
		});

		if (!res.ok) {
			throw new Error(`HTTP ${res.status}`);
		}

		const data = await res.json();
		let shareUrl = data && data.url ? data.url : '';

		if (!shareUrl) {
			throw new Error('Unerwartete Antwort von collections_create.php');
		}

		// relative URL ggf. in absolute URL umwandeln
		if (!/^https?:\/\//i.test(shareUrl)) {
			const origin = window.location.origin || (window.location.protocol + '//' + window.location.host);
			if (shareUrl.charAt(0) !== '/') {
				shareUrl = '/' + shareUrl;
			}
			shareUrl = origin + shareUrl;
		}

		// Link in die Zwischenablage kopieren (best effort)
		try {
			if (navigator.clipboard && navigator.clipboard.writeText) {
				await navigator.clipboard.writeText(shareUrl);
			}
		} catch (e) {
			console.warn('Konnte Link nicht in die Zwischenablage kopieren:', e);
		}

		// Nur anzeigen, nicht weiterleiten
		const pattern = msg(
			'share_collection_copied',
			'Link zur Sammlung wurde in die Zwischenablage kopiert:\n\n{url}'
		);
		alert(pattern.replace('{url}', shareUrl));
	} catch (err) {
		const logMsg = msg(
			'share_collection_error_log',
			'Fehler beim Erzeugen der Sammlung:'
		);
		console.error(logMsg, err);
		alert(msg('share_collection_error', 'Fehler beim Erzeugen des Teilungs-Links.'));
	}
}



/**
 * Zeigt eine einfache Fehlermeldung in der Liste.
 */
function renderError(msg) {
	const container = document.getElementById('recipes-container');
	if (!container) return;
	container.innerHTML = `<p class="error-message">${escapeHtml(msg)}</p>`;
}

/**
 * Navigation zur Detailansicht (read-only).
 */
function openRecipeView(id) {
	window.location.href = 'view.php?id=' + encodeURIComponent(id);
}

/**
 * Einfaches Debounce-Helferlein.
 */
function debounce(fn, delay) {
	let timer = null;
	return (...args) => {
		if (timer) {
			clearTimeout(timer);
		}
		timer = setTimeout(() => fn(...args), delay);
	};
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
