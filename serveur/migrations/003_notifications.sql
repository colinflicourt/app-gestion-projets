-- Lot 2, plan 1 : un abonnement aux notifications par appareil. Heures en UTC.
CREATE TABLE IF NOT EXISTS abonnements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  endpoint TEXT NOT NULL,
  empreinte CHAR(64) NOT NULL,
  p256dh VARCHAR(100) NOT NULL,
  auth VARCHAR(30) NOT NULL,
  session_id INT UNSIGNED NULL,
  cree_le DATETIME NOT NULL,
  vu_le DATETIME NOT NULL,
  echecs TINYINT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY empreinte (empreinte)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
