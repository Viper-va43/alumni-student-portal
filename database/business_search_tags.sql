-- Adds partner-managed business search tags.
-- Safe to rerun.

DROP PROCEDURE IF EXISTS where2go_add_column_if_missing;

DELIMITER //

CREATE PROCEDURE where2go_add_column_if_missing(
    IN p_table_name VARCHAR(64),
    IN p_column_name VARCHAR(64),
    IN p_ddl TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table_name
          AND COLUMN_NAME = p_column_name
    ) THEN
        SET @where2go_column_ddl = p_ddl;
        PREPARE where2go_column_stmt FROM @where2go_column_ddl;
        EXECUTE where2go_column_stmt;
        DEALLOCATE PREPARE where2go_column_stmt;
    END IF;
END//

DELIMITER ;

CALL where2go_add_column_if_missing(
    'businesses',
    'search_tags',
    'ALTER TABLE businesses ADD COLUMN search_tags TEXT NULL AFTER custom_type'
);

DROP PROCEDURE IF EXISTS where2go_add_column_if_missing;
