-- ============================================================
-- Elevionsupply E-Commerce Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS elevionsupply CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE elevionsupply;

-- Users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    role ENUM('customer', 'admin') DEFAULT 'customer',
    is_active TINYINT(1) DEFAULT 1,
    notification_prefs JSON DEFAULT NULL COMMENT 'Keys: order_updates, shipping, promotions, wholesale',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- For existing installs: ALTER TABLE users ADD COLUMN notification_prefs JSON DEFAULT NULL;
-- For existing installs: ALTER TABLE addresses MODIFY COLUMN user_id INT DEFAULT NULL;
-- For existing installs: ALTER TABLE addresses ADD COLUMN email VARCHAR(255) DEFAULT NULL;
-- For existing installs: ALTER TABLE shipments ADD COLUMN tracking_url VARCHAR(512) DEFAULT NULL;

-- Password Resets
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email)
);

-- Sessions (defined but not used — site uses PHP native sessions, not DB sessions)
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Categories
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    parent_id INT DEFAULT NULL, -- reserved for nested categories (not queried in code)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    sku VARCHAR(100) UNIQUE NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    sale_price DECIMAL(10,2),
    stock_quantity INT DEFAULT 0,
    category_id INT,
    images JSON,
    specifications JSON,
    rating DECIMAL(3,2) DEFAULT 0,
    review_count INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    is_featured TINYINT(1) DEFAULT 0,
    badge VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_products_active_featured (is_active, is_featured),
    INDEX idx_products_category_active (category_id, is_active)
);

-- Addresses
CREATE TABLE IF NOT EXISTS addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    type ENUM('shipping','billing','both') DEFAULT 'shipping',
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    street_address VARCHAR(255) NOT NULL,
    apt_suite VARCHAR(100),
    city VARCHAR(100) NOT NULL,
    state_province VARCHAR(100),
    postal_code VARCHAR(20) NOT NULL,
    country VARCHAR(100) DEFAULT 'United States',
    phone VARCHAR(20),
    email VARCHAR(255),
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_addresses_email (email)
);

-- Carts
CREATE TABLE IF NOT EXISTS carts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    session_token VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Cart Items
CREATE TABLE IF NOT EXISTS cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cart_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Orders
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    status ENUM('pending','processing','shipped','delivered','cancelled','refunded') DEFAULT 'pending',
    subtotal DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    shipping_cost DECIMAL(10,2) DEFAULT 0,
    discount_amount DECIMAL(10,2) DEFAULT 0, -- reserved for future coupon feature (never set in code)
    total_amount DECIMAL(10,2) NOT NULL,
    shipping_address_id INT,
    billing_address_id INT, -- reserved (billing = shipping currently; never populated)
    payment_method VARCHAR(50),
    payment_status ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (shipping_address_id) REFERENCES addresses(id) ON DELETE SET NULL,
    FOREIGN KEY (billing_address_id) REFERENCES addresses(id) ON DELETE SET NULL,
    INDEX idx_orders_status (status),
    INDEX idx_orders_created_at (created_at),
    INDEX idx_orders_user_status (user_id, status)
);

-- Order Items
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT,
    product_name VARCHAR(255) NOT NULL,
    product_sku VARCHAR(100),
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- Payments
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    method VARCHAR(50) NOT NULL,
    status ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
    transaction_id VARCHAR(255),
    card_last_four VARCHAR(4),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- Shipments
CREATE TABLE IF NOT EXISTS shipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    carrier VARCHAR(100), -- set manually via DB or future shipment update UI
    tracking_number VARCHAR(255),
    tracking_url VARCHAR(512),
    status ENUM('preparing','in_transit','out_for_delivery','delivered','failed') DEFAULT 'preparing',
    estimated_delivery DATE, -- set manually via DB or future shipment update UI
    shipped_at DATETIME,   -- set manually via DB or future shipment update UI
    delivered_at DATETIME, -- set manually via DB or future shipment update UI
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- ============================================================
-- Indexes
-- ============================================================

-- orders: status filtered on every list/filter page; created_at used in ORDER BY
ALTER TABLE orders ADD INDEX IF NOT EXISTS idx_orders_status (status);
ALTER TABLE orders ADD INDEX IF NOT EXISTS idx_orders_created_at (created_at);
ALTER TABLE orders ADD INDEX IF NOT EXISTS idx_orders_user_status (user_id, status);

-- products: is_active + is_featured filtered on catalog, admin, homepage
ALTER TABLE products ADD INDEX IF NOT EXISTS idx_products_active_featured (is_active, is_featured);
ALTER TABLE products ADD INDEX IF NOT EXISTS idx_products_category_active (category_id, is_active);

