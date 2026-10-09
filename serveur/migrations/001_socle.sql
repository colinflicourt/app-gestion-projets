-- Lot 1 : les 7 tables du socle. Heures en UTC.

CREATE TABLE reglages (
  cle VARCHAR(60) NOT NULL PRIMARY KEY,
  valeur JSON NOT NULL,
  maj_le DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  empreinte CHAR(64) NOT NULL,
  cree_le DATETIME NOT NULL,
  vu_le DATETIME NOT NULL,
  expire_le DATETIME NOT NULL,
  appareil VARCHAR(120) NOT NULL DEFAULT '',
  UNIQUE KEY empreinte (empreinte),
  KEY expire_le (expire_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE essais_connexion (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  quand DATETIME NOT NULL,
  KEY ip_quand (ip, quand)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE jetons (
  service VARCHAR(40) NOT NULL PRIMARY KEY,
  contenu BLOB NOT NULL,
  expire_le DATETIME NULL,
  maj_le DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE copies (
  source VARCHAR(40) NOT NULL PRIMARY KEY,
  contenu JSON NOT NULL,
  recupere_le DATETIME NOT NULL,
  empreinte CHAR(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE actions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(30) NOT NULL,
  domaine VARCHAR(10) NOT NULL,
  carte_id CHAR(24) NOT NULL,
  element_id CHAR(24) NULL,
  quand DATETIME NOT NULL,
  source VARCHAR(10) NOT NULL,
  precision_heure VARCHAR(10) NOT NULL,
  annulee TINYINT(1) NOT NULL DEFAULT 0,
  donnees JSON NULL,
  KEY recherche (type, carte_id, element_id, quand)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE ecritures (
  id_client CHAR(36) NOT NULL PRIMARY KEY,
  route VARCHAR(40) NOT NULL,
  recu_le DATETIME NOT NULL,
  resultat JSON NOT NULL,
  KEY recu_le (recu_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
