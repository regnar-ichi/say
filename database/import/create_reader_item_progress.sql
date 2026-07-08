CREATE TABLE reader_item_progress (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    page_number INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_reader_item_progress_user_item (user_id, item_id),
    KEY idx_reader_item_progress_user_item (user_id, item_id),
    KEY idx_reader_item_progress_item (item_id)
);
