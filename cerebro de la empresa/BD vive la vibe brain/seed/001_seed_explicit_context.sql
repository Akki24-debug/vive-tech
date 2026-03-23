USE `vive_la_vibe_brain`;

SET NAMES utf8mb4;

START TRANSACTION;

INSERT INTO `organization` (
  `name`,
  `description`,
  `country`,
  `status`,
  `vision_summary`,
  `notes`
)
SELECT
  'Vive la Vibe',
  'Sistema de inteligencia conectado al contexto real de la empresa para apoyar la gestión interna, la coordinación, el seguimiento y la organización del negocio.',
  'Mexico',
  'active',
  'Convertir la IA en el cerebro operativo y estratégico del negocio.',
  'Semilla inicial de fase temprana basada solo en información explícita de los documentos fundacionales.'
WHERE NOT EXISTS (
  SELECT 1
  FROM `organization`
  WHERE `name` = 'Vive la Vibe'
);

SET @organization_id := (
  SELECT `id`
  FROM `organization`
  WHERE `name` = 'Vive la Vibe'
  ORDER BY `id`
  LIMIT 1
);

INSERT INTO `business_area` (
  `organization_id`,
  `name`,
  `code`
)
SELECT @organization_id, 'Dirección', NULL
WHERE NOT EXISTS (
  SELECT 1 FROM `business_area`
  WHERE `organization_id` = @organization_id
    AND `name` = 'Dirección'
);

INSERT INTO `business_area` (
  `organization_id`,
  `name`,
  `code`
)
SELECT @organization_id, 'Operación', NULL
WHERE NOT EXISTS (
  SELECT 1 FROM `business_area`
  WHERE `organization_id` = @organization_id
    AND `name` = 'Operación'
);

INSERT INTO `business_area` (
  `organization_id`,
  `name`,
  `code`
)
SELECT @organization_id, 'Tecnología', NULL
WHERE NOT EXISTS (
  SELECT 1 FROM `business_area`
  WHERE `organization_id` = @organization_id
    AND `name` = 'Tecnología'
);

INSERT INTO `business_area` (
  `organization_id`,
  `name`,
  `code`
)
SELECT @organization_id, 'Finanzas', NULL
WHERE NOT EXISTS (
  SELECT 1 FROM `business_area`
  WHERE `organization_id` = @organization_id
    AND `name` = 'Finanzas'
);

INSERT INTO `business_area` (
  `organization_id`,
  `name`,
  `code`
)
SELECT @organization_id, 'Marketing', NULL
WHERE NOT EXISTS (
  SELECT 1 FROM `business_area`
  WHERE `organization_id` = @organization_id
    AND `name` = 'Marketing'
);

INSERT INTO `business_area` (
  `organization_id`,
  `name`,
  `code`
)
SELECT @organization_id, 'Captación', NULL
WHERE NOT EXISTS (
  SELECT 1 FROM `business_area`
  WHERE `organization_id` = @organization_id
    AND `name` = 'Captación'
);

INSERT INTO `business_area` (
  `organization_id`,
  `name`,
  `code`
)
SELECT @organization_id, 'Experiencia huésped', NULL
WHERE NOT EXISTS (
  SELECT 1 FROM `business_area`
  WHERE `organization_id` = @organization_id
    AND `name` = 'Experiencia huésped'
);

SET @area_direccion_id := (
  SELECT `id`
  FROM `business_area`
  WHERE `organization_id` = @organization_id
    AND `name` = 'Dirección'
  LIMIT 1
);

SET @area_operacion_id := (
  SELECT `id`
  FROM `business_area`
  WHERE `organization_id` = @organization_id
    AND `name` = 'Operación'
  LIMIT 1
);

SET @area_tecnologia_id := (
  SELECT `id`
  FROM `business_area`
  WHERE `organization_id` = @organization_id
    AND `name` = 'Tecnología'
  LIMIT 1
);

SET @area_marketing_id := (
  SELECT `id`
  FROM `business_area`
  WHERE `organization_id` = @organization_id
    AND `name` = 'Marketing'
  LIMIT 1
);

