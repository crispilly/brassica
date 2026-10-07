package com.flauschcode.broccoli.sync;

import android.app.Application;
import android.os.Build;
import android.util.Base64;

import androidx.preference.PreferenceManager;

import android.content.SharedPreferences;

import com.fasterxml.jackson.core.type.TypeReference;
import com.fasterxml.jackson.databind.ObjectMapper;
import com.flauschcode.broccoli.FileUtils;
import com.flauschcode.broccoli.category.Category;
import com.flauschcode.broccoli.category.CategoryRepository;
import com.flauschcode.broccoli.recipe.Recipe;
import com.flauschcode.broccoli.recipe.RecipeRepository;
import com.flauschcode.broccoli.recipe.images.RecipeImageService;

import java.io.ByteArrayOutputStream;
import java.io.File;
import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.util.ArrayList;
import java.util.Comparator;
import java.util.HashMap;
import java.util.LinkedHashMap;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Map;
import java.util.Set;
import java.util.UUID;
import java.util.concurrent.CompletableFuture;
import java.util.concurrent.CompletionException;

import javax.inject.Inject;
import javax.inject.Singleton;

@Singleton
public class BrassicaSyncService {
    public enum Action { UPLOAD, DOWNLOAD, CONFLICT }

    public interface ProgressListener {
        void onProgress(int completed, int total, String title);
    }

    public static class SyncItem {
        public String uuid;
        public String title;
        public String localHash;
        public String remoteHash;
        public List<String> categories = new ArrayList<>();
        public Action action;
        public Recipe localRecipe;
        public boolean selected;
    }

    private final Application application;
    private final RecipeRepository recipeRepository;
    private final CategoryRepository categoryRepository;
    private final RecipeImageService imageService;
    private final ObjectMapper mapper = new ObjectMapper();
    private final SharedPreferences prefs;

    @Inject
    public BrassicaSyncService(
            Application application,
            RecipeRepository recipeRepository,
            CategoryRepository categoryRepository,
            RecipeImageService imageService
    ) {
        this.application = application;
        this.recipeRepository = recipeRepository;
        this.categoryRepository = categoryRepository;
        this.imageService = imageService;
        this.prefs = PreferenceManager.getDefaultSharedPreferences(application);
    }

    public void saveSettings(String server, String user) {
        prefs.edit()
                .putString("sync-server", normalizeServer(server))
                .putString("sync-user", user.trim())
                .apply();
    }

    public String getServer() {
        return prefs.getString("sync-server", "");
    }

    public String getUser() {
        return prefs.getString("sync-user", "");
    }

    /**
     * Passwörter werden ab 2.0.2 nicht mehr dauerhaft gespeichert.
     * Ein eventuell aus 2.0.0/2.0.1 vorhandenes Passwort wird nur noch
     * einmalig zur Erzeugung des Geräte-Keys benutzt und danach gelöscht.
     */
    public String getPassword() {
        return "";
    }

    public boolean hasSyncKey(String server, String user) {
        return tokenFor(server, user) != null;
    }

