-- Existing installations only: apply once after settings.sql was already installed.
-- Adds appearance preferences without changing the other saved settings.
ALTER TABLE user_settings
    ADD COLUMN theme VARCHAR(5) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'light',
    ADD COLUMN panel_background VARCHAR(11) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'transparent',
    ADD CONSTRAINT chk_settings_theme CHECK (theme IN ('light', 'dark')),
    ADD CONSTRAINT chk_settings_panel CHECK (panel_background IN ('solid', 'transparent'));
