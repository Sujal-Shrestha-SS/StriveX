-- StriveX Tournament Management System


CREATE DATABASE IF NOT EXISTS strivex;
USE strivex;

-- Users table (replaces single admin)
CREATE TABLE users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Players table (scoped per user)
CREATE TABLE players (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    name       VARCHAR(100) NOT NULL,
    club       VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tournaments table (scoped per user)
CREATE TABLE tournaments (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    name       VARCHAR(150) NOT NULL,
    status     ENUM('group','knockout','finished') DEFAULT 'group',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tournament players (which players are in which tournament)
CREATE TABLE tournament_players (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    player_id     INT NOT NULL
);

-- Fixtures table
-- round_type values: group | semi1 | semi2 | qualifier | final
CREATE TABLE fixtures (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    home_player   INT NOT NULL,
    away_player   INT NOT NULL DEFAULT 0,   -- 0 = TBD
    home_score    INT DEFAULT NULL,
    away_score    INT DEFAULT NULL,
    round_type    ENUM('group','semi1','semi2','qualifier','final') DEFAULT 'group',
    played        TINYINT(1) DEFAULT 0
);

-- Winners table
CREATE TABLE winners (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    player_id     INT NOT NULL,
    won_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