    public CompletableFuture<String> shareWebLink(Recipe recipe) {
        return CompletableFuture.supplyAsync(() -> {
            try {
                String server = getServer();
                String user = getUser();

                if (server == null || server.trim().isEmpty() || user == null || user.trim().isEmpty()) {
                    throw new IOException("Bitte zuerst die Brassica-Synchronisierung einrichten.");
                }

                String token = tokenFor(server, user);
                if (token == null || token.isEmpty()) {
                    throw new IOException("Bitte zuerst einmal synchronisieren, damit der Geräte-Key eingerichtet wird.");
                }

                String uuid = uuidFor(recipe.getRecipeId());
                String localHash = syncHash(recipe);

                SyncItem item = new SyncItem();
                item.uuid = uuid;
                item.title = recipe.getTitle();
                item.localRecipe = recipe;
                item.localHash = localHash;
                item.categories = categoryNames(recipe);

                Map<String, Object> body = new LinkedHashMap<>();
                body.put("recipe", uploadPayload(item));

                String base = baseHash(uuid);
                if (base != null && !base.isEmpty()) {
                    body.put("baseHash", base);
                }

                byte[] response;
                try {
                    response = requestJson(
                            "POST",
                            normalizeServer(server) + "/api/v1/share/recipe",
                            "Bearer " + token,
                            mapper.writeValueAsBytes(body)
                    );
                } catch (IOException e) {
                    if (isUnauthorized(e)) {
                        clearToken(server, user);
                        throw new IOException("Der Sync-Key ist ungültig oder wurde widerrufen. Bitte zuerst erneut synchronisieren.");
                    }
                    throw e;
                }

                Map<String, Object> root = mapper.readValue(
                        response,
                        new TypeReference<Map<String, Object>>() {}
                );

                String serverHash = nullableString(root.get("syncHash"));
                if (serverHash == null || !localHash.equals(serverHash)) {
                    throw new IOException("Der Webserver hat nach der Freigabe einen abweichenden Rezeptstand gemeldet.");
                }

                String shareUrl = nullableString(root.get("url"));
                if (shareUrl == null || shareUrl.isEmpty()) {
                    throw new IOException("Der Webserver hat keinen Freigabe-Link geliefert.");
                }

                saveBaseHash(uuid, serverHash);

                if (!shareUrl.matches("^https?://.*")) {
                    if (!shareUrl.startsWith("/")) {
                        shareUrl = "/" + shareUrl;
                    }
                    shareUrl = normalizeServer(server) + shareUrl;
                }

                return shareUrl;
            } catch (Exception e) {
                throw new CompletionException(e);
            }
        });
    }

    public CompletableFuture<List<SyncItem>> preview(String server, String user, String password) {
        return CompletableFuture.supplyAsync(() -> {
            try {
                saveSettings(server, user);
                String token = ensureToken(server, user, password);
                Map<String, RemoteItem> remote;

                try {
                    remote = loadRemoteManifest(server, token);
                } catch (IOException e) {
                    if (isUnauthorized(e)) {
                        clearToken(server, user);
                        throw new IOException("Der Sync-Key ist ungültig oder wurde widerrufen. Bitte Passwort einmalig neu eingeben.");
                    }
                    throw e;
                }

                List<Recipe> localRecipes = recipeRepository.findAll().get();
                Map<String, SyncItem> result = new LinkedHashMap<>();

                for (Recipe recipe : localRecipes) {
                    String localHash = syncHash(recipe);
                    String uuid = prefs.getString("sync-uuid-" + recipe.getRecipeId(), null);

                    if (uuid == null) {
                        RemoteItem exact = null;
                        for (RemoteItem candidate : remote.values()) {
                            if (localHash.equals(candidate.syncHash)) {
                                exact = candidate;
                                break;
                            }
                        }

                        if (exact != null) {
                            uuid = exact.uuid;
                            prefs.edit()
                                    .putString("sync-uuid-" + recipe.getRecipeId(), uuid)
                                    .apply();
                            saveBaseHash(uuid, localHash);
                        } else {
                            uuid = uuidFor(recipe.getRecipeId());
                        }
                    }

                    SyncItem item = new SyncItem();
                    item.uuid = uuid;
                    item.title = recipe.getTitle();
                    item.localRecipe = recipe;
                    item.localHash = localHash;
                    item.categories = categoryNames(recipe);
                    item.selected = true;

                    RemoteItem remoteItem = remote.remove(uuid);

                    if (remoteItem == null) {
                        item.action = Action.UPLOAD;
                    } else {
                        item.remoteHash = remoteItem.syncHash;
                        item.categories = merge(item.categories, remoteItem.categories);

                        if (item.localHash.equals(item.remoteHash)) {
                            saveBaseHash(uuid, item.localHash);
                            continue;
                        }

                        String baseHash = baseHash(uuid);

                        if (baseHash != null && item.localHash.equals(baseHash) && !item.remoteHash.equals(baseHash)) {
                            item.action = Action.DOWNLOAD;
                        } else if (baseHash != null && item.remoteHash.equals(baseHash) && !item.localHash.equals(baseHash)) {
                            item.action = Action.UPLOAD;
                        } else {
                            item.action = Action.CONFLICT;
                            item.selected = false;
                        }
                    }

                    result.put(uuid, item);
                }

                for (RemoteItem remoteItem : remote.values()) {
                    SyncItem item = new SyncItem();
                    item.uuid = remoteItem.uuid;
                    item.title = remoteItem.title;
                    item.remoteHash = remoteItem.syncHash;
                    item.categories = remoteItem.categories;
                    item.action = Action.DOWNLOAD;
                    item.selected = true;
                    result.put(item.uuid, item);
                }

                List<SyncItem> out = new ArrayList<>(result.values());
                out.sort(Comparator.comparing(
                        i -> i.title == null ? "" : i.title,
                        String.CASE_INSENSITIVE_ORDER
                ));
                return out;
            } catch (Exception e) {
                throw new CompletionException(e);
            }
        });
    }

