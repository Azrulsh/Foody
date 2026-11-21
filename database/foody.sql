-- Foody consolidated schema for XAMPP / MariaDB.
-- Select the foody database before importing this file.
-- Safe to re-import: existing records and edited restaurant details are retained.
-- Contains schema and initial restaurant names only, not an export of account/business data.
-- Existing installations need no re-import merely because this file moved.

SET NAMES utf8mb4;

-- Legacy rows have no restaurant/module/period identity; keep them unchanged.
CREATE TABLE IF NOT EXISTS commission (
    commissions DOUBLE NOT NULL,
    penalties DOUBLE NOT NULL,
    services DOUBLE NOT NULL,
    totals DOUBLE NOT NULL,
    ID INT NOT NULL AUTO_INCREMENT PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Service amounts are separate from commission data. Amounts use integer cents.
CREATE TABLE IF NOT EXISTS service_amounts (
    restaurant VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    period VARCHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    slot_number TINYINT UNSIGNED NOT NULL,
    amount_cents BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (restaurant, period, slot_number),
    CONSTRAINT chk_service_restaurant CHECK (restaurant IN ('jamilah', 'greenview', 'lepak')),
    CONSTRAINT chk_service_slot CHECK (
        (period = 'weekly' AND slot_number BETWEEN 1 AND 7)
        OR (period = 'monthly' AND slot_number BETWEEN 1 AND 4)
    ),
    CONSTRAINT chk_service_amount CHECK (amount_cents <= 999999999)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Report amounts remain separate from service and commission records.
CREATE TABLE IF NOT EXISTS report_amounts (
    restaurant VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    period VARCHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    slot_number TINYINT UNSIGNED NOT NULL,
    amount_cents BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (restaurant, period, slot_number),
    CONSTRAINT chk_report_restaurant CHECK (restaurant IN ('jamilah', 'greenview', 'lepak')),
    CONSTRAINT chk_report_slot CHECK (
        (period = 'weekly' AND slot_number BETWEEN 1 AND 7)
        OR (period = 'monthly' AND slot_number BETWEEN 1 AND 4)
    ),
    CONSTRAINT chk_report_amount CHECK (amount_cents <= 999999999)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Preserves legacy commission rows, which have no restaurant/module/period identity.
CREATE TABLE IF NOT EXISTS commission_records (
    restaurant VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_module VARCHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    period VARCHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    gross_cents BIGINT UNSIGNED NOT NULL,
    penalty_cents BIGINT UNSIGNED NOT NULL,
    delivery_cents BIGINT UNSIGNED NOT NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (restaurant, source_module, period),
    CONSTRAINT chk_commission_restaurant CHECK (restaurant IN ('jamilah', 'greenview', 'lepak')),
    CONSTRAINT chk_commission_module CHECK (source_module IN ('service', 'report')),
    CONSTRAINT chk_commission_period CHECK (period IN ('weekly', 'monthly')),
    CONSTRAINT chk_commission_money CHECK (gross_cents <= 999999999 AND penalty_cents <= 999999999 AND delivery_cents <= 999999999),
    CONSTRAINT chk_commission_revision CHECK (revision >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS auth_users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(254) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    password_hash VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    auth_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    profile_revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS auth_login_limits (
    bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    attempts INT UNSIGNED NOT NULL,
    expires_at BIGINT UNSIGNED NOT NULL,
    INDEX idx_auth_limit_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    restaurant_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    order_date DATE NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    total_cents BIGINT UNSIGNED NOT NULL,
    status VARCHAR(9) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending',
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_orders_restaurant_date (restaurant_code, order_date, id),
    INDEX idx_orders_date (order_date, id),
    CONSTRAINT fk_orders_restaurant FOREIGN KEY (restaurant_code) REFERENCES restaurants (code),
    CONSTRAINT fk_orders_creator FOREIGN KEY (created_by) REFERENCES auth_users (id),
    CONSTRAINT fk_orders_updater FOREIGN KEY (updated_by) REFERENCES auth_users (id),
    CONSTRAINT chk_order_total CHECK (total_cents <= 999999999),
    CONSTRAINT chk_order_status CHECK (status IN ('pending', 'completed', 'cancelled')),
    CONSTRAINT chk_order_revision CHECK (revision >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

-- Upgrade installations created before appearance preferences were introduced.
-- MariaDB conditional DDL also makes repeated imports safe on current schemas.
ALTER TABLE user_settings
    ADD COLUMN IF NOT EXISTS theme VARCHAR(5) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'light',
    ADD COLUMN IF NOT EXISTS panel_background VARCHAR(11) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'transparent',
    ADD CONSTRAINT IF NOT EXISTS chk_settings_theme CHECK (theme IN ('light', 'dark')),
    ADD CONSTRAINT IF NOT EXISTS chk_settings_panel CHECK (panel_background IN ('solid', 'transparent'));
