@echo off
powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process PowerShell -Verb RunAs -ArgumentList '-NoExit -ExecutionPolicy Bypass -File ""C:\Users\ragnarok\Documents\repos\vive-tech-tools\windows\cleanup-asus-ai-suite.ps1""'"
