-- 'copied': nutrients taken from an earlier entry with the same food (suggestions)
ALTER TABLE food_entries MODIFY nutrients_source ENUM('ai','manual','copied') NULL;
