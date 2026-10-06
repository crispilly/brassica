// public/index_open.js

const API_BASE = '../api';

let currentPage = 1;
let currentCategory = '';
let currentSearch = '';
let totalPages = 1;

// i18n-Helper für diese Seite
function iomsg(key, fallback) {
	if (window.indexOpenMessages && Object.prototype.hasOwnProperty.call(window.indexOpenMessages, key)) {
		return window.indexOpenMessages[key];
	}
	return fallback;
}

document.addEventListener('DOMContentLoaded', () => {
	// Elemente der offenen Sammlungsseite
	const mainEl           = document.querySelector('.app-main');
	const searchInput      = document.getElementById('search-input');
	const categorySelect   = document.getElementById('category-select');
	const reloadButton     = document.getElementById('reload-button');
	const pagePrev         = document.getElementById('page-prev');
	const pageNext         = document.getElementById('page-next');
	const btnSelectAll     = document.getElementById('select-all');
	const btnSelectNone    = document.getElementById('select-none');
	const btnSelectAllBot  = document.getElementById('select-all-bottom');
	const btnSelectNoneBot = document.getElementById('select-none-bottom');
	const btnExportTop     = document.getElementById('export-selected');
	const btnExportBottom  = document.getElementById('export-selected-bottom');
	const btnImportTop     = document.getElementById('import-selected');
	const btnImportBottom  = document.getElementById('import-selected-bottom');

	if (!mainEl) {
		console.error('index_open.js: .app-main nicht gefunden.');
		return;
	}

	const token = mainEl.dataset.token || '';
	if (!token) {
		console.error('index_open.js: Kein Token in data-token gefunden.');
		return;
	}

	// Suche
	if (searchInput) {
		searchInput.addEventListener('input', debounce(() => {
			currentSearch = searchInput.value.trim();
			currentPage = 1;
			loadCollectionRecipes(token);
		}, 300));
	}

	// Kategorie-Wechsel
	if (categorySelect) {
		categorySelect.addEventListener('change', () => {
			currentCategory = categorySelect.value;
			currentPage = 1;
			loadCollectionRecipes(token);
		});
	}

	// Neu laden
	if (reloadButton) {
		reloadButton.addEventListener('click', () => {
			loadCollectionRecipes(token);
		});
	}

	// Paging
	if (pagePrev) {
		pagePrev.addEventListener('click', () => {
			if (currentPage > 1) {
				currentPage--;
				loadCollectionRecipes(token);
			}
		});
	}

	if (pageNext) {
		pageNext.addEventListener('click', () => {
			if (currentPage < totalPages) {
				currentPage++;
				loadCollectionRecipes(token);
			}
		});
	}

	// Auswahl-Buttons
	if (btnSelectAll) {
		btnSelectAll.addEventListener('click', () => {
			selectAllRecipes();
			updateExportButtonState();
		});
	}

	if (btnSelectNone) {
		btnSelectNone.addEventListener('click', () => {
			selectNoneRecipes();
			updateExportButtonState();
		});
	}

	if (btnSelectAllBot) {
		btnSelectAllBot.addEventListener('click', () => {
			selectAllRecipes();
			updateExportButtonState();
		});
	}

	if (btnSelectNoneBot) {
		btnSelectNoneBot.addEventListener('click', () => {
			selectNoneRecipes();
			updateExportButtonState();
		});
	}

	// Export-Buttons
	if (btnExportTop) {
		btnExportTop.addEventListener('click', exportSelectedRecipes);
	}
	if (btnExportBottom) {
		btnExportBottom.addEventListener('click', exportSelectedRecipes);
	}

	// Import-Buttons
	if (btnImportTop) {
		btnImportTop.addEventListener('click', () => importSelectedRecipes(token));
	}
	if (btnImportBottom) {
		btnImportBottom.addEventListener('click', () => importSelectedRecipes(token));
	}

	// Erster Aufruf
	loadCollectionRecipes(token);
});

/**
 * Sammlungseinträge laden.
 */
