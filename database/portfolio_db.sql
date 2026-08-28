-- Portfolio database schema for the introductory SQL phase.
-- This script designs the database only; PHP does not connect to it yet.

CREATE DATABASE IF NOT EXISTS portfolio_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE portfolio_db;

CREATE TABLE IF NOT EXISTS projects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    github_url VARCHAR(500) NOT NULL,
    image_path VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS personal_info (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    professional_title VARCHAR(150) NULL,
    email VARCHAR(150) NULL,
    phone_primary VARCHAR(30) NULL,
    phone_secondary VARCHAR(30) NULL,
    location VARCHAR(150) NULL,
    about_me TEXT NULL,
    work_description TEXT NULL,
    linkedin_url VARCHAR(255) NULL,
    github_url VARCHAR(255) NULL,
    instagram_url VARCHAR(255) NULL,
    facebook_url VARCHAR(255) NULL,
    website_url VARCHAR(255) NULL,
    profile_image_path VARCHAR(255) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS skills (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    skill_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- The one existing portfolio project is the only seed record.
INSERT INTO projects (title, category, description, github_url)
VALUES (
    'Machine Learning Project',
    'AI Project',
    'This is My Project to Apply Thinking in Machine Learning (MLR) to filter Percentage matcher Semantic and Lexical Matcher',
    'https://github.com/stoers12/CareerFit_ML_Engine.git'
);

-- Example queries for classroom practice:
-- SELECT * FROM projects;
-- SELECT * FROM messages;
