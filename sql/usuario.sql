CREATE USER 'academia_app'@'127.0.0.1' IDENTIFIED BY 'nodo1';
GRANT SELECT, INSERT, UPDATE, DELETE ON academia.* TO 'academia_app'@'127.0.0.1';