    public CompletableFuture<Integer> sync(
            String server,
            String user,
            String password,
            List<SyncItem> items,
            ProgressListener progressListener
    ) {
        return CompletableFuture.supplyAsync(() -> {
            try {
                saveSettings(server, user);
                String token = ensureToken(server, user, password);

                List<SyncItem> selected = new ArrayList<>();
                for (SyncItem item : items) {
                    if (item.selected && item.action != Action.CONFLICT) {
                        selected.add(item);
                    }
                }

                int total = selected.size();
                int completed = 0;

                if (progressListener != null) {
                    progressListener.onProgress(0, total, "");
                }

                for (SyncItem item : selected) {
                    if (item.action == Action.UPLOAD) {
                        String serverHash = uploadRecipe(server, token, item);
                        if (item.localHash != null && !item.localHash.equals(serverHash)) {
                            throw new IOException("Hash-Prüfung nach Upload fehlgeschlagen: " + safeTitle(item.title));
                        }
                        saveBaseHash(item.uuid, serverHash);
                    } else if (item.action == Action.DOWNLOAD) {
                        String localHash = downloadRecipe(server, token, item);
                        if (item.remoteHash != null && !item.remoteHash.equals(localHash)) {
                            throw new IOException("Hash-Prüfung nach Download fehlgeschlagen: " + safeTitle(item.title));
                        }
                        saveBaseHash(item.uuid, localHash);
                    }

                    completed++;
                    if (progressListener != null) {
                        progressListener.onProgress(completed, total, safeTitle(item.title));
                    }
                }

                return completed;
            } catch (Exception e) {
                throw new CompletionException(e);
            }
        });
    }

    public CompletableFuture<Integer> sync(
            String server,
            String user,
            String password,
            List<SyncItem> items
    ) {
        return sync(server, user, password, items, null);
    }

    private String uploadRecipe(String server, String token, SyncItem item) throws Exception {
        Map<String, Object> body = new LinkedHashMap<>();
        List<Map<String, Object>> recipes = new ArrayList<>();
        recipes.add(uploadPayload(item));
        body.put("recipes", recipes);

        byte[] response = requestJson(
                "POST",
                normalizeServer(server) + "/api/v1/sync/apply",
                "Bearer " + token,
                mapper.writeValueAsBytes(body)
        );

        Map<String, Object> root = mapper.readValue(
                response,
                new TypeReference<Map<String, Object>>() {}
        );

        Object hashesObject = root.get("hashes");
        if (hashesObject instanceof Map<?, ?> hashes) {
            Object value = hashes.get(item.uuid);
            if (value != null) {
                return String.valueOf(value);
            }
        }

        return syncHash(item.localRecipe);
    }

