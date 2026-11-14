-- LIRANDZU 001: expansão do modelo para os três portais e intermediação centralizada.
-- Executar uma única vez sobre a base criada por database/schema.sql.
-- Compatível com MySQL 8 / MariaDB recente.

ALTER TABLE users
  MODIFY role ENUM('admin','provider','customer') NOT NULL DEFAULT 'customer',
  ADD COLUMN email_verified_at TIMESTAMP NULL AFTER status,
  ADD COLUMN last_login_at TIMESTAMP NULL AFTER email_verified_at,
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE providers
  ADD COLUMN contact_email VARCHAR(190) NULL AFTER description,
  ADD COLUMN contact_phone VARCHAR(30) NULL AFTER contact_email,
  ADD COLUMN whatsapp_phone VARCHAR(30) NULL AFTER contact_phone,
  ADD COLUMN province VARCHAR(100) NULL AFTER city,
  ADD COLUMN district VARCHAR(100) NULL AFTER province,
  ADD COLUMN address VARCHAR(255) NULL AFTER district,
  ADD COLUMN website_url VARCHAR(500) NULL AFTER cover_image,
  ADD COLUMN instagram_url VARCHAR(500) NULL AFTER website_url,
  ADD COLUMN facebook_url VARCHAR(500) NULL AFTER instagram_url,
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE provider_applications
  ADD COLUMN province VARCHAR(100) NULL AFTER city,
  ADD COLUMN district VARCHAR(100) NULL AFTER province,
  ADD COLUMN website_url VARCHAR(500) NULL AFTER description,
  ADD COLUMN social_links JSON NULL AFTER website_url,
  ADD COLUMN reviewed_by BIGINT UNSIGNED NULL AFTER reviewed_at,
  ADD CONSTRAINT fk_applications_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE service_requests
  ADD COLUMN customer_user_id BIGINT UNSIGNED NULL AFTER provider_id,
  ADD COLUMN assigned_admin_id BIGINT UNSIGNED NULL AFTER customer_user_id,
  ADD COLUMN event_province VARCHAR(100) NULL AFTER event_city,
  ADD COLUMN event_district VARCHAR(100) NULL AFTER event_province,
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  ADD COLUMN closed_at TIMESTAMP NULL AFTER updated_at,
  ADD CONSTRAINT fk_requests_customer FOREIGN KEY (customer_user_id) REFERENCES users(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_requests_admin FOREIGN KEY (assigned_admin_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE request_messages
  ADD COLUMN delivery_target ENUM('internal','customer','provider') NOT NULL DEFAULT 'internal' AFTER sender_role,
  ADD COLUMN moderated_by BIGINT UNSIGNED NULL AFTER message,
  ADD COLUMN moderated_at TIMESTAMP NULL AFTER moderated_by,
  ADD CONSTRAINT fk_message_moderator FOREIGN KEY (moderated_by) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS customer_profiles (
  user_id BIGINT UNSIGNED PRIMARY KEY,
  full_name VARCHAR(140) NOT NULL,
  phone VARCHAR(30) NULL,
  province VARCHAR(100) NULL,
  city VARCHAR(100) NULL,
  preferred_contact ENUM('phone','email','whatsapp') NOT NULL DEFAULT 'phone',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_customer_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provider_categories (
  provider_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  is_primary BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (provider_id, category_id),
  INDEX idx_provider_categories_category (category_id, provider_id),
  CONSTRAINT fk_provider_categories_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
  CONSTRAINT fk_provider_categories_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO provider_categories(provider_id, category_id, is_primary)
SELECT id, category_id, TRUE FROM providers;

CREATE TABLE IF NOT EXISTS provider_services (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL,
  description TEXT NULL,
  price_from DECIMAL(12,2) NULL,
  price_to DECIMAL(12,2) NULL,
  price_unit ENUM('fixed','hour','day','guest','package','quote') NOT NULL DEFAULT 'quote',
  active BOOLEAN NOT NULL DEFAULT TRUE,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_provider_services_public (provider_id, active, sort_order),
  CONSTRAINT fk_services_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
  CONSTRAINT fk_services_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provider_media (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id BIGINT UNSIGNED NOT NULL,
  media_type ENUM('image','video') NOT NULL DEFAULT 'image',
  media_url VARCHAR(1000) NOT NULL,
  alt_text VARCHAR(255) NULL,
  is_cover BOOLEAN NOT NULL DEFAULT FALSE,
  sort_order INT NOT NULL DEFAULT 0,
  approved BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_provider_media_gallery (provider_id, approved, sort_order),
  CONSTRAINT fk_provider_media_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provider_application_categories (
  application_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  is_primary BOOLEAN NOT NULL DEFAULT FALSE,
  PRIMARY KEY (application_id, category_id),
  CONSTRAINT fk_application_categories_application FOREIGN KEY (application_id) REFERENCES provider_applications(id) ON DELETE CASCADE,
  CONSTRAINT fk_application_categories_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO provider_application_categories(application_id, category_id, is_primary)
SELECT id, category_id, TRUE FROM provider_applications;

CREATE TABLE IF NOT EXISTS request_status_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id BIGINT UNSIGNED NOT NULL,
  old_status VARCHAR(40) NULL,
  new_status VARCHAR(40) NOT NULL,
  changed_by BIGINT UNSIGNED NULL,
  note VARCHAR(1000) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_request_history (request_id, created_at),
  CONSTRAINT fk_request_history_request FOREIGN KEY (request_id) REFERENCES service_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_request_history_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  body VARCHAR(1000) NOT NULL,
  type VARCHAR(60) NOT NULL DEFAULT 'general',
  resource_type VARCHAR(60) NULL,
  resource_id BIGINT UNSIGNED NULL,
  read_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notifications_inbox (user_id, read_at, created_at),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_user_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  details JSON NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_entity (entity_type, entity_id, created_at),
  INDEX idx_audit_actor (actor_user_id, created_at),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A tabela guarda mensagens internas e mensagens que a administração encaminha.
-- A API deve impedir que clientes e fornecedores comuniquem directamente entre si.
