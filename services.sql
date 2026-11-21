-- Run once against the existing foody database; no commission data is changed.
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