async function loadCollectionRecipes(token) {
	const limit = 30;
	const params = new URLSearchParams();
	params.set('token', token);
	params.set('page', String(currentPage));
	params.set('limit', String(limit));

	if (currentCategory) {
		params.set('category', currentCategory);
	}
	if (currentSearch) {
		params.set('q', currentSearch);
	}

	const url = `${API_BASE}/collections_recipes.php?${params.toString()}`;

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
		renderCollectionRecipes(data);
	} catch (err) {
		console.error(iomsg('load_error_log', 'Fehler beim Laden der Sammlungs-Rezepte:'), err);
		renderError(iomsg('load_error', 'Fehler beim Laden der Rezepte.'));
	}
}

async function importSelectedRecipes(token) {
	const ids = getSelectedRecipeIds();
	if (ids.length === 0) {
		return;
	}

	try {
		const res = await fetch(`${API_BASE}/collections_import.php`, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Accept': 'application/json'
			},
			body: JSON.stringify({ token, ids })
		});

		if (res.status === 401) {
			alert(iomsg('login_required_import', 'Bitte melde Dich an, um Rezepte zu übernehmen.'));
			return;
		}

		if (!res.ok) {
			throw new Error(`HTTP ${res.status}`);
		}

		const data = await res.json();
		if (!data.success) {
			const prefix = iomsg('import_not_successful_prefix', 'Import nicht erfolgreich: ');
			alert(prefix + (data.message || iomsg('import_unknown_error', 'Unbekannter Fehler')));
			return;
		}

		const template = iomsg(
			'import_result',
			'Import abgeschlossen. Übernommen: {imported}, übersprungen (eigene): {skipped_own}.'
		);
		const msg = template
			.replace('{imported}', String(data.imported ?? 0))
			.replace('{skipped_own}', String(data.skipped_own ?? 0));

		alert(msg);
	} catch (err) {
		console.error(iomsg('import_error_log', 'Fehler beim Import der Rezepte:'), err);
		alert(iomsg('import_error', 'Fehler beim Import der Rezepte.'));
	}
}

/**
 * Ergebnisliste rendern (analog zu app.js, aber ohne Löschen).
 */
function renderCollectionRecipes(data) {
	const container = document.getElementById('recipes-container');
	const pageInfo  = document.getElementById('page-info');
	const pagePrev  = document.getElementById('page-prev');
	const pageNext  = document.getElementById('page-next');

	if (!container) return;

	container.innerHTML = '';

	const items = Array.isArray(data.items) ? data.items : [];
	const page  = typeof data.page === 'number' ? data.page : 1;
	const pages = typeof data.pages === 'number' ? data.pages : 0;

	totalPages = pages || 1;

	if (items.length === 0) {
		const p = document.createElement('p');
		p.className = 'empty-hint';
		p.textContent = iomsg('empty_hint', 'Keine Rezepte gefunden.');
		container.appendChild(p);

		if (pageInfo) {
			const tmpl = iomsg('page_info', 'Seite {page} / {pages}');
			pageInfo.textContent = tmpl
				.replace('{page}', '1')
				.replace('{pages}', '1');
		}
		if (pagePrev) pagePrev.disabled = true;
		if (pageNext) pageNext.disabled = true;

		updateExportButtonState();
		return;
	}

	const grid = container;

	for (const item of items) {
		const card = document.createElement('article');
		card.className = 'recipe-card';
		card.dataset.id = String(item.id);

		// Klick auf Karte → Detailansicht
		card.addEventListener('click', () => {
			openRecipeView(item.id);
		});

		// Checkbox nur, wenn nicht eigener Besitzer (is_owner === false)
		const selectWrapper = document.createElement('div');
		selectWrapper.className = 'recipe-card-select';

		if (!item.is_owner) {
			const checkbox = document.createElement('input');
			checkbox.type = 'checkbox';
			checkbox.className = 'recipe-select';
			checkbox.value = item.id;
			checkbox.addEventListener('click', (e) => {
				e.stopPropagation();
				updateExportButtonState();
			});
			selectWrapper.appendChild(checkbox);
		}

		// Bild
		const imgWrapper = document.createElement('div');
		imgWrapper.className = 'recipe-card-image';

		if (item.image_url) {
			const img = document.createElement('img');
			img.src = item.image_url;
			img.alt = item.title || iomsg('image_alt_fallback', 'Rezeptbild');
			imgWrapper.appendChild(img);
		} else {
			const placeholder = document.createElement('div');
			placeholder.className = 'recipe-card-image-placeholder';
			placeholder.textContent = iomsg('no_image', 'Kein Bild');
			imgWrapper.appendChild(placeholder);
		}

		// Textkörper
		const body = document.createElement('div');
		body.className = 'recipe-card-body';

		const titleEl = document.createElement('h2');
		titleEl.textContent = item.title || iomsg('title_fallback', 'Unbenanntes Rezept');

		const catEl = document.createElement('div');
		catEl.className = 'recipe-card-categories';

		let cats = [];
		if (Array.isArray(item.categories)) {
			cats = item.categories
				.map(c => (c && typeof c.name === 'string' ? c.name.trim() : ''))
				.filter(Boolean);
		}

		if (cats.length > 0) {
			catEl.textContent = cats.join(' · ');
		} else {
			catEl.textContent = iomsg('no_category', 'Keine Kategorie');
		}

		body.appendChild(titleEl);
		body.appendChild(catEl);

		card.appendChild(selectWrapper);
		card.appendChild(imgWrapper);
		card.appendChild(body);

		grid.appendChild(card);
	}

	if (pageInfo) {
		const tmpl = iomsg('page_info', 'Seite {page} / {pages}');
		pageInfo.textContent = tmpl
			.replace('{page}', String(page))
			.replace('{pages}', String(pages || 1));
	}
	if (pagePrev) {
		pagePrev.disabled = page <= 1;
	}
	if (pageNext) {
		pageNext.disabled = page >= pages;
	}

	updateCategoryFilterFromItems(items);
	updateExportButtonState();
}

