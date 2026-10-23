-- Lot 3 : boîtes mail en lecture seule. Un compte par boîte (mot de passe chiffré par chiffrer(), jamais en clair),
-- et les corrections d'expéditeur de Colin. Heures en UTC. Les copies des mails vivent dans la table copies (source mail:<compte>).
CREATE TABLE IF NOT EXISTS comptes_mail (
  compte VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
  identifiant VARCHAR(200) NOT NULL,
  mot_de_passe VARBINARY(512) NOT NULL,
  modifie_le DATETIME NOT NULL,
  dernier_succes DATETIME NULL,
  derniere_erreur VARCHAR(20) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS expediteurs_tri (
  adresse VARCHAR(254) NOT NULL PRIMARY KEY,
  choix VARCHAR(10) CHARACTER SET ascii NOT NULL,
  cree_le DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
