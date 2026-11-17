USE academia;
CREATE TABLE profesor (
 id INT PRIMARY KEY, documento VARCHAR(20) NOT NULL UNIQUE,
 nombre VARCHAR(100) NOT NULL, telefono VARCHAR(25), activo BOOLEAN NOT NULL DEFAULT 1
) ENGINE=InnoDB;
CREATE TABLE grupo (
 codigo VARCHAR(20) PRIMARY KEY, especialidad VARCHAR(12) NOT NULL, grado TINYINT NOT NULL,
 tutor INT NOT NULL, cupo TINYINT NOT NULL DEFAULT 15 CHECK(cupo=15),
 UNIQUE(codigo,especialidad,grado), FOREIGN KEY(especialidad,grado) REFERENCES curso(especialidad,grado),
 FOREIGN KEY(tutor) REFERENCES profesor(id)
) ENGINE=InnoDB;
CREATE TABLE docencia (
 grupo VARCHAR(20) NOT NULL, especialidad VARCHAR(12) NOT NULL, grado TINYINT NOT NULL,
 asignatura VARCHAR(12) NOT NULL, profesor INT NOT NULL,
 PRIMARY KEY(grupo,asignatura,profesor),
 FOREIGN KEY(grupo,especialidad,grado) REFERENCES grupo(codigo,especialidad,grado),
 FOREIGN KEY(especialidad,grado,asignatura) REFERENCES plan_estudio(especialidad,grado,asignatura),
 FOREIGN KEY(profesor) REFERENCES profesor(id)
) ENGINE=InnoDB;
CREATE TABLE alumno (
 id INT PRIMARY KEY, documento VARCHAR(20) NOT NULL UNIQUE, nombre VARCHAR(100) NOT NULL
) ENGINE=InnoDB;
-- Una plaza 1..15 única por grupo limita el cupo incluso bajo concurrencia.
CREATE TABLE matricula (
 grupo VARCHAR(20) NOT NULL, alumno INT NOT NULL, plaza TINYINT NOT NULL CHECK(plaza BETWEEN 1 AND 15),
 PRIMARY KEY(grupo,alumno), UNIQUE(grupo,plaza),
 FOREIGN KEY(grupo) REFERENCES grupo(codigo), FOREIGN KEY(alumno) REFERENCES alumno(id)
) ENGINE=InnoDB;