    private Map<String, Object> uploadPayload(SyncItem item) throws Exception {
        Recipe recipe = item.localRecipe;
        Map<String, Object> payload = new LinkedHashMap<>();
        payload.put("uuid", item.uuid);
        payload.put("data", dataFor(recipe));

        if (recipe.getImageName() != null && !recipe.getImageName().isEmpty()) {
            File file = imageService.findImage(recipe.getImageName());
            if (file.exists()) {
                payload.put("imageName", recipe.getImageName());
                payload.put(
                        "imageBase64",
                        Base64.encodeToString(
                                java.nio.file.Files.readAllBytes(file.toPath()),
                                Base64.NO_WRAP
                        )
                );
            }
        }

        return payload;
    }

    @SuppressWarnings("unchecked")
    private String downloadRecipe(String server, String token, SyncItem item) throws Exception {
        String uuid = item.uuid;
        Map<String, Object> payload = mapper.readValue(
                requestJson(
                        "GET",
                        normalizeServer(server) + "/api/v1/sync/recipes/" + URLEncoder.encode(uuid, "UTF-8"),
                        "Bearer " + token,
                        null
                ),
                new TypeReference<Map<String, Object>>() {}
        );

        Object dataObject = payload.get("data");
        if (!(dataObject instanceof Map<?, ?> rawData)) {
            throw new IOException("Ungültige Rezeptdaten vom Server: " + safeTitle(item.title));
        }

        @SuppressWarnings("unchecked")
        Map<String, Object> dataMap = (Map<String, Object>)rawData;

        // Vor dem lokalen Speichern prüfen. So kann eine fehlerhafte
        // Übertragung nicht erst NACH einem bereits erfolgten Import
        // den kompletten Sync abbrechen.
        String payloadHash = contentHashFromData(dataMap);
        if (item.remoteHash != null && !item.remoteHash.equals(payloadHash)) {
            throw new IOException("Hash-Prüfung vor Download fehlgeschlagen: " + safeTitle(item.title));
        }

        Recipe recipe = mapper.convertValue(dataMap, Recipe.class);
        recipe.setRecipeId(item.localRecipe == null ? 0 : item.localRecipe.getRecipeId());

        List<Category> requested = recipe.getCategories() == null
                ? new ArrayList<>()
                : recipe.getCategories();

        for (Category category : categoryRepository.retainNonExisting(requested).get()) {
            categoryRepository.insertOrUpdate(category).get();
        }
        recipe.setCategories(categoryRepository.retainExisting(requested).get());

        String imageBase64 = (String)payload.get("imageBase64");
        if (imageBase64 != null && !imageBase64.isEmpty()) {
            byte[] raw = Base64.decode(imageBase64, Base64.DEFAULT);
            File temp = imageService.createTemporaryImageFileInCache();
            try (OutputStream out = new java.io.FileOutputStream(temp)) {
                out.write(raw);
            }
            recipe.setImageName(temp.getName());
        } else {
            recipe.setImageName("");
        }

        long id = recipeRepository.insertOrUpdate(recipe).get();
        prefs.edit().putString("sync-uuid-" + id, uuid).apply();

        if (!recipe.getImageName().isEmpty()) {
            imageService.moveImage(recipe.getImageName()).get();
        }

        return payloadHash;
    }

