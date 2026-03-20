# PHP Database Connection Test

This folder contains lightweight PHP utilities that can run on a standard shared hosting provider (such as Hostinger) to validate connectivity with the MariaDB/MySQL schema included in this repository.

## Files

- `config.php` - Default credentials for the VIBE company database user (`u508158532_rod`). Update these values if your hosting provider changes them.
- `connection.php` - Reusable helper that builds a PDO connection using the credentials above.
- `test-connection.php` - Minimal script you can upload and visit in the browser to ensure the database accepts connections and executes queries.
- `.gitignore` - Keeps your real `config.php` and any generated logs out of version control.

## Usage

1. **Review the configuration file**  
   The tracked `config.php` already contains the Hostinger credentials that were verified (`u508158532_rod` / `HalconRuby24`). If you need a local override, create `config.local.php` with the same keys and adjust values there.
   You can also override at runtime with env vars: `PMS_DB_HOST`, `PMS_DB_PORT`, `PMS_DB_DATABASE`/`PMS_DB_NAME`, `PMS_DB_USERNAME`, `PMS_DB_PASSWORD`, `PMS_DB_CHARSET` (also supports `DB_NAME` and common aliases).

2. **Upload the files** to your hosting account (e.g., through FTP or the Hostinger file manager). Place them inside a directory that is web-accessible but that you can remove after testing.

3. **Run the test** by visiting `https://your-domain.com/path/to/test-connection.php` in your browser. You should see messages confirming a successful connection, the result of a simple `SELECT 1` query, and the database server version that answered the request.

4. **Clean up** once you confirm the connection works. Delete `test-connection.php` (and optionally `config.php`) from your host to prevent exposing credentials.

## Troubleshooting

- If you receive an error such as `SQLSTATE[HY000] [1045] Access denied`, double-check that the username, password, and host match the values from Hostinger.
- For firewall-related errors, ensure that your database user is allowed to connect from your hosting environment.
- Enable PDO in your PHP configuration if it is disabled (it is enabled by default on Hostinger shared hosting).

## Next steps

Once the connection test succeeds, you can reuse `connection.php` within your PHP application to perform real queries against the schema located in [`bd pms/u508158532_rodbd.sql`](../bd%20pms/u508158532_rodbd.sql).
