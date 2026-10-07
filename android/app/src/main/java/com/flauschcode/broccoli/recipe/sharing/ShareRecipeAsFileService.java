package com.flauschcode.broccoli.recipe.sharing;

import android.app.Application;
import android.net.Uri;
import android.util.Log;

import androidx.core.content.FileProvider;

import com.flauschcode.broccoli.BuildConfig;
import com.flauschcode.broccoli.recipe.Recipe;

import java.io.File;
import java.io.FileNotFoundException;
import java.io.FileOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.util.Optional;
import java.util.zip.ZipInputStream;
import java.util.zip.ZipOutputStream;

import javax.inject.Inject;

public class ShareRecipeAsFileService {

    private static final String AUTHORITY = BuildConfig.APPLICATION_ID + ".fileprovider";

    private final Application application;
    private final RecipeZipWriter recipeZipWriter;
    private final RecipeZipReader recipeZipReader;

    @Inject
    public ShareRecipeAsFileService(Application application, RecipeZipWriter recipeZipWriter, RecipeZipReader recipeZipReader) {
        this.application = application;
        this.recipeZipWriter = recipeZipWriter;
        this.recipeZipReader = recipeZipReader;
    }

    public Uri shareAsFile(Recipe recipe) throws IOException {
        File zipFile = new File(application.getCacheDir(), suggestedFileName(recipe));

        try (FileOutputStream fos = new FileOutputStream(zipFile)) {
            writeRecipe(recipe, fos);
        }

        return FileProvider.getUriForFile(application, AUTHORITY, zipFile);
    }

    public String suggestedFileName(Recipe recipe) {
        String title = recipe.getTitle() == null ? "recipe" : recipe.getTitle();
        String safeTitle = title.replaceAll("[^a-zA-Z0-9\\.\\-]", "_");
        if (safeTitle.isEmpty()) {
            safeTitle = "recipe";
        }
        return safeTitle + ".broccoli";
    }

    public void saveAsFile(Recipe recipe, Uri target) throws IOException {
        try (java.io.OutputStream out = application.getContentResolver().openOutputStream(target)) {
            if (out == null) {
                throw new IOException("Zieldatei konnte nicht geöffnet werden.");
            }
            writeRecipe(recipe, out);
        }
    }

    private void writeRecipe(Recipe recipe, java.io.OutputStream out) throws IOException {
        try (ZipOutputStream zos = new ZipOutputStream(out)) {
            zos.setComment(String.valueOf(BuildConfig.VERSION_CODE));
            recipeZipWriter.write(recipe).to(zos);
        }
    }

    public Optional<Recipe> loadFromFile(Uri uri) {
        InputStream inputStream;
        try {
            inputStream = application.getContentResolver().openInputStream(uri);
        } catch (FileNotFoundException e) {
            Log.e(getClass().getName(), e.getMessage());
            return Optional.empty();
        }

        try (ZipInputStream zis = new ZipInputStream(inputStream)) {
            return recipeZipReader.read()
                    .unfavored()
                    .keepOnlyExistingCategories()
                    .from(zis);
        } catch (IOException e) {
            Log.e(getClass().getName(), e.getMessage());
            return Optional.empty();
        }
    }

}