-- addresses: email used in track.php WHERE for guest order lookup
ALTER TABLE addresses ADD INDEX IF NOT EXISTS idx_addresses_email (email);

-- ============================================================
-- Seed Data
-- ============================================================

INSERT INTO categories (name, slug, description) VALUES
('Smartphones', 'smartphones', 'Latest flagship smartphones'),
('Laptops', 'laptops', 'Premium laptops and notebooks'),
('Earbuds & Audio', 'earbuds-audio', 'Headphones, earbuds, speakers'),
('Accessories', 'accessories', 'Phone cases and accessories'),
('Computer Parts', 'computer-parts', 'GPUs, CPUs, storage'),
('Wearables', 'wearables', 'Smartwatches and fitness bands');

INSERT INTO products (name, slug, sku, description, price, sale_price, stock_quantity, category_id, rating, review_count, is_featured, badge) VALUES
('Samsung Galaxy S24 Ultra 256GB', 'samsung-galaxy-s24-ultra', 'SAM-S24U-256', 'The ultimate Android flagship with 200MP camera, S Pen, and AI features.', 899.00, NULL, 45, 1, 4.8, 284, 1, 'Hot'),
('Bose S1 Pro+ Bluetooth Speaker', 'bose-s1-pro-plus', 'BOSE-S1PRO', 'Premium portable PA system with crystal-clear sound and rechargeable battery.', 549.00, 499.00, 20, 3, 4.9, 142, 1, 'Sale'),
('Sony WH-1000XM5 Noise Cancelling Headphones', 'sony-wh1000xm5', 'SONY-WH1000XM5', 'Industry-leading noise cancellation with up to 30-hour battery life.', 279.00, NULL, 60, 3, 4.9, 519, 1, NULL),
('MacBook Pro M3 14" 512GB', 'macbook-pro-m3-14', 'APPLE-MBP-M3-14', 'Apple M3 chip with 8-core CPU, stunning Liquid Retina XDR display.', 1749.00, NULL, 18, 2, 4.9, 87, 1, 'Hot'),
('iPhone 16 Pro 128GB Natural Titanium', 'iphone-16-pro-128', 'APPLE-IP16P-128', 'A18 Pro chip, 48MP camera system, titanium design with Action button.', 999.00, NULL, 32, 1, 4.7, 203, 1, 'New'),
('NVIDIA RTX 4070 Super 12GB GDDR6X', 'nvidia-rtx-4070-super', 'NVDA-RTX4070S', 'Next-gen GPU with DLSS 3 and ray tracing for ultra-high performance gaming.', 599.00, NULL, 15, 5, 4.8, 76, 0, NULL),
('Anker 240W USB-C Charging Hub 6-Port', 'anker-240w-hub', 'ANKR-240W-HUB6', 'Power 6 devices simultaneously with Anker PowerIQ 4.0 technology.', 79.00, 59.00, 120, 4, 4.6, 338, 0, 'Sale'),
('Apple Watch Series 10 45mm GPS+Cell', 'apple-watch-s10-45', 'APPLE-AW10-45', 'Largest, thinnest Apple Watch yet. Advanced health monitoring and cellular.', 449.00, NULL, 28, 6, 4.8, 194, 1, 'Hot'),
('Google Pixel 9 Pro 256GB', 'google-pixel-9-pro', 'GOOG-P9P-256', 'AI-powered camera, real-time translation, and 7 years of OS updates.', 799.00, NULL, 35, 1, 4.7, 156, 0, 'New'),
('Dell XPS 15 Intel Core Ultra 9', 'dell-xps-15-ultra9', 'DELL-XPS15-U9', '15.6" OLED display, 32GB RAM, 1TB SSD. The ultimate Windows laptop.', 1599.00, 1399.00, 12, 2, 4.6, 98, 0, 'Sale'),
('AirPods Pro 3rd Generation', 'airpods-pro-3rd-gen', 'APPLE-APP3', 'Active Noise Cancellation, Spatial Audio, and all-day battery life.', 249.00, NULL, 75, 3, 4.8, 621, 0, 'New'),
('Samsung Galaxy Tab S9 Ultra', 'samsung-tab-s9-ultra', 'SAM-TABS9U', '14.6" Dynamic AMOLED 2X display with S Pen. Android powerhouse.', 1099.00, NULL, 22, 1, 4.7, 134, 0, NULL);

-- Demo admin user (password: Admin123!)
INSERT INTO users (email, password_hash, first_name, last_name, role) VALUES
('admin@elevionsupply.com', '$2b$10$lSnnzj8Egqr3VuMTudXJ4uHQfyofXi2R0bIVVfPf32fycJCxAliEO', 'Admin', 'User', 'admin');
