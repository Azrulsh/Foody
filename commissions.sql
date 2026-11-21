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
