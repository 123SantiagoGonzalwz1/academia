USE academia;
CREATE TABLE hoja_diaria (
 id INT PRIMARY KEY, profesor INT NOT NULL, fecha DATE NOT NULL,
 firma_profesor VARCHAR(150) NOT NULL CHECK(CHAR_LENGTH(TRIM(firma_profesor))>0),
 UNIQUE(profesor,fecha), UNIQUE(id,profesor), FOREIGN KEY(profesor) REFERENCES profesor(id)
) ENGINE=InnoDB;
CREATE TABLE clase (
 id INT PRIMARY KEY, hoja INT NOT NULL, profesor INT NOT NULL,
 grupo VARCHAR(20) NOT NULL, asignatura VARCHAR(12) NOT NULL,
 inicio TIME NOT NULL, fin TIME NOT NULL CHECK(fin>inicio), tema VARCHAR(200) NOT NULL,
 UNIQUE(id,grupo), FOREIGN KEY(hoja,profesor) REFERENCES hoja_diaria(id,profesor),
 FOREIGN KEY(grupo,asignatura,profesor) REFERENCES docencia(grupo,asignatura,profesor)
) ENGINE=InnoDB;
CREATE TABLE asistencia (
 clase INT NOT NULL, grupo VARCHAR(20) NOT NULL, alumno INT NOT NULL,
 firma_alumno VARCHAR(150) NOT NULL CHECK(CHAR_LENGTH(TRIM(firma_alumno))>0),
 PRIMARY KEY(clase,alumno), FOREIGN KEY(clase,grupo) REFERENCES clase(id,grupo),
 FOREIGN KEY(grupo,alumno) REFERENCES matricula(grupo,alumno)
) ENGINE=InnoDB;
CREATE VIEW v_ficha_alumno AS
 SELECT a.id alumno_id,a.nombre,c.id clase_id,h.fecha,c.grupo,c.asignatura,c.tema,
 c.inicio,c.fin,p.nombre profesor,h.firma_profesor,s.firma_alumno
 FROM asistencia s JOIN alumno a ON a.id=s.alumno JOIN clase c ON c.id=s.clase
 JOIN hoja_diaria h ON h.id=c.hoja JOIN profesor p ON p.id=c.profesor;