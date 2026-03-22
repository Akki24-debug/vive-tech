-- ============================================================================
-- Vive la Vibe - Business Brain Schema v1
-- Motor objetivo: MariaDB / MySQL compatible (Hostinger)
-- Propósito:
--   Base de datos independiente del PMS, enfocada en:
--   - gestión interna del equipo,
--   - proyectos / subproyectos / tareas,
--   - reuniones / decisiones / documentación,
--   - alertas / recordatorios,
--   - sugerencias e insights de IA,
--   - integraciones y referencias a sistemas externos.
-- ============================================================================
CREATE DATABASE IF NOT EXISTS `vive_la_vibe_brain`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `vive_la_vibe_brain`;

SET NAMES utf8mb4;

-- ============================================================================
-- MÓDULO 1: Núcleo organizacional
-- ============================================================================

CREATE TABLE IF NOT EXISTS `organization` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `legal_name` VARCHAR(200) NULL,
  `description` TEXT NULL,
  `base_city` VARCHAR(120) NULL,
  `base_state` VARCHAR(120) NULL,
  `country` VARCHAR(120) NULL DEFAULT 'Mexico',
  `status` VARCHAR(50) NOT NULL DEFAULT 'active',
  `current_stage` VARCHAR(100) NULL,
  `vision_summary` TEXT NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_organization_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_account` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `first_name` VARCHAR(120) NOT NULL,
  `last_name` VARCHAR(120) NULL,
  `display_name` VARCHAR(180) NOT NULL,
  `email` VARCHAR(190) NULL,
  `phone` VARCHAR(40) NULL,
  `role_summary` VARCHAR(255) NULL,
  `employment_status` VARCHAR(50) NOT NULL DEFAULT 'active',
  `timezone` VARCHAR(80) NOT NULL DEFAULT 'America/Mexico_City',
  `notes` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_account_email` (`email`),
  KEY `idx_user_account_org` (`organization_id`),
  KEY `idx_user_account_active` (`is_active`),
  CONSTRAINT `fk_user_account_organization`
    FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `description` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_role` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `role_id` BIGINT UNSIGNED NOT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_role_user_role` (`user_id`, `role_id`),
  KEY `idx_user_role_role` (`role_id`),
  CONSTRAINT `fk_user_role_user`
    FOREIGN KEY (`user_id`) REFERENCES `user_account` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_user_role_role`
    FOREIGN KEY (`role_id`) REFERENCES `role` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `business_area` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `code` VARCHAR(50) NULL,
  `description` TEXT NULL,
  `priority_level` VARCHAR(50) NOT NULL DEFAULT 'medium',
  `responsible_user_id` BIGINT UNSIGNED NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_business_area_org_name` (`organization_id`, `name`),
  UNIQUE KEY `uq_business_area_org_code` (`organization_id`, `code`),
  KEY `idx_business_area_responsible` (`responsible_user_id`),
  CONSTRAINT `fk_business_area_organization`
    FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_business_area_responsible_user`
    FOREIGN KEY (`responsible_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_area_assignment` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `business_area_id` BIGINT UNSIGNED NOT NULL,
  `responsibility_level` VARCHAR(50) NOT NULL DEFAULT 'member',
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `start_date` DATE NULL,
  `end_date` DATE NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_area_assignment` (`user_id`, `business_area_id`, `start_date`),
  KEY `idx_user_area_assignment_area` (`business_area_id`),
  CONSTRAINT `fk_user_area_assignment_user`
    FOREIGN KEY (`user_id`) REFERENCES `user_account` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_user_area_assignment_area`
    FOREIGN KEY (`business_area_id`) REFERENCES `business_area` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_capacity_profile` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `weekly_capacity_hours` DECIMAL(6,2) NULL,
  `max_parallel_projects` INT UNSIGNED NULL,
  `max_parallel_tasks` INT UNSIGNED NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_capacity_profile_user` (`user_id`),
  CONSTRAINT `fk_user_capacity_profile_user`
    FOREIGN KEY (`user_id`) REFERENCES `user_account` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `business_line` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `business_area_id` BIGINT UNSIGNED NULL,
  `name` VARCHAR(180) NOT NULL,
  `description` TEXT NULL,
  `business_model_summary` TEXT NULL,
  `current_status` VARCHAR(50) NOT NULL DEFAULT 'planned',
  `monetization_notes` TEXT NULL,
  `strategic_priority` VARCHAR(50) NOT NULL DEFAULT 'medium',
  `owner_user_id` BIGINT UNSIGNED NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_business_line_org_name` (`organization_id`, `name`),
  KEY `idx_business_line_area` (`business_area_id`),
  KEY `idx_business_line_owner` (`owner_user_id`),
  CONSTRAINT `fk_business_line_organization`
    FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_business_line_area`
    FOREIGN KEY (`business_area_id`) REFERENCES `business_area` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_business_line_owner_user`
    FOREIGN KEY (`owner_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `business_priority` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(220) NOT NULL,
  `description` TEXT NULL,
  `scope_type` VARCHAR(50) NOT NULL DEFAULT 'organization',
  `scope_id` BIGINT UNSIGNED NULL,
  `priority_order` INT UNSIGNED NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'active',
  `target_period` VARCHAR(100) NULL,
  `owner_user_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_business_priority_org` (`organization_id`),
  KEY `idx_business_priority_owner` (`owner_user_id`),
  KEY `idx_business_priority_scope` (`scope_type`, `scope_id`),
  CONSTRAINT `fk_business_priority_organization`
    FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_business_priority_owner_user`
    FOREIGN KEY (`owner_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `objective_record` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `business_area_id` BIGINT UNSIGNED NULL,
  `title` VARCHAR(220) NOT NULL,
  `description` TEXT NULL,
  `objective_type` VARCHAR(50) NOT NULL DEFAULT 'strategic',
  `status` VARCHAR(50) NOT NULL DEFAULT 'active',
  `target_date` DATE NULL,
  `owner_user_id` BIGINT UNSIGNED NULL,
  `completion_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_objective_record_org` (`organization_id`),
  KEY `idx_objective_record_area` (`business_area_id`),
  KEY `idx_objective_record_owner` (`owner_user_id`),
  CONSTRAINT `fk_objective_record_organization`
    FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_objective_record_area`
    FOREIGN KEY (`business_area_id`) REFERENCES `business_area` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_objective_record_owner_user`
    FOREIGN KEY (`owner_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MÓDULO 2 y 3: Proyectos, tareas, jerarquías y seguimiento
-- ============================================================================

CREATE TABLE IF NOT EXISTS `project_category` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `business_area_id` BIGINT UNSIGNED NULL,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_category_org_name` (`organization_id`, `name`),
  KEY `idx_project_category_area` (`business_area_id`),
  CONSTRAINT `fk_project_category_organization`
    FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_project_category_area`
    FOREIGN KEY (`business_area_id`) REFERENCES `business_area` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `initiative` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `project_category_id` BIGINT UNSIGNED NULL,
  `title` VARCHAR(220) NOT NULL,
  `description` TEXT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'planned',
  `priority_level` VARCHAR(50) NOT NULL DEFAULT 'medium',
  `owner_user_id` BIGINT UNSIGNED NULL,
  `start_date` DATE NULL,
  `target_date` DATE NULL,
  `completion_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_initiative_org` (`organization_id`),
  KEY `idx_initiative_category` (`project_category_id`),
  KEY `idx_initiative_owner` (`owner_user_id`),
  CONSTRAINT `fk_initiative_organization`
    FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_initiative_project_category`
    FOREIGN KEY (`project_category_id`) REFERENCES `project_category` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_initiative_owner_user`
    FOREIGN KEY (`owner_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `project` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `initiative_id` BIGINT UNSIGNED NULL,
  `project_category_id` BIGINT UNSIGNED NULL,
  `business_area_id` BIGINT UNSIGNED NULL,
  `business_line_id` BIGINT UNSIGNED NULL,
  `title` VARCHAR(220) NOT NULL,
  `short_code` VARCHAR(60) NULL,
  `description` TEXT NULL,
  `objective` TEXT NULL,
  `current_status` VARCHAR(50) NOT NULL DEFAULT 'planned',
  `priority_level` VARCHAR(50) NOT NULL DEFAULT 'medium',
  `health_status` VARCHAR(50) NOT NULL DEFAULT 'green',
  `owner_user_id` BIGINT UNSIGNED NULL,
  `sponsor_user_id` BIGINT UNSIGNED NULL,
  `start_date` DATE NULL,
  `target_date` DATE NULL,
  `completed_at` DATETIME NULL,
  `completion_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `last_reported_at` DATETIME NULL,
  `last_activity_at` DATETIME NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by_user_id` BIGINT UNSIGNED NULL,
  `updated_by_user_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_org_short_code` (`organization_id`, `short_code`),
  KEY `idx_project_org` (`organization_id`),
  KEY `idx_project_initiative` (`initiative_id`),
  KEY `idx_project_category` (`project_category_id`),
  KEY `idx_project_area` (`business_area_id`),
  KEY `idx_project_line` (`business_line_id`),
  KEY `idx_project_owner` (`owner_user_id`),
  KEY `idx_project_status` (`current_status`),
  KEY `idx_project_target_date` (`target_date`),
  CONSTRAINT `fk_project_organization`
    FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_project_initiative`
    FOREIGN KEY (`initiative_id`) REFERENCES `initiative` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_project_project_category`
    FOREIGN KEY (`project_category_id`) REFERENCES `project_category` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_project_business_area`
    FOREIGN KEY (`business_area_id`) REFERENCES `business_area` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_project_business_line`
    FOREIGN KEY (`business_line_id`) REFERENCES `business_line` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_project_owner_user`
    FOREIGN KEY (`owner_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_project_sponsor_user`
    FOREIGN KEY (`sponsor_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_project_created_by_user`
    FOREIGN KEY (`created_by_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_project_updated_by_user`
    FOREIGN KEY (`updated_by_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `project_member` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `role_in_project` VARCHAR(80) NOT NULL DEFAULT 'member',
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_member_project_user` (`project_id`, `user_id`),
  KEY `idx_project_member_user` (`user_id`),
  CONSTRAINT `fk_project_member_project`
    FOREIGN KEY (`project_id`) REFERENCES `project` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_project_member_user`
    FOREIGN KEY (`user_id`) REFERENCES `user_account` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `project_objective_link` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `objective_record_id` BIGINT UNSIGNED NOT NULL,
  `relation_type` VARCHAR(50) NOT NULL DEFAULT 'contributes_to',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_objective_link` (`project_id`, `objective_record_id`, `relation_type`),
  CONSTRAINT `fk_project_objective_link_project`
    FOREIGN KEY (`project_id`) REFERENCES `project` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_project_objective_link_objective_record`
    FOREIGN KEY (`objective_record_id`) REFERENCES `objective_record` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `subproject` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(220) NOT NULL,
  `description` TEXT NULL,
  `current_status` VARCHAR(50) NOT NULL DEFAULT 'planned',
  `priority_level` VARCHAR(50) NOT NULL DEFAULT 'medium',
  `owner_user_id` BIGINT UNSIGNED NULL,
  `start_date` DATE NULL,
  `target_date` DATE NULL,
  `completed_at` DATETIME NULL,
  `completion_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `last_reported_at` DATETIME NULL,
  `created_by_user_id` BIGINT UNSIGNED NULL,
  `updated_by_user_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_subproject_project` (`project_id`),
  KEY `idx_subproject_owner` (`owner_user_id`),
  KEY `idx_subproject_status` (`current_status`),
  CONSTRAINT `fk_subproject_project`
    FOREIGN KEY (`project_id`) REFERENCES `project` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_subproject_owner_user`
    FOREIGN KEY (`owner_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_subproject_created_by_user`
    FOREIGN KEY (`created_by_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_subproject_updated_by_user`
    FOREIGN KEY (`updated_by_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `task` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `subproject_id` BIGINT UNSIGNED NULL,
  `parent_task_id` BIGINT UNSIGNED NULL,
  `title` VARCHAR(220) NOT NULL,
  `description` TEXT NULL,
  `task_type` VARCHAR(50) NOT NULL DEFAULT 'task',
  `current_status` VARCHAR(50) NOT NULL DEFAULT 'pending',
  `priority_level` VARCHAR(50) NOT NULL DEFAULT 'medium',
  `owner_user_id` BIGINT UNSIGNED NULL,
  `assigned_user_id` BIGINT UNSIGNED NULL,
  `reviewer_user_id` BIGINT UNSIGNED NULL,
  `start_date` DATE NULL,
  `due_date` DATETIME NULL,
  `completed_at` DATETIME NULL,
  `estimated_hours` DECIMAL(8,2) NULL,
  `actual_hours` DECIMAL(8,2) NULL,
  `completion_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `order_index` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_blocked` TINYINT(1) NOT NULL DEFAULT 0,
  `last_reported_at` DATETIME NULL,
  `last_activity_at` DATETIME NULL,
  `created_by_user_id` BIGINT UNSIGNED NULL,
  `updated_by_user_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_task_project` (`project_id`),
  KEY `idx_task_subproject` (`subproject_id`),
  KEY `idx_task_parent` (`parent_task_id`),
  KEY `idx_task_owner` (`owner_user_id`),
  KEY `idx_task_assigned` (`assigned_user_id`),
  KEY `idx_task_status` (`current_status`),
  KEY `idx_task_due_date` (`due_date`),
  CONSTRAINT `fk_task_project`
    FOREIGN KEY (`project_id`) REFERENCES `project` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_task_subproject`
    FOREIGN KEY (`subproject_id`) REFERENCES `subproject` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_task_parent_task`
    FOREIGN KEY (`parent_task_id`) REFERENCES `task` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_task_owner_user`
    FOREIGN KEY (`owner_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_task_assigned_user`
    FOREIGN KEY (`assigned_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_task_reviewer_user`
    FOREIGN KEY (`reviewer_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_task_created_by_user`
    FOREIGN KEY (`created_by_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_task_updated_by_user`
    FOREIGN KEY (`updated_by_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `milestone` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `subproject_id` BIGINT UNSIGNED NULL,
  `title` VARCHAR(220) NOT NULL,
  `description` TEXT NULL,
  `milestone_type` VARCHAR(50) NOT NULL DEFAULT 'delivery',
  `current_status` VARCHAR(50) NOT NULL DEFAULT 'pending',
  `target_date` DATETIME NULL,
  `completed_at` DATETIME NULL,
  `owner_user_id` BIGINT UNSIGNED NULL,
  `created_by_user_id` BIGINT UNSIGNED NULL,
  `updated_by_user_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_milestone_project` (`project_id`),
  KEY `idx_milestone_subproject` (`subproject_id`),
  KEY `idx_milestone_owner` (`owner_user_id`),
  KEY `idx_milestone_target_date` (`target_date`),
  CONSTRAINT `fk_milestone_project`
    FOREIGN KEY (`project_id`) REFERENCES `project` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_milestone_subproject`
    FOREIGN KEY (`subproject_id`) REFERENCES `subproject` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_milestone_owner_user`
    FOREIGN KEY (`owner_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_milestone_created_by_user`
    FOREIGN KEY (`created_by_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_milestone_updated_by_user`
    FOREIGN KEY (`updated_by_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `task_dependency` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `predecessor_task_id` BIGINT UNSIGNED NOT NULL,
  `successor_task_id` BIGINT UNSIGNED NOT NULL,
  `dependency_type` VARCHAR(50) NOT NULL DEFAULT 'finish_to_start',
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_task_dependency_pair` (`predecessor_task_id`, `successor_task_id`, `dependency_type`),
  KEY `idx_task_dependency_successor` (`successor_task_id`),
  CONSTRAINT `fk_task_dependency_predecessor_task`
    FOREIGN KEY (`predecessor_task_id`) REFERENCES `task` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_task_dependency_successor_task`
    FOREIGN KEY (`successor_task_id`) REFERENCES `task` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `task_update` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id` BIGINT UNSIGNED NOT NULL,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `update_type` VARCHAR(50) NOT NULL DEFAULT 'progress',
  `progress_percent_after` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `summary` TEXT NULL,
  `blockers_summary` TEXT NULL,
  `next_step` TEXT NULL,
  `confidence_level` VARCHAR(50) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_task_update_task` (`task_id`),
  KEY `idx_task_update_project` (`project_id`),
  KEY `idx_task_update_user` (`user_id`),
  KEY `idx_task_update_created_at` (`created_at`),
  CONSTRAINT `fk_task_update_task`
    FOREIGN KEY (`task_id`) REFERENCES `task` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_task_update_project`
    FOREIGN KEY (`project_id`) REFERENCES `project` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_task_update_user`
    FOREIGN KEY (`user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `project_update` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `summary` TEXT NULL,
  `completion_percent_after` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `health_status_after` VARCHAR(50) NOT NULL DEFAULT 'green',
  `major_risks` TEXT NULL,
  `next_actions` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_project_update_project` (`project_id`),
  KEY `idx_project_update_user` (`user_id`),
  KEY `idx_project_update_created_at` (`created_at`),
  CONSTRAINT `fk_project_update_project`
    FOREIGN KEY (`project_id`) REFERENCES `project` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_project_update_user`
    FOREIGN KEY (`user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `blocker` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `task_id` BIGINT UNSIGNED NULL,
  `title` VARCHAR(220) NOT NULL,
  `description` TEXT NULL,
  `blocker_type` VARCHAR(50) NOT NULL DEFAULT 'general',
  `severity_level` VARCHAR(50) NOT NULL DEFAULT 'medium',
  `owner_user_id` BIGINT UNSIGNED NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'open',
  `detected_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` DATETIME NULL,
  `resolution_notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_blocker_project` (`project_id`),
  KEY `idx_blocker_task` (`task_id`),
  KEY `idx_blocker_owner` (`owner_user_id`),
  KEY `idx_blocker_status` (`status`),
  CONSTRAINT `fk_blocker_project`
    FOREIGN KEY (`project_id`) REFERENCES `project` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_blocker_task`
    FOREIGN KEY (`task_id`) REFERENCES `task` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_blocker_owner_user`
    FOREIGN KEY (`owner_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `project_tag` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `description` TEXT NULL,
  `color_hex` VARCHAR(20) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_tag_org_name` (`organization_id`, `name`),
  CONSTRAINT `fk_project_tag_organization`
    FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `project_tag_link` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `project_tag_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_tag_link` (`project_id`, `project_tag_id`),
  CONSTRAINT `fk_project_tag_link_project`
    FOREIGN KEY (`project_id`) REFERENCES `project` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_project_tag_link_project_tag`
    FOREIGN KEY (`project_tag_id`) REFERENCES `project_tag` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `task_tag_link` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id` BIGINT UNSIGNED NOT NULL,
  `project_tag_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_task_tag_link` (`task_id`, `project_tag_id`),
  CONSTRAINT `fk_task_tag_link_task`
    FOREIGN KEY (`task_id`) REFERENCES `task` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_task_tag_link_project_tag`
    FOREIGN KEY (`project_tag_id`) REFERENCES `project_tag` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `daily_checkin` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `checkin_date` DATE NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'submitted',
  `summary_yesterday` TEXT NULL,
  `focus_today` TEXT NULL,
  `blockers` TEXT NULL,
  `general_notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_daily_checkin_user_date` (`user_id`, `checkin_date`),
  KEY `idx_daily_checkin_org` (`organization_id`),
  KEY `idx_daily_checkin_date` (`checkin_date`),
  CONSTRAINT `fk_daily_checkin_organization`
    FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_daily_checkin_user`
    FOREIGN KEY (`user_id`) REFERENCES `user_account` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `daily_checkin_item` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `daily_checkin_id` BIGINT UNSIGNED NOT NULL,
  `project_id` BIGINT UNSIGNED NULL,
  `task_id` BIGINT UNSIGNED NULL,
  `item_type` VARCHAR(50) NOT NULL DEFAULT 'planned',
  `content` TEXT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_daily_checkin_item_checkin` (`daily_checkin_id`),
  KEY `idx_daily_checkin_item_project` (`project_id`),
  KEY `idx_daily_checkin_item_task` (`task_id`),
  CONSTRAINT `fk_daily_checkin_item_daily_checkin`
    FOREIGN KEY (`daily_checkin_id`) REFERENCES `daily_checkin` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_daily_checkin_item_project`
    FOREIGN KEY (`project_id`) REFERENCES `project` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_daily_checkin_item_task`
    FOREIGN KEY (`task_id`) REFERENCES `task` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MÓDULO 4: Reuniones, decisiones y seguimiento
-- ============================================================================

CREATE TABLE IF NOT EXISTS `meeting` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(220) NOT NULL,
  `meeting_type` VARCHAR(50) NOT NULL DEFAULT 'internal',
  `scheduled_start_at` DATETIME NULL,
  `scheduled_end_at` DATETIME NULL,
  `actual_start_at` DATETIME NULL,
  `actual_end_at` DATETIME NULL,
  `facilitator_user_id` BIGINT UNSIGNED NULL,
  `summary` TEXT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'scheduled',
  `created_by_user_id` BIGINT UNSIGNED NULL,
  `updated_by_user_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_meeting_org` (`organization_id`),
  KEY `idx_meeting_start` (`scheduled_start_at`),
  KEY `idx_meeting_status` (`status`),
  KEY `idx_meeting_facilitator` (`facilitator_user_id`),
  CONSTRAINT `fk_meeting_organization`
    FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_meeting_facilitator_user`
    FOREIGN KEY (`facilitator_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_meeting_created_by_user`
    FOREIGN KEY (`created_by_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_meeting_updated_by_user`
    FOREIGN KEY (`updated_by_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `meeting_participant` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `meeting_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `participation_role` VARCHAR(50) NOT NULL DEFAULT 'participant',
  `attended` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_meeting_participant_meeting_user` (`meeting_id`, `user_id`),
  KEY `idx_meeting_participant_user` (`user_id`),
  CONSTRAINT `fk_meeting_participant_meeting`
    FOREIGN KEY (`meeting_id`) REFERENCES `meeting` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_meeting_participant_user`
    FOREIGN KEY (`user_id`) REFERENCES `user_account` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `meeting_note` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `meeting_id` BIGINT UNSIGNED NOT NULL,
  `note_type` VARCHAR(50) NOT NULL DEFAULT 'general',
  `content` TEXT NOT NULL,
  `created_by_user_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_meeting_note_meeting` (`meeting_id`),
  CONSTRAINT `fk_meeting_note_meeting`
    FOREIGN KEY (`meeting_id`) REFERENCES `meeting` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_meeting_note_created_by_user`
    FOREIGN KEY (`created_by_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `decision_record` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `meeting_id` BIGINT UNSIGNED NULL,
  `project_id` BIGINT UNSIGNED NULL,
  `business_area_id` BIGINT UNSIGNED NULL,
  `title` VARCHAR(220) NOT NULL,
  `description` TEXT NOT NULL,
  `rationale` TEXT NULL,
  `decision_status` VARCHAR(50) NOT NULL DEFAULT 'approved',
  `impact_level` VARCHAR(50) NOT NULL DEFAULT 'medium',
  `effective_date` DATE NULL,
  `owner_user_id` BIGINT UNSIGNED NULL,
  `created_by_user_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_decision_record_meeting` (`meeting_id`),
  KEY `idx_decision_record_project` (`project_id`),
  KEY `idx_decision_record_area` (`business_area_id`),
  KEY `idx_decision_record_owner` (`owner_user_id`),
  CONSTRAINT `fk_decision_record_meeting`
    FOREIGN KEY (`meeting_id`) REFERENCES `meeting` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_decision_record_project`
    FOREIGN KEY (`project_id`) REFERENCES `project` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_decision_record_business_area`
    FOREIGN KEY (`business_area_id`) REFERENCES `business_area` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_decision_record_owner_user`
    FOREIGN KEY (`owner_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_decision_record_created_by_user`
    FOREIGN KEY (`created_by_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `decision_link` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `decision_record_id` BIGINT UNSIGNED NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` BIGINT UNSIGNED NOT NULL,
  `relation_type` VARCHAR(50) NOT NULL DEFAULT 'affects',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_decision_link_decision` (`decision_record_id`),
  KEY `idx_decision_link_entity` (`entity_type`, `entity_id`),
  CONSTRAINT `fk_decision_link_decision_record`
    FOREIGN KEY (`decision_record_id`) REFERENCES `decision_record` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `follow_up_item` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `meeting_id` BIGINT UNSIGNED NULL,
  `decision_record_id` BIGINT UNSIGNED NULL,
  `task_id` BIGINT UNSIGNED NULL,
  `title` VARCHAR(220) NOT NULL,
  `description` TEXT NULL,
  `assigned_user_id` BIGINT UNSIGNED NULL,
  `due_date` DATETIME NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
  `created_by_user_id` BIGINT UNSIGNED NULL,
  `updated_by_user_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_follow_up_item_meeting` (`meeting_id`),
  KEY `idx_follow_up_item_decision` (`decision_record_id`),
  KEY `idx_follow_up_item_task` (`task_id`),
  KEY `idx_follow_up_item_assigned` (`assigned_user_id`),
  CONSTRAINT `fk_follow_up_item_meeting`
    FOREIGN KEY (`meeting_id`) REFERENCES `meeting` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_follow_up_item_decision_record`
    FOREIGN KEY (`decision_record_id`) REFERENCES `decision_record` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_follow_up_item_task`
    FOREIGN KEY (`task_id`) REFERENCES `task` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_follow_up_item_assigned_user`
    FOREIGN KEY (`assigned_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_follow_up_item_created_by_user`
    FOREIGN KEY (`created_by_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_follow_up_item_updated_by_user`
    FOREIGN KEY (`updated_by_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MÓDULO 5: Conocimiento del negocio y documentación estructurada
-- ============================================================================

CREATE TABLE IF NOT EXISTS `knowledge_document` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `business_area_id` BIGINT UNSIGNED NULL,
  `project_id` BIGINT UNSIGNED NULL,
  `title` VARCHAR(220) NOT NULL,
  `document_type` VARCHAR(50) NOT NULL DEFAULT 'general',
  `storage_type` VARCHAR(50) NOT NULL DEFAULT 'drive',
  `external_url` VARCHAR(500) NULL,
  `version_label` VARCHAR(50) NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'draft',
  `owner_user_id` BIGINT UNSIGNED NULL,
  `summary` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_knowledge_document_org` (`organization_id`),
  KEY `idx_knowledge_document_area` (`business_area_id`),
  KEY `idx_knowledge_document_project` (`project_id`),
  KEY `idx_knowledge_document_owner` (`owner_user_id`),
  CONSTRAINT `fk_knowledge_document_organization`
    FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_knowledge_document_business_area`
    FOREIGN KEY (`business_area_id`) REFERENCES `business_area` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_knowledge_document_project`
    FOREIGN KEY (`project_id`) REFERENCES `project` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_knowledge_document_owner_user`
    FOREIGN KEY (`owner_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `knowledge_note` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `business_area_id` BIGINT UNSIGNED NULL,
  `project_id` BIGINT UNSIGNED NULL,
  `note_type` VARCHAR(50) NOT NULL DEFAULT 'general',
  `title` VARCHAR(220) NOT NULL,
  `content` TEXT NOT NULL,
  `importance_level` VARCHAR(50) NOT NULL DEFAULT 'medium',
  `owner_user_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_knowledge_note_org` (`organization_id`),
  KEY `idx_knowledge_note_area` (`business_area_id`),
  KEY `idx_knowledge_note_project` (`project_id`),
  KEY `idx_knowledge_note_owner` (`owner_user_id`),
  CONSTRAINT `fk_knowledge_note_organization`
    FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_knowledge_note_business_area`
    FOREIGN KEY (`business_area_id`) REFERENCES `business_area` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_knowledge_note_project`
    FOREIGN KEY (`project_id`) REFERENCES `project` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_knowledge_note_owner_user`
    FOREIGN KEY (`owner_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `policy_record` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `business_area_id` BIGINT UNSIGNED NULL,
  `title` VARCHAR(220) NOT NULL,
  `description` TEXT NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'active',
  `owner_user_id` BIGINT UNSIGNED NULL,
  `external_document_url` VARCHAR(500) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_policy_record_org` (`organization_id`),
  KEY `idx_policy_record_area` (`business_area_id`),
  KEY `idx_policy_record_owner` (`owner_user_id`),
  CONSTRAINT `fk_policy_record_organization`
    FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_policy_record_business_area`
    FOREIGN KEY (`business_area_id`) REFERENCES `business_area` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_policy_record_owner_user`
    FOREIGN KEY (`owner_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sop` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `business_area_id` BIGINT UNSIGNED NULL,
  `title` VARCHAR(220) NOT NULL,
  `objective` TEXT NULL,
  `scope` TEXT NULL,
  `current_status` VARCHAR(50) NOT NULL DEFAULT 'draft',
  `external_document_url` VARCHAR(500) NULL,
  `owner_user_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sop_org` (`organization_id`),
  KEY `idx_sop_area` (`business_area_id`),
  KEY `idx_sop_owner` (`owner_user_id`),
  CONSTRAINT `fk_sop_organization`
    FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sop_business_area`
    FOREIGN KEY (`business_area_id`) REFERENCES `business_area` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_sop_owner_user`
    FOREIGN KEY (`owner_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `business_hypothesis` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `business_area_id` BIGINT UNSIGNED NULL,
  `title` VARCHAR(220) NOT NULL,
  `description` TEXT NOT NULL,
  `expected_result` TEXT NULL,
  `current_status` VARCHAR(50) NOT NULL DEFAULT 'open',
  `owner_user_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_business_hypothesis_area` (`business_area_id`),
  KEY `idx_business_hypothesis_owner` (`owner_user_id`),
  CONSTRAINT `fk_business_hypothesis_business_area`
    FOREIGN KEY (`business_area_id`) REFERENCES `business_area` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_business_hypothesis_owner_user`
    FOREIGN KEY (`owner_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `learning_record` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source_type` VARCHAR(50) NOT NULL,
  `source_id` BIGINT UNSIGNED NULL,
  `title` VARCHAR(220) NOT NULL,
  `description` TEXT NOT NULL,
  `category` VARCHAR(50) NOT NULL DEFAULT 'general',
  `impact_level` VARCHAR(50) NOT NULL DEFAULT 'medium',
  `created_by_user_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_learning_record_source` (`source_type`, `source_id`),
  KEY `idx_learning_record_created_by_user` (`created_by_user_id`),
  CONSTRAINT `fk_learning_record_created_by_user`
    FOREIGN KEY (`created_by_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MÓDULO 6: Alertas, recordatorios y configuración de avisos
-- ============================================================================

CREATE TABLE IF NOT EXISTS `reminder` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `entity_type` VARCHAR(50) NULL,
  `entity_id` BIGINT UNSIGNED NULL,
  `title` VARCHAR(220) NOT NULL,
  `description` TEXT NULL,
  `remind_at` DATETIME NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
  `delivery_channel` VARCHAR(50) NOT NULL DEFAULT 'system',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reminder_user` (`user_id`),
  KEY `idx_reminder_remind_at` (`remind_at`),
  KEY `idx_reminder_status` (`status`),
  CONSTRAINT `fk_reminder_user`
    FOREIGN KEY (`user_id`) REFERENCES `user_account` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `alert_rule` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(180) NOT NULL,
  `description` TEXT NULL,
  `trigger_type` VARCHAR(80) NOT NULL,
  `scope_type` VARCHAR(50) NOT NULL DEFAULT 'organization',
  `config_json` LONGTEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alert_rule_org` (`organization_id`),
  KEY `idx_alert_rule_active` (`is_active`),
  CONSTRAINT `fk_alert_rule_organization`
    FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `alert_event` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `alert_rule_id` BIGINT UNSIGNED NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` BIGINT UNSIGNED NULL,
  `severity_level` VARCHAR(50) NOT NULL DEFAULT 'medium',
  `title` VARCHAR(220) NOT NULL,
  `description` TEXT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'open',
  `triggered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `acknowledged_by_user_id` BIGINT UNSIGNED NULL,
  `acknowledged_at` DATETIME NULL,
  `resolved_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alert_event_rule` (`alert_rule_id`),
  KEY `idx_alert_event_entity` (`entity_type`, `entity_id`),
  KEY `idx_alert_event_status` (`status`),
  KEY `idx_alert_event_triggered_at` (`triggered_at`),
  CONSTRAINT `fk_alert_event_alert_rule`
    FOREIGN KEY (`alert_rule_id`) REFERENCES `alert_rule` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_alert_event_acknowledged_by_user`
    FOREIGN KEY (`acknowledged_by_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notification_preference` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `channel_type` VARCHAR(50) NOT NULL,
  `alert_type` VARCHAR(80) NOT NULL,
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notification_preference` (`user_id`, `channel_type`, `alert_type`),
  CONSTRAINT `fk_notification_preference_user`
    FOREIGN KEY (`user_id`) REFERENCES `user_account` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MÓDULO 7: Capa de IA, sugerencias e insights
-- ============================================================================

CREATE TABLE IF NOT EXISTS `ai_suggestion` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `related_entity_type` VARCHAR(50) NOT NULL,
  `related_entity_id` BIGINT UNSIGNED NULL,
  `business_area_id` BIGINT UNSIGNED NULL,
  `project_id` BIGINT UNSIGNED NULL,
  `title` VARCHAR(220) NOT NULL,
  `description` TEXT NOT NULL,
  `suggestion_type` VARCHAR(80) NOT NULL DEFAULT 'general',
  `impact_estimate` VARCHAR(50) NULL,
  `priority_level` VARCHAR(50) NOT NULL DEFAULT 'medium',
  `review_status` VARCHAR(50) NOT NULL DEFAULT 'pending',
  `reviewed_by_user_id` BIGINT UNSIGNED NULL,
  `reviewed_at` DATETIME NULL,
  `implementation_task_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_suggestion_entity` (`related_entity_type`, `related_entity_id`),
  KEY `idx_ai_suggestion_area` (`business_area_id`),
  KEY `idx_ai_suggestion_project` (`project_id`),
  KEY `idx_ai_suggestion_review_status` (`review_status`),
  KEY `idx_ai_suggestion_implementation_task` (`implementation_task_id`),
  CONSTRAINT `fk_ai_suggestion_business_area`
    FOREIGN KEY (`business_area_id`) REFERENCES `business_area` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ai_suggestion_project`
    FOREIGN KEY (`project_id`) REFERENCES `project` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ai_suggestion_reviewed_by_user`
    FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ai_suggestion_implementation_task`
    FOREIGN KEY (`implementation_task_id`) REFERENCES `task` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_insight` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(220) NOT NULL,
  `description` TEXT NOT NULL,
  `source_scope` VARCHAR(80) NOT NULL DEFAULT 'system',
  `severity_level` VARCHAR(50) NOT NULL DEFAULT 'medium',
  `confidence_score` DECIMAL(5,2) NULL,
  `related_entity_type` VARCHAR(50) NULL,
  `related_entity_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_insight_entity` (`related_entity_type`, `related_entity_id`),
  KEY `idx_ai_insight_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_action_proposal` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `proposal_type` VARCHAR(80) NOT NULL,
  `related_entity_type` VARCHAR(50) NULL,
  `related_entity_id` BIGINT UNSIGNED NULL,
  `payload_json` LONGTEXT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'pending_review',
  `reviewed_by_user_id` BIGINT UNSIGNED NULL,
  `reviewed_at` DATETIME NULL,
  `executed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_action_proposal_entity` (`related_entity_type`, `related_entity_id`),
  KEY `idx_ai_action_proposal_status` (`status`),
  CONSTRAINT `fk_ai_action_proposal_reviewed_by_user`
    FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_context_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `interaction_source` VARCHAR(80) NOT NULL,
  `source_type` VARCHAR(80) NOT NULL,
  `source_id` BIGINT UNSIGNED NULL,
  `purpose` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_context_log_source` (`source_type`, `source_id`),
  KEY `idx_ai_context_log_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `automation_rule` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(180) NOT NULL,
  `description` TEXT NULL,
  `trigger_type` VARCHAR(80) NOT NULL,
  `action_type` VARCHAR(80) NOT NULL,
  `config_json` LONGTEXT NULL,
  `requires_approval` TINYINT(1) NOT NULL DEFAULT 1,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_automation_rule_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `automation_run` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `automation_rule_id` BIGINT UNSIGNED NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'running',
  `execution_summary` TEXT NULL,
  `triggered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_automation_run_rule` (`automation_rule_id`),
  KEY `idx_automation_run_status` (`status`),
  KEY `idx_automation_run_triggered_at` (`triggered_at`),
  CONSTRAINT `fk_automation_run_automation_rule`
    FOREIGN KEY (`automation_rule_id`) REFERENCES `automation_rule` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MÓDULO 8: Integraciones y sistemas externos
-- ============================================================================

CREATE TABLE IF NOT EXISTS `external_system` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `system_type` VARCHAR(80) NOT NULL,
  `description` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_external_system_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `external_entity_link` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `external_system_id` BIGINT UNSIGNED NOT NULL,
  `internal_entity_type` VARCHAR(50) NOT NULL,
  `internal_entity_id` BIGINT UNSIGNED NOT NULL,
  `external_entity_type` VARCHAR(50) NOT NULL,
  `external_entity_id` VARCHAR(190) NOT NULL,
  `reference_label` VARCHAR(190) NULL,
  `metadata_json` LONGTEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_external_entity_link` (`external_system_id`, `internal_entity_type`, `internal_entity_id`, `external_entity_type`, `external_entity_id`),
  KEY `idx_external_entity_link_internal` (`internal_entity_type`, `internal_entity_id`),
  KEY `idx_external_entity_link_external` (`external_entity_type`, `external_entity_id`),
  CONSTRAINT `fk_external_entity_link_external_system`
    FOREIGN KEY (`external_system_id`) REFERENCES `external_system` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sync_event` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `external_system_id` BIGINT UNSIGNED NOT NULL,
  `event_type` VARCHAR(80) NOT NULL,
  `internal_entity_type` VARCHAR(50) NULL,
  `internal_entity_id` BIGINT UNSIGNED NULL,
  `external_entity_type` VARCHAR(50) NULL,
  `external_entity_id` VARCHAR(190) NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
  `payload_summary` TEXT NULL,
  `occurred_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sync_event_system` (`external_system_id`),
  KEY `idx_sync_event_status` (`status`),
  KEY `idx_sync_event_occurred_at` (`occurred_at`),
  CONSTRAINT `fk_sync_event_external_system`
    FOREIGN KEY (`external_system_id`) REFERENCES `external_system` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `integration_note` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `external_system_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(220) NOT NULL,
  `description` TEXT NOT NULL,
  `note_type` VARCHAR(50) NOT NULL DEFAULT 'general',
  `created_by_user_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_integration_note_system` (`external_system_id`),
  CONSTRAINT `fk_integration_note_external_system`
    FOREIGN KEY (`external_system_id`) REFERENCES `external_system` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_integration_note_created_by_user`
    FOREIGN KEY (`created_by_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MÓDULO 9: Auditoría y trazabilidad
-- ============================================================================

CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL,
  `action_type` VARCHAR(80) NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` BIGINT UNSIGNED NULL,
  `field_name` VARCHAR(120) NULL,
  `old_value` TEXT NULL,
  `new_value` TEXT NULL,
  `change_summary` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_log_user` (`user_id`),
  KEY `idx_audit_log_entity` (`entity_type`, `entity_id`),
  KEY `idx_audit_log_created_at` (`created_at`),
  CONSTRAINT `fk_audit_log_user`
    FOREIGN KEY (`user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `status_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` BIGINT UNSIGNED NOT NULL,
  `old_status` VARCHAR(50) NULL,
  `new_status` VARCHAR(50) NOT NULL,
  `changed_by_user_id` BIGINT UNSIGNED NULL,
  `changed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status_history_entity` (`entity_type`, `entity_id`),
  KEY `idx_status_history_changed_by` (`changed_by_user_id`),
  KEY `idx_status_history_changed_at` (`changed_at`),
  CONSTRAINT `fk_status_history_changed_by_user`
    FOREIGN KEY (`changed_by_user_id`) REFERENCES `user_account` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MÓDULO 10: Catálogos auxiliares (flexibles)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `lookup_catalog` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `description` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lookup_catalog_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lookup_value` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lookup_catalog_id` BIGINT UNSIGNED NOT NULL,
  `code` VARCHAR(80) NOT NULL,
  `label` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lookup_value_catalog_code` (`lookup_catalog_id`, `code`),
  KEY `idx_lookup_value_active` (`is_active`),
  CONSTRAINT `fk_lookup_value_lookup_catalog`
    FOREIGN KEY (`lookup_catalog_id`) REFERENCES `lookup_catalog` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
