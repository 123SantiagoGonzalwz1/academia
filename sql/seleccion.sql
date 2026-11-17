USE academia;
CREATE TABLE candidato (
 id INT PRIMARY KEY, documento VARCHAR(20) NOT NULL UNIQUE,
 nombre VARCHAR(100) NOT NULL, telefono VARCHAR(25) NOT NULL
) ENGINE=InnoDB;
CREATE TABLE vacante (
 id INT PRIMARY KEY, especialidad VARCHAR(12) NOT NULL, descripcion VARCHAR(150) NOT NULL,
 FOREIGN KEY(especialidad) REFERENCES especialidad(codigo)
) ENGINE=InnoDB;
CREATE TABLE comite (
 id INT PRIMARY KEY, presidente INT NOT NULL, secretario INT NOT NULL, vocal INT NOT NULL,
 CHECK(presidente<>secretario AND presidente<>vocal AND secretario<>vocal),
 FOREIGN KEY(presidente) REFERENCES profesor(id), FOREIGN KEY(secretario) REFERENCES profesor(id),
 FOREIGN KEY(vocal) REFERENCES profesor(id)
) ENGINE=InnoDB;
CREATE TABLE proceso (
 id INT PRIMARY KEY, candidato INT NOT NULL, vacante INT NOT NULL, comite INT NOT NULL,
 estado ENUM('ABIERTO','SELECCIONADO','NO_SELECCIONADO') NOT NULL DEFAULT 'ABIERTO',
 UNIQUE(candidato,vacante), FOREIGN KEY(candidato) REFERENCES candidato(id),
 FOREIGN KEY(vacante) REFERENCES vacante(id), FOREIGN KEY(comite) REFERENCES comite(id)
) ENGINE=InnoDB;
CREATE TABLE llamada (
 id INT PRIMARY KEY, proceso INT NOT NULL, fecha DATETIME NOT NULL, resultado VARCHAR(200) NOT NULL,
 FOREIGN KEY(proceso) REFERENCES proceso(id)
) ENGINE=InnoDB;
CREATE TABLE entrevista (
 id INT PRIMARY KEY, proceso INT NOT NULL, fecha DATETIME NOT NULL, observaciones VARCHAR(250) NOT NULL,
 FOREIGN KEY(proceso) REFERENCES proceso(id)
) ENGINE=InnoDB;
CREATE TABLE evaluacion (
 proceso INT NOT NULL, rol ENUM('PRESIDENTE','SECRETARIO','VOCAL') NOT NULL,
 nota DECIMAL(5,2) NOT NULL CHECK(nota BETWEEN 0 AND 100), comentario VARCHAR(250) NOT NULL,
 PRIMARY KEY(proceso,rol), FOREIGN KEY(proceso) REFERENCES proceso(id)
) ENGINE=InnoDB;
-- Cada rol identifica al miembro del comité del proceso. No duplicar su identidad.
CREATE VIEW v_evaluacion AS
 SELECT e.*, CASE e.rol WHEN 'PRESIDENTE' THEN c.presidente WHEN 'SECRETARIO' THEN c.secretario ELSE c.vocal END evaluador
 FROM evaluacion e JOIN proceso p ON p.id=e.proceso JOIN comite c ON c.id=p.comite;