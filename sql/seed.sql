-- RePlate Initial Seed Data

-- 1. Insert Categories
INSERT INTO categories (category_id, name, description) VALUES
(1, 'Prepared Meals', 'Freshly cooked meals and catering surplus'),
(2, 'Produce & Fruits', 'Fresh vegetables, fruits, and organic produce'),
(3, 'Bakery & Grains', 'Bread, pastries, flour, and grain products'),
(4, 'Dairy & Groceries', 'Milk, cheese, eggs, and packaged goods')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO food_categories (category_id, name, description) VALUES
(1, 'Prepared Meals', 'Freshly cooked meals and catering surplus'),
(2, 'Produce & Fruits', 'Fresh vegetables, fruits, and organic produce'),
(3, 'Bakery & Grains', 'Bread, pastries, flour, and grain products'),
(4, 'Dairy & Groceries', 'Milk, cheese, eggs, and packaged goods')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- 2. Insert Test Users (Password for all is: password123 -> $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi)
INSERT INTO users (user_id, name, email, normalized_email, password_hash, role, status, verification_status) VALUES
(1, 'System Admin', 'admin@replate.com', 'admin@replate.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Active', 'Verified'),
(2, 'Green Valley Supermarket', 'donor@replate.com', 'donor@replate.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'donor', 'Active', 'Verified'),
(3, 'Hope Community Kitchen', 'recipient@replate.com', 'recipient@replate.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'recipient', 'Active', 'Verified')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- 3. Insert Sample Food Donations / Surplus Listings
INSERT INTO food_donations (donation_id, donor_id, category_id, food_name, quantity, unit, expiry_date, pickup_location, status) VALUES
(1, 2, 2, 'Fresh Organic Apples', 25, 'kg', DATE_ADD(NOW(), INTERVAL 3 DAY), '123 Market Street, Downtown', 'Available'),
(2, 2, 3, 'Assorted Artisan Breads', 15, 'loaves', DATE_ADD(NOW(), INTERVAL 1 DAY), '45 Bakery Lane', 'Available'),
(3, 2, 1, 'Prepared Vegetable Casseroles', 30, 'servings', DATE_ADD(NOW(), INTERVAL 2 DAY), '789 Commercial Ave', 'Available')
ON DUPLICATE KEY UPDATE food_name=VALUES(food_name);

-- 4. Insert Sample Claims / Requests
INSERT INTO donation_requests (request_id, donation_id, recipient_id, quantity_requested, status) VALUES
(1, 1, 3, 10, 'Pending'),
(2, 2, 3, 5, 'Approved')
ON DUPLICATE KEY UPDATE status=VALUES(status);