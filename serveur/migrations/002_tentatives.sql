-- Lot 1, plan 3 : captures dont l'envoi à Trello a commencé. Si la réponse de Trello se perd, le renvoi
-- (même id_client) retrouve la carte créée au lieu d'en créer une seconde. Heures en UTC.

CREATE TABLE IF NOT EXISTS tentatives (
  id_client CHAR(36) NOT NULL PRIMARY KEY,
  debut DATETIME NOT NULL,
  KEY debut (debut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
