SET FOREIGN_KEY_CHECKS = 0;
CREATE TABLE `reference_metadata` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `resource_id` INT NOT NULL,
    `value_id` INT NOT NULL,
    `field` VARCHAR(190) NOT NULL,
    `lang` VARCHAR(255) DEFAULT '' NOT NULL,
    `is_public` TINYINT(1) DEFAULT '1' NOT NULL,
    `text` LONGTEXT NOT NULL,
    INDEX IDX_971E6F6B89329D25 (`resource_id`),
    INDEX IDX_971E6F6BF920BBA2 (`value_id`),
    INDEX idx_field (`field`),
    INDEX idx_lang (`lang`),
    INDEX idx_resource_field (`resource_id`, `field`),
    INDEX idx_text (`text`(190)),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
ALTER TABLE `reference_metadata` ADD CONSTRAINT FK_971E6F6B89329D25 FOREIGN KEY (`resource_id`) REFERENCES `resource` (`id`) ON DELETE CASCADE;
ALTER TABLE `reference_metadata` ADD CONSTRAINT FK_971E6F6BF920BBA2 FOREIGN KEY (`value_id`) REFERENCES `value` (`id`) ON DELETE CASCADE;
SET FOREIGN_KEY_CHECKS = 1;
