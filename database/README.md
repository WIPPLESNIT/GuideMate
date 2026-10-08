# GuideMate database

This folder contains a full dump of the `guidemate` MySQL database so the project
can be restored on any machine (XAMPP / MySQL).

## Restore on a new PC

1. Make sure MySQL is running (e.g. start it from the XAMPP Control Panel).
2. From a terminal, import the dump (the file already contains
   `CREATE DATABASE guidemate`, so you don't need to create it first):

   ```bash
   mysql -u root < database/guidemate.sql
   ```

   On Windows with XAMPP the `mysql` client is usually at
   `C:\xampp\mysql\bin\mysql.exe` (adjust the path to your install), e.g.:

   ```powershell
   & "C:\xampp\mysql\bin\mysql.exe" -u root < "database\guidemate.sql"
   ```

3. Copy `.env.example` to `.env` and fill in your local database credentials and
   API keys (Google Maps / Mapbox / Mapillary). `.env` is intentionally **not**
   committed because it contains secrets.

## Update the dump after schema/data changes

```powershell
& "C:\xampp\mysql\bin\mysqldump.exe" -u root --databases guidemate --routines --events --single-transaction --result-file="database\guidemate.sql"
```
