CREATE DATABASE IF NOT EXISTS lumatek
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE lumatek;

CREATE TABLE companies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    legal_name VARCHAR(200) NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    email_verified_at TIMESTAMP NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_users_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id),

    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id)
        REFERENCES roles(id)
);

CREATE TABLE plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    max_greenhouses INT NOT NULL,
    max_users INT NOT NULL,
    max_zones INT NOT NULL,
    max_sensors INT NOT NULL,
    history_days INT NOT NULL,
    reports_enabled BOOLEAN NOT NULL DEFAULT 0,
    advanced_alerts BOOLEAN NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    status ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_subscriptions_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id),

    CONSTRAINT fk_subscriptions_plan
        FOREIGN KEY (plan_id)
        REFERENCES plans(id)
);

CREATE TABLE greenhouses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    crop VARCHAR(120) NULL,
    shape ENUM('rectangular','other') NOT NULL DEFAULT 'rectangular',
    length_m DECIMAL(10,2) NULL,
    width_m DECIMAL(10,2) NULL,
    area_m2 DECIMAL(12,2) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_greenhouses_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id)
);

CREATE TABLE zones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    greenhouse_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    position_x DECIMAL(5,2) NULL,
    position_y DECIMAL(5,2) NULL,
    width_percent DECIMAL(5,2) NULL,
    height_percent DECIMAL(5,2) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_zones_greenhouse
        FOREIGN KEY (greenhouse_id)
        REFERENCES greenhouses(id)
);

CREATE TABLE devices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    zone_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    device_code VARCHAR(100) NOT NULL UNIQUE,
    device_type VARCHAR(80) NULL,
    connection_type ENUM('wifi','lora','ethernet','other') NULL,
    status ENUM('online','offline','inactive') NOT NULL DEFAULT 'offline',
    last_connection_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_devices_zone
        FOREIGN KEY (zone_id)
        REFERENCES zones(id)
);

CREATE TABLE sensors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    sensor_type ENUM(
        'temperature',
        'air_humidity',
        'soil_moisture',
        'light',
        'co2',
        'other'
    ) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    min_threshold DECIMAL(10,2) NULL,
    max_threshold DECIMAL(10,2) NULL,
    position_x DECIMAL(5,2) NULL,
    position_y DECIMAL(5,2) NULL,
    status ENUM('active','inactive','maintenance') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_sensors_device
        FOREIGN KEY (device_id)
        REFERENCES devices(id)
);

CREATE TABLE readings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sensor_id BIGINT UNSIGNED NOT NULL,
    value DECIMAL(12,4) NOT NULL,
    recorded_at DATETIME NOT NULL,
    source ENUM('simulation','iot') NOT NULL DEFAULT 'simulation',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_readings_sensor
        FOREIGN KEY (sensor_id)
        REFERENCES sensors(id)
);

CREATE TABLE alerts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sensor_id BIGINT UNSIGNED NOT NULL,
    reading_id BIGINT UNSIGNED NULL,
    alert_type ENUM(
        'below_minimum',
        'above_maximum',
        'sensor_offline',
        'other'
    ) NOT NULL,
    severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    message VARCHAR(255) NOT NULL,
    status ENUM('active','acknowledged','resolved') NOT NULL DEFAULT 'active',
    acknowledged_by BIGINT UNSIGNED NULL,
    acknowledged_at DATETIME NULL,
    resolved_at DATETIME NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_alerts_sensor
        FOREIGN KEY (sensor_id)
        REFERENCES sensors(id),

    CONSTRAINT fk_alerts_reading
        FOREIGN KEY (reading_id)
        REFERENCES readings(id),

    CONSTRAINT fk_alerts_acknowledged_by
        FOREIGN KEY (acknowledged_by)
        REFERENCES users(id)
);

CREATE TABLE irrigation_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    greenhouse_id BIGINT UNSIGNED NOT NULL,
    zone_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    event_type ENUM('manual','automatic') NOT NULL DEFAULT 'manual',
    started_at DATETIME NOT NULL,
    ended_at DATETIME NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_irrigation_greenhouse
        FOREIGN KEY (greenhouse_id)
        REFERENCES greenhouses(id),

    CONSTRAINT fk_irrigation_zone
        FOREIGN KEY (zone_id)
        REFERENCES zones(id),

    CONSTRAINT fk_irrigation_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
);

CREATE TABLE crop_cycles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    greenhouse_id BIGINT UNSIGNED NOT NULL,
    crop_name VARCHAR(120) NOT NULL,
    variety VARCHAR(120) NULL,
    sowing_date DATE NULL,
    expected_end_date DATE NULL,
    actual_end_date DATE NULL,
    status ENUM('planned','active','completed','cancelled') NOT NULL DEFAULT 'planned',
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_crop_cycles_greenhouse
        FOREIGN KEY (greenhouse_id)
        REFERENCES greenhouses(id)
);


CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(120) NOT NULL,
    entity_type VARCHAR(100) NULL,
    entity_id BIGINT UNSIGNED NULL,
    description VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NULL,

    CONSTRAINT fk_audit_logs_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id),

    CONSTRAINT fk_audit_logs_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
);

CREATE INDEX idx_users_company
    ON users(company_id);

CREATE INDEX idx_greenhouses_company
    ON greenhouses(company_id);

CREATE INDEX idx_zones_greenhouse
    ON zones(greenhouse_id);

CREATE INDEX idx_devices_zone
    ON devices(zone_id);

CREATE INDEX idx_sensors_device
    ON sensors(device_id);

CREATE INDEX idx_readings_sensor_date
    ON readings(sensor_id, recorded_at);

CREATE INDEX idx_alerts_sensor_status
    ON alerts(sensor_id, status);

CREATE INDEX idx_crop_cycles_greenhouse
    ON crop_cycles(greenhouse_id);

