document.addEventListener('DOMContentLoaded', () => {
	const btn = document.getElementById('btn-import-recipe');
	if (!btn) return;

	btn.addEventListener('click', async () => {
		const archiveId = parseInt(btn.dataset.archiveId || '0', 10) || 0;
		const filename = btn.dataset.filename || '';

		if (!archiveId || !filename) {
			alert('Archiv-ID oder Dateiname fehlt.');
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

			if (Array.isArray(data.imported_ids) && data.imported_ids.length === 0) {
				// Ein Rezept ausgewählt, nichts importiert -> Duplikat
				alert('Rezept existiert bereits. Kein Rezept übernommen.');
			} else {
				alert(`Import abgeschlossen. ${data.imported_ids.length} Rezepte übernommen.`);
			}

			// zurück zur Archiv-Liste mit gleicher archive_id
			window.location.href = 'archive.php?archive_id=' + archiveId;
		} catch (e) {
			console.error(e);
			alert('Fehler beim Importieren.');
		}
	});
});
