CREATE TABLE IF NOT EXISTS user_settings (
    user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    default_restaurant VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
    default_duration VARCHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'weekly',
    orders_per_page TINYINT UNSIGNED NOT NULL DEFAULT 25,
    date_format VARCHAR(5) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'Y-m-d',
    theme VARCHAR(5) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'light',
    panel_background VARCHAR(11) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'transparent',
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT fk_settings_user FOREIGN KEY (user_id) REFERENCES auth_users (id) ON DELETE CASCADE,
    CONSTRAINT chk_settings_restaurant CHECK (default_restaurant IN ('', 'jamilah', 'greenview', 'lepak')),
    CONSTRAINT chk_settings_duration CHECK (default_duration IN ('weekly', 'monthly')),
    CONSTRAINT chk_settings_page_size CHECK (orders_per_page IN (10, 25, 50)),
    CONSTRAINT chk_settings_date CHECK (date_format IN ('d/m/Y', 'Y-m-d')),
    CONSTRAINT chk_settings_theme CHECK (theme IN ('light', 'dark')),
    CONSTRAINT chk_settings_panel CHECK (panel_background IN ('solid', 'transparent')),
    CONSTRAINT chk_settings_revision CHECK (revision >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
