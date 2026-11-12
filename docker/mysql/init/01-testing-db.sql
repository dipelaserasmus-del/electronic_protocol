-- Separate database for the automated test suite (dev stack only).
CREATE DATABASE IF NOT EXISTS `testing` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON `testing`.* TO 'laravel'@'%';
FLUSH PRIVILEGES;
