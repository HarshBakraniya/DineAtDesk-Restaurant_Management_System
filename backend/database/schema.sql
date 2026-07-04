-- ============================================================
-- Restaurant Management System - Database Schema
-- Engine: MySQL / MariaDB
-- ============================================================


-- ---------------- users ----------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','manager','waiter','kitchen') NOT NULL DEFAULT 'waiter',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ---------------- categories ----------------
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    sort_order INT DEFAULT 0
);

-- ---------------- menu_items ----------------
CREATE TABLE menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    price DECIMAL(8,2) NOT NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    image_path VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- ---------------- restaurant_tables ----------------
CREATE TABLE restaurant_tables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_number VARCHAR(10) NOT NULL,
    capacity INT NOT NULL DEFAULT 2,
    status ENUM('free','occupied','reserved') NOT NULL DEFAULT 'free'
);

-- ---------------- orders ----------------
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_id INT NOT NULL,
    waiter_id INT NOT NULL,
    status ENUM('pending','preparing','served','paid','cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (table_id) REFERENCES restaurant_tables(id),
    FOREIGN KEY (waiter_id) REFERENCES users(id)
);

-- ---------------- order_items ----------------
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    menu_item_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price_at_order DECIMAL(8,2) NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
);

-- ---------------- bills ----------------
CREATE TABLE bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    subtotal DECIMAL(8,2) NOT NULL,
    tax DECIMAL(8,2) NOT NULL DEFAULT 0,
    discount DECIMAL(8,2) NOT NULL DEFAULT 0,
    total DECIMAL(8,2) NOT NULL,
    payment_method ENUM('cash','card','upi') DEFAULT NULL,
    is_paid TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id)
);

-- ---------------- inventory (basic, optional use) ----------------
CREATE TABLE inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(150) NOT NULL,
    quantity DECIMAL(8,2) NOT NULL DEFAULT 0,
    unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
    low_stock_threshold DECIMAL(8,2) NOT NULL DEFAULT 0
);

-- ============================================================
-- Seed data
-- ============================================================

-- Default users (password for all = "password123")
-- Hash is a real bcrypt hash — verified working with PHP's password_verify()
INSERT INTO users (name, email, password, role) VALUES
('Admin User', 'admin@restaurant.test', '$2b$10$XKw2XmKE3OipLc0/AJrcquPw0cULuj021GTfTVPiJ727Mxsnww.Aq', 'admin'),
('Ravi Manager', 'manager@restaurant.test', '$2b$10$XKw2XmKE3OipLc0/AJrcquPw0cULuj021GTfTVPiJ727Mxsnww.Aq', 'manager'),
('Asha Waiter', 'waiter@restaurant.test', '$2b$10$XKw2XmKE3OipLc0/AJrcquPw0cULuj021GTfTVPiJ727Mxsnww.Aq', 'waiter'),
('Kitchen Staff', 'kitchen@restaurant.test', '$2b$10$XKw2XmKE3OipLc0/AJrcquPw0cULuj021GTfTVPiJ727Mxsnww.Aq', 'kitchen');

INSERT INTO categories (name, sort_order) VALUES
('Starters', 1), ('Main Course', 2), ('Breads', 3), ('Beverages', 4), ('Desserts', 5);

INSERT INTO menu_items (category_id, name, price, is_available) VALUES
(1, 'Paneer Tikka', 220.00, 1),
(1, 'Veg Spring Rolls', 180.00, 1),
(1, 'Chicken Seekh Kebab', 260.00, 1),
(2, 'Butter Chicken', 320.00, 1),
(2, 'Dal Makhani', 210.00, 1),
(2, 'Veg Biryani', 240.00, 1),
(2, 'Paneer Butter Masala', 260.00, 1),
(3, 'Butter Naan', 45.00, 1),
(3, 'Tandoori Roti', 30.00, 1),
(4, 'Masala Chai', 40.00, 1),
(4, 'Fresh Lime Soda', 60.00, 1),
(5, 'Gulab Jamun', 90.00, 1);

INSERT INTO restaurant_tables (table_number, capacity, status) VALUES
('T1', 2, 'free'), ('T2', 2, 'free'), ('T3', 4, 'free'),
('T4', 4, 'free'), ('T5', 6, 'free'), ('T6', 4, 'free');
