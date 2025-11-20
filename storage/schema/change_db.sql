#-- Upgrading OzzieAccom 3.1 to 3.3

INSERT INTO `settings` (`id`, `name`, `value`, `type`) VALUES (NULL, 'facebook_login', '1', 'social'), (NULL, 'google_login', '0', 'social'), (NULL, 'min_search_price', '1', 'preferences'), (NULL, 'max_search_price', '10000', 'preferences'), (NULL, 'email', 'stockpile@techvill.net', 'general');

INSERT INTO `permissions` (`id`, `name`, `display_name`, `description`, `created_at`, `updated_at`) VALUES (NULL, 'social_logins', 'Social Logins', 'Manage Social Logins', NULL, NULL);

INSERT INTO `seo_metas` (`id`, `url`, `title`, `description`, `keywords`) VALUES (NULL, 'user/favourite', 'Favourite List', NULL, NULL);

CREATE TABLE `favourites` ( `id` int(10) unsigned NOT NULL AUTO_INCREMENT, `property_id` int(11) NOT NULL, `user_id` int(11) NOT NULL, `status` enum('Active','Inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active', `created_at` timestamp NULL DEFAULT NULL, `updated_at` timestamp NULL DEFAULT NULL, PRIMARY KEY (`id`) ) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `banks` (`id` int(10) unsigned NOT NULL AUTO_INCREMENT,`account_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,`iban` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,`swift_code` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,`routing_no` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,`bank_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,`branch_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,`branch_city` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,`branch_address` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,`description` text COLLATE utf8mb4_unicode_ci,`country` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,`logo` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,`status` enum('Active','Inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',`created_at` timestamp NULL DEFAULT NULL,`updated_at` timestamp NULL DEFAULT NULL,PRIMARY KEY (`id`)) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `bookings` ADD `bank_id` INT(10) NULL AFTER `accepted_at`, ADD `note` VARCHAR(191) NULL AFTER `bank_id`,  ADD `attachment` VARCHAR(191) NULL AFTER `accepted_at`;

CREATE TABLE `bank_dates` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,`booking_id` int(11) NOT NULL,`date` date NOT NULL,PRIMARY KEY (`id`)) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE `email_templates` SET `body` = concat(body, '\r\n<br>\r\nPayment Via: {payment_method}') WHERE `email_templates`.`id` = 4;

INSERT INTO `permission_role` (`permission_id`, `role_id`) VALUES ('42', '1');