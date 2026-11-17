@echo off
"C:\lab-academia\mariadb\bin\mariadbd.exe" --defaults-file="C:\lab-academia\nodo2\my.ini" --bind-address=127.0.0.1 --innodb-flush-log-at-trx-commit=1 --console
pause