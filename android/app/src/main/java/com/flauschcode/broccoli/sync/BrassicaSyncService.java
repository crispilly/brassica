package com.flauschcode.broccoli.sync;

import android.app.Application;
import android.content.SharedPreferences;
import android.util.Base64;

import androidx.preference.PreferenceManager;

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
    public BrassicaSyncService(Application application, RecipeRepository recipeRepository, CategoryRepository categoryRepository, RecipeImageService imageService) {
        this.application=application; this.recipeRepository=recipeRepository; this.categoryRepository=categoryRepository; this.imageService=imageService;
        this.prefs= PreferenceManager.getDefaultSharedPreferences(application);
    }

    public void saveSettings(String server, String user, String password) {
        prefs.edit().putString("sync-server", normalizeServer(server)).putString("sync-user", user.trim()).putString("sync-password", password).apply();
    }
    public String getServer(){return prefs.getString("sync-server","");}
    public String getUser(){return prefs.getString("sync-user","");}
    public String getPassword(){return prefs.getString("sync-password","");}

    public CompletableFuture<List<SyncItem>> preview(String server,String user,String password) {
        return CompletableFuture.supplyAsync(() -> {
            try {
                saveSettings(server,user,password);
                Map<String,RemoteItem> remote=loadRemoteManifest(server,user,password);
                List<Recipe> localRecipes=recipeRepository.findAll().get();
                Map<String,SyncItem> result=new LinkedHashMap<>();
                for(Recipe recipe:localRecipes){
                    String localHash=hash(recipe);
                    String uuid=prefs.getString("sync-uuid-"+recipe.getRecipeId(),null);
                    if(uuid==null){
                        RemoteItem exact=null;
                        for(RemoteItem candidate:remote.values()){
                            if(localHash.equals(candidate.contentHash)){exact=candidate;break;}
                        }
                        if(exact!=null){uuid=exact.uuid;prefs.edit().putString("sync-uuid-"+recipe.getRecipeId(),uuid).apply();}
                        else uuid=uuidFor(recipe.getRecipeId());
                    }
                    SyncItem item=new SyncItem();item.uuid=uuid;item.title=recipe.getTitle();item.localRecipe=recipe;item.localHash=localHash;item.categories=categoryNames(recipe);item.selected=true;
                    RemoteItem r=remote.remove(uuid);
                    if(r==null){item.action=Action.UPLOAD;} else {item.remoteHash=r.contentHash;if(item.localHash.equals(r.contentHash))continue;item.action=Action.CONFLICT;item.selected=false;item.categories=merge(item.categories,r.categories);}
                    result.put(uuid,item);
                }
                for(RemoteItem r:remote.values()){
                    SyncItem item=new SyncItem();item.uuid=r.uuid;item.title=r.title;item.remoteHash=r.contentHash;item.categories=r.categories;item.action=Action.DOWNLOAD;item.selected=true;result.put(item.uuid,item);
                }
                List<SyncItem> out=new ArrayList<>(result.values());out.sort(Comparator.comparing(i->i.title==null?"":i.title,String.CASE_INSENSITIVE_ORDER));return out;
            } catch(Exception e){throw new CompletionException(e);}
        });
    }

    public CompletableFuture<Integer> sync(String server,String user,String password,List<SyncItem> items) {
        return CompletableFuture.supplyAsync(() -> {
            try {
                List<Map<String,Object>> uploads=new ArrayList<>();int count=0;
                for(SyncItem item:items){if(!item.selected)continue;if(item.action==Action.UPLOAD){uploads.add(uploadPayload(item));count++;}}
                if(!uploads.isEmpty()){Map<String,Object> body=new LinkedHashMap<>();body.put("recipes",uploads);requestJson("POST",normalizeServer(server)+"/api/v1/sync/apply",user,password,mapper.writeValueAsBytes(body));}
                for(SyncItem item:items){if(!item.selected||item.action!=Action.DOWNLOAD)continue;downloadRecipe(server,user,password,item);count++;}
                return count;
            }catch(Exception e){throw new CompletionException(e);}
        });
    }

    private Map<String,Object> uploadPayload(SyncItem item) throws Exception {
        Recipe recipe=item.localRecipe;Map<String,Object> p=new LinkedHashMap<>();p.put("uuid",item.uuid);p.put("data",dataFor(recipe));
        if(recipe.getImageName()!=null&&!recipe.getImageName().isEmpty()){
            File f=imageService.findImage(recipe.getImageName());if(f.exists()){p.put("imageName",recipe.getImageName());p.put("imageBase64",Base64.encodeToString(java.nio.file.Files.readAllBytes(f.toPath()),Base64.NO_WRAP));}
        }
        return p;
    }

    @SuppressWarnings("unchecked")
    private void downloadRecipe(String server,String user,String password,SyncItem item) throws Exception {
        String uuid=item.uuid;
        Map<String,Object> payload=mapper.readValue(requestJson("GET",normalizeServer(server)+"/api/v1/sync/recipes/"+java.net.URLEncoder.encode(uuid,"UTF-8"),user,password,null),new TypeReference<Map<String,Object>>(){});
        Object dataObject=payload.get("data");Recipe recipe=mapper.convertValue(dataObject,Recipe.class);
        recipe.setRecipeId(item.localRecipe == null ? 0 : item.localRecipe.getRecipeId());
        List<Category> requested=recipe.getCategories()==null?new ArrayList<>():recipe.getCategories();
        for(Category c:categoryRepository.retainNonExisting(requested).get())categoryRepository.insertOrUpdate(c).get();
        recipe.setCategories(categoryRepository.retainExisting(requested).get());
        String imageBase64=(String)payload.get("imageBase64");
        if(imageBase64!=null&&!imageBase64.isEmpty()){
            byte[] raw=Base64.decode(imageBase64,Base64.DEFAULT);File temp=imageService.createTemporaryImageFileInCache();try(OutputStream out=new java.io.FileOutputStream(temp)){out.write(raw);}recipe.setImageName(temp.getName());
        }else recipe.setImageName("");
        long id=recipeRepository.insertOrUpdate(recipe).get();prefs.edit().putString("sync-uuid-"+id,uuid).apply();
        if(!recipe.getImageName().isEmpty())imageService.moveImage(recipe.getImageName()).get();
    }

    private Map<String,RemoteItem> loadRemoteManifest(String server,String user,String password) throws Exception {
        byte[] bytes=requestJson("GET",normalizeServer(server)+"/api/v1/sync/manifest",user,password,null);
        Map<String,Object> root=mapper.readValue(bytes,new TypeReference<Map<String,Object>>(){});Map<String,RemoteItem> out=new HashMap<>();
        Object items=root.get("items");if(items instanceof List<?> list){for(Object obj:list){Map<String,Object> m=(Map<String,Object>)obj;RemoteItem r=new RemoteItem();r.uuid=String.valueOf(m.get("uuid"));r.title=String.valueOf(m.get("title"));r.contentHash=String.valueOf(m.get("contentHash"));Object cats=m.get("categories");if(cats instanceof List<?> cl)for(Object c:cl)r.categories.add(String.valueOf(c));out.put(r.uuid,r);}}
        return out;
    }

    private byte[] requestJson(String method,String url,String user,String password,byte[] body) throws IOException {
        HttpURLConnection c=(HttpURLConnection)new URL(url).openConnection();c.setRequestMethod(method);c.setConnectTimeout(15000);c.setReadTimeout(30000);c.setRequestProperty("Accept","application/json");
        String auth=Base64.encodeToString((user+":"+password).getBytes(StandardCharsets.UTF_8),Base64.NO_WRAP);c.setRequestProperty("Authorization","Basic "+auth);
        if(body!=null){c.setDoOutput(true);c.setRequestProperty("Content-Type","application/json; charset=utf-8");try(OutputStream out=c.getOutputStream()){out.write(body);}}
        int code=c.getResponseCode();InputStream in=code>=200&&code<300?c.getInputStream():c.getErrorStream();byte[] bytes=readAll(in);if(code<200||code>=300)throw new IOException("HTTP "+code+": "+new String(bytes,StandardCharsets.UTF_8));return bytes;
    }
    private byte[] readAll(InputStream in)throws IOException{if(in==null)return new byte[0];try(in;ByteArrayOutputStream out=new ByteArrayOutputStream()){FileUtils.copy(in,out);return out.toByteArray();}}
    private String normalizeServer(String s){s=s==null?"":s.trim();while(s.endsWith("/"))s=s.substring(0,s.length()-1);return s;}
    private String uuidFor(long recipeId){String key="sync-uuid-"+recipeId;String uuid=prefs.getString(key,null);if(uuid==null){uuid=UUID.randomUUID().toString();prefs.edit().putString(key,uuid).apply();}return uuid;}
    private List<String> categoryNames(Recipe recipe){List<String> out=new ArrayList<>();for(Category c:recipe.getCategories())out.add(c.getName());out.sort(String.CASE_INSENSITIVE_ORDER);return out;}
    private List<String> merge(List<String>a,List<String>b){Set<String>s=new LinkedHashSet<>(a);s.addAll(b);return new ArrayList<>(s);}
    private Map<String,Object> dataFor(Recipe r){Map<String,Object>d=new LinkedHashMap<>();d.put("title",r.getTitle());d.put("description",r.getDescription());d.put("directions",r.getDirections());d.put("ingredients",r.getIngredients());d.put("notes",r.getNotes());d.put("nutritionalValues",r.getNutritionalValues());d.put("preparationTime",r.getPreparationTime());d.put("servings",r.getServings());d.put("source",r.getSource());d.put("favorite",r.isFavorite());List<Map<String,String>>cats=new ArrayList<>();for(String n:categoryNames(r)){Map<String,String>c=new LinkedHashMap<>();c.put("name",n);cats.add(c);}d.put("categories",cats);return d;}
    private String hash(Recipe recipe)throws Exception{byte[] bytes=mapper.writeValueAsBytes(dataFor(recipe));MessageDigest md=MessageDigest.getInstance("SHA-256");byte[] h=md.digest(bytes);StringBuilder sb=new StringBuilder();for(byte b:h)sb.append(String.format("%02x",b));return sb.toString();}
    private static class RemoteItem{String uuid,title,contentHash;List<String>categories=new ArrayList<>();}
}
