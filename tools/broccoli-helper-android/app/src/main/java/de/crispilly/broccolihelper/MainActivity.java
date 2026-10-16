package de.crispilly.broccolihelper;

import android.app.Activity;
import android.content.ActivityNotFoundException;
import android.content.ClipData;
import android.content.Intent;
import android.database.Cursor;
import android.net.Uri;
import android.os.Bundle;
import android.provider.OpenableColumns;
import android.widget.Button;
import android.widget.EditText;
import android.widget.TextView;
import android.widget.Toast;

import androidx.core.content.FileProvider;

import org.json.JSONException;
import org.json.JSONObject;

import java.io.File;
import java.io.FileOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.nio.charset.StandardCharsets;
import java.util.Locale;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.zip.ZipEntry;
import java.util.zip.ZipOutputStream;

public final class MainActivity extends Activity {

    private static final int PICK_IMAGE = 100;
    private static final String BRASSICA_PACKAGE = "de.crispilly.brassica";

    private EditText jsonInput;
    private TextView selectedImageLabel;
    private Button sendButton;
    private Uri selectedImageUri;
    private final ExecutorService executor = Executors.newSingleThreadExecutor();

    @Override
    protected void onCreate(Bundle state) {
        super.onCreate(state);
        setContentView(R.layout.activity_main);

        jsonInput = findViewById(R.id.json_input);
        selectedImageLabel = findViewById(R.id.selected_image_label);
        sendButton = findViewById(R.id.send_to_brassica);

        findViewById(R.id.select_image).setOnClickListener(v -> selectImage());
        sendButton.setOnClickListener(v -> sendToBrassica());

        if (state != null) {
            selectedImageUri = state.getParcelable("selectedImageUri");
            updateImageLabel();
        }
    }

    private void selectImage() {
        Intent intent = new Intent(Intent.ACTION_OPEN_DOCUMENT);
        intent.addCategory(Intent.CATEGORY_OPENABLE);
        intent.setType("image/*");
        intent.putExtra(Intent.EXTRA_MIME_TYPES,
                new String[]{"image/jpeg", "image/png", "image/webp"});
        startActivityForResult(intent, PICK_IMAGE);
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        super.onActivityResult(requestCode, resultCode, data);

        if (requestCode != PICK_IMAGE || resultCode != RESULT_OK ||
                data == null || data.getData() == null) {
            return;
        }

        selectedImageUri = data.getData();
        try {
            getContentResolver().takePersistableUriPermission(
                    selectedImageUri, Intent.FLAG_GRANT_READ_URI_PERMISSION);
        } catch (SecurityException ignored) {
            // Der Picker kann die URI auch ohne dauerhafte Berechtigung bereitstellen.
        }
        updateImageLabel();
    }

    private void updateImageLabel() {
        selectedImageLabel.setText(selectedImageUri == null
                ? "Kein Bild ausgewählt"
                : imageNameFor(selectedImageUri));
    }

    private String imageNameFor(Uri uri) {
        String name = null;
        try (Cursor cursor = getContentResolver().query(
                uri, new String[]{OpenableColumns.DISPLAY_NAME}, null, null, null)) {
            if (cursor != null && cursor.moveToFirst()) {
                name = cursor.getString(0);
            }
        } catch (Exception ignored) {
            // Bei Providern ohne Dateinamen wird die MIME-Type-Erweiterung verwendet.
        }

        String mimeType = getContentResolver().getType(uri);
        if (name == null || name.trim().isEmpty()) {
            if ("image/jpeg".equals(mimeType)) name = "bild.jpg";
            else if ("image/png".equals(mimeType)) name = "bild.png";
            else if ("image/webp".equals(mimeType)) name = "bild.webp";
            else name = "bild";
        }

        // Ein ZIP-Eintrag darf keinen Verzeichnispfad aus der Dateiauswahl übernehmen.
        return name.replace('/', '_').replace('\\', '_').replace('\0', '_');
    }

    private void sendToBrassica() {
        if (selectedImageUri == null) {
            show("Bitte zuerst ein Bild auswählen.");
            return;
        }

        String imageName = imageNameFor(selectedImageUri);
        String lower = imageName.toLowerCase(Locale.ROOT);
        if (!(lower.endsWith(".jpg") || lower.endsWith(".jpeg") ||
                lower.endsWith(".png") || lower.endsWith(".webp"))) {
            show("Brassica unterstützt hier JPG, PNG und WebP. Bitte ein entsprechendes Bild auswählen.");
            return;
        }

        final String updatedJson;
        final String displayJson;
        try {
            JSONObject json = new JSONObject(jsonInput.getText().toString());
            json.put("imageName", imageName);
            updatedJson = json.toString();
            displayJson = json.toString(2);
        } catch (JSONException ex) {
            show("Ungültiges Rezept-JSON: " + ex.getMessage());
            return;
        }

        // Sichtbar machen, welcher Dateiname tatsächlich in der JSON steht.
        jsonInput.setText(displayJson);
        jsonInput.setSelection(jsonInput.length());

        final Uri imageUri = selectedImageUri;
        sendButton.setEnabled(false);

        executor.execute(() -> {
            try {
                File folder = new File(getCacheDir(), "broccoli_exports");
                if (!folder.exists() && !folder.mkdirs()) {
                    throw new IOException("Temporärer Ordner konnte nicht angelegt werden.");
                }

                File broccoli = new File(folder, "rezept-" +
                        System.currentTimeMillis() + ".broccoli");

                try (ZipOutputStream zip =
                             new ZipOutputStream(new FileOutputStream(broccoli))) {
                    zip.putNextEntry(new ZipEntry("rezept.json"));
                    zip.write(updatedJson.getBytes(StandardCharsets.UTF_8));
                    zip.closeEntry();

                    zip.putNextEntry(new ZipEntry(imageName));
                    try (InputStream imageStream =
                                 getContentResolver().openInputStream(imageUri)) {
                        if (imageStream == null) {
                            throw new IOException("Bilddatei konnte nicht geöffnet werden.");
                        }
                        byte[] buffer = new byte[32768];
                        int count;
                        while ((count = imageStream.read(buffer)) != -1) {
                            zip.write(buffer, 0, count);
                        }
                    }
                    zip.closeEntry();
                }

                Uri contentUri = FileProvider.getUriForFile(
                        MainActivity.this,
                        getPackageName() + ".fileprovider",
                        broccoli
                );

                runOnUiThread(() -> {
                    sendButton.setEnabled(true);
                    Intent intent = new Intent(Intent.ACTION_VIEW);
                    intent.setDataAndType(contentUri, "application/broccoli");
                    intent.setPackage(BRASSICA_PACKAGE);
                    intent.setClipData(ClipData.newRawUri("Rezept", contentUri));
                    intent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);

                    try {
                        startActivity(intent);
                    } catch (ActivityNotFoundException ex) {
                        show("Brassica Android wurde nicht gefunden oder kann die Datei nicht öffnen.");
                    }
                });
            } catch (Exception ex) {
                runOnUiThread(() -> {
                    sendButton.setEnabled(true);
                    show("Export fehlgeschlagen: " + ex.getMessage());
                });
            }
        });
    }

    @Override
    protected void onSaveInstanceState(Bundle outState) {
        outState.putParcelable("selectedImageUri", selectedImageUri);
        super.onSaveInstanceState(outState);
    }

    @Override
    protected void onDestroy() {
        executor.shutdown();
        super.onDestroy();
    }

    private void show(String message) {
        Toast.makeText(this, message, Toast.LENGTH_LONG).show();
    }
}