    @SuppressWarnings("unchecked")
    private Map<String, RemoteItem> loadRemoteManifest(String server, String token) throws Exception {
        byte[] bytes = requestJson(
                "GET",
                normalizeServer(server) + "/api/v1/sync/manifest",
                "Bearer " + token,
                null
        );

        Map<String, Object> root = mapper.readValue(
                bytes,
                new TypeReference<Map<String, Object>>() {}
        );

        Map<String, RemoteItem> out = new HashMap<>();
        Object items = root.get("items");

        if (items instanceof List<?> list) {
            for (Object obj : list) {
                Map<String, Object> map = (Map<String, Object>)obj;
                RemoteItem remote = new RemoteItem();
                remote.uuid = String.valueOf(map.get("uuid"));
                remote.title = String.valueOf(map.get("title"));
                remote.contentHash = nullableString(map.get("contentHash"));
                remote.imageHash = nullableString(map.get("imageHash"));
                remote.syncHash = nullableString(map.get("syncHash"));

                if (remote.syncHash == null || remote.syncHash.isEmpty()) {
                    remote.syncHash = combinedHash(remote.contentHash, remote.imageHash);
                }

                Object cats = map.get("categories");
                if (cats instanceof List<?> categoryList) {
                    for (Object category : categoryList) {
                        remote.categories.add(String.valueOf(category));
                    }
                }

                out.put(remote.uuid, remote);
            }
        }

        return out;
    }

    private String ensureToken(String server, String user, String password) throws Exception {
        String existing = tokenFor(server, user);
        if (existing != null) {
            return existing;
        }

        String pairingPassword = password == null ? "" : password;
        if (pairingPassword.isEmpty()) {
            pairingPassword = prefs.getString("sync-password", "");
        }

        if (user == null || user.trim().isEmpty() || pairingPassword.isEmpty()) {
            throw new IOException("Für die erste Verbindung bitte Benutzername und Passwort eingeben. Danach verwendet Brassica nur noch den Geräte-Key.");
        }

        Map<String, Object> body = new LinkedHashMap<>();
        body.put("deviceId", deviceId());
        body.put("deviceName", deviceName());

        String basic = Base64.encodeToString(
                (user.trim() + ":" + pairingPassword).getBytes(StandardCharsets.UTF_8),
                Base64.NO_WRAP
        );

        byte[] response = requestJson(
                "POST",
                normalizeServer(server) + "/api/v1/sync/token",
                "Basic " + basic,
                mapper.writeValueAsBytes(body)
        );

        Map<String, Object> root = mapper.readValue(
                response,
                new TypeReference<Map<String, Object>>() {}
        );

        String token = nullableString(root.get("token"));
        if (token == null || token.isEmpty()) {
            throw new IOException("Server hat keinen Sync-Key geliefert.");
        }

        prefs.edit()
                .putString("sync-token", token)
                .putString("sync-token-server", normalizeServer(server))
                .putString("sync-token-user", user.trim())
                .remove("sync-password")
                .apply();

        return token;
    }

    private String tokenFor(String server, String user) {
        String token = prefs.getString("sync-token", null);
        String tokenServer = prefs.getString("sync-token-server", "");
        String tokenUser = prefs.getString("sync-token-user", "");

        if (token == null || token.isEmpty()) {
            return null;
        }

        if (!normalizeServer(server).equals(tokenServer) || !safe(user).trim().equals(tokenUser)) {
            return null;
        }

        return token;
    }

    private void clearToken(String server, String user) {
        if (tokenFor(server, user) == null) {
            return;
        }

        prefs.edit()
                .remove("sync-token")
                .remove("sync-token-server")
                .remove("sync-token-user")
                .apply();
    }

    private String deviceId() {
        String id = prefs.getString("sync-device-id", null);
        if (id == null || id.isEmpty()) {
            id = UUID.randomUUID().toString();
            prefs.edit().putString("sync-device-id", id).apply();
        }
        return id;
    }

    private String deviceName() {
        String manufacturer = safe(Build.MANUFACTURER).trim();
        String model = safe(Build.MODEL).trim();
        String value = (manufacturer + " " + model).trim();
        return value.isEmpty() ? "Brassica Android" : value;
    }

