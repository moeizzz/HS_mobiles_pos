-- Default data for a fresh hs_mobile database (no users, no transactions).
-- The admin login is NOT included: create it in the `users` table.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

DELETE FROM `settings`;
INSERT INTO `settings` (`setting_key`,`setting_value`,`label`,`group_name`,`sort_order`) VALUES
('invoice_terms','Please check the IMEI and the box contents before leaving the shop.\nWarranty applies only as written against each item; physical damage, water and software tampering are not covered.\nGoods once sold are not returnable. Exchange only with the original box and this invoice.','Invoice terms (one per line)','receipt','7'),
('receipt_thanks','Thank you for visiting','Thank-you line on receipts','receipt','6'),
('shop_address','','Address','shop','4'),
('shop_logo','assets/img/logo.svg','Logo image path','shop','5'),
('shop_name','HS Mobiles','Shop name','shop','1'),
('shop_phone','03268711118','Phone / Cell','shop','3'),
('shop_tagline','','Tagline (printed under the name)','shop','2');

DELETE FROM `app_options`;
INSERT INTO `app_options` (`option_type`,`option_value`,`sort_order`,`status`) VALUES
('accessory','Original Box','10','1'),
('accessory','Original Charger','20','1'),
('accessory','Data Cable','30','1'),
('accessory','Handsfree/Cover','40','1'),
('network_note','Factory Unlocked','10','1'),
('network_note','2-Month SIM Working','20','1'),
('network_note','4-Month SIM Working','30','1'),
('network_note','Locked (JV)','40','1'),
('phone_condition','Box Pack (New)','10','1'),
('phone_condition','Open Box','20','1'),
('phone_condition','Used (10/10 Lush)','30','1'),
('phone_condition','Used (Normal)','40','1'),
('phone_condition','Kit Only (Phone Only)','50','1'),
('pta_status','PTA Approved (Official)','10','1'),
('pta_status','Non-PTA','20','1'),
('pta_status','JV / Carrier Locked','30','1'),
('pta_status','Patch / CPID Approved','40','1'),
('ram','2GB','10','1'),
('ram','3GB','20','1'),
('ram','4GB','30','1'),
('ram','6GB','40','1'),
('ram','8GB','50','1'),
('ram','12GB','60','1'),
('ram','16GB','70','1'),
('storage','32GB','10','1'),
('storage','64GB','20','1'),
('storage','128GB','30','1'),
('storage','256GB','40','1'),
('storage','512GB','50','1'),
('storage','1TB','60','1'),
('warranty','1 Year Official','10','1'),
('warranty','6 Months','20','1'),
('warranty','3 Months','30','1'),
('warranty','1 Month','40','1'),
('warranty','7 Days Check Warranty','50','1'),
('warranty','No Warranty','60','1');

DELETE FROM `brands`;
INSERT INTO `brands` (`id`,`name`,`description`,`status`,`created_at`,`updated_at`) VALUES
('1','Apple',NULL,'1','2026-11-20',NULL),
('2','Samsung',NULL,'1','2026-11-20',NULL),
('3','Vivo',NULL,'1','2026-11-20',NULL),
('4','Oppo',NULL,'1','2026-11-20',NULL),
('5','Infinix',NULL,'1','2026-11-20',NULL),
('6','Tecno',NULL,'1','2026-11-20',NULL),
('7','Xiaomi/Redmi',NULL,'1','2026-11-20',NULL),
('8','Realme',NULL,'1','2026-11-20',NULL),
('9','OnePlus',NULL,'1','2026-11-20',NULL),
('10','Google Pixel',NULL,'1','2026-11-20',NULL),
('11','Other',NULL,'1','2026-11-20',NULL),
('17','Nokia',NULL,'1','2026-11-24',NULL);

DELETE FROM `categories`;
INSERT INTO `categories` (`id`,`name`,`description`,`product_type`,`status`,`created_at`,`updated_at`) VALUES
('1','Mobile','Mobile phones','mobile','1','2026-08-10','2026-08-10'),
('3','General','General products','general','1','2026-08-10','2026-08-10');

DELETE FROM `branches`;
INSERT INTO `branches` (`id`,`name`,`address`,`phone`,`status`,`created_at`,`updated_at`) VALUES
('1','AL NOOR MOBILE ACCESSORIES','Ouch, Lower Dir','0341-1817181 / 0306-','1','2026-08-10',NULL);

SET FOREIGN_KEY_CHECKS=1;
