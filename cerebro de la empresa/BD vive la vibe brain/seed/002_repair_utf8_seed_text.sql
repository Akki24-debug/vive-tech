USE `vive_la_vibe_brain`;

SET NAMES utf8mb4;

START TRANSACTION;

UPDATE `organization`
SET
  `description` = 'Sistema de inteligencia conectado al contexto real de la empresa para apoyar la gestión interna, la coordinación, el seguimiento y la organización del negocio.',
  `vision_summary` = 'Convertir la IA en el cerebro operativo y estratégico del negocio.',
  `notes` = 'Semilla inicial de fase temprana basada solo en información explícita de los documentos fundacionales.'
WHERE `id` = 1;

UPDATE `business_area` SET `name` = 'Dirección' WHERE `id` = 1;
UPDATE `business_area` SET `name` = 'Operación' WHERE `id` = 2;
UPDATE `business_area` SET `name` = 'Tecnología' WHERE `id` = 3;
UPDATE `business_area` SET `name` = 'Captación' WHERE `id` = 6;
UPDATE `business_area` SET `name` = 'Experiencia huésped' WHERE `id` = 7;

UPDATE `business_line` SET `name` = 'Administración de hospedajes' WHERE `id` = 1;
UPDATE `business_line` SET `name` = 'Automatización de hospedajes' WHERE `id` = 2;
UPDATE `business_line` SET `name` = 'Reacondicionamiento y decoración' WHERE `id` = 7;
UPDATE `business_line` SET `name` = 'Marketing y captación' WHERE `id` = 8;

UPDATE `business_priority`
SET
  `title` = 'Fortalecer la planeación del equipo y la estructura interna del negocio',
  `description` = 'La primera fase debe enfocarse en entender el negocio, centralizar contexto, saber qué proyectos existen, dar seguimiento, registrar avances y detectar pendientes.'
WHERE `id` = 1;

UPDATE `objective_record`
SET `description` = 'Convertir la IA en el cerebro operativo y estratégico del negocio, conectado al contexto real de la empresa y capaz de apoyar coordinación, seguimiento, documentación y análisis.'
WHERE `id` = 1;

UPDATE `external_system`
SET `description` = 'Sistema externo que sigue siendo la fuente primaria para reservas, huéspedes, pricing operativo, disponibilidad y transacciones; esta base solo guarda contexto y referencias cuando sea necesario.'
WHERE `id` = 1;

UPDATE `knowledge_document`
SET
  `title` = 'Plan de infraestructura de inteligencia artificial — Vive la Vibe',
  `summary` = 'Documento técnico de planeación e implementación con foco en convertir la IA en el cerebro operativo y estratégico del negocio.'
WHERE `id` = 1;

UPDATE `knowledge_document`
SET
  `title` = 'Vive la Vibe — Esqueleto Sugerido de Base de Datos de Negocio',
  `summary` = 'Documento técnico funcional que define el esqueleto de una base de datos nueva, independiente del PMS actual y orientada a convertirse en el cerebro operativo de Vive la Vibe.'
WHERE `id` = 2;

UPDATE `knowledge_document`
SET `summary` = 'Schema SQL completo de la base vive_la_vibe_brain para gestión interna, proyectos, documentación, alertas, sugerencias de IA e integraciones externas.'
WHERE `id` = 3;

COMMIT;
