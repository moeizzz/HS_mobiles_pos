-- =====================================================================
-- Migration: Pakistani mobile-market fields for "Add Mobile / Product"
-- Target: MariaDB 10.4+ (XAMPP).  Safe to run more than once.
-- (On stock MySQL 5.7 remove the "IF NOT EXISTS" clauses and run once.)
-- Usage:  mysql -u root al_noor_pos < migrations/2026_11_20_mobile_market_fields.sql
-- =====================================================================

-- ---------- products ----------
-- model_name is stored separately so Brand+Model+RAM+Storage+Color grouping is exact.
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS model_name VARCHAR(100) DEFAULT NULL COMMENT 'Mobile model, used for stock grouping' AFTER name;

CREATE INDEX IF NOT EXISTS idx_products_group ON products (product_type, brand_id, model_name, ram, storage, color);

-- ---------- product_serials ----------
-- Existing convention kept: imei_number = IMEI 1, serial_number = IMEI 2 (mobiles).
-- Existing status enum ('available','sold','returned') kept: 'available' == "In Stock".
ALTER TABLE product_serials
    ADD COLUMN IF NOT EXISTS device_serial_no  VARCHAR(100) DEFAULT NULL COMMENT 'Manufacturer serial number' AFTER serial_number,
    ADD COLUMN IF NOT EXISTS phone_condition   VARCHAR(30)  DEFAULT NULL COMMENT 'Box Pack, Open Box, Used 10/10, Used Normal, Kit Only' AFTER device_serial_no,
    ADD COLUMN IF NOT EXISTS pta_status        VARCHAR(30)  DEFAULT NULL COMMENT 'PTA Approved, Non-PTA, JV/Locked, Patch/CPID' AFTER phone_condition,
    ADD COLUMN IF NOT EXISTS network_note      VARCHAR(150) DEFAULT NULL AFTER pta_status,
    ADD COLUMN IF NOT EXISTS battery_health    VARCHAR(10)  DEFAULT NULL COMMENT 'e.g. 95% or N/A' AFTER network_note,
    ADD COLUMN IF NOT EXISTS warranty_status   VARCHAR(60)  DEFAULT NULL AFTER battery_health,
    ADD COLUMN IF NOT EXISTS accessories       VARCHAR(255) DEFAULT NULL COMMENT 'Comma list: Box, Charger, Cable, Handsfree/Cover' AFTER warranty_status,
    ADD COLUMN IF NOT EXISTS fault_notes       TEXT         DEFAULT NULL AFTER accessories,
    ADD COLUMN IF NOT EXISTS supplier_id       INT          DEFAULT NULL AFTER fault_notes,
    ADD COLUMN IF NOT EXISTS seller_name       VARCHAR(100) DEFAULT NULL COMMENT 'Walk-in seller' AFTER supplier_id,
    ADD COLUMN IF NOT EXISTS seller_cnic       VARCHAR(15)  DEFAULT NULL COMMENT 'XXXXX-XXXXXXX-X' AFTER seller_name,
    ADD COLUMN IF NOT EXISTS seller_phone      VARCHAR(20)  DEFAULT NULL AFTER seller_cnic,
    ADD COLUMN IF NOT EXISTS sale_price        DECIMAL(12,2) DEFAULT NULL COMMENT 'Per-unit asking price' AFTER purchase_price;

CREATE INDEX IF NOT EXISTS idx_serials_imei   ON product_serials (imei_number);
CREATE INDEX IF NOT EXISTS idx_serials_imei2  ON product_serials (serial_number);
CREATE INDEX IF NOT EXISTS idx_serials_status ON product_serials (status);
CREATE INDEX IF NOT EXISTS idx_serials_seller ON product_serials (seller_cnic);

-- ---------- brands (seed the common Pakistani-market brands) ----------
INSERT INTO brands (name, status, created_at)
SELECT b.name, 1, CURDATE() FROM (
    SELECT 'Apple' AS name UNION SELECT 'Samsung' UNION SELECT 'Vivo' UNION SELECT 'Oppo'
    UNION SELECT 'Infinix' UNION SELECT 'Tecno' UNION SELECT 'Xiaomi/Redmi' UNION SELECT 'Realme'
    UNION SELECT 'OnePlus' UNION SELECT 'Google Pixel' UNION SELECT 'Other'
) b WHERE NOT EXISTS (SELECT 1 FROM brands x WHERE x.name = b.name);

-- ---------- Integrity: audit fixes ----------
-- NOTE: run only when there are no duplicate IMEIs / invoice numbers (see audit checklist query 5).
ALTER TABLE product_serials
    ADD UNIQUE KEY IF NOT EXISTS uq_serial_imei  (imei_number),
    ADD UNIQUE KEY IF NOT EXISTS uq_serial_imei2 (serial_number);
ALTER TABLE purchases ADD UNIQUE KEY IF NOT EXISTS uq_purchase_invoice (invoice_no);
-- Link supplier payments to their purchase so edits/reversals match exactly.
ALTER TABLE supplier_payments ADD COLUMN IF NOT EXISTS purchase_id INT DEFAULT NULL AFTER supplier_id;
