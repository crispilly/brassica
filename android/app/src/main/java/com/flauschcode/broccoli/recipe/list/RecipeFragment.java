package com.flauschcode.broccoli.recipe.list;

import android.app.Activity;
import android.content.Intent;
import android.content.SharedPreferences;
import android.net.Uri;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.MenuItem;
import android.view.View;
import android.view.ViewGroup;
import android.widget.AdapterView;
import android.widget.ArrayAdapter;
import android.widget.TextView;
import android.widget.Spinner;
import android.widget.Toast;

import androidx.activity.OnBackPressedCallback;
import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.annotation.NonNull;
import androidx.core.content.FileProvider;
import androidx.appcompat.app.AlertDialog;
import androidx.appcompat.widget.SearchView;
import androidx.appcompat.widget.Toolbar;
import androidx.fragment.app.Fragment;
import androidx.lifecycle.ViewModelProvider;
import androidx.navigation.NavController;
import androidx.navigation.Navigation;
import androidx.preference.PreferenceManager;
import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.ListAdapter;
import androidx.recyclerview.widget.RecyclerView;

import com.flauschcode.broccoli.BR;
import com.flauschcode.broccoli.BuildConfig;
import com.flauschcode.broccoli.R;
import com.flauschcode.broccoli.RecyclerViewAdapter;
import com.flauschcode.broccoli.category.Category;
import com.flauschcode.broccoli.recipe.Recipe;
import com.flauschcode.broccoli.recipe.RecipeRepository;
import com.flauschcode.broccoli.recipe.crud.CreateAndEditRecipeActivity;
import com.flauschcode.broccoli.recipe.details.RecipeDetailsActivity;
import com.flauschcode.broccoli.recipe.sharing.QrCodeDialog;
import com.flauschcode.broccoli.recipe.sharing.ShareRecipeAsFileService;
import com.flauschcode.broccoli.recipe.transfer.RecipeFileService;
import com.flauschcode.broccoli.sync.BrassicaSyncService;
import com.flauschcode.broccoli.seasons.SeasonalFood;
import com.google.android.material.button.MaterialButtonToggleGroup;
import com.google.android.material.chip.Chip;
import com.google.android.material.floatingactionbutton.FloatingActionButton;
import com.google.zxing.integration.android.IntentIntegrator;
import com.journeyapps.barcodescanner.ScanContract;
import com.journeyapps.barcodescanner.ScanOptions;

import java.io.File;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Date;
import java.util.HashSet;
import java.util.List;
import java.util.Locale;
import java.util.Optional;
import java.util.Set;
import java.util.concurrent.CompletableFuture;

import javax.inject.Inject;

import dagger.android.support.AndroidSupportInjection;

public class RecipeFragment extends Fragment implements AdapterView.OnItemSelectedListener, SearchView.OnQueryTextListener {

    @Inject ViewModelProvider.Factory viewModelFactory;
    @Inject RecipeFileService recipeFileService;
    @Inject ShareRecipeAsFileService shareRecipeAsFileService;
    @Inject BrassicaSyncService syncService;

    private RecipeViewModel viewModel;
    private MenuItem searchItem;
    private SearchView searchView;
    private Spinner spinner;
    private Chip seasonalIngredientChip;
    private View categoryMultiBar;
    private TextView categoryMultiButton;
    private MaterialButtonToggleGroup categoryMatchMode;
    private final List<Category> availableCategories = new ArrayList<>();
    private final Set<Long> selectedCategoryIds = new HashSet<>();
    private List<Recipe> pendingExportRecipes = new ArrayList<>();
    private boolean suppressSpinner = false;

    private final ActivityResultLauncher<ScanOptions> qrScanner = registerForActivityResult(
            new ScanContract(),
            result -> {
                if (result.getContents() != null) {
                    importFromQrLink(result.getContents().trim());
                }
            });

