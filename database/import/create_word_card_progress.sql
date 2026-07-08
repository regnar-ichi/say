CREATE TABLE word_card_progress (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    word_id INT NOT NULL,
    status ENUM('known', 'learning') NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_word_card_progress_user_word (user_id, word_id),
    KEY idx_word_card_progress_status (user_id, status)
);
