CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS instruments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  symbol VARCHAR(20) UNIQUE NOT NULL,
  description VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS user_instrument_config (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  instrument_id INT NOT NULL,
  target_price DECIMAL(12,6) NULL,
  quantity DECIMAL(12,4) NULL,
  side ENUM('buy','sell') NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_instr (user_id, instrument_id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (instrument_id) REFERENCES instruments(id)
);

-- Usuarios en texto plano para facilitar pruebas locales
INSERT INTO users (username, password_hash) VALUES
('admin',  'admin123'),
('trader', 'trader123')
ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash);

INSERT INTO instruments (symbol, description) VALUES
('USD','US Dollar'),
('EUR','Euro'),
('JPY','Japanese Yen'),
('GBP','British Pound vs US Dollar')
ON DUPLICATE KEY UPDATE description=VALUES(description);
