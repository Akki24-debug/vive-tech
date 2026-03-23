USE `vive_la_vibe_brain`;

SET NAMES utf8mb4;

START TRANSACTION;

UPDATE `organization`
SET `description` = NULL
WHERE `name` = 'Vive la Vibe';

COMMIT;