    private final ActivityResultLauncher<String[]> importLauncher = registerForActivityResult(
            new ActivityResultContracts.OpenDocument(), uri -> {
                if (uri == null) return;
                recipeFileService.importFrom(uri).whenComplete((result, error) -> requireActivity().runOnUiThread(() -> {
                    if (error != null) Toast.makeText(requireContext(), R.string.import_failed, Toast.LENGTH_LONG).show();
                    else Toast.makeText(requireContext(), getString(R.string.import_success, result.count), Toast.LENGTH_LONG).show();
                }));
            });

    private final ActivityResultLauncher<String> exportLauncher = registerForActivityResult(
            new ActivityResultContracts.CreateDocument("application/octet-stream"), uri -> {
                if (uri == null || pendingExportRecipes.isEmpty()) return;
                List<Recipe> recipes = new ArrayList<>(pendingExportRecipes);
                pendingExportRecipes.clear();
                recipeFileService.exportTo(uri, recipes).whenComplete((unused, error) -> requireActivity().runOnUiThread(() ->
                        Toast.makeText(requireContext(), error == null ? R.string.export_success : R.string.export_failed, Toast.LENGTH_LONG).show()));
            });

    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, ViewGroup container, Bundle savedInstanceState) {
        AndroidSupportInjection.inject(this);
        View root = inflater.inflate(R.layout.fragment_recipes, container, false);

        RecyclerView recyclerView = root.findViewById(R.id.recycler_view);
        recyclerView.setLayoutManager(new LinearLayoutManager(getActivity()));
        recyclerView.setHasFixedSize(true);
        View emptyMessageLayout = root.findViewById(R.id.recipes_empty);
        ListAdapter<Recipe, RecyclerViewAdapter<Recipe>.Holder> adapter = new RecyclerViewAdapter<Recipe>() {
            @Override protected int getLayoutResourceId(){return R.layout.recipe_item;}
            @Override protected int getBindingVariableId(){return BR.recipe;}
            @Override protected void onItemClick(Recipe item){onListInteraction(item);}
            @Override protected void onAdapterDataChanged(int itemCount){emptyMessageLayout.setVisibility(itemCount==0?View.VISIBLE:View.GONE);}
        };
        recyclerView.setAdapter(adapter);

        FloatingActionButton fab = root.findViewById(R.id.fab_recipes);
        setUpFloatingActionButton(fab);

        viewModel = new ViewModelProvider(this, viewModelFactory).get(RecipeViewModel.class);
        viewModel.getRecipes().observe(getViewLifecycleOwner(), adapter::submitList);

        root.post(() -> {
            if (!isAdded()) return;
            Toolbar toolbar = requireActivity().findViewById(R.id.toolbar);
            if (toolbar != null) {
                setUpMenu(toolbar);
            }
        });
        spinner = root.findViewById(R.id.spinner);
        setUpSpinner();

        categoryMultiBar = root.findViewById(R.id.category_multi_bar);
        categoryMultiButton = root.findViewById(R.id.category_multi_button);
        categoryMatchMode = root.findViewById(R.id.category_match_mode);
        setUpMultiCategoryFilter();

        seasonalIngredientChip = root.findViewById(R.id.chip);
        getSeasonalFoodArgument().ifPresent(seasonalFood -> {
            resetCategory();
            seasonalIngredientChip.setText(seasonalFood.getName());
            seasonalIngredientChip.setOnClickListener(view -> {
                NavController navController = Navigation.findNavController(requireActivity(), R.id.nav_host_fragment);
                navController.popBackStack(R.id.nav_seasons, true);
                resetCategoryAndArguments();
                seasonalIngredientChip.setVisibility(View.GONE);
                spinner.setVisibility(View.VISIBLE);
                categoryMultiBar.setVisibility(View.VISIBLE);
            });
            spinner.post(() -> { viewModel.setSeasonalTerms(seasonalFood.getTerms()); viewModel.setFilterName(seasonalFood.getName()); });
        });

        requireActivity().getOnBackPressedDispatcher().addCallback(getViewLifecycleOwner(), new OnBackPressedCallback(true) {
            @Override public void handleOnBackPressed() {
                Toolbar toolbar = requireActivity().findViewById(R.id.toolbar);
                if (searchView != null && !searchView.isIconified() && toolbar != null) {
                    toolbar.collapseActionView();
                } else {
                    setEnabled(false);
                    requireActivity().onBackPressed();
                }
            }
        });
        return root;
    }

    @Override public void onResume(){
        super.onResume();
        boolean seasonal=getSeasonalFoodArgument().isPresent();
        safeSetVisibility(seasonalIngredientChip, seasonal?View.VISIBLE:View.GONE);
        safeSetVisibility(spinner, seasonal?View.GONE:View.VISIBLE);
        safeSetVisibility(categoryMultiBar, seasonal?View.GONE:View.VISIBLE);
    }
    private void safeSetVisibility(View view,int visibility){if(view!=null)view.setVisibility(visibility);}

    @Override public void onItemSelected(AdapterView<?> parent, View view, int position, long id) {
        if (suppressSpinner) return;
        Category category=(Category)parent.getItemAtPosition(position);
        selectedCategoryIds.clear(); updateCategoryButton();
        viewModel.setFilterCategory(category); viewModel.setFilterName(category.getName());
    }
    @Override public void onNothingSelected(AdapterView<?> parent){}
    @Override public boolean onQueryTextSubmit(String query){return false;}
    @Override public boolean onQueryTextChange(String newText){viewModel.setSearchTerm(newText);return false;}

    private final ActivityResultLauncher<Intent> detailsResultLauncher = registerForActivityResult(new ActivityResultContracts.StartActivityForResult(), result -> {
        if (result.getResultCode()==Activity.RESULT_OK && result.getData()!=null && result.getData().hasExtra("hashtag")) {
            resetCategoryAndArguments(); searchItem.expandActionView(); searchView.post(() -> searchView.setQuery(result.getData().getStringExtra("hashtag"), false));
        } else if (result.getResultCode()==Activity.RESULT_OK && result.getData()!=null && result.getData().getBooleanExtra("navigateToSupportPage",false)) {
            Navigation.findNavController(requireActivity(),R.id.nav_host_fragment).navigate(R.id.nav_support);
        }
    });

    private void onListInteraction(Recipe recipe){Intent intent=new Intent(getContext(),RecipeDetailsActivity.class);intent.putExtra(Recipe.class.getName(),recipe);detailsResultLauncher.launch(intent);}

    private void resetCategory(){
        selectedCategoryIds.clear(); updateCategoryButton();
        spinner.setSelection(0); Category all=viewModel.getCategoryAll(); viewModel.setFilterCategory(all); viewModel.setFilterName(all.getName());
    }
    private void resetCategoryAndArguments(){resetCategory();if(getArguments()!=null)getArguments().clear();}

    private void setUpFloatingActionButton(FloatingActionButton fab){
        ActivityResultLauncher<Intent> launcher=registerForActivityResult(new ActivityResultContracts.StartActivityForResult(), result->{
            if(result.getResultCode()==Activity.RESULT_OK && result.getData()!=null){resetCategoryAndArguments();Recipe r=(Recipe)result.getData().getSerializableExtra(Recipe.class.getName());onListInteraction(r);}
        });
        fab.setOnClickListener(view->launcher.launch(new Intent(getActivity(),CreateAndEditRecipeActivity.class)));
    }

    private void setUpMenu(Toolbar toolbar){
        toolbar.getMenu().clear();
        toolbar.inflateMenu(R.menu.recipes);
        toolbar.setOnMenuItemClickListener(item -> {
            if(item.getItemId()==R.id.action_import_file){ showImportMethodDialog(); return true; }
            if(item.getItemId()==R.id.action_export_file){ showExportSelection(); return true; }
            return false;
        });
        searchItem=toolbar.getMenu().findItem(R.id.action_search);
        searchView=new SearchView(toolbar.getContext()); searchView.setMaxWidth(Integer.MAX_VALUE);
        searchView.setMinimumHeight(requireContext().getResources().getDimensionPixelSize(R.dimen.min_height_for_accessibility));
        searchItem.setActionView(searchView);
        viewModel.getFilterName().observe(getViewLifecycleOwner(), filterName->searchView.setQueryHint(getString(R.string.search_in,filterName.toUpperCase())));
        searchView.setOnQueryTextListener(this);
    }


    private void showImportMethodDialog() {
        String[] options = {
                getString(R.string.import_from_file),
                getString(R.string.import_from_qr)
        };
        new AlertDialog.Builder(requireContext())
                .setTitle(R.string.import_file)
                .setItems(options, (dialog, which) -> {
                    if (which == 0) {
                        importLauncher.launch(new String[]{"application/broccoli","application/octet-stream","application/zip"});
                    } else {
                        ScanOptions scanOptions = new ScanOptions();
                        scanOptions.setPrompt(getString(R.string.scan_recipe_qr_prompt));
                        scanOptions.setBeepEnabled(false);
                        scanOptions.setOrientationLocked(false);
                        qrScanner.launch(scanOptions);
                    }
                })
                .show();
    }

    private void importFromQrLink(String value) {
        Uri uri = Uri.parse(value);
        String scheme = uri.getScheme();
        if (scheme == null || (!"https".equalsIgnoreCase(scheme) && !"http".equalsIgnoreCase(scheme))) {
            Toast.makeText(requireContext(), R.string.qr_import_invalid, Toast.LENGTH_LONG).show();
            return;
        }

        List<String> segments = uri.getPathSegments();
        if (segments.size() == 3
                && "share".equals(segments.get(0))
                && "recipe".equals(segments.get(1))) {
            String downloadUrl = value.replaceAll("/+$", "") + "/download";
            downloadAndImport(downloadUrl);
            return;
        }

        if (segments.size() == 2 && "share".equals(segments.get(0))) {
            loadCollectionForImport(uri, segments.get(1));
            return;
        }

        if (segments.size() == 4
                && "share".equals(segments.get(0))
                && "recipe".equals(segments.get(2))) {
            String downloadUrl = value.replaceAll("/+$", "") + "/download";
            downloadAndImport(downloadUrl);
            return;
        }

        Toast.makeText(requireContext(), R.string.qr_import_invalid, Toast.LENGTH_LONG).show();
    }

    private void loadCollectionForImport(Uri source, String token) {
        CompletableFuture.supplyAsync(() -> {
            try {
                URL url = new URL(source.getScheme(), source.getHost(), source.getPort(),
                        "/share/" + token + "/recipes.json");
                HttpURLConnection connection = (HttpURLConnection) url.openConnection();
                connection.setConnectTimeout(15000);
                connection.setReadTimeout(30000);
                connection.setRequestProperty("Accept", "application/json");
                if (connection.getResponseCode() != 200) {
                    throw new IllegalStateException("HTTP " + connection.getResponseCode());
                }

                byte[] data;
                try (InputStream in = connection.getInputStream()) {
                    data = in.readAllBytes();
                }

                com.fasterxml.jackson.databind.JsonNode root =
                        new com.fasterxml.jackson.databind.ObjectMapper().readTree(data);
                List<Long> ids = new ArrayList<>();
                List<String> titles = new ArrayList<>();
                for (com.fasterxml.jackson.databind.JsonNode item : root.path("items")) {
                    ids.add(item.path("id").asLong());
                    titles.add(item.path("title").asText());
                }
                return new CollectionImportData(ids, titles);
            } catch (Exception e) {
                throw new RuntimeException(e);
            }
        }).whenComplete((data, error) -> requireActivity().runOnUiThread(() -> {
            if (error != null || data == null || data.ids.isEmpty()) {
                Toast.makeText(requireContext(), R.string.qr_import_failed, Toast.LENGTH_LONG).show();
                return;
            }

            boolean[] checked = new boolean[data.ids.size()];
            new AlertDialog.Builder(requireContext())
                    .setTitle(R.string.select_recipes_import)
                    .setMultiChoiceItems(data.titles.toArray(new String[0]), checked,
                            (dialog, which, isChecked) -> checked[which] = isChecked)
                    .setNegativeButton(android.R.string.cancel, null)
                    .setPositiveButton(android.R.string.ok, (dialog, which) -> {
                        List<String> urls = new ArrayList<>();
                        for (int i = 0; i < checked.length; i++) {
                            if (checked[i]) {
                                urls.add(source.getScheme() + "://" + source.getAuthority()
                                        + "/share/" + token + "/recipe/" + data.ids.get(i) + "/download");
                            }
                        }
                        downloadAndImportAll(urls);
                    })
                    .show();
        }));
    }

    private void downloadAndImport(String url) {
        downloadAndImportAll(java.util.Collections.singletonList(url));
    }

    private void downloadAndImportAll(List<String> urls) {
        if (urls.isEmpty()) return;

        CompletableFuture.runAsync(() -> {
            int imported = 0;
            try {
                for (String url : urls) {
                    HttpURLConnection connection = (HttpURLConnection) new URL(url).openConnection();
                    connection.setConnectTimeout(15000);
                    connection.setReadTimeout(30000);
                    if (connection.getResponseCode() != 200) {
                        throw new IllegalStateException("HTTP " + connection.getResponseCode());
                    }

                    File target = File.createTempFile("brassica_qr_", ".broccoli", requireContext().getCacheDir());
                    try (InputStream in = connection.getInputStream();
                         FileOutputStream out = new FileOutputStream(target)) {
                        in.transferTo(out);
                    }

                    RecipeFileService.ImportResult result = recipeFileService.importFrom(Uri.fromFile(target)).get();
                    imported += result.count;
                    target.delete();
                }

                int count = imported;
                requireActivity().runOnUiThread(() ->
                        Toast.makeText(requireContext(), getString(R.string.import_success, count), Toast.LENGTH_LONG).show());
            } catch (Exception e) {
                requireActivity().runOnUiThread(() ->
                        Toast.makeText(requireContext(), R.string.qr_import_failed, Toast.LENGTH_LONG).show());
            }
        });
    }

    private static class CollectionImportData {
        final List<Long> ids;
        final List<String> titles;

        CollectionImportData(List<Long> ids, List<String> titles) {
            this.ids = ids;
            this.titles = titles;
        }
    }

    private void setUpSpinner(){
        ArrayAdapter<Category> adapter=new ArrayAdapter<>(getActivity(),android.R.layout.simple_spinner_item);
        adapter.add(viewModel.getCategoryAll()); adapter.add(viewModel.getCategorySeasonal()); adapter.add(viewModel.getCategoryUnassigned()); adapter.add(viewModel.getCategoryFavorites());
        adapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item); spinner.setAdapter(adapter); spinner.setOnItemSelectedListener(this);
        Category preferred=getPreferredCategory(); spinner.setSelection(adapter.getPosition(preferred),false); viewModel.setFilterName(preferred.getName());
    }

    private void setUpMultiCategoryFilter(){
        viewModel.getCategories().observe(getViewLifecycleOwner(), categories->{availableCategories.clear();availableCategories.addAll(categories);updateCategoryButton();});
        categoryMultiButton.setOnClickListener(v->showCategoryDialog());
        categoryMatchMode.addOnButtonCheckedListener((group,checkedId,isChecked)->{
            if(!isChecked || selectedCategoryIds.isEmpty())return;
            viewModel.setFilterCategories(getSelectedCategories(), checkedId==R.id.category_mode_and?RecipeRepository.CategoryMatchMode.AND:RecipeRepository.CategoryMatchMode.OR);
        });
    }

    private void showCategoryDialog(){
        String[] names=availableCategories.stream().map(Category::getName).toArray(String[]::new);
        boolean[] checked=new boolean[availableCategories.size()];
        for(int i=0;i<availableCategories.size();i++)checked[i]=selectedCategoryIds.contains(availableCategories.get(i).getCategoryId());
        new AlertDialog.Builder(requireContext()).setTitle(R.string.categories).setMultiChoiceItems(names,checked,(d,which,isChecked)->checked[which]=isChecked)
                .setNegativeButton(android.R.string.cancel,null)
                .setNeutralButton(R.string.all_categories,(d,w)->{selectedCategoryIds.clear();suppressSpinner=true;spinner.setSelection(0,false);spinner.post(() -> suppressSpinner=false);viewModel.setFilterCategory(viewModel.getCategoryAll());viewModel.setFilterName(viewModel.getCategoryAll().getName());updateCategoryButton();})
                .setPositiveButton(android.R.string.ok,(d,w)->{
                    selectedCategoryIds.clear();for(int i=0;i<checked.length;i++)if(checked[i])selectedCategoryIds.add(availableCategories.get(i).getCategoryId());
                    if(selectedCategoryIds.isEmpty()){viewModel.setFilterCategory(viewModel.getCategoryAll());viewModel.setFilterName(viewModel.getCategoryAll().getName());}
                    else {suppressSpinner=true;spinner.setSelection(0,false);spinner.post(() -> suppressSpinner=false);RecipeRepository.CategoryMatchMode mode=categoryMatchMode.getCheckedButtonId()==R.id.category_mode_and?RecipeRepository.CategoryMatchMode.AND:RecipeRepository.CategoryMatchMode.OR;viewModel.setFilterCategories(getSelectedCategories(),mode);viewModel.setFilterName(getString(R.string.categories_selected,selectedCategoryIds.size()));}
                    updateCategoryButton();
                }).show();
    }

    private List<Category> getSelectedCategories(){List<Category> out=new ArrayList<>();for(Category c:availableCategories)if(selectedCategoryIds.contains(c.getCategoryId()))out.add(c);return out;}
    private void updateCategoryButton(){if(categoryMultiButton!=null)categoryMultiButton.setText(selectedCategoryIds.isEmpty()?getString(R.string.all_categories):getString(R.string.categories_selected,selectedCategoryIds.size()));}

    private void showExportSelection(){
        viewModel.findAllRecipes().whenComplete((recipes,error)->requireActivity().runOnUiThread(()->{
            if(error!=null||recipes==null||recipes.isEmpty()){Toast.makeText(requireContext(),R.string.export_failed,Toast.LENGTH_LONG).show();return;}
            String[] titles=recipes.stream().map(Recipe::getTitle).toArray(String[]::new); boolean[] checked=new boolean[recipes.size()];
            new AlertDialog.Builder(requireContext()).setTitle(R.string.select_recipes_export).setMultiChoiceItems(titles,checked,(d,w,isChecked)->checked[w]=isChecked)
                    .setNegativeButton(android.R.string.cancel,null).setPositiveButton(android.R.string.ok,(d,w)->{
                        pendingExportRecipes=new ArrayList<>();for(int i=0;i<checked.length;i++)if(checked[i])pendingExportRecipes.add(recipes.get(i));
                        if(pendingExportRecipes.isEmpty())return;
                        showShareMethodSelection(new ArrayList<>(pendingExportRecipes));
                    }).show();
        }));
    }

    private void showShareMethodSelection(List<Recipe> recipes) {
        List<String> actions = new ArrayList<>();
        actions.add(getString(R.string.save_file_action));
        actions.add(getString(R.string.share_file_action));

        boolean online = recipes.size() == 1 && hasOnlineSharing();
        if (online) {
            actions.add(getString(R.string.share_web_link_action));
            actions.add(getString(R.string.share_qr_code_action));
        }

        new AlertDialog.Builder(requireContext())
                .setTitle(R.string.share_method_title)
                .setItems(actions.toArray(new String[0]), (dialog, which) -> {
                    if (which == 0) {
                        pendingExportRecipes = new ArrayList<>(recipes);
                        String filename=recipes.size()==1 ? sanitize(recipes.get(0).getTitle())+".broccoli" : "EXPORT_"+new SimpleDateFormat("yyyyMMdd_HHmmss", Locale.US).format(new Date())+".broccoli-archive";
                        exportLauncher.launch(filename);
                    } else if (which == 1) {
                        shareSelectedFiles(recipes);
                    } else if (which == 2 && online) {
                        shareSelectedWebLink(recipes.get(0), false);
                    } else if (which == 3 && online) {
                        shareSelectedWebLink(recipes.get(0), true);
                    }
                })
                .show();
    }

    private boolean hasOnlineSharing() {
        String server = syncService.getServer();
        String user = syncService.getUser();
        return server != null && !server.trim().isEmpty()
                && user != null && !user.trim().isEmpty()
                && syncService.hasSyncKey(server, user);
    }

    private void shareSelectedFiles(List<Recipe> recipes) {
        CompletableFuture.runAsync(() -> {
            try {
                String name = recipes.size()==1
                        ? sanitize(recipes.get(0).getTitle()) + ".broccoli"
                        : "EXPORT_" + new SimpleDateFormat("yyyyMMdd_HHmmss", Locale.US).format(new Date()) + ".broccoli-archive";
                File file = new File(requireContext().getCacheDir(), name);
                recipeFileService.exportTo(Uri.fromFile(file), recipes).get();
                Uri contentUri = FileProvider.getUriForFile(requireContext(), BuildConfig.APPLICATION_ID + ".fileprovider", file);

                requireActivity().runOnUiThread(() -> {
                    Intent intent = new Intent(Intent.ACTION_SEND);
                    intent.putExtra(Intent.EXTRA_STREAM, contentUri);
                    intent.setType(recipes.size()==1 ? "application/broccoli" : "application/octet-stream");
                    intent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);
                    startActivity(Intent.createChooser(intent, getString(R.string.share_file_action)));
                });
            } catch (Exception e) {
                requireActivity().runOnUiThread(() ->
                        Toast.makeText(requireContext(), R.string.export_failed, Toast.LENGTH_LONG).show());
            }
        });
    }

    private void shareSelectedWebLink(Recipe recipe, boolean qr) {
        Toast.makeText(requireContext(), R.string.share_web_link_creating, Toast.LENGTH_SHORT).show();
        syncService.shareWebLink(recipe).whenComplete((url, error) -> requireActivity().runOnUiThread(() -> {
            if (error != null) {
                Toast.makeText(requireContext(), R.string.share_web_link_failed, Toast.LENGTH_LONG).show();
                return;
            }
            if (qr) {
                QrCodeDialog.show(requireActivity(), getString(R.string.share_qr_code_action), url);
            } else {
                Intent intent = new Intent(Intent.ACTION_SEND);
                intent.putExtra(Intent.EXTRA_SUBJECT, recipe.getTitle());
                intent.putExtra(Intent.EXTRA_TEXT, url);
                intent.setType("text/plain");
                startActivity(Intent.createChooser(intent, getString(R.string.share_web_link_action)));
            }
        }));
    }
    private String sanitize(String value){String s=value==null?"recipe":value.replaceAll("[^a-zA-Z0-9._-]","_");return s.isEmpty()?"recipe":s;}

    @Override
    public void onDestroyView() {
        Toolbar toolbar = requireActivity().findViewById(R.id.toolbar);
        if (toolbar != null) {
            toolbar.setOnMenuItemClickListener(null);
            toolbar.getMenu().clear();
        }
        super.onDestroyView();
    }

    private Category getPreferredCategory(){
        SharedPreferences prefs=PreferenceManager.getDefaultSharedPreferences(requireActivity());
        String id=prefs.getString("preferred-category","-1");
        if("-2".equals(id))return viewModel.getCategoryFavorites(); if("-4".equals(id))return viewModel.getCategorySeasonal(); return viewModel.getCategoryAll();
    }
    private Optional<SeasonalFood> getSeasonalFoodArgument(){return Optional.ofNullable(RecipeFragmentArgs.fromBundle(getArguments()).getSeasonalFood());}
}
