<?php
declare(strict_types=1);

use Brassica\Core\Router;
use Brassica\Http\LegacyController;
use Brassica\Http\SetupController;
use Brassica\Http\SyncController;

return static function (Router $router): void {
    $router->any('/', static fn() => LegacyController::page('index'));
    $router->any('/admin_collection_delete.php', static fn() => LegacyController::page('admin_collection_delete'));
    $router->any('/admin_collection_recipes.php', static fn() => LegacyController::page('admin_collection_recipes'));
    $router->any('/admin_collections.php', static fn() => LegacyController::page('admin_collections'));
    $router->any('/admin_recipe_clone.php', static fn() => LegacyController::page('admin_recipe_clone'));
    $router->any('/admin_user_delete.php', static fn() => LegacyController::page('admin_user_delete'));
    $router->any('/admin_user_recipes.php', static fn() => LegacyController::page('admin_user_recipes'));
    $router->any('/admin_users.php', static fn() => LegacyController::page('admin_users'));
    $router->any('/archive.php', static fn() => LegacyController::page('archive'));
    $router->any('/archive_view.php', static fn() => LegacyController::page('archive_view'));
    $router->any('/editor.php', static fn() => LegacyController::page('editor'));
    $router->any('/editor_new.php', static fn() => LegacyController::page('editor_new'));
    $router->any('/index.php', static fn() => LegacyController::page('index'));
    $router->any('/index_open.php', static fn() => LegacyController::page('index_open'));
    $router->any('/login.php', static fn() => LegacyController::page('login'));
    $router->any('/logout.php', static fn() => LegacyController::page('logout'));
    $router->any('/register.php', static fn() => LegacyController::page('register'));
    $router->any('/set_admin_password.php', static fn() => SetupController::handle());
    $router->any('/view.php', static fn() => LegacyController::page('view'));
    $router->any('/login', static fn() => LegacyController::page('login'));
    $router->any('/logout', static fn() => LegacyController::page('logout'));
    $router->any('/register', static fn() => LegacyController::page('register'));
    $router->any('/set-admin-password', static fn() => SetupController::handle());
    $router->any('/setup', static fn() => SetupController::handle());
    $router->any('/recipes', static fn() => LegacyController::page('index'));
    $router->any('/recipes/new', static fn() => LegacyController::page('editor_new'));
    $router->any('/import', static fn() => LegacyController::page('archive'));
    $router->any('/admin/users', static fn() => LegacyController::page('admin_users'));
    $router->any('/admin/collections', static fn() => LegacyController::page('admin_collections'));
    $router->any('/api/archive_image.php', static fn() => LegacyController::api('archive_image'));
    $router->any('/api/archive_import.php', static fn() => LegacyController::api('archive_import'));
    $router->any('/api/archive_items.php', static fn() => LegacyController::api('archive_items'));
    $router->any('/api/archive_upload.php', static fn() => LegacyController::api('archive_upload'));
    $router->any('/api/collections_create.php', static fn() => LegacyController::api('collections_create'));
    $router->any('/api/collections_import.php', static fn() => LegacyController::api('collections_import'));
    $router->any('/api/collections_recipes.php', static fn() => LegacyController::api('collections_recipes'));
    $router->any('/api/image.php', static fn() => LegacyController::api('image'));
    $router->any('/api/categories.php', static fn() => LegacyController::api('categories'));
    $router->get('/api/categories', static fn() => LegacyController::api('categories'));
    $router->any('/api/import_broccoli.php', static fn() => LegacyController::api('import_broccoli'));
    $router->any('/api/recipe_clone_to_me.php', static fn() => LegacyController::api('recipe_clone_to_me'));
    $router->any('/api/recipe_get.php', static fn() => LegacyController::api('recipe_get'));
    $router->any('/api/recipe_save.php', static fn() => LegacyController::api('recipe_save'));
    $router->any('/api/recipes_delete.php', static fn() => LegacyController::api('recipes_delete'));
    $router->any('/api/recipes_export.php', static fn() => LegacyController::api('recipes_export'));
    $router->any('/api/recipes_list.php', static fn() => LegacyController::api('recipes_list'));
    $router->any('/api/recipes', static fn() => LegacyController::api('recipes_list'));
    $router->any('/api/recipe', static fn() => LegacyController::api('recipe_get'));
    $router->any('/api/recipe/save', static fn() => LegacyController::api('recipe_save'));
    $router->any('/api/recipes/delete', static fn() => LegacyController::api('recipes_delete'));
    $router->any('/api/recipes/export', static fn() => LegacyController::api('recipes_export'));
    $router->any('/api/import', static fn() => LegacyController::api('import_broccoli'));
    $router->any('/api/archive/upload', static fn() => LegacyController::api('archive_upload'));
    $router->any('/api/archive/items', static fn() => LegacyController::api('archive_items'));
    $router->any('/api/archive/import', static fn() => LegacyController::api('archive_import'));
    $router->any('/api/image', static fn() => LegacyController::api('image'));
    $router->any('/api/archive/image', static fn() => LegacyController::api('archive_image'));
    $router->any('/api/collections/create', static fn() => LegacyController::api('collections_create'));
    $router->any('/api/collections/recipes', static fn() => LegacyController::api('collections_recipes'));
    $router->any('/api/collections/import', static fn() => LegacyController::api('collections_import'));

    // Brassica Android Sync API v1 (HTTP Basic über den bestehenden Benutzeraccount).
    $router->get('/api/v1/sync/manifest', static fn() => SyncController::manifest());
    $router->get('/api/v1/sync/recipes/{uuid}', static fn(array $p) => SyncController::recipe($p));
    $router->post('/api/v1/sync/apply', static fn() => SyncController::apply());

    // Lesbare Routen mit Parametern. Die alten *.php-URLs bleiben parallel erhalten.
    $router->get('/recipes/{id}', static function (array $p): void { $_GET['id'] = $p['id']; LegacyController::page('view'); });
    $router->get('/recipes/{id}/cook', static function (array $p): void { $_GET['id'] = $p['id']; LegacyController::page('cook'); });
    $router->any('/recipes/{id}/edit', static function (array $p): void { $_GET['id'] = $p['id']; LegacyController::page('editor'); });
    $router->get('/archives/{id}', static function (array $p): void { $_GET['archive_id'] = $p['id']; LegacyController::page('archive_view'); });
    $router->get('/share/{token}', static function (array $p): void { $_GET['token'] = $p['token']; LegacyController::page('index_open'); });
    $router->get('/admin/users/{id}/recipes', static function (array $p): void { $_GET['user_id'] = $p['id']; LegacyController::page('admin_user_recipes'); });
    $router->get('/admin/collections/{id}/recipes', static function (array $p): void { $_GET['collection_id'] = $p['id']; LegacyController::page('admin_collection_recipes'); });

    $router->get('/api/recipes/{id}', static function (array $p): void { $_GET['id'] = $p['id']; LegacyController::api('recipe_get'); });
};
