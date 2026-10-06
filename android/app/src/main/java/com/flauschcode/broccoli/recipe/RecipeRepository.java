package com.flauschcode.broccoli.recipe;

import androidx.lifecycle.LiveData;
import androidx.room.Transaction;

import com.flauschcode.broccoli.category.Category;
import com.flauschcode.broccoli.category.CategoryRepository;
import com.flauschcode.broccoli.recipe.images.RecipeImageService;
import com.flauschcode.broccoli.seasons.SeasonalCalendar;
import com.flauschcode.broccoli.seasons.SeasonalCalendarHolder;

import java.util.ArrayList;
import java.util.Collection;
import java.util.List;
import java.util.Optional;
import java.util.concurrent.CompletableFuture;
import java.util.stream.Collectors;

import javax.inject.Inject;
import javax.inject.Singleton;

@Singleton
public class RecipeRepository {
    public enum CategoryMatchMode { OR, AND }

    private final RecipeDAO recipeDAO;
    private final RecipeImageService recipeImageService;
    private final SeasonalCalendarHolder seasonalCalendarHolder;
    private final CategoryRepository categoryRepository;

    @Inject
    RecipeRepository(RecipeDAO recipeDAO, RecipeImageService recipeImageService, SeasonalCalendarHolder seasonalCalendarHolder, CategoryRepository categoryRepository) {
        this.recipeDAO = recipeDAO;
        this.recipeImageService = recipeImageService;
        this.seasonalCalendarHolder = seasonalCalendarHolder;
        this.categoryRepository = categoryRepository;
    }

    public LiveData<List<Recipe>> find(SearchCriteria criteria) {
        Category category = criteria.getCategory();
        String searchTerm = criteria.getSearchTerm();

        if (!criteria.getSeasonalTerms().isEmpty()) {
            String wildcardQuery = getSanitizedWildcardQuery(searchTerm);
            String seasonalTerm = buildQueryFor(criteria.getSeasonalTerms());
            return "".equals(searchTerm) ? recipeDAO.findSeasonal(seasonalTerm) : recipeDAO.searchForSeasonal(seasonalTerm, wildcardQuery);
        }

        if (!criteria.getCategories().isEmpty()) {
            List<Long> ids = criteria.getCategories().stream().map(Category::getCategoryId).collect(Collectors.toList());
            boolean all = criteria.getCategoryMatchMode() == CategoryMatchMode.AND;
            if ("".equals(searchTerm)) {
                return all ? recipeDAO.filterByAll(ids, ids.size()) : recipeDAO.filterByAny(ids);
            }
            String wildcardQuery = getSanitizedWildcardQuery(searchTerm);
            return all ? recipeDAO.filterByAllAndSearchFor(ids, ids.size(), wildcardQuery) : recipeDAO.filterByAnyAndSearchFor(ids, wildcardQuery);
        }

        if (category.equals(categoryRepository.getAllRecipesCategory()) || category.equals(categoryRepository.getFavoritesCategory())) {
            List<Boolean> favoriteStates = getChosenFavoriteStates(category);
            return "".equals(searchTerm) ? recipeDAO.findAll(favoriteStates) : recipeDAO.searchFor(getSanitizedWildcardQuery(searchTerm), favoriteStates);
        }
        if (category.equals(categoryRepository.getUnassignedRecipesCategory())) {
            return "".equals(searchTerm) ? recipeDAO.findUnassigned() : recipeDAO.searchForUnassigned(getSanitizedWildcardQuery(searchTerm));
        }
        if (category.equals(categoryRepository.getSeasonalRecipesCategory())) {
            String seasonalSearchTerm = getSeasonalSearchTerm();
            return "".equals(searchTerm) ? recipeDAO.findSeasonal(seasonalSearchTerm) : recipeDAO.searchForSeasonal(seasonalSearchTerm, getSanitizedWildcardQuery(searchTerm));
        }
        return "".equals(searchTerm) ? recipeDAO.filterBy(category.getCategoryId()) : recipeDAO.filterByAndSearchFor(category.getCategoryId(), getSanitizedWildcardQuery(searchTerm));
    }

    private static String getSanitizedWildcardQuery(String term) {
        String trailingDashesRemoved = term.replaceFirst("^-+", "");
        String quotesEscaped = trailingDashesRemoved.replace("\"", "");
        return String.format("%s*", quotesEscaped);
    }

    private String getSeasonalSearchTerm() {
        Optional<SeasonalCalendar> seasonalCalendarOptional = seasonalCalendarHolder.get();
        if (seasonalCalendarOptional.isPresent()) return buildQueryFor(seasonalCalendarOptional.get().getSearchTermsForCurrentMonth());
        return "";
    }

    private String buildQueryFor(Collection<String> seasonalTerms) {
        return seasonalTerms.stream().map(term -> "\"" + term + "\"").collect(Collectors.joining(" OR "));
    }

    private List<Boolean> getChosenFavoriteStates(Category category) {
        List<Boolean> states = new ArrayList<>(); states.add(Boolean.TRUE);
        if (!category.equals(categoryRepository.getFavoritesCategory())) states.add(Boolean.FALSE);
        return states;
    }

    @Transaction
    public CompletableFuture<Long> insertOrUpdate(Recipe recipe) {
        return CompletableFuture.supplyAsync(() -> {
            if (recipe.getRecipeId() == 0) {
                long recipeId = recipeDAO.insert(recipe.getCoreRecipe());
                recipe.getCategories().forEach(category -> recipeDAO.insert(new RecipeCategoryAssociation(recipeId, category.getCategoryId())));
                return recipeId;
            }
            recipeDAO.update(recipe.getCoreRecipe());
            recipeDAO.getCategoriesFor(recipe.getRecipeId()).forEach(recipeDAO::delete);
            recipe.getCategories().forEach(category -> recipeDAO.insert(new RecipeCategoryAssociation(recipe.getRecipeId(), category.getCategoryId())));
            return recipe.getRecipeId();
        });
    }

    public CompletableFuture<Void> delete(Recipe recipe) {
        return CompletableFuture.allOf(recipeImageService.deleteImage(recipe.getImageName()), CompletableFuture.runAsync(() -> recipeDAO.delete(recipe.getCoreRecipe())));
    }

    public CompletableFuture<List<Recipe>> findAll() { return CompletableFuture.supplyAsync(recipeDAO::findAll); }

    public static class SearchCriteria {
        private Category category;
        private String searchTerm;
        private List<String> seasonalTerms;
        private List<Category> categories = new ArrayList<>();
        private CategoryMatchMode categoryMatchMode = CategoryMatchMode.OR;

        public SearchCriteria(Category category, String searchTerm, List<String> seasonalTerms) {
            this.category=category; this.searchTerm=searchTerm; this.seasonalTerms=seasonalTerms;
        }
        public Category getCategory(){return category;} public void setCategory(Category c){category=c;}
        public String getSearchTerm(){return searchTerm;} public void setSearchTerm(String s){searchTerm=s;}
        public List<String> getSeasonalTerms(){return seasonalTerms;} public void setSeasonalTerms(List<String> s){seasonalTerms=s;}
        public List<Category> getCategories(){return categories;} public void setCategories(List<Category> c){categories=new ArrayList<>(c);}
        public CategoryMatchMode getCategoryMatchMode(){return categoryMatchMode;} public void setCategoryMatchMode(CategoryMatchMode m){categoryMatchMode=m;}
    }
}
