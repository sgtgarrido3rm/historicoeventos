CREATE DATABASE IF NOT EXISTS controle_eventos
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE controle_eventos;

CREATE TABLE IF NOT EXISTS eventos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    data_evento DATE NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_eventos_data (data_evento)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
