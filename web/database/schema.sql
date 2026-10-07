PRAGMA foreign_keys = ON;

CREATE TABLE archives (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    owner_id      INTEGER,
    original_name TEXT NOT NULL,
    stored_path   TEXT NOT NULL,
    imported_at   TEXT NOT NULL
);

CREATE TABLE categories (
    id   INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE
);

CREATE TABLE collection_recipes (
    collection_id INTEGER NOT NULL,
    recipe_id     INTEGER NOT NULL,
    PRIMARY KEY (collection_id, recipe_id)
);

CREATE TABLE collections (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    owner_id   INTEGER NOT NULL,
    token      TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL
);

CREATE TABLE recipe_categories (
    recipe_id   INTEGER NOT NULL,
    category_id INTEGER NOT NULL,
    PRIMARY KEY (recipe_id, category_id)
);

CREATE TABLE recipes (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    owner_id         INTEGER,
    uuid             TEXT UNIQUE,
    title            TEXT NOT NULL,
    description      TEXT,
    directions       TEXT,
    ingredients      TEXT,
    notes            TEXT,
    nutritional_vals TEXT,
    preparation_time TEXT,
    servings         TEXT,
    source           TEXT,
    favorite         INTEGER NOT NULL DEFAULT 0,
    image_name_orig  TEXT,
    image_path       TEXT,
    json_data        TEXT NOT NULL,
    source_type      TEXT NOT NULL,
    source_file      TEXT,
    created_at       TEXT NOT NULL,
    updated_at       TEXT NOT NULL
, user_id INTEGER, content_hash TEXT);

CREATE TABLE users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at    TEXT NOT NULL
);

CREATE INDEX idx_collection_recipes_collection_id ON collection_recipes(collection_id);

CREATE INDEX idx_collection_recipes_recipe_id     ON collection_recipes(recipe_id);

CREATE INDEX idx_collections_owner_id ON collections(owner_id);

CREATE INDEX idx_collections_token     ON collections(token);

CREATE INDEX idx_recipe_categories_rid ON recipe_categories(recipe_id);

CREATE INDEX idx_recipes_owner_hash
    ON recipes (owner_id, content_hash);

CREATE INDEX idx_recipes_user_id ON recipes(user_id);


CREATE TABLE sync_tokens (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id      INTEGER NOT NULL,
    device_id    TEXT NOT NULL,
    device_name  TEXT,
    token_hash   TEXT NOT NULL UNIQUE,
    created_at   TEXT NOT NULL,
    last_used_at TEXT,
    UNIQUE (user_id, device_id)
);

CREATE INDEX idx_sync_tokens_user_id ON sync_tokens(user_id);
