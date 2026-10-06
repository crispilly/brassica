package com.flauschcode.broccoli.recipe.transfer;

import android.app.Application;
import android.database.Cursor;
import android.net.Uri;
import android.provider.OpenableColumns;

import com.fasterxml.jackson.core.JsonGenerator;
import com.fasterxml.jackson.databind.ObjectMapper;
import com.flauschcode.broccoli.BuildConfig;
import com.flauschcode.broccoli.backup.RestoreService;
import com.flauschcode.broccoli.category.Category;
import com.flauschcode.broccoli.category.CategoryRepository;
import com.flauschcode.broccoli.recipe.Recipe;
import com.flauschcode.broccoli.recipe.RecipeRepository;
import com.flauschcode.broccoli.recipe.images.RecipeImageService;
import com.flauschcode.broccoli.recipe.sharing.RecipeZipReader;
import com.flauschcode.broccoli.recipe.sharing.RecipeZipWriter;

import org.apache.commons.io.output.CloseShieldOutputStream;

import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;
import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import java.util.Optional;
import java.util.concurrent.CompletableFuture;
import java.util.concurrent.CompletionException;
import java.util.zip.ZipEntry;
import java.util.zip.ZipInputStream;
import java.util.zip.ZipOutputStream;

import javax.inject.Inject;
import javax.inject.Singleton;

@Singleton
public class RecipeFileService {
    private final Application application;
    private final RecipeZipReader reader;
    private final RecipeZipWriter writer;
    private final RecipeRepository recipeRepository;
    private final CategoryRepository categoryRepository;
    private final RecipeImageService imageService;
    private final RestoreService restoreService;

    @Inject
    public RecipeFileService(Application application, RecipeZipReader reader, RecipeZipWriter writer,
                             RecipeRepository recipeRepository, CategoryRepository categoryRepository,
                             RecipeImageService imageService, RestoreService restoreService) {
        this.application = application;
        this.reader = reader;
        this.writer = writer;
        this.recipeRepository = recipeRepository;
        this.categoryRepository = categoryRepository;
        this.imageService = imageService;
        this.restoreService = restoreService;
    }

    public CompletableFuture<ImportResult> importFrom(Uri uri) {
        return CompletableFuture.supplyAsync(() -> {
            try {
                String name = displayName(uri).toLowerCase(java.util.Locale.ROOT);
                if (name.endsWith(".broccoli-archive") || looksLikeArchive(uri)) {
                    int count = restoreService.restore(uri).get();
                    return new ImportResult(count, true);
                }
                return new ImportResult(importSingle(uri), false);
            } catch (Exception e) {
                throw new CompletionException(e);
            }
        });
    }

    private int importSingle(Uri uri) throws Exception {
        try (InputStream in = application.getContentResolver().openInputStream(uri);
             ZipInputStream zis = new ZipInputStream(in)) {
            Optional<Recipe> optional = reader.read().unfavored().from(zis);
            if (optional.isEmpty()) throw new IOException("Keine gültige .broccoli-Datei.");
            Recipe recipe = optional.get();
            recipe.setRecipeId(0);

            List<Category> requested = recipe.getCategories() == null ? new ArrayList<>() : recipe.getCategories();
            List<Category> missing = categoryRepository.retainNonExisting(requested).get();
            for (Category category : missing) categoryRepository.insertOrUpdate(category).get();
            recipe.setCategories(categoryRepository.retainExisting(requested).get());

            recipeRepository.insertOrUpdate(recipe).get();
            if (recipe.getImageName() != null && !recipe.getImageName().isEmpty()) imageService.moveImage(recipe.getImageName()).get();
            return 1;
        }
    }

    public CompletableFuture<Void> exportTo(Uri uri, List<Recipe> recipes) {
        return CompletableFuture.runAsync(() -> {
            if (recipes == null || recipes.isEmpty()) throw new CompletionException(new IOException("Keine Rezepte ausgewählt."));
            try (OutputStream raw = application.getContentResolver().openOutputStream(uri);
                 ZipOutputStream zos = new ZipOutputStream(raw)) {
                if (recipes.size() == 1) {
                    writer.write(recipes.get(0)).to(zos);
                } else {
                    writeArchive(zos, recipes);
                }
            } catch (IOException e) {
                throw new CompletionException(e);
            }
        });
    }

    private void writeArchive(ZipOutputStream zos, List<Recipe> recipes) throws IOException {
        zos.setComment(String.valueOf(BuildConfig.VERSION_CODE));
        Map<String, Category> categories = new LinkedHashMap<>();
        for (Recipe recipe : recipes) for (Category c : recipe.getCategories()) categories.put(c.getName().toLowerCase(java.util.Locale.ROOT), c);

        zos.putNextEntry(new ZipEntry("categories.json"));
        ObjectMapper mapper = new ObjectMapper();
        mapper.configure(JsonGenerator.Feature.AUTO_CLOSE_TARGET, false);
        mapper.writeValue(zos, new ArrayList<>(categories.values()));
        zos.closeEntry();

        for (Recipe recipe : recipes) {
            String safeTitle = recipe.getTitle().replaceAll("[^a-zA-Z0-9\\.\\-]", "_");
            zos.putNextEntry(new ZipEntry(recipe.getRecipeId() + "_" + safeTitle + ".broccoli"));
            try (CloseShieldOutputStream shield = CloseShieldOutputStream.wrap(zos);
                 ZipOutputStream nested = new ZipOutputStream(shield)) {
                writer.write(recipe).to(nested);
                nested.finish();
            }
            zos.closeEntry();
        }
    }

    private boolean looksLikeArchive(Uri uri) {
        try (InputStream in = application.getContentResolver().openInputStream(uri); ZipInputStream zis = new ZipInputStream(in)) {
            ZipEntry entry;
            while ((entry = zis.getNextEntry()) != null) {
                String name = entry.getName().toLowerCase(java.util.Locale.ROOT);
                if ("categories.json".equals(name) || name.endsWith(".broccoli")) return true;
            }
        } catch (IOException ignored) { }
        return false;
    }

    private String displayName(Uri uri) {
        try (Cursor c = application.getContentResolver().query(uri, new String[]{OpenableColumns.DISPLAY_NAME}, null, null, null)) {
            if (c != null && c.moveToFirst()) return c.getString(0);
        }
        String last = uri.getLastPathSegment();
        return last == null ? "" : last;
    }

    public static class ImportResult {
        public final int count;
        public final boolean archive;
        public ImportResult(int count, boolean archive) { this.count = count; this.archive = archive; }
    }
}
