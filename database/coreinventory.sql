-- CoreInventory database. Import in phpMyAdmin (Import tab).
CREATE DATABASE IF NOT EXISTS coreinventory CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE coreinventory;

DROP TABLE IF EXISTS stock_transactions, products, suppliers, categories, users;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  phone VARCHAR(20),
  password VARCHAR(255) NOT NULL,               -- hashed with password_hash()
  role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description TEXT,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  contact_person VARCHAR(100),
  phone VARCHAR(20),
  email VARCHAR(120),
  address TEXT,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  sku VARCHAR(50) NOT NULL UNIQUE,
  category_id INT NOT NULL,
  supplier_id INT NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  quantity INT NOT NULL DEFAULT 0,
  minimum_stock INT NOT NULL DEFAULT 5,
  description TEXT,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id),
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  CHECK (quantity >= 0)
) ENGINE=InnoDB;

CREATE TABLE stock_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  user_id INT NOT NULL,
  type ENUM('STOCK_IN','STOCK_OUT') NOT NULL,
  quantity INT NOT NULL,
  previous_quantity INT NOT NULL,
  new_quantity INT NOT NULL,
  reason VARCHAR(100),
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Sample data. Login: admin@coreinventory.com / admin123, staff1@coreinventory.com / staff123
INSERT INTO users (name,email,phone,password,role) VALUES
('Admin','admin@coreinventory.com','9000000001','$2b$12$FUK2.uwAHin8RkJbdLQOPOvvGKHbG6UYQg649S2xinDi8KseHBNxi','admin'),
('Staff 1','staff1@coreinventory.com','9000000002','$2b$12$pwciohKKpRVdFzaqH4XcHOM6tCFZ/aPpIkwVFxnfW1KLfKXhTW9pe','staff'),
('Staff 2','staff2@coreinventory.com','9000000003','$2b$12$pwciohKKpRVdFzaqH4XcHOM6tCFZ/aPpIkwVFxnfW1KLfKXhTW9pe','staff');

INSERT INTO categories (name,description) VALUES
('Electronics','General electronic items'),
('Computer Accessories','Mice, keyboards, storage'),
('Networking','Routers, switches, cables'),
('Stationery','Office stationery');

INSERT INTO suppliers (name,contact_person,phone,email,address) VALUES
('ABC Computers','Ramesh Patel','9811111111','sales@abccomputers.com','12 Ring Road, Ahmedabad'),
('XYZ Technologies','Sneha Shah','9822222222','info@xyztech.com','45 CG Road, Ahmedabad'),
('Office World','Kiran Mehta','9833333333','hello@officeworld.com','7 Navrangpura, Ahmedabad');

INSERT INTO products (name,sku,category_id,supplier_id,price,quantity,minimum_stock,description) VALUES
('Wireless Mouse','WM-001',2,1,599,50,10,'2.4GHz wireless optical mouse'),
('Mechanical Keyboard','KB-002',2,1,2499,8,10,'Blue switch keyboard'),
('USB Flash Drive 64GB','USB-003',1,2,450,120,20,'USB 3.0 pen drive'),
('HDMI Cable 2m','HDMI-004',1,2,299,0,15,'High speed HDMI cable'),
('WiFi Router AC1200','RTR-005',3,2,2999,25,5,'Dual band router'),
('Ethernet Cable 10m','ETH-006',3,1,349,40,10,'Cat6 patch cable'),
('Network Switch 8-Port','SW-007',3,2,1899,4,5,'Gigabit unmanaged switch'),
('A4 Paper Ream','PAP-008',4,3,280,200,50,'500 sheets, 75 GSM'),
('Ball Pen Box (50)','PEN-009',4,3,250,35,10,'Blue ball pens'),
('Heavy Duty Stapler','STP-010',4,3,180,5,5,'Metal stapler');

-- Sample transactions (consistent with current quantities)
INSERT INTO stock_transactions (product_id,user_id,type,quantity,previous_quantity,new_quantity,reason,notes,created_at) VALUES
(1,2,'STOCK_IN',60,0,60,'Purchase','Supplier: ABC Computers','2026-09-20 10:00:00'),
(1,3,'STOCK_OUT',10,60,50,'Sold','Counter sale','2026-09-25 12:30:00'),
(2,2,'STOCK_IN',20,0,20,'Purchase','Supplier: ABC Computers','2026-09-21 11:00:00'),
(2,3,'STOCK_OUT',12,20,8,'Sold','Bulk order','2026-09-28 15:45:00'),
(4,2,'STOCK_IN',30,0,30,'Purchase','Supplier: XYZ Technologies','2026-09-22 09:30:00'),
(4,2,'STOCK_OUT',30,30,0,'Issued','Lab setup','2026-10-01 14:10:00'),
(7,3,'STOCK_IN',10,0,10,'Purchase','Supplier: XYZ Technologies','2026-09-23 10:15:00'),
(7,3,'STOCK_OUT',6,10,4,'Issued','Network lab','2026-10-02 16:00:00');
