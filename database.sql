-- RentEase Database Schema
-- MySQL 8.0+

CREATE DATABASE IF NOT EXISTS rentease CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rentease;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('tenant','landlord','agent','admin') NOT NULL DEFAULT 'tenant',
    avatar VARCHAR(255),
    bio TEXT,
    is_active TINYINT(1) DEFAULT 1,
    reset_token VARCHAR(100),
    reset_token_expiry DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Properties table
CREATE TABLE properties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    property_type ENUM('room','apartment','hostel','house','studio') NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    location VARCHAR(255) NOT NULL,
    address TEXT,
    city VARCHAR(100),
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    num_rooms INT DEFAULT 1,
    num_bathrooms INT DEFAULT 1,
    amenities TEXT,
    status ENUM('available','occupied','maintenance','inactive') DEFAULT 'available',
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Property images
CREATE TABLE property_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
);

-- Rentals / Tenancy
CREATE TABLE rentals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    tenant_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    monthly_rent DECIMAL(10,2) NOT NULL,
    deposit DECIMAL(10,2),
    status ENUM('active','expired','terminated','pending') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Messages
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    property_id INT,
    subject VARCHAR(255),
    body TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    parent_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL
);

-- Complaints & Maintenance
CREATE TABLE complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    property_id INT NOT NULL,
    landlord_id INT NOT NULL,
    type ENUM('maintenance','complaint','damage','noise','other') DEFAULT 'complaint',
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    status ENUM('open','in_progress','resolved','closed') DEFAULT 'open',
    resolution_notes TEXT,
    resolved_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    FOREIGN KEY (landlord_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Payments
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rental_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    month_covered VARCHAR(7),
    method ENUM('cash','bank_transfer','mobile_money','cheque') DEFAULT 'cash',
    reference VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rental_id) REFERENCES rentals(id) ON DELETE CASCADE
);

-- Notifications
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50),
    title VARCHAR(200),
    body TEXT,
    link VARCHAR(255),
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Default admin user (password: Admin@123)
INSERT INTO users (name, email, phone, password_hash, role) VALUES
('System Admin', 'admin@rentease.com', '+256700000000', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Sample landlord (password: Test@123)
INSERT INTO users (name, email, phone, password_hash, role) VALUES
('John Mukasa', 'landlord@rentease.com', '+256701234567', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'landlord'),
('Mary Nakato', 'tenant@rentease.com', '+256702345678', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'tenant');

-- Sample properties
INSERT INTO properties (owner_id, title, description, property_type, price, location, address, city, latitude, longitude, num_rooms, num_bathrooms, amenities, status) VALUES
(2, 'Modern 2-Bedroom Apartment', 'Spacious modern apartment with great city views, fully furnished with high-speed WiFi.', 'apartment', 850000, 'Kololo', 'Plot 45, Kololo Hill Drive', 'Kampala', 0.33600, 32.59300, 2, 1, 'WiFi,Parking,Security,Water', 'available'),
(2, 'Self-Contained Room - Ntinda', 'Clean self-contained room in a quiet neighborhood, close to shopping centers.', 'room', 300000, 'Ntinda', 'Ntinda Market Road', 'Kampala', 0.35200, 32.61800, 1, 1, 'Water,Security', 'available'),
(2, 'Studio Apartment - Bukoto', 'Cozy studio apartment ideal for single professionals. Walking distance to amenities.', 'studio', 550000, 'Bukoto', 'Bukoto Street Plot 12', 'Kampala', 0.34100, 32.60200, 1, 1, 'WiFi,Water,Parking', 'occupied'),
(2, '3-Bedroom House - Kira', 'Spacious family house with garden, garage, and 24-hour security.', 'house', 1500000, 'Kira', 'Kira Town, Off Kira Road', 'Kira', 0.38900, 32.64500, 3, 2, 'Parking,Garden,Security,Generator', 'available'),
(2, 'Student Hostel Room - Wandegeya', 'Affordable student accommodation near Makerere University.', 'hostel', 150000, 'Wandegeya', 'Wandegeya, Near Makerere', 'Kampala', 0.33900, 32.57000, 1, 0, 'WiFi,Security', 'available');
