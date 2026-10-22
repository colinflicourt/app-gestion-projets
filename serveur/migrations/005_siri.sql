-- Lot 2, plan 3 : appels réussis de Siri (limite par heure, doublons). Heures en UTC, purgée au-delà de 24 h.
-- On n'y garde que l'empreinte du texte nettoyé, jamais le texte dicté.
CREATE TABLE IF NOT EXISTS siri_appels (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  recu_le DATETIME NOT NULL,
  empreinte CHAR(64) NOT NULL,
  KEY recu_le (recu_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
