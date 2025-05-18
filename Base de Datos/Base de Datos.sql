CREATE SCHEMA IF NOT EXISTS `prototipo`;
USE `prototipo`;

CREATE TABLE `Administrator` (
    `id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NULL,
    `email` VARCHAR(255) NULL,
    `password` VARCHAR(255) NULL
);

CREATE TABLE `Customer` (
    `id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NULL,
    `email` VARCHAR(255) NULL,
    `password` VARCHAR(255) NULL,
    `phone` VARCHAR(255) NULL,
    `address` VARCHAR(255) NULL
);

CREATE TABLE `Product` (
    `id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NULL,
    `description` VARCHAR(255) NULL,
    `price` DECIMAL(8, 2) NULL,
    `stock` INT NULL
);

CREATE TABLE `Sale` (
    `id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `customer_id` BIGINT NOT NULL,
    `administrator_id` BIGINT NOT NULL,
    `date` DATE NULL,
    `total` DECIMAL(8, 2) NULL,
    CONSTRAINT `sale_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `Customer` (`id`),
    CONSTRAINT `sale_administrator_id_foreign` FOREIGN KEY (`administrator_id`) REFERENCES `Administrator` (`id`)
);

CREATE TABLE `SaleDetail` (
    `id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `sale_id` BIGINT NOT NULL,
    `product_id` BIGINT NOT NULL,
    `quantity` INT NOT NULL,
    `subtotal` DECIMAL(8, 2) NOT NULL,
    CONSTRAINT `saledetail_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `Sale` (`id`),
    CONSTRAINT `saledetail_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `Product` (`id`)
);

CREATE TABLE `Contact` (
    `id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);