SET @area_captacion_id := (
  SELECT `id`
  FROM `business_area`
  WHERE `organization_id` = @organization_id
    AND `name` = 'Captación'
  LIMIT 1
);

SET @area_experiencia_huesped_id := (
  SELECT `id`
  FROM `business_area`
  WHERE `organization_id` = @organization_id
    AND `name` = 'Experiencia huésped'
  LIMIT 1
);

INSERT INTO `business_line` (
  `organization_id`,
  `business_area_id`,
  `name`
)
SELECT @organization_id, @area_operacion_id, 'Administración de hospedajes'
WHERE NOT EXISTS (
  SELECT 1 FROM `business_line`
  WHERE `organization_id` = @organization_id
    AND `name` = 'Administración de hospedajes'
);

INSERT INTO `business_line` (
  `organization_id`,
  `business_area_id`,
  `name`
)
SELECT @organization_id, @area_tecnologia_id, 'Automatización de hospedajes'
WHERE NOT EXISTS (
  SELECT 1 FROM `business_line`
  WHERE `organization_id` = @organization_id
    AND `name` = 'Automatización de hospedajes'
);

INSERT INTO `business_line` (
  `organization_id`,
  `business_area_id`,
  `name`
)
SELECT @organization_id, @area_operacion_id, 'Tours y taxis'
WHERE NOT EXISTS (
  SELECT 1 FROM `business_line`
  WHERE `organization_id` = @organization_id
    AND `name` = 'Tours y taxis'
);

INSERT INTO `business_line` (
  `organization_id`,
  `business_area_id`,
  `name`
)
SELECT @organization_id, @area_operacion_id, 'Entregas'
WHERE NOT EXISTS (
  SELECT 1 FROM `business_line`
  WHERE `organization_id` = @organization_id
    AND `name` = 'Entregas'
);

INSERT INTO `business_line` (
  `organization_id`,
  `business_area_id`,
  `name`
)
SELECT @organization_id, @area_experiencia_huesped_id, 'Actividades Vive la Vibe'
WHERE NOT EXISTS (
  SELECT 1 FROM `business_line`
  WHERE `organization_id` = @organization_id
    AND `name` = 'Actividades Vive la Vibe'
);

INSERT INTO `business_line` (
  `organization_id`,
  `business_area_id`,
  `name`
)
SELECT @organization_id, @area_operacion_id, 'Plataforma de comida'
WHERE NOT EXISTS (
  SELECT 1 FROM `business_line`
  WHERE `organization_id` = @organization_id
    AND `name` = 'Plataforma de comida'
);

INSERT INTO `business_line` (
  `organization_id`,
  `business_area_id`,
  `name`
)
SELECT @organization_id, @area_direccion_id, 'Reacondicionamiento y decoración'
WHERE NOT EXISTS (
  SELECT 1 FROM `business_line`
  WHERE `organization_id` = @organization_id
    AND `name` = 'Reacondicionamiento y decoración'
);

INSERT INTO `business_line` (
  `organization_id`,
  `business_area_id`,
  `name`
)
SELECT @organization_id, @area_captacion_id, 'Marketing y captación'
WHERE NOT EXISTS (
  SELECT 1 FROM `business_line`
  WHERE `organization_id` = @organization_id
    AND `name` = 'Marketing y captación'
);

INSERT INTO `business_priority` (
  `organization_id`,
  `title`,
  `description`,
  `scope_type`,
  `priority_order`,
  `status`,
  `target_period`
)
SELECT
  @organization_id,
  'Fortalecer la planeación del equipo y la estructura interna del negocio',
  'La primera fase debe enfocarse en entender el negocio, centralizar contexto, saber qué proyectos existen, dar seguimiento, registrar avances y detectar pendientes.',
  'organization',
  1,
  'active',
  'Fase 1'
WHERE NOT EXISTS (
  SELECT 1
  FROM `business_priority`
  WHERE `organization_id` = @organization_id
    AND `title` = 'Fortalecer la planeación del equipo y la estructura interna del negocio'
);

