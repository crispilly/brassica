// public/view.js

const API_BASE = '../api';

document.addEventListener('DOMContentLoaded', () => {
	if (!RECIPE_ID || RECIPE_ID <= 0) {
		alert(vmsg('import_invalid_id', 'Ungültige Rezept-ID.'));
		return;
	}
	loadRecipe(RECIPE_ID);
});

/**
 * Rezept aus DB laden
 */
async function loadRecipe(id) {
	try {
		const res = await fetch(`${API_BASE}/recipe_get.php?id=${id}`);
		if (!res.ok) {
			throw new Error(`HTTP ${res.status}`);
		}
		const data = await res.json();
		renderRecipe(data);
	} catch (err) {
		const logMsg = vmsg('load_error_log', 'Fehler beim Laden des Rezepts:');
		console.error(logMsg, err);
		alert(vmsg('load_error', 'Fehler beim Laden des Rezepts.'));
	}
}

/**
 * Anzeige bauen – nur Felder mit Inhalt werden angezeigt
 */
function renderRecipe(r) {
	const fallbackTitle = vmsg('title_fallback', 'Rezept');
	const title = r.title || fallbackTitle;
	document.getElementById('view-title').textContent = title;

	// Bild
	const imgBox = document.getElementById('view-image');
	if (r.image_url) {
		imgBox.classList.remove('hidden');
		imgBox.innerHTML = '';
		const img = document.createElement('img');
		img.src = r.image_url;
		img.alt = title;
		imgBox.appendChild(img);
	} else {
		imgBox.classList.add('hidden');
		imgBox.innerHTML = '';
	}

	// Meta (Kategorien, Zeit, Portionen)
	const metaBox = document.getElementById('view-meta');
	let metaHtml = '';

	if (Array.isArray(r.categories) && r.categories.length > 0) {
		const cats = r.categories
			.map(c => (c && typeof c.name === 'string' ? c.name.trim() : ''))
			.filter(Boolean);

		if (cats.length > 0) {
			const catLabel = escapeHtml(vmsg('meta_category_label', 'Kategorie:'));
			metaHtml += `<div><strong>${catLabel}</strong> ${escapeHtml(cats.join(', '))}</div>`;
		}
	}

	if (r.preparation_time) {
		const timeLabel = escapeHtml(vmsg('meta_time_label', 'Zeit:'));
		metaHtml += `<div><strong>${timeLabel}</strong> ${escapeHtml(r.preparation_time)}</div>`;
	}
	if (r.servings) {
		const servingsLabel = escapeHtml(vmsg('meta_servings_label', 'Portionen:'));
		metaHtml += `<div><strong>${servingsLabel}</strong> ${escapeHtml(r.servings)}</div>`;
	}

	if (metaHtml) {
		metaBox.classList.remove('hidden');
		metaBox.innerHTML = metaHtml;
	} else {
		metaBox.classList.add('hidden');
		metaBox.innerHTML = '';
	}

	// Inhaltsblöcke – nur anzeigen, wenn etwas drin ist
	setSection('view-ingredients', vmsg('section_ingredients', 'Zutaten'), r.ingredients);
	setSection('view-directions', vmsg('section_directions', 'Zubereitung'), r.directions);
	setSection('view-notes', vmsg('section_notes', 'Notizen'), r.notes);
	setSection('view-nutrition', vmsg('section_nutrition', 'Nährwerte'), r.nutritional_vals);

	// Quelle
	const srcBox = document.getElementById('view-source');
	const sourceStr = r.source ? String(r.source).trim() : '';

	if (sourceStr !== '') {
		const isLink = /^https?:\/\/\S+$/i.test(sourceStr);

		srcBox.classList.remove('hidden');

		const sourceHeading = escapeHtml(vmsg('section_source', 'Quelle'));

		if (isLink) {
			const safeUrl = escapeUrl(sourceStr);
			srcBox.innerHTML = `
				<h2>${sourceHeading}</h2>
				<p><a href="${safeUrl}" target="_blank" rel="noopener noreferrer">${escapeHtml(sourceStr)}</a></p>
			`;
		} else {
			srcBox.innerHTML = `
				<h2>${sourceHeading}</h2>
				<p>${escapeHtml(sourceStr)}</p>
			`;
		}
	} else {
		srcBox.classList.add('hidden');
		srcBox.innerHTML = '';
	}

 	// Aktions-Buttons je nach Owner-Status
 	updateActionButtons(!!r.is_owner);
 }

function updateActionButtons(isOwner) {
	const editTop = document.getElementById('btn-edit-top');
	const editBottom = document.getElementById('btn-edit-bottom');
	const importTop = document.getElementById('btn-import-top');
	const importBottom = document.getElementById('btn-import-bottom');

	const showEdit = !!isOwner;
	const showImport = !isOwner;

	if (editTop) editTop.classList.toggle('hidden', !showEdit);
	if (editBottom) editBottom.classList.toggle('hidden', !showEdit);

	if (importTop) importTop.classList.toggle('hidden', !showImport);
	if (importBottom) importBottom.classList.toggle('hidden', !showImport);
}


/* Hilfsfunktion: Abschnitt setzen oder ausblenden */

function setSection(elementId, title, content) {
	const el = document.getElementById(elementId);
	if (!el) return;

	if (!content || String(content).trim() === '') {
		el.classList.add('hidden');
		el.innerHTML = '';
		return;
	}

	el.classList.remove('hidden');
	el.innerHTML = `
		<h2>${escapeHtml(title)}</h2>
		<div class="view-content">${escapeHtml(content).replace(/\n/g, '<br>')}</div>
	`;
}

/* Zurück zur Übersicht */

function goBack() {
	window.location.href = 'index.php';
}


/* Weiter zum Editor */
function openEditor() {
	window.location.href = `editor.php?id=${RECIPE_ID}`;
}

/* Als Broccoli-Datei herunterladen */

function downloadBroccoli() {
	window.location.href = `editor.php?id=${RECIPE_ID}&download=1`;
}

/* Rezept in eigene Rezepte übernehmen */

async function importRecipeToMe(id) {
	try {
		const res = await fetch(`${API_BASE}/recipe_clone_to_me.php`, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Accept': 'application/json',
			},
			body: JSON.stringify({ id })
		});

		if (res.status === 401) {
			alert(vmsg('login_required_import', 'Bitte melde Dich an, um das Rezept zu übernehmen.'));
			return;
		}

		if (!res.ok) {
			throw new Error(`HTTP ${res.status}`);
		}

		const data = await res.json();

		if (!data.success) {
			const prefix = vmsg('import_failed_prefix', 'Import fehlgeschlagen: ');
			const unknown = vmsg('import_unknown_error', 'Unbekannter Fehler');
			alert(prefix + (data.message || unknown));
			return;
		}

		if (data.imported && data.new_recipe_id) {
			alert(vmsg('import_success', 'Rezept wurde in Deine Rezepte übernommen.'));
		} else {
			const fallback = vmsg('import_already_owned', 'Rezept gehört bereits Dir.');
			alert(data.message || fallback);
		}
	} catch (err) {
		const logMsg = vmsg('import_error_log', 'Fehler beim Einzelimport:');
		console.error(logMsg, err);
		alert(vmsg('import_generic_error', 'Fehler beim Übernehmen des Rezepts.'));
	}
}

/* Escape-Helfer */

function escapeHtml(str) {
	return String(str)
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#039;');
}

function escapeUrl(str) {
	return String(str).replace(/"/g, '%22');
}
