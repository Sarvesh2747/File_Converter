-- Database Schema for File Converter Web App

CREATE DATABASE IF NOT EXISTS file_converter;
USE file_converter;

CREATE TABLE IF NOT EXISTS conversions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    original_filename VARCHAR(255) NOT NULL,
    converted_filename VARCHAR(255),
    original_path VARCHAR(500),
    converted_path VARCHAR(500),
    format_from VARCHAR(10) NOT NULL,
    format_to VARCHAR(10),
    original_size INT,
    converted_size INT,
    upload_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    user_ip VARCHAR(45),
    session_id VARCHAR(100),
    INDEX (status),
    INDEX (upload_time),
    INDEX (session_id)
);