INSERT INTO `objective_record` (
  `organization_id`,
  `title`,
  `description`,
  `objective_type`,
  `status`,
  `completion_percent`
)
SELECT
  @organization_id,
  'Convertir la IA en un asistente integral de negocio',
  'Convertir la IA en el cerebro operativo y estratégico del negocio, conectado al contexto real de la empresa y capaz de apoyar coordinación, seguimiento, documentación y análisis.',
  'strategic',
  'active',
  0.00
WHERE NOT EXISTS (
  SELECT 1
  FROM `objective_record`
  WHERE `organization_id` = @organization_id
    AND `title` = 'Convertir la IA en un asistente integral de negocio'
);

INSERT INTO `external_system` (
  `name`,
  `system_type`,
  `description`,
  `is_active`
)
SELECT
  'PMS actual',
  'PMS',
  'Sistema externo que sigue siendo la fuente primaria para reservas, huéspedes, pricing operativo, disponibilidad y transacciones; esta base solo guarda contexto y referencias cuando sea necesario.',
  1
WHERE NOT EXISTS (
  SELECT 1
  FROM `external_system`
  WHERE `name` = 'PMS actual'
);

INSERT INTO `knowledge_document` (
  `organization_id`,
  `title`,
  `document_type`,
  `storage_type`,
  `external_url`,
  `version_label`,
  `summary`
)
SELECT
  @organization_id,
  'Plan de infraestructura de inteligencia artificial — Vive la Vibe',
  'plan',
  'local_path',
  'C:/Users/ragnarok/Downloads/Vive_la_Vibe_Plan_Infraestructura_IA_v1.md',
  'v1',
  'Documento técnico de planeación e implementación con foco en convertir la IA en el cerebro operativo y estratégico del negocio.'
WHERE NOT EXISTS (
  SELECT 1
  FROM `knowledge_document`
  WHERE `organization_id` = @organization_id
    AND `title` = 'Plan de infraestructura de inteligencia artificial — Vive la Vibe'
    AND `external_url` = 'C:/Users/ragnarok/Downloads/Vive_la_Vibe_Plan_Infraestructura_IA_v1.md'
);

INSERT INTO `knowledge_document` (
  `organization_id`,
  `title`,
  `document_type`,
  `storage_type`,
  `external_url`,
  `version_label`,
  `summary`
)
SELECT
  @organization_id,
  'Vive la Vibe — Esqueleto Sugerido de Base de Datos de Negocio',
  'database_design',
  'local_path',
  'C:/Users/ragnarok/Downloads/Vive_la_Vibe_Esqueleto_Base_de_Datos_de_Negocio_v1.md',
  'v1',
  'Documento técnico funcional que define el esqueleto de una base de datos nueva, independiente del PMS actual y orientada a convertirse en el cerebro operativo de Vive la Vibe.'
WHERE NOT EXISTS (
  SELECT 1
  FROM `knowledge_document`
  WHERE `organization_id` = @organization_id
    AND `title` = 'Vive la Vibe — Esqueleto Sugerido de Base de Datos de Negocio'
    AND `external_url` = 'C:/Users/ragnarok/Downloads/Vive_la_Vibe_Esqueleto_Base_de_Datos_de_Negocio_v1.md'
);

INSERT INTO `knowledge_document` (
  `organization_id`,
  `title`,
  `document_type`,
  `storage_type`,
  `external_url`,
  `version_label`,
  `summary`
)
SELECT
  @organization_id,
  'Vive la Vibe - Business Brain Schema v1',
  'schema',
  'local_path',
  'C:/Users/ragnarok/Downloads/Vive_la_Vibe_Business_Brain_Schema_v1.sql',
  'v1',
  'Schema SQL completo de la base vive_la_vibe_brain para gestión interna, proyectos, documentación, alertas, sugerencias de IA e integraciones externas.'
WHERE NOT EXISTS (
  SELECT 1
  FROM `knowledge_document`
  WHERE `organization_id` = @organization_id
    AND `title` = 'Vive la Vibe - Business Brain Schema v1'
    AND `external_url` = 'C:/Users/ragnarok/Downloads/Vive_la_Vibe_Business_Brain_Schema_v1.sql'
);

COMMIT;
