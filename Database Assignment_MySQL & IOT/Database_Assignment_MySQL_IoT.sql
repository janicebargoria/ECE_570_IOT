
-- =====================================================
-- ECE 570 - INTERNET OF THINGS
-- DATABASE ASSIGNMENT 6
-- Student: Janice Bargoria
-- Database: JaniceBargoria
-- Platform: Hostinger / MariaDB / phpMyAdmin
-- =====================================================

-- This script is intended for a new, empty database.
-- Select your database in phpMyAdmin before running.

-- =====================================================
-- PART 1: DATABASE COMMANDS
-- =====================================================

-- Display available databases
SHOW DATABASES;

-- Display available tables
SHOW TABLES;


-- =====================================================
-- PART 2: CREATE BOOK TABLE
-- =====================================================

CREATE TABLE Book (
    id INT PRIMARY KEY,
    name VARCHAR(100)
);

-- Insert five books
INSERT INTO Book (id, name)
VALUES
(1, 'MariaDB Book1a'),
(2, 'SQLDB Book'),
(3, 'MariaDB Book3'),
(4, 'MariaDB Book4'),
(5, 'MariaDB Book5');

-- Display Book table
SELECT * FROM Book;


-- =====================================================
-- PART 3: CREATE PRICE TABLE
-- =====================================================

CREATE TABLE Price (
    id INT PRIMARY KEY,
    price INT
);

-- Insert five book prices
INSERT INTO Price (id, price)
VALUES
(1, 250),
(2, 252),
(3, 223),
(4, 194),
(5, 305);

-- Display Price table
SELECT * FROM Price;


-- =====================================================
-- PART 4: COMBINE BOOK AND PRICE TABLES
-- =====================================================

-- INNER JOIN combines matching book IDs and prices
SELECT
    Book.name,
    Price.price
FROM Book
INNER JOIN Price
ON Book.id = Price.id
ORDER BY Book.id;

-- LEFT JOIN displays all books,
-- including books without matching prices
SELECT
    Book.id,
    Book.name,
    Price.price
FROM Book
LEFT JOIN Price
ON Book.id = Price.id
ORDER BY Book.id;


-- =====================================================
-- PART 5: CREATE SENSOR REGISTER TABLE
-- =====================================================

-- Stores registered sensor information
CREATE TABLE sensor_register (
    node_name VARCHAR(10) PRIMARY KEY,
    manufacturer VARCHAR(10),
    longitude DECIMAL(15,8),
    latitude DECIMAL(15,8)
);

-- Register five different sensors
-- Coordinates are example locations
INSERT INTO sensor_register
(node_name, manufacturer, longitude, latitude)
VALUES
('node_1', 'Bosch', -122.7000, 38.3400),
('node_2', 'Siemens', -122.7100, 38.3500),
('node_3', 'Honeywell', -122.7200, 38.3600),
('node_4', 'Sensirion', -122.7300, 38.3700),
('node_5', 'TexasInst', -122.7400, 38.3800);

-- Display registered sensors
SELECT * FROM sensor_register;


-- =====================================================
-- PART 6: CREATE SENSOR DATA TABLE
-- =====================================================

-- AUTO_INCREMENT automatically creates unique IDs
-- FOREIGN KEY only allows registered sensors

CREATE TABLE sensor_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    node_name VARCHAR(10) NOT NULL,
    time_received DATETIME NOT NULL,
    temperature DECIMAL(6,2),
    humidity DECIMAL(6,2),

    FOREIGN KEY (node_name)
        REFERENCES sensor_register(node_name)
);

-- Add temperature and humidity restrictions
ALTER TABLE sensor_data
ADD CONSTRAINT chk_temperature
CHECK (temperature BETWEEN -10 AND 100),
ADD CONSTRAINT chk_humidity
CHECK (humidity BETWEEN 0 AND 100);


-- =====================================================
-- PART 7: INSERT 20 SENSOR READINGS
-- =====================================================

-- Start date: October 1, 2022 at 11:00 AM
-- Readings are recorded every 30 minutes
-- Five sensors, four readings per sensor

INSERT INTO sensor_data
(node_name, time_received, temperature, humidity)
VALUES

-- Reading 1: 11:00 AM
('node_1', '2022-10-01 11:00:00', 24.5, 45),
('node_2', '2022-10-01 11:00:00', 25.0, 48),
('node_3', '2022-10-01 11:00:00', 23.8, 50),
('node_4', '2022-10-01 11:00:00', 26.1, 43),
('node_5', '2022-10-01 11:00:00', 22.7, 52),

