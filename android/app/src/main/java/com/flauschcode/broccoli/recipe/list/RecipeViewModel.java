package com.flauschcode.broccoli.recipe.list;

import androidx.lifecycle.LiveData;
import androidx.lifecycle.MutableLiveData;
import androidx.lifecycle.Transformations;
import androidx.lifecycle.ViewModel;

import com.flauschcode.broccoli.category.Category;
import com.flauschcode.broccoli.category.CategoryRepository;
import com.flauschcode.broccoli.recipe.Recipe;
import com.flauschcode.broccoli.recipe.RecipeRepository;

import java.util.ArrayList;
import java.util.List;
import java.util.concurrent.CompletableFuture;

import javax.inject.Inject;

public class RecipeViewModel extends ViewModel {
    private final LiveData<List<Recipe>> recipes;
    private final MutableLiveData<RecipeRepository.SearchCriteria> criteriaLiveData = new MutableLiveData<>();
    private final MutableLiveData<String> filterName = new MutableLiveData<>();
    private final CategoryRepository categoryRepository;
    private final RecipeRepository recipeRepository;

    @Inject
    RecipeViewModel(RecipeRepository recipeRepository, CategoryRepository categoryRepository) {
        this.categoryRepository=categoryRepository; this.recipeRepository=recipeRepository;
        criteriaLiveData.setValue(createDefaultSearchCriteria()); filterName.setValue("");
        recipes=Transformations.switchMap(criteriaLiveData, recipeRepository::find);
    }

    LiveData<List<Category>> getCategories(){return categoryRepository.findAll();}
    LiveData<List<Recipe>> getRecipes(){return recipes;}
    CompletableFuture<List<Recipe>> findAllRecipes(){return recipeRepository.findAll();}

    void setFilterCategory(Category filterCategory){
        RecipeRepository.SearchCriteria c=copyCriteria(); c.setCategory(filterCategory); c.setCategories(new ArrayList<>()); c.setSeasonalTerms(new ArrayList<>()); criteriaLiveData.setValue(c);
    }
    void setFilterCategories(List<Category> categories, RecipeRepository.CategoryMatchMode mode){
        RecipeRepository.SearchCriteria c=copyCriteria(); c.setCategory(getCategoryAll()); c.setCategories(categories); c.setCategoryMatchMode(mode); c.setSeasonalTerms(new ArrayList<>()); criteriaLiveData.setValue(c);
    }
    void setCategoryMatchMode(RecipeRepository.CategoryMatchMode mode){RecipeRepository.SearchCriteria c=copyCriteria();c.setCategoryMatchMode(mode);criteriaLiveData.setValue(c);}
    void setSearchTerm(String searchTerm){RecipeRepository.SearchCriteria c=copyCriteria();c.setSearchTerm(searchTerm);criteriaLiveData.setValue(c);}
    void setSeasonalTerms(List<String> terms){RecipeRepository.SearchCriteria c=createDefaultSearchCriteria();c.setSeasonalTerms(terms);c.setSearchTerm(criteriaLiveData.getValue().getSearchTerm());criteriaLiveData.setValue(c);}

    private RecipeRepository.SearchCriteria copyCriteria(){
        RecipeRepository.SearchCriteria old=criteriaLiveData.getValue();
        RecipeRepository.SearchCriteria c=createDefaultSearchCriteria();
        if(old!=null){c.setCategory(old.getCategory());c.setSearchTerm(old.getSearchTerm());c.setSeasonalTerms(old.getSeasonalTerms());c.setCategories(old.getCategories());c.setCategoryMatchMode(old.getCategoryMatchMode());}
        return c;
    }
    public MutableLiveData<String> getFilterName(){return filterName;} void setFilterName(String n){filterName.setValue(n);}
    public Category getCategoryAll(){return categoryRepository.getAllRecipesCategory();}
    public Category getCategoryFavorites(){return categoryRepository.getFavoritesCategory();}
    public Category getCategoryUnassigned(){return categoryRepository.getUnassignedRecipesCategory();}
    public Category getCategorySeasonal(){return categoryRepository.getSeasonalRecipesCategory();}
    private RecipeRepository.SearchCriteria createDefaultSearchCriteria(){return new RecipeRepository.SearchCriteria(categoryRepository.getAllRecipesCategory(),"",new ArrayList<>());}
}
