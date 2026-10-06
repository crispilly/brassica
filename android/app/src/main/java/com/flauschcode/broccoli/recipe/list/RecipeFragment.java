package com.flauschcode.broccoli.recipe.list;

import android.app.Activity;
import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.MenuItem;
import android.view.View;
import android.view.ViewGroup;
import android.widget.AdapterView;
import android.widget.ArrayAdapter;
import android.widget.Button;
import android.widget.RadioGroup;
import android.widget.Spinner;
import android.widget.Toast;

import androidx.activity.OnBackPressedCallback;
import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.annotation.NonNull;
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
import com.flauschcode.broccoli.R;
import com.flauschcode.broccoli.RecyclerViewAdapter;
import com.flauschcode.broccoli.category.Category;
import com.flauschcode.broccoli.recipe.Recipe;
import com.flauschcode.broccoli.recipe.RecipeRepository;
import com.flauschcode.broccoli.recipe.crud.CreateAndEditRecipeActivity;
import com.flauschcode.broccoli.recipe.details.RecipeDetailsActivity;
import com.flauschcode.broccoli.recipe.transfer.RecipeFileService;
import com.flauschcode.broccoli.seasons.SeasonalFood;
import com.google.android.material.chip.Chip;
import com.google.android.material.floatingactionbutton.FloatingActionButton;

import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Date;
import java.util.HashSet;
import java.util.List;
import java.util.Locale;
import java.util.Optional;
import java.util.Set;

import javax.inject.Inject;

import dagger.android.support.AndroidSupportInjection;

public class RecipeFragment extends Fragment implements AdapterView.OnItemSelectedListener, SearchView.OnQueryTextListener {

    @Inject ViewModelProvider.Factory viewModelFactory;
    @Inject RecipeFileService recipeFileService;

    private RecipeViewModel viewModel;
    private MenuItem searchItem;
    private SearchView searchView;
    private Spinner spinner;
    private Chip seasonalIngredientChip;
    private View categoryMultiBar;
    private Button categoryMultiButton;
    private RadioGroup categoryMatchMode;
    private final List<Category> availableCategories = new ArrayList<>();
    private final Set<Long> selectedCategoryIds = new HashSet<>();
    private List<Recipe> pendingExportRecipes = new ArrayList<>();
    private boolean suppressSpinner = false;

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

        Toolbar toolbar = root.findViewById(R.id.toolbar_recipes);
        setUpMenu(toolbar);
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
                if (!searchView.isIconified()) toolbar.collapseActionView();
                else { setEnabled(false); requireActivity().onBackPressed(); }
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
        toolbar.inflateMenu(R.menu.recipes);
        toolbar.setOnMenuItemClickListener(item -> {
            if(item.getItemId()==R.id.action_import_file){ importLauncher.launch(new String[]{"application/broccoli","application/octet-stream","application/zip"}); return true; }
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

    private void setUpSpinner(){
        ArrayAdapter<Category> adapter=new ArrayAdapter<>(getActivity(),android.R.layout.simple_spinner_item);
        adapter.add(viewModel.getCategoryAll()); adapter.add(viewModel.getCategorySeasonal()); adapter.add(viewModel.getCategoryUnassigned()); adapter.add(viewModel.getCategoryFavorites());
        adapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item); spinner.setAdapter(adapter); spinner.setOnItemSelectedListener(this);
        Category preferred=getPreferredCategory(); spinner.setSelection(adapter.getPosition(preferred),false); viewModel.setFilterName(preferred.getName());
    }

    private void setUpMultiCategoryFilter(){
        viewModel.getCategories().observe(getViewLifecycleOwner(), categories->{availableCategories.clear();availableCategories.addAll(categories);updateCategoryButton();});
        categoryMultiButton.setOnClickListener(v->showCategoryDialog());
        categoryMatchMode.setOnCheckedChangeListener((group,checkedId)->{
            if(selectedCategoryIds.isEmpty())return;
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
                    else {suppressSpinner=true;spinner.setSelection(0,false);spinner.post(() -> suppressSpinner=false);RecipeRepository.CategoryMatchMode mode=categoryMatchMode.getCheckedRadioButtonId()==R.id.category_mode_and?RecipeRepository.CategoryMatchMode.AND:RecipeRepository.CategoryMatchMode.OR;viewModel.setFilterCategories(getSelectedCategories(),mode);viewModel.setFilterName(getString(R.string.categories_selected,selectedCategoryIds.size()));}
                    updateCategoryButton();
                }).show();
    }

    private List<Category> getSelectedCategories(){List<Category> out=new ArrayList<>();for(Category c:availableCategories)if(selectedCategoryIds.contains(c.getCategoryId()))out.add(c);return out;}
    private void updateCategoryButton(){if(categoryMultiButton!=null)categoryMultiButton.setText(selectedCategoryIds.isEmpty()?getString(R.string.all_categories):getString(R.string.categories_selected,selectedCategoryIds.size()));}

    private void showExportSelection(){
        viewModel.findAllRecipes().whenComplete((recipes,error)->requireActivity().runOnUiThread(()->{
            if(error!=null||recipes==null||recipes.isEmpty()){Toast.makeText(requireContext(),R.string.export_failed,Toast.LENGTH_LONG).show();return;}
            String[] titles=recipes.stream().map(Recipe::getTitle).toArray(String[]::new); boolean[] checked=new boolean[recipes.size()];
            new AlertDialog.Builder(requireContext()).setTitle(R.string.select_recipes_export).setMultiChoiceItems(titles,checked,(d,w,c)->checked[w]=c)
                    .setNegativeButton(android.R.string.cancel,null).setPositiveButton(android.R.string.ok,(d,w)->{
                        pendingExportRecipes=new ArrayList<>();for(int i=0;i<checked.length;i++)if(checked[i])pendingExportRecipes.add(recipes.get(i));
                        if(pendingExportRecipes.isEmpty())return;
                        String filename=pendingExportRecipes.size()==1 ? sanitize(pendingExportRecipes.get(0).getTitle())+".broccoli" : "EXPORT_"+new SimpleDateFormat("yyyyMMdd_HHmmss", Locale.US).format(new Date())+".broccoli-archive";
                        exportLauncher.launch(filename);
                    }).show();
        }));
    }
    private String sanitize(String value){String s=value==null?"recipe":value.replaceAll("[^a-zA-Z0-9._-]","_");return s.isEmpty()?"recipe":s;}

    private Category getPreferredCategory(){
        SharedPreferences prefs=PreferenceManager.getDefaultSharedPreferences(requireActivity());
        String id=prefs.getString("preferred-category","-1");
        if("-2".equals(id))return viewModel.getCategoryFavorites(); if("-4".equals(id))return viewModel.getCategorySeasonal(); return viewModel.getCategoryAll();
    }
    private Optional<SeasonalFood> getSeasonalFoodArgument(){return Optional.ofNullable(RecipeFragmentArgs.fromBundle(getArguments()).getSeasonalFood());}
}
