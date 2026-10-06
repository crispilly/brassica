// public/archive_view.js

function avmsg(key, fallback) {
	if (window.archiveViewMessages && Object.prototype.hasOwnProperty.call(window.archiveViewMessages, key)) {
		return window.archiveViewMessages[key];
	}
	// Fallback: ggf. gemeinsame Messages aus archive.js mitbenutzen
	if (window.archiveMessages && Object.prototype.hasOwnProperty.call(window.archiveMessages, key)) {
		return window.archiveMessages[key];
	}
	return fallback;
}

document.addEventListener('DOMContentLoaded', () => {
	const btn = document.getElementById('btn-import-recipe');
	if (!btn) return;

	btn.addEventListener('click', async () => {
		const archiveId = parseInt(btn.dataset.archiveId || '0', 10) || 0;
		const filename = btn.dataset.filename || '';

		if (!archiveId || !filename) {
			alert(avmsg('missing_archive_or_filename', 'Archiv-ID oder Dateiname fehlt.'));
			return;
		}

		try {
			const res = await fetch('../api/archive_import.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					archive_id: archiveId,
					filenames: [filename]
				})
			});

			if (!res.ok) {
				throw new Error('HTTP ' + res.status);
			}

			const data = await res.json();
			const count = Array.isArray(data.imported_ids) ? data.imported_ids.length : 0;

			if (count === 0) {
				// Ein Rezept ausgewählt, nichts importiert -> Duplikat
				alert(avmsg('duplicate_only_message', 'Rezept existiert bereits. Kein Rezept übernommen.'));
			} else {
				let msg = avmsg('import_done', 'Import abgeschlossen. {count} Rezepte übernommen.');
				msg = msg.replace('{count}', String(count));
				alert(msg);
			}

			// zurück zur Archiv-Liste mit gleicher archive_id
			window.location.href = 'archive.php?archive_id=' + archiveId;
		} catch (e) {
			console.error(e);
			alert(avmsg('import_error', 'Fehler beim Importieren.'));
		}
	});
});