    private byte[] requestJson(
            String method,
            String url,
            String authorization,
            byte[] body
    ) throws IOException {
        HttpURLConnection connection = (HttpURLConnection)new URL(url).openConnection();
        connection.setRequestMethod(method);
        connection.setConnectTimeout(15000);
        connection.setReadTimeout(30000);
        connection.setRequestProperty("Accept", "application/json");

        if (authorization != null && !authorization.isEmpty()) {
            connection.setRequestProperty("Authorization", authorization);
        }

        if (body != null) {
            connection.setDoOutput(true);
            connection.setRequestProperty("Content-Type", "application/json; charset=utf-8");
            try (OutputStream out = connection.getOutputStream()) {
                out.write(body);
            }
        }

        int code = connection.getResponseCode();
        InputStream in = code >= 200 && code < 300
                ? connection.getInputStream()
                : connection.getErrorStream();
        byte[] bytes = readAll(in);

        if (code < 200 || code >= 300) {
            throw new IOException("HTTP " + code + ": " + new String(bytes, StandardCharsets.UTF_8));
        }

        return bytes;
    }

    private byte[] readAll(InputStream in) throws IOException {
        if (in == null) {
            return new byte[0];
        }

        try (in; ByteArrayOutputStream out = new ByteArrayOutputStream()) {
            FileUtils.copy(in, out);
            return out.toByteArray();
        }
    }

    private String normalizeServer(String value) {
        String server = value == null ? "" : value.trim();
        while (server.endsWith("/")) {
            server = server.substring(0, server.length() - 1);
        }
        return server;
    }

    private String uuidFor(long recipeId) {
        String key = "sync-uuid-" + recipeId;
        String uuid = prefs.getString(key, null);

        if (uuid == null) {
            uuid = UUID.randomUUID().toString();
            prefs.edit().putString(key, uuid).apply();
        }

        return uuid;
    }

    private String baseHash(String uuid) {
        return prefs.getString("sync-base-" + uuid, null);
    }

    private void saveBaseHash(String uuid, String hash) {
        if (uuid == null || hash == null || hash.isEmpty()) {
            return;
        }
        prefs.edit().putString("sync-base-" + uuid, hash).apply();
    }

    private List<String> categoryNames(Recipe recipe) {
        Set<String> unique = new LinkedHashSet<>();
        if (recipe.getCategories() != null) {
            for (Category category : recipe.getCategories()) {
                if (category != null && category.getName() != null && !category.getName().trim().isEmpty()) {
                    unique.add(category.getName());
                }
            }
        }

        List<String> out = new ArrayList<>(unique);
        out.sort(String::compareTo);
        return out;
    }

    private List<String> merge(List<String> first, List<String> second) {
        Set<String> set = new LinkedHashSet<>(first);
        set.addAll(second);
        return new ArrayList<>(set);
    }

    private Map<String, Object> dataFor(Recipe recipe) {
        Map<String, Object> data = new LinkedHashMap<>();
        data.put("title", safe(recipe.getTitle()));
        data.put("description", safe(recipe.getDescription()));
        data.put("directions", safe(recipe.getDirections()));
        data.put("ingredients", safe(recipe.getIngredients()));
        data.put("notes", safe(recipe.getNotes()));
        data.put("nutritionalValues", safe(recipe.getNutritionalValues()));
        data.put("preparationTime", safe(recipe.getPreparationTime()));
        data.put("servings", safe(recipe.getServings()));
        data.put("source", safe(recipe.getSource()));
        data.put("favorite", recipe.isFavorite());

        List<Map<String, String>> cats = new ArrayList<>();
        for (String name : categoryNames(recipe)) {
            Map<String, String> cat = new LinkedHashMap<>();
            cat.put("name", name);
            cats.add(cat);
        }
        data.put("categories", cats);

        return data;
    }

    private String syncHash(Recipe recipe) throws Exception {
        // RecipeImageService komprimiert Bilder beim lokalen Speichern. Deshalb
        // darf der rohe Bild-Bytehash nicht Teil der Konflikterkennung sein.
        return contentHash(recipe);
    }

    private String contentHash(Recipe recipe) throws Exception {
        return contentHashFromData(dataFor(recipe));
    }