/**
 * Kategorie-Dropdown aus den geladenen Items füllen.
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

	// Dropdown neu aufbauen
	select.innerHTML = '';
	const optAll = document.createElement('option');
	optAll.value = '';
	optAll.textContent = iomsg('category_all', 'Alle Kategorien');
	select.appendChild(optAll);

	for (const name of categories) {
		const opt = document.createElement('option');
		opt.value = name;
		opt.textContent = name;
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
 * IDs der aktuell ausgewählten (nicht eigenen) Rezepte holen.
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
 * Export-Button aktivieren/deaktivieren.
 */
function updateExportButtonState() {
	const ids = getSelectedRecipeIds();
	const disable = ids.length === 0;

	const btnExportTop = document.getElementById('export-selected');
	const btnExportBottom = document.getElementById('export-selected-bottom');
	const btnImportTop = document.getElementById('import-selected');
	const btnImportBottom = document.getElementById('import-selected-bottom');

	if (btnExportTop) btnExportTop.disabled = disable;
	if (btnExportBottom) btnExportBottom.disabled = disable;
	if (btnImportTop) btnImportTop.disabled = disable;
	if (btnImportBottom) btnImportBottom.disabled = disable;
}


/**
 * Alle Rezepte der aktuellen Seite auswählen (soweit auswählbar).
 */
function selectAllRecipes() {
	const boxes = document.querySelectorAll('.recipe-select');
	boxes.forEach(b => {
		if (!b.disabled) {
			b.checked = true;
		}
	});
}

/**
 * Auswahl aller Rezepte der aktuellen Seite aufheben.
 */
function selectNoneRecipes() {
	const boxes = document.querySelectorAll('.recipe-select');
	boxes.forEach(b => {
		b.checked = false;
	});
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
 * Detailansicht öffnen.
 */
function openRecipeView(id) {
	window.location.href = 'view.php?id=' + encodeURIComponent(id);
}

/**
 * Fehlermeldung in den Container schreiben.
 */
function renderError(message) {
	const container = document.getElementById('recipes-container');
	if (!container) return;
	container.innerHTML = '';

	const p = document.createElement('p');
	p.className = 'error-message';
	p.textContent = message;
	container.appendChild(p);
}

/**
 * Einfaches Debounce.
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
