CREATE DATABASE academia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE academia;
CREATE TABLE especialidad (
 codigo VARCHAR(12) PRIMARY KEY, nombre VARCHAR(80) NOT NULL,
 titulo VARCHAR(120) NOT NULL,
 tipologia ENUM('Técnico profesional','Tecnólogo','Especialización tecnológica','Pregrado','Posgrado especialización','Maestría') NOT NULL
) ENGINE=InnoDB;
CREATE TABLE curso (
 especialidad VARCHAR(12) NOT NULL, grado TINYINT NOT NULL CHECK(grado IN (1,2)),
 PRIMARY KEY(especialidad,grado), FOREIGN KEY(especialidad) REFERENCES especialidad(codigo)
) ENGINE=InnoDB;
CREATE TABLE asignatura (
 codigo VARCHAR(12) PRIMARY KEY, nombre VARCHAR(80) NOT NULL,
 tipo ENUM('TRONCAL','ESPECIALIDAD') NOT NULL
) ENGINE=InnoDB;
CREATE TABLE plan_estudio (
 especialidad VARCHAR(12) NOT NULL, grado TINYINT NOT NULL, asignatura VARCHAR(12) NOT NULL,
 PRIMARY KEY(especialidad,grado,asignatura),
 FOREIGN KEY(especialidad,grado) REFERENCES curso(especialidad,grado),
 FOREIGN KEY(asignatura) REFERENCES asignatura(codigo)
) ENGINE=InnoDB;
CREATE VIEW v_especialidad AS
 SELECT e.*, (SELECT COUNT(DISTINCT p.asignatura) FROM plan_estudio p WHERE p.especialidad=e.codigo) numero_asignaturas,
 (SELECT COUNT(*) FROM curso c WHERE c.especialidad=e.codigo) numero_cursos
 FROM especialidad e;
CREATE TABLE tx_aplicada (id VARCHAR(48) PRIMARY KEY) ENGINE=InnoDB;