    private String contentHashFromData(Map<String, Object> data) throws Exception {
        ByteArrayOutputStream out = new ByteArrayOutputStream();
        writeUtf8(out, "brassica-sync-v3");
        out.write(0);

        for (String field : List.of(
                "title",
                "description",
                "directions",
                "ingredients",
                "notes",
                "nutritionalValues",
                "preparationTime",
                "servings",
                "source"
        )) {
            writeField(out, field, valueAsString(data.get(field)));
        }

        writeUtf8(out, "favorite");
        out.write(0);
        writeUtf8(out, Boolean.TRUE.equals(data.get("favorite")) ? "1" : "0");
        out.write(0);

        List<String> categories = new ArrayList<>();
        Object categoriesObject = data.get("categories");
        if (categoriesObject instanceof List<?> list) {
            for (Object entry : list) {
                if (entry instanceof Map<?, ?> map) {
                    String name = valueAsString(map.get("name"));
                    if (!name.isEmpty() && !categories.contains(name)) {
                        categories.add(name);
                    }
                } else {
                    String name = valueAsString(entry);
                    if (!name.isEmpty() && !categories.contains(name)) {
                        categories.add(name);
                    }
                }
            }
        }
        categories.sort(String::compareTo);

        writeUtf8(out, "categories");
        out.write(0);
        writeUtf8(out, Integer.toString(categories.size()));
        out.write(0);

        for (String category : categories) {
            byte[] bytes = category.getBytes(StandardCharsets.UTF_8);
            writeUtf8(out, Integer.toString(bytes.length));
            out.write(0);
            out.write(bytes);
            out.write(0);
        }

        return sha256(out.toByteArray());
    }

    private void writeField(ByteArrayOutputStream out, String field, String value) throws IOException {
        byte[] bytes = value.getBytes(StandardCharsets.UTF_8);
        writeUtf8(out, field);
        out.write(0);
        writeUtf8(out, Integer.toString(bytes.length));
        out.write(0);
        out.write(bytes);
        out.write(0);
    }

    private void writeUtf8(ByteArrayOutputStream out, String value) throws IOException {
        out.write(value.getBytes(StandardCharsets.UTF_8));
    }

    private String valueAsString(Object value) {
        return value == null ? "" : String.valueOf(value);
    }

    private String imageHash(Recipe recipe) throws Exception {
        if (recipe == null || recipe.getImageName() == null || recipe.getImageName().isEmpty()) {
            return null;
        }

        File file = imageService.findImage(recipe.getImageName());
        if (!file.exists()) {
            return null;
        }

        return sha256(java.nio.file.Files.readAllBytes(file.toPath()));
    }

    private String combinedHash(String contentHash, String imageHash) {
        // Kompatibler Fallback für Manifeste ohne syncHash.
        return safe(contentHash);
    }

    private String sha256(byte[] value) throws Exception {
        MessageDigest digest = MessageDigest.getInstance("SHA-256");
        byte[] hash = digest.digest(value);
        return hex(hash);
    }

    private String sha256Unchecked(String value) {
        try {
            return sha256(value.getBytes(StandardCharsets.UTF_8));
        } catch (Exception e) {
            throw new IllegalStateException(e);
        }
    }

    private String hex(byte[] bytes) {
        StringBuilder out = new StringBuilder();
        for (byte value : bytes) {
            out.append(String.format("%02x", value));
        }
        return out.toString();
    }

    private boolean isUnauthorized(IOException e) {
        String message = e.getMessage();
        return message != null && message.startsWith("HTTP 401");
    }

    private String safe(String value) {
        return value == null ? "" : value;
    }

    private String safeTitle(String value) {
        String title = safe(value).trim();
        return title.isEmpty() ? "Rezept" : title;
    }

    private String nullableString(Object value) {
        if (value == null) {
            return null;
        }
        String string = String.valueOf(value);
        return "null".equals(string) ? null : string;
    }

    private static class RemoteItem {
        String uuid;
        String title;
        String contentHash;
        String imageHash;
        String syncHash;
        List<String> categories = new ArrayList<>();
    }
}
