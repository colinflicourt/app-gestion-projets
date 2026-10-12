-- Lot 2, plan 2 : rappels manuels, et une ligne par notification à envoyer (la clé interdit les doublons).
-- La clé est en ASCII binaire : jamais deux clés confondues par la casse ou les accents. Heures en UTC.
CREATE TABLE IF NOT EXISTS rappels (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_client CHAR(36) NOT NULL,
  texte VARCHAR(500) NOT NULL,
  quand DATETIME NOT NULL,
  cree_le DATETIME NOT NULL,
  source VARCHAR(10) NOT NULL DEFAULT 'app',
  annule TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY id_client (id_client),
  KEY quand (quand)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS envois (
  cle VARCHAR(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
  type VARCHAR(20) NOT NULL,
  prevu_pour DATETIME NOT NULL,
  titre VARCHAR(200) NOT NULL,
  texte VARCHAR(500) NOT NULL,
  url VARCHAR(200) NOT NULL,
  statut VARCHAR(10) NOT NULL DEFAULT 'prevu',
  essais TINYINT UNSIGNED NOT NULL DEFAULT 0,
  envoye_le DATETIME NULL,
  KEY statut_prevu (statut, prevu_pour)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