-- Reading 2: 11:30 AM
('node_1', '2022-10-01 11:30:00', 24.8, 46),
('node_2', '2022-10-01 11:30:00', 25.3, 47),
('node_3', '2022-10-01 11:30:00', 24.0, 49),
('node_4', '2022-10-01 11:30:00', 26.3, 44),
('node_5', '2022-10-01 11:30:00', 23.0, 51),

-- Reading 3: 12:00 PM
('node_1', '2022-10-01 12:00:00', 25.1, 44),
('node_2', '2022-10-01 12:00:00', 25.6, 46),
('node_3', '2022-10-01 12:00:00', 24.3, 48),
('node_4', '2022-10-01 12:00:00', 26.5, 42),
('node_5', '2022-10-01 12:00:00', 23.2, 50),

-- Reading 4: 12:30 PM
('node_1', '2022-10-01 12:30:00', 25.4, 43),
('node_2', '2022-10-01 12:30:00', 25.9, 45),
('node_3', '2022-10-01 12:30:00', 24.6, 47),
('node_4', '2022-10-01 12:30:00', 26.7, 41),
('node_5', '2022-10-01 12:30:00', 23.5, 49);

-- Display all readings
SELECT *
FROM sensor_data
ORDER BY time_received, node_name;


-- =====================================================
-- PART 8: CREATE SENSOR_COMBINED VIEW
-- =====================================================

-- Combine sensor readings with registration details
CREATE VIEW sensor_combined AS
SELECT
    sensor_data.node_name,
    sensor_data.time_received,
    sensor_data.temperature,
    sensor_data.humidity,
    sensor_register.manufacturer,
    sensor_register.longitude,
    sensor_register.latitude
FROM sensor_data
INNER JOIN sensor_register
ON sensor_data.node_name =
   sensor_register.node_name;

-- Display the combined VIEW
SELECT *
FROM sensor_combined
ORDER BY time_received, node_name;


-- =====================================================
-- PART 9: VERIFY SENSOR RECORDS
-- =====================================================

-- Count total readings
SELECT COUNT(*) AS total_readings
FROM sensor_data;

-- Count readings per sensor
SELECT node_name, COUNT(*) AS total_readings
FROM sensor_data
GROUP BY node_name;

-- Display database table definitions
SHOW CREATE TABLE sensor_data;
SHOW CREATE TABLE sensor_register;

-- Display the last five inserted records
SELECT *
FROM sensor_data
ORDER BY id DESC
LIMIT 5;


-- =====================================================
-- PART 10: FOREIGN KEY VALIDATION TEST
-- =====================================================

-- Expected result: ERROR #1452
-- node_6 is not registered
-- Uncomment to demonstrate rejection

-- INSERT INTO sensor_data
-- (node_name, time_received, temperature, humidity)
-- VALUES
-- ('node_6', '2022-10-01 13:00:00', 25.5, 50);


-- =====================================================
-- PART 11: TEMPERATURE CONSTRAINT TESTS
-- =====================================================

-- Test invalid temperature: -11 Celsius
-- Expected result: CHECK constraint violation
-- Uncomment to run this test

-- INSERT INTO sensor_data
-- (node_name, time_received, temperature, humidity)
-- VALUES
-- ('node_2', '2022-10-01 13:30:00', -11, 30);


-- Test valid temperature: -8 Celsius
-- AUTO_INCREMENT generates the ID automatically
-- Uncomment to insert this additional test reading

-- INSERT INTO sensor_data
-- (node_name, time_received, temperature, humidity)
-- VALUES
-- ('node_1', '2026-10-01 11:00:00', -8, 30);


-- =====================================================
-- PART 12: OPTIONAL AUTOMATIC TIMESTAMP
-- =====================================================

-- Option 1: Insert the current database server time
-- Uncomment only if additional testing is needed

-- INSERT INTO sensor_data
-- (node_name, time_received, temperature, humidity)
-- VALUES
-- ('node_1', NOW(), -8, 30);


-- Option 2: Automatically assign timestamps by default
-- This changes the table definition
-- Uncomment only if required

-- ALTER TABLE sensor_data
-- MODIFY time_received DATETIME NOT NULL
-- DEFAULT CURRENT_TIMESTAMP;


-- After enabling the default timestamp,
-- readings can be inserted without time_received

-- INSERT INTO sensor_data
-- (node_name, temperature, humidity)
-- VALUES
-- ('node_1', -8, 30);


-- =====================================================
-- END OF DATABASE ASSIGNMENT
-- =====================================================
