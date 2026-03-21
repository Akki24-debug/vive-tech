@echo off
powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process PowerShell -Verb RunAs -ArgumentList '-NoExit -ExecutionPolicy Bypass -File ""C:\Users\ragnarok\Documents\repos\vive-tech-tools\windows\migrate-mariadb-to-11.8.3.ps1""'"
