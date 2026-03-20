/* ============================================================================
   LIMPIEZA MASIVA DE PREFIJO EN NOTAS
   Objetivo:
   - Quitar la cadena exacta: "Monto capturado: "
   - En todas las columnas de texto del schema actual cuyo nombre contenga
     "note" o "notes".

   Uso:
   1) Selecciona tu base de datos (USE tu_db;).
   2) Ejecuta este script completo.
   ============================================================================ */

SET @target_prefix := 'Monto capturado: ';
SET @target_schema := DATABASE();

DROP PROCEDURE IF EXISTS sp_remove_monto_capturado_prefix_all_notes;

DELIMITER $$

CREATE PROCEDURE sp_remove_monto_capturado_prefix_all_notes()
BEGIN
  DECLARE done INT DEFAULT 0;
  DECLARE v_table_name VARCHAR(128);
  DECLARE v_column_name VARCHAR(128);
  DECLARE v_sql LONGTEXT;
  DECLARE v_rows BIGINT DEFAULT 0;
  DECLARE v_table_esc VARCHAR(256);
  DECLARE v_col_esc VARCHAR(256);

  IF @target_schema IS NULL OR @target_schema = '' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'No hay base de datos seleccionada. Usa: USE tu_db;';
  END IF;

  DROP TEMPORARY TABLE IF EXISTS tmp_note_columns;
  CREATE TEMPORARY TABLE tmp_note_columns (
    table_name VARCHAR(128) NOT NULL,
    column_name VARCHAR(128) NOT NULL,
    PRIMARY KEY (table_name, column_name)
  ) ENGINE=Memory;

  INSERT INTO tmp_note_columns (table_name, column_name)
  SELECT
    c.table_name,
    c.column_name
  FROM information_schema.columns c
  WHERE c.table_schema = @target_schema
    AND c.data_type IN ('char','varchar','tinytext','text','mediumtext','longtext')
    AND (
      LOWER(c.column_name) LIKE '%note%'
      OR LOWER(c.column_name) LIKE '%notes%'
    );

  DROP TEMPORARY TABLE IF EXISTS tmp_note_cleanup_log;
  CREATE TEMPORARY TABLE tmp_note_cleanup_log (
    id_log BIGINT NOT NULL AUTO_INCREMENT,
    table_name VARCHAR(128) NOT NULL,
    column_name VARCHAR(128) NOT NULL,
    rows_updated BIGINT NOT NULL DEFAULT 0,
    PRIMARY KEY (id_log),
    KEY idx_tbl_col (table_name, column_name)
  ) ENGINE=InnoDB;

  BEGIN
    DECLARE cur CURSOR FOR
      SELECT table_name, column_name
      FROM tmp_note_columns
      ORDER BY table_name, column_name;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN cur;
    read_loop: LOOP
      FETCH cur INTO v_table_name, v_column_name;
      IF done = 1 THEN
        LEAVE read_loop;
      END IF;

      SET v_table_esc = REPLACE(v_table_name, '`', '``');
      SET v_col_esc = REPLACE(v_column_name, '`', '``');

      SET v_sql = CONCAT(
        'UPDATE `', v_table_esc, '` ',
        'SET `', v_col_esc, '` = REPLACE(`', v_col_esc, '`, ', QUOTE(@target_prefix), ', '''') ',
        'WHERE `', v_col_esc, '` LIKE CONCAT(''%'', ', QUOTE(@target_prefix), ', ''%'')'
      );

      PREPARE stmt FROM v_sql;
      EXECUTE stmt;
      SET v_rows = ROW_COUNT();
      DEALLOCATE PREPARE stmt;

      IF v_rows > 0 THEN
        INSERT INTO tmp_note_cleanup_log (table_name, column_name, rows_updated)
        VALUES (v_table_name, v_column_name, v_rows);
      END IF;
    END LOOP;
    CLOSE cur;
  END;

  SELECT
    table_name,
    column_name,
    rows_updated
  FROM tmp_note_cleanup_log
  ORDER BY rows_updated DESC, table_name, column_name;

  SELECT
    COALESCE(SUM(rows_updated), 0) AS total_rows_updated,
    COUNT(*) AS total_columns_touched
  FROM tmp_note_cleanup_log;
END $$

DELIMITER ;

CALL sp_remove_monto_capturado_prefix_all_notes();

/* Opcional: elimina el procedimiento al final */
DROP PROCEDURE IF EXISTS sp_remove_monto_capturado_prefix_all_notes;

