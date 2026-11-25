-- =====================================================================
-- Remove the "Laptop" product type. Keeps Mobile and General only.
-- Run ONCE. (If run again it stops at step 1 with "Unknown column 'processor'" before changing anything - harmless.)
-- MariaDB 10.2+; on MySQL 5.7 remove the "IF EXISTS" words from the DROP COLUMN lines.
-- No Mobile / General record is deleted. TAKE A BACKUP FIRST:
--     mysqldump -u root al_noor_pos > backup_before_laptop_removal.sql
-- Usage:  mysql -u root al_noor_pos < migrations/2026_11_21_remove_laptop.sql
-- =====================================================================

-- 0) PREVIEW what is affected (run these SELECTs first if you like)
--    SELECT product_type, COUNT(*) FROM products GROUP BY product_type;
--    SELECT id, name, processor, screen_size, graphics FROM products
--     WHERE product_type = 'laptop' OR processor <> '' OR screen_size <> '' OR graphics <> '';

START TRANSACTION;

-- 1) Any leftover laptop product (or any row that still carries laptop specs) becomes a General item.
--    Its specs are copied into the description first, so no information is lost when the columns are dropped.
--    Stock, prices, purchase history and any serial rows stay untouched.
UPDATE products
   SET description = CONCAT_WS(' | ',
           NULLIF(description, ''),
           IF(processor   IS NOT NULL AND processor   <> '', CONCAT('Processor: ', processor), NULL),
           IF(screen_size IS NOT NULL AND screen_size <> '', CONCAT('Screen: ',    screen_size), NULL),
           IF(graphics    IS NOT NULL AND graphics    <> '', CONCAT('Graphics: ',  graphics), NULL)),
       product_type = IF(product_type = 'laptop', 'general', product_type),
       updated_at = CURDATE()
 WHERE product_type = 'laptop'
    OR processor <> '' OR screen_size <> '' OR graphics <> '';

-- 2) Move products that used the Laptop category to the General category, then delete the category.
UPDATE products
   SET category_id = (SELECT id FROM (SELECT id FROM categories WHERE product_type = 'general' ORDER BY id LIMIT 1) g)
 WHERE category_id IN (SELECT id FROM (SELECT id FROM categories WHERE product_type = 'laptop') l);
DELETE FROM categories WHERE product_type = 'laptop';

COMMIT;

-- 3) Drop the laptop-only columns (products.product_type is a VARCHAR, not an ENUM, so only its comment changes)
ALTER TABLE products
    DROP COLUMN IF EXISTS processor,
    DROP COLUMN IF EXISTS screen_size,
    DROP COLUMN IF EXISTS graphics;

ALTER TABLE products
    MODIFY product_type VARCHAR(50) DEFAULT 'general' COMMENT 'general, mobile';
ALTER TABLE categories
    MODIFY product_type VARCHAR(50) DEFAULT 'general' COMMENT 'general, mobile';

-- 4) OPTIONAL hard guard (MariaDB 10.2+ / MySQL 8.0.16+; ignored by MySQL 5.7). Run once only.
-- ALTER TABLE products   ADD CONSTRAINT chk_products_type   CHECK (product_type   IN ('mobile','general'));
-- ALTER TABLE categories ADD CONSTRAINT chk_categories_type CHECK (product_type IN ('mobile','general'));

-- 5) VERIFY
--    SELECT product_type, COUNT(*) FROM products GROUP BY product_type;          -- only mobile / general
--    SELECT id, name, product_type FROM categories;                              -- Mobile, General only
--    SHOW COLUMNS FROM products;                                                 -- no processor / screen_size / graphics
