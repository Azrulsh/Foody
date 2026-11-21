CREATE TABLE IF NOT EXISTS restaurants (
    code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    address VARCHAR(500) NOT NULL DEFAULT '',
    phone VARCHAR(30) NOT NULL DEFAULT '',
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT chk_restaurant_code CHECK (code IN ('jamilah', 'greenview', 'lepak')),
    CONSTRAINT chk_restaurant_revision CHECK (revision >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stable codes preserve existing service, report and commission identities.
-- Re-importing this script never replaces edited restaurant details.
INSERT INTO restaurants (code, name) VALUES
    ('jamilah', 'Jamilah restaurant'),
    ('greenview', 'Greenview restaurant'),
    ('lepak', 'Lepak cafe')
ON DUPLICATE KEY UPDATE code = VALUES(code);
