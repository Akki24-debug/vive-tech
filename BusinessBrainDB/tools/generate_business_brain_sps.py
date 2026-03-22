#!/usr/bin/env python3
from __future__ import annotations

import re
from dataclasses import dataclass
from pathlib import Path
from typing import Dict, List, Optional


ROOT = Path(r"C:\Users\ragnarok\Documents\repos\Proyecto VLV\BusinessBrainDB")
SCHEMA_PATH = ROOT / "schema" / "001_business_brain_schema.sql"
SP_ROOT = ROOT / "stored-procedures"
DOCS_PATH = ROOT / "docs" / "BUSINESS_BRAIN_SQL_SP_REFERENCE.md"
INDIVIDUAL_SP_ROOT = SP_ROOT / "pms-style"


STATUS_COLUMNS = [
    "status",
    "current_status",
    "review_status",
    "decision_status",
    "employment_status",
]


READ_ONLY_TABLES = {"audit_log", "status_history"}
CREATE_ONLY_TABLES = {
    "integration_note",
    "learning_record",
    "meeting_note",
    "project_update",
    "task_update",
    "ai_context_log",
    "ai_insight",
}
DELETE_ALLOWED_TABLES = {
    "user_role",
    "project_member",
    "project_objective_link",
    "task_dependency",
    "project_tag_link",
    "task_tag_link",
    "meeting_participant",
    "decision_link",
    "external_entity_link",
    "lookup_value",
    "user_area_assignment",
}


DOMAIN_TABLES = {
    "helpers": [],
    "10_core_entities": [
        "organization",
        "user_account",
        "role",
        "user_role",
        "business_area",
        "user_area_assignment",
        "user_capacity_profile",
        "business_line",
        "business_priority",
        "objective_record",
    ],
    "20_execution": [
        "project_category",
        "initiative",
        "project",
        "project_member",
        "project_objective_link",
        "subproject",
        "task",
        "milestone",
        "task_dependency",
        "task_update",
        "project_update",
        "blocker",
        "project_tag",
        "project_tag_link",
        "task_tag_link",
        "daily_checkin",
        "daily_checkin_item",
    ],
    "30_meetings_and_followup": [
        "meeting",
        "meeting_participant",
        "meeting_note",
        "decision_record",
        "decision_link",
        "follow_up_item",
    ],
    "40_knowledge": [
        "knowledge_document",
        "knowledge_note",
        "policy_record",
        "sop",
        "business_hypothesis",
        "learning_record",
    ],
    "50_alerts_ai": [
        "reminder",
        "alert_rule",
        "alert_event",
        "notification_preference",
        "ai_suggestion",
        "ai_insight",
        "ai_action_proposal",
        "ai_context_log",
        "automation_rule",
        "automation_run",
    ],
    "60_integrations_governance": [
        "external_system",
        "external_entity_link",
        "sync_event",
        "integration_note",
        "audit_log",
        "status_history",
        "lookup_catalog",
        "lookup_value",
    ],
    "90_composite_flows": [],
}


FLOW_FILES = {
    "90_composite_flows.sql": [
        "sp_user_role_sync",
        "sp_project_member_sync",
        "sp_project_objective_link_sync",
        "sp_project_tag_sync",
        "sp_task_tag_sync",
        "sp_task_status_update",
        "sp_project_status_update",
        "sp_blocker_resolve",
        "sp_meeting_close",
        "sp_decision_record_apply",
        "sp_follow_up_complete",
        "sp_knowledge_document_publish",
        "sp_policy_record_activate",
        "sp_sop_publish",
        "sp_alert_event_resolve",
        "sp_ai_suggestion_review",
        "sp_automation_run_finalize",
        "sp_external_entity_link_sync",
    ]
}


@dataclass
class Column:
    name: str
    sql_type: str
    data_type: str
    nullable: bool
    has_default: bool


@dataclass
class Table:
    name: str
    columns: List[Column]

    @property
    def column_map(self) -> Dict[str, Column]:
        return {column.name: column for column in self.columns}

    @property
    def status_column(self) -> Optional[str]:
        for candidate in STATUS_COLUMNS:
            if candidate in self.column_map:
                return candidate
        return None

    @property
    def has_is_active(self) -> bool:
        return "is_active" in self.column_map


def parse_schema(schema_path: Path) -> Dict[str, Table]:
    content = schema_path.read_text(encoding="utf-8")
    tables: Dict[str, Table] = {}
    current_table: Optional[str] = None
    current_columns: List[Column] = []

    create_re = re.compile(r"CREATE TABLE IF NOT EXISTS `([^`]+)` \(")
    column_re = re.compile(r"\s*`([^`]+)`\s+(.+?)(?:,)?$")

    for raw_line in content.splitlines():
        line = raw_line.rstrip()
        create_match = create_re.search(line)
        if create_match:
            current_table = create_match.group(1)
            current_columns = []
            continue

        if current_table and line.startswith(") ENGINE="):
            tables[current_table] = Table(name=current_table, columns=current_columns)
            current_table = None
            current_columns = []
            continue

        if not current_table:
            continue

        if not line.lstrip().startswith("`"):
            continue

        match = column_re.match(line.strip())
        if not match:
            continue
        name = match.group(1)
        remainder = match.group(2)
        parts = remainder.split()
        sql_type = parts[0]
        if len(parts) > 1 and parts[1].lower() == "unsigned":
            sql_type = f"{sql_type} UNSIGNED"
        data_type = sql_type.split("(")[0].split()[0].lower()
        nullable = "NOT NULL" not in remainder.upper()
        has_default = "DEFAULT" in remainder.upper()
        current_columns.append(
            Column(
                name=name,
                sql_type=sql_type,
                data_type=data_type,
                nullable=nullable,
                has_default=has_default,
            )
        )
    return tables


def sql_param_type(column: Column) -> str:
    return column.sql_type


def is_mutable(column_name: str) -> bool:
    return column_name not in {"id", "created_at", "updated_at"}


def is_create_only_table(table_name: str) -> bool:
    return table_name in CREATE_ONLY_TABLES


def is_read_only_table(table_name: str) -> bool:
    return table_name in READ_ONLY_TABLES


def allow_delete(table_name: str) -> bool:
    return table_name in DELETE_ALLOWED_TABLES


def bootstrap_allowed(table_name: str) -> bool:
    return table_name in {"organization", "user_account", "role", "user_role"}


def procedure_header(name: str, purpose: str, tables_touched: str, security: str, outputs: str, example: str) -> str:
    return (
        "/**\n"
        f" * Procedure: {name}\n"
        f" * Purpose: {purpose}\n"
        f" * Tables touched: {tables_touched}\n"
        f" * Security: {security}\n"
        f" * Output: {outputs}\n"
        f" * Example: {example}\n"
        " */\n"
    )


def resolve_org_sql(table_name: str, mode: str) -> str:
    if table_name == "organization":
        if mode == "create":
            return "SET v_organization_id = NULL;"
        return "SET v_organization_id = p_id;"

    direct_org_tables = {
        "user_account",
        "business_area",
        "business_line",
        "business_priority",
        "daily_checkin",
        "initiative",
        "knowledge_document",
        "knowledge_note",
        "meeting",
        "objective_record",
        "policy_record",
        "project",
        "project_category",
        "project_tag",
        "sop",
        "alert_rule",
    }
    if table_name in direct_org_tables:
        if mode == "create":
            return "SET v_organization_id = p_organization_id;"
        return f"SELECT organization_id INTO v_organization_id FROM {table_name} WHERE id = p_id LIMIT 1;"

    one_hop = {
        "user_area_assignment": ("business_area", "business_area_id"),
        "user_capacity_profile": ("user_account", "user_id"),
        "project_member": ("project", "project_id"),
        "project_objective_link": ("project", "project_id"),
        "subproject": ("project", "project_id"),
        "task_update": ("project", "project_id"),
        "project_update": ("project", "project_id"),
        "blocker": ("project", "project_id"),
        "project_tag_link": ("project", "project_id"),
        "daily_checkin_item": ("daily_checkin", "daily_checkin_id"),
        "meeting_participant": ("meeting", "meeting_id"),
        "meeting_note": ("meeting", "meeting_id"),
        "business_hypothesis": ("business_area", "business_area_id"),
        "reminder": ("user_account", "user_id"),
        "notification_preference": ("user_account", "user_id"),
        "alert_event": ("alert_rule", "alert_rule_id"),
        "lookup_value": ("lookup_catalog", "lookup_catalog_id"),
    }
    if table_name in one_hop:
        parent_table, fk_column = one_hop[table_name]
        if mode == "create":
            return (
                f"SELECT organization_id INTO v_organization_id "
                f"FROM {parent_table} WHERE id = p_{fk_column} LIMIT 1;"
            )
        return (
            f"SELECT p.organization_id INTO v_organization_id "
            f"FROM {table_name} t "
            f"JOIN {parent_table} p ON p.id = t.{fk_column} "
            f"WHERE t.id = p_id LIMIT 1;"
        )

    if table_name == "task":
        if mode == "create":
            return "SELECT organization_id INTO v_organization_id FROM project WHERE id = p_project_id LIMIT 1;"
        return (
            "SELECT p.organization_id INTO v_organization_id "
            "FROM task t JOIN project p ON p.id = t.project_id "
            "WHERE t.id = p_id LIMIT 1;"
        )

    if table_name == "milestone":
        if mode == "create":
            return "SELECT organization_id INTO v_organization_id FROM project WHERE id = p_project_id LIMIT 1;"
        return (
            "SELECT p.organization_id INTO v_organization_id "
            "FROM milestone t JOIN project p ON p.id = t.project_id "
            "WHERE t.id = p_id LIMIT 1;"
        )

    if table_name == "task_dependency":
        if mode == "create":
            return (
                "SELECT p.organization_id INTO v_organization_id "
                "FROM task t JOIN project p ON p.id = t.project_id "
                "WHERE t.id = p_predecessor_task_id LIMIT 1;"
            )
        return (
            "SELECT p.organization_id INTO v_organization_id "
            "FROM task_dependency t "
            "JOIN task tt ON tt.id = t.predecessor_task_id "
            "JOIN project p ON p.id = tt.project_id "
            "WHERE t.id = p_id LIMIT 1;"
        )

    if table_name == "task_tag_link":
        if mode == "create":
            return (
                "SELECT p.organization_id INTO v_organization_id "
                "FROM task t JOIN project p ON p.id = t.project_id "
                "WHERE t.id = p_task_id LIMIT 1;"
            )
        return (
            "SELECT p.organization_id INTO v_organization_id "
            "FROM task_tag_link l "
            "JOIN task t ON t.id = l.task_id "
            "JOIN project p ON p.id = t.project_id "
            "WHERE l.id = p_id LIMIT 1;"
        )

    if table_name == "decision_record":
        if mode == "create":
            return (
                "SELECT COALESCE(m.organization_id, p.organization_id, a.organization_id) "
                "INTO v_organization_id "
                "FROM (SELECT 1 AS anchor) x "
                "LEFT JOIN meeting m ON m.id = p_meeting_id "
                "LEFT JOIN project p ON p.id = p_project_id "
                "LEFT JOIN business_area a ON a.id = p_business_area_id "
                "LIMIT 1;"
            )
        return (
            "SELECT COALESCE(m.organization_id, p.organization_id, a.organization_id) "
            "INTO v_organization_id "
            "FROM decision_record d "
            "LEFT JOIN meeting m ON m.id = d.meeting_id "
            "LEFT JOIN project p ON p.id = d.project_id "
            "LEFT JOIN business_area a ON a.id = d.business_area_id "
            "WHERE d.id = p_id LIMIT 1;"
        )

    if table_name == "follow_up_item":
        if mode == "create":
            return (
                "SELECT COALESCE(m.organization_id, p.organization_id, a.organization_id) "
                "INTO v_organization_id "
                "FROM (SELECT 1 AS anchor) x "
                "LEFT JOIN meeting m ON m.id = p_meeting_id "
                "LEFT JOIN task t ON t.id = p_task_id "
                "LEFT JOIN project p ON p.id = t.project_id "
                "LEFT JOIN decision_record d ON d.id = p_decision_record_id "
                "LEFT JOIN business_area a ON a.id = d.business_area_id "
                "LIMIT 1;"
            )
        return (
            "SELECT COALESCE(m.organization_id, p.organization_id, a.organization_id) "
            "INTO v_organization_id "
            "FROM follow_up_item f "
            "LEFT JOIN meeting m ON m.id = f.meeting_id "
            "LEFT JOIN task t ON t.id = f.task_id "
            "LEFT JOIN project p ON p.id = t.project_id "
            "LEFT JOIN decision_record d ON d.id = f.decision_record_id "
            "LEFT JOIN business_area a ON a.id = d.business_area_id "
            "WHERE f.id = p_id LIMIT 1;"
        )

    if table_name == "ai_suggestion":
        if mode == "create":
            return (
                "SELECT COALESCE(p.organization_id, a.organization_id) "
                "INTO v_organization_id "
                "FROM (SELECT 1 AS anchor) x "
                "LEFT JOIN project p ON p.id = p_project_id "
                "LEFT JOIN business_area a ON a.id = p_business_area_id "
                "LIMIT 1;"
            )
        return (
            "SELECT COALESCE(p.organization_id, a.organization_id) "
            "INTO v_organization_id "
            "FROM ai_suggestion s "
            "LEFT JOIN project p ON p.id = s.project_id "
            "LEFT JOIN business_area a ON a.id = s.business_area_id "
            "WHERE s.id = p_id LIMIT 1;"
        )

    return "SET v_organization_id = NULL;"


def data_org_filter(table_name: str) -> str:
    direct_org_tables = {
        "user_account",
        "business_area",
        "business_line",
        "business_priority",
        "daily_checkin",
        "initiative",
        "knowledge_document",
        "knowledge_note",
        "meeting",
        "objective_record",
        "policy_record",
        "project",
        "project_category",
        "project_tag",
        "sop",
        "alert_rule",
    }
    if table_name == "organization":
        return "(p_organization_id IS NULL OR p_organization_id = 0 OR t.id = p_organization_id)"
    if table_name in direct_org_tables:
        return "(p_organization_id IS NULL OR p_organization_id = 0 OR t.organization_id = p_organization_id)"
    via_exists = {
        "user_role": "EXISTS (SELECT 1 FROM user_account u WHERE u.id = t.user_id AND u.organization_id = p_organization_id)",
        "user_area_assignment": "EXISTS (SELECT 1 FROM business_area a WHERE a.id = t.business_area_id AND a.organization_id = p_organization_id)",
        "user_capacity_profile": "EXISTS (SELECT 1 FROM user_account u WHERE u.id = t.user_id AND u.organization_id = p_organization_id)",
        "project_member": "EXISTS (SELECT 1 FROM project p WHERE p.id = t.project_id AND p.organization_id = p_organization_id)",
        "project_objective_link": "EXISTS (SELECT 1 FROM project p WHERE p.id = t.project_id AND p.organization_id = p_organization_id)",
        "subproject": "EXISTS (SELECT 1 FROM project p WHERE p.id = t.project_id AND p.organization_id = p_organization_id)",
        "task": "EXISTS (SELECT 1 FROM project p WHERE p.id = t.project_id AND p.organization_id = p_organization_id)",
        "milestone": "EXISTS (SELECT 1 FROM project p WHERE p.id = t.project_id AND p.organization_id = p_organization_id)",
        "task_dependency": "EXISTS (SELECT 1 FROM task tt JOIN project p ON p.id = tt.project_id WHERE tt.id = t.predecessor_task_id AND p.organization_id = p_organization_id)",
        "task_update": "EXISTS (SELECT 1 FROM project p WHERE p.id = t.project_id AND p.organization_id = p_organization_id)",
        "project_update": "EXISTS (SELECT 1 FROM project p WHERE p.id = t.project_id AND p.organization_id = p_organization_id)",
        "blocker": "EXISTS (SELECT 1 FROM project p WHERE p.id = t.project_id AND p.organization_id = p_organization_id)",
        "project_tag_link": "EXISTS (SELECT 1 FROM project p WHERE p.id = t.project_id AND p.organization_id = p_organization_id)",
        "task_tag_link": "EXISTS (SELECT 1 FROM task tt JOIN project p ON p.id = tt.project_id WHERE tt.id = t.task_id AND p.organization_id = p_organization_id)",
        "daily_checkin_item": "EXISTS (SELECT 1 FROM daily_checkin d WHERE d.id = t.daily_checkin_id AND d.organization_id = p_organization_id)",
        "meeting_participant": "EXISTS (SELECT 1 FROM meeting m WHERE m.id = t.meeting_id AND m.organization_id = p_organization_id)",
        "meeting_note": "EXISTS (SELECT 1 FROM meeting m WHERE m.id = t.meeting_id AND m.organization_id = p_organization_id)",
        "decision_record": "(EXISTS (SELECT 1 FROM meeting m WHERE m.id = t.meeting_id AND m.organization_id = p_organization_id) OR EXISTS (SELECT 1 FROM project p WHERE p.id = t.project_id AND p.organization_id = p_organization_id) OR EXISTS (SELECT 1 FROM business_area a WHERE a.id = t.business_area_id AND a.organization_id = p_organization_id))",
        "follow_up_item": "(EXISTS (SELECT 1 FROM meeting m WHERE m.id = t.meeting_id AND m.organization_id = p_organization_id) OR EXISTS (SELECT 1 FROM task tt JOIN project p ON p.id = tt.project_id WHERE tt.id = t.task_id AND p.organization_id = p_organization_id) OR EXISTS (SELECT 1 FROM decision_record d LEFT JOIN meeting m ON m.id = d.meeting_id LEFT JOIN project p ON p.id = d.project_id LEFT JOIN business_area a ON a.id = d.business_area_id WHERE d.id = t.decision_record_id AND (m.organization_id = p_organization_id OR p.organization_id = p_organization_id OR a.organization_id = p_organization_id)))",
        "business_hypothesis": "EXISTS (SELECT 1 FROM business_area a WHERE a.id = t.business_area_id AND a.organization_id = p_organization_id)",
        "reminder": "EXISTS (SELECT 1 FROM user_account u WHERE u.id = t.user_id AND u.organization_id = p_organization_id)",
        "alert_event": "EXISTS (SELECT 1 FROM alert_rule ar WHERE ar.id = t.alert_rule_id AND ar.organization_id = p_organization_id)",
        "notification_preference": "EXISTS (SELECT 1 FROM user_account u WHERE u.id = t.user_id AND u.organization_id = p_organization_id)",
        "ai_suggestion": "(EXISTS (SELECT 1 FROM project p WHERE p.id = t.project_id AND p.organization_id = p_organization_id) OR EXISTS (SELECT 1 FROM business_area a WHERE a.id = t.business_area_id AND a.organization_id = p_organization_id))",
    }
    if table_name in via_exists:
        return f"(p_organization_id IS NULL OR p_organization_id = 0 OR {via_exists[table_name]})"
    return "1 = 1"


def data_scope_note(table_name: str) -> str:
    direct_org_tables = {
        "organization",
        "user_account",
        "business_area",
        "business_line",
        "business_priority",
        "daily_checkin",
        "initiative",
        "knowledge_document",
        "knowledge_note",
        "meeting",
        "objective_record",
        "policy_record",
        "project",
        "project_category",
        "project_tag",
        "sop",
        "alert_rule",
    }
    indirect_tables = {
        "user_role",
        "user_area_assignment",
        "user_capacity_profile",
        "project_member",
        "project_objective_link",
        "subproject",
        "task",
        "milestone",
        "task_dependency",
        "task_update",
        "project_update",
        "blocker",
        "project_tag_link",
        "task_tag_link",
        "daily_checkin_item",
        "meeting_participant",
        "meeting_note",
        "decision_record",
        "follow_up_item",
        "business_hypothesis",
        "reminder",
        "alert_event",
        "notification_preference",
        "ai_suggestion",
    }
    if table_name in direct_org_tables:
        return "Lectura. `p_organization_id` filtra de forma directa cuando la tabla tiene alcance organizacional."
    if table_name in indirect_tables:
        return "Lectura. `p_organization_id` filtra por alcance organizacional indirecto mediante joins o subconsultas."
    return "Lectura. Tabla global o tecnica; `p_organization_id` se acepta por contrato estandar pero se ignora por diseno."


def search_columns(table: Table) -> List[str]:
    preferred = [
        "name",
        "legal_name",
        "title",
        "description",
        "code",
        "display_name",
        "email",
        "role_summary",
        "summary",
        "content",
        "notes",
        "label",
        "external_entity_id",
        "reference_label",
        "system_type",
        "document_type",
        "meeting_type",
        "note_type",
        "category",
        "source_type",
        "purpose",
        "event_type",
        "trigger_type",
        "action_type",
        "objective",
        "scope",
        "vision_summary",
        "business_model_summary",
        "monetization_notes",
        "payload_summary",
        "execution_summary",
        "change_summary",
    ]
    column_names = {column.name for column in table.columns}
    return [name for name in preferred if name in column_names]


def render_data_proc(table: Table) -> str:
    searchable = search_columns(table)
    search_condition = "1 = 1"
    if searchable:
        predicates = [
            f"LOWER(COALESCE(t.{column}, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci)"
            for column in searchable
        ]
        search_condition = (
            "(p_search IS NULL OR TRIM(p_search) = '' OR "
            + " OR ".join(predicates)
            + ")"
        )

    active_condition = "1 = 1"
    if table.has_is_active:
        active_condition = "(COALESCE(p_only_active, 0) = 0 OR t.is_active = 1)"

    order_column = "created_at" if "created_at" in table.column_map else "id"

    header = procedure_header(
        name=f"sp_{table.name}_data",
        purpose=f"Consulta registros de `{table.name}` con filtros predecibles para IA e integraciones.",
        tables_touched=table.name,
        security=data_scope_note(table.name),
        outputs="Result set por SELECT con filas de la entidad.",
        example=f"CALL sp_{table.name}_data(NULL, NULL, NULL, NULL, 100);",
    )

    return (
        header
        + "DELIMITER $$\n\n"
        + f"DROP PROCEDURE IF EXISTS `sp_{table.name}_data` $$\n"
        + f"CREATE PROCEDURE `sp_{table.name}_data` (\n"
        + "  IN p_id BIGINT UNSIGNED,\n"
        + "  IN p_organization_id BIGINT UNSIGNED,\n"
        + "  IN p_search VARCHAR(255),\n"
        + "  IN p_only_active TINYINT,\n"
        + "  IN p_limit_rows INT\n"
        + ")\n"
        + "proc:BEGIN\n"
        + "  DECLARE v_limit_rows INT DEFAULT 100;\n\n"
        + "  SET v_limit_rows = COALESCE(NULLIF(p_limit_rows, 0), 100);\n"
        + "  IF v_limit_rows < 1 THEN\n"
        + "    SET v_limit_rows = 100;\n"
        + "  END IF;\n\n"
        + "  SELECT t.*\n"
        + f"  FROM `{table.name}` t\n"
        + "  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)\n"
        + f"    AND {data_org_filter(table.name)}\n"
        + f"    AND {active_condition}\n"
        + f"    AND {search_condition}\n"
        + f"  ORDER BY t.{order_column} DESC, t.id DESC\n"
        + "  LIMIT v_limit_rows;\n"
        + "END $$\n\n"
        + "DELIMITER ;\n\n"
    )


def required_create_checks(table: Table) -> str:
    lines: List[str] = []
    for column in table.columns:
        if not is_mutable(column.name):
            continue
        if column.nullable or column.has_default:
            continue
        if column.name in {"created_by_user_id", "updated_by_user_id"}:
            continue
        lines.append(
            f"  IF p_{column.name} IS NULL THEN\n"
            f"    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{column.name} is required';\n"
            "  END IF;\n"
        )
    return "".join(lines)


def create_insert_value(column_name: str) -> str:
    if column_name == "created_by_user_id":
        return "COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0))"
    if column_name == "updated_by_user_id":
        return "COALESCE(p_updated_by_user_id, NULLIF(p_actor_user_id, 0), COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0)))"
    return f"p_{column_name}"


def update_assignment(column_name: str) -> Optional[str]:
    if column_name == "created_by_user_id":
        return None
    if column_name == "updated_by_user_id":
        return "    updated_by_user_id = COALESCE(p_updated_by_user_id, NULLIF(p_actor_user_id, 0))"
    return f"    `{column_name}` = p_{column_name}"


def render_write_proc(table: Table) -> str:
    mutable_columns = [column for column in table.columns if is_mutable(column.name)]
    params = []
    if not is_create_only_table(table.name):
        params.append("  IN p_id BIGINT UNSIGNED")
    for column in mutable_columns:
        params.append(f"  IN p_{column.name} {sql_param_type(column)}")
    params.append("  IN p_actor_user_id BIGINT UNSIGNED")

    proc_name = f"sp_{table.name}_{'create' if is_create_only_table(table.name) else 'upsert'}"
    purpose = "Inserta registros append-only." if is_create_only_table(table.name) else "Crea o actualiza registros."
    example_args = []
    if not is_create_only_table(table.name):
        example_args.append("NULL")
    example_args.extend(["..." for _ in mutable_columns])
    example_args.append("1")
    header = procedure_header(
        name=proc_name,
        purpose=f"{purpose} Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.",
        tables_touched=f"{table.name}, audit_log" + (", status_history" if table.status_column else ""),
        security=(
            "Write seguro. Requiere `p_actor_user_id`, salvo bootstrap para tablas base."
            if bootstrap_allowed(table.name)
            else "Write seguro. Requiere `p_actor_user_id` valido."
        ),
        outputs="Result set por SELECT con el registro final.",
        example=f"CALL {proc_name}({', '.join(example_args)});",
    )

    declare_lines = [
        "  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;",
        "  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;",
        "  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;" if table.status_column else "",
        "  DECLARE v_new_status VARCHAR(50) DEFAULT NULL;" if table.status_column else "",
    ]
    declare_sql = "\n".join(line for line in declare_lines if line) + "\n\n"

    create_resolver = resolve_org_sql(table.name, "create")
    update_resolver = resolve_org_sql(table.name, "update")
    actor_allow = "1" if bootstrap_allowed(table.name) else "0"
    required_sql = required_create_checks(table)

    insert_columns = ", ".join(f"`{column.name}`" for column in mutable_columns)
    insert_values = ", ".join(create_insert_value(column.name) for column in mutable_columns)
    assignments = ",\n".join(
        line
        for line in (update_assignment(column.name) for column in mutable_columns)
        if line is not None
    )
    status_sql = ""
    if table.status_column:
        status_sql = (
            f"  SET v_new_status = (SELECT `{table.status_column}` FROM `{table.name}` WHERE id = v_target_id LIMIT 1);\n"
            "  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN\n"
            f"    CALL sp_status_history_insert('{table.name}', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via {proc_name}'));\n"
            "  END IF;\n\n"
        )

    if is_create_only_table(table.name):
        return (
            header
            + "DELIMITER $$\n\n"
            + f"DROP PROCEDURE IF EXISTS `{proc_name}` $$\n"
            + f"CREATE PROCEDURE `{proc_name}` (\n"
            + ",\n".join(params)
            + "\n)\n"
            + "proc:BEGIN\n"
            + declare_sql
            + f"{create_resolver}\n"
            + f"  CALL sp_actor_assert(p_actor_user_id, v_organization_id, {actor_allow});\n\n"
            + required_sql
            + f"  INSERT INTO `{table.name}` ({insert_columns})\n"
            + f"  VALUES ({insert_values});\n"
            + "  SET v_target_id = LAST_INSERT_ID();\n\n"
            + f"  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', '{table.name}', v_target_id, NULL, NULL, NULL, CONCAT('Created via {proc_name}'));\n\n"
            + status_sql
            + f"  SELECT * FROM `{table.name}` WHERE id = v_target_id LIMIT 1;\n"
            + "END $$\n\n"
            + "DELIMITER ;\n\n"
        )

    update_old_status = ""
    if table.status_column:
        update_old_status = f"    SELECT `{table.status_column}` INTO v_old_status FROM `{table.name}` WHERE id = p_id LIMIT 1;\n"

    return (
        header
        + "DELIMITER $$\n\n"
        + f"DROP PROCEDURE IF EXISTS `{proc_name}` $$\n"
        + f"CREATE PROCEDURE `{proc_name}` (\n"
        + ",\n".join(params)
        + "\n)\n"
        + "proc:BEGIN\n"
        + declare_sql
        + "  IF p_id IS NULL OR p_id = 0 THEN\n"
        + f"    {create_resolver}\n"
        + f"    CALL sp_actor_assert(p_actor_user_id, v_organization_id, {actor_allow});\n\n"
        + required_sql
        + f"    INSERT INTO `{table.name}` ({insert_columns})\n"
        + f"    VALUES ({insert_values});\n"
        + "    SET v_target_id = LAST_INSERT_ID();\n"
        + f"    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', '{table.name}', v_target_id, NULL, NULL, NULL, CONCAT('Created via {proc_name}'));\n"
        + "  ELSE\n"
        + f"    {update_resolver}\n"
        + f"    CALL sp_actor_assert(p_actor_user_id, v_organization_id, {actor_allow});\n"
        + f"{update_old_status}"
        + f"    UPDATE `{table.name}`\n"
        + "    SET\n"
        + assignments
        + "\n"
        + "    WHERE id = p_id;\n\n"
        + "    IF ROW_COUNT() = 0 THEN\n"
        + "      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';\n"
        + "    END IF;\n"
        + "    SET v_target_id = p_id;\n"
        + f"    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', '{table.name}', v_target_id, NULL, NULL, NULL, CONCAT('Updated via {proc_name}'));\n"
        + "  END IF;\n\n"
        + status_sql
        + f"  SELECT * FROM `{table.name}` WHERE id = v_target_id LIMIT 1;\n"
        + "END $$\n\n"
        + "DELIMITER ;\n\n"
    )


def render_delete_proc(table: Table) -> str:
    header = procedure_header(
        name=f"sp_{table.name}_delete",
        purpose=f"Elimina fisicamente registros de `{table.name}` solo donde el modelo lo permite.",
        tables_touched=f"{table.name}, audit_log",
        security="Requiere actor valido y aplica solo a tablas puente o tecnicas.",
        outputs="Result set de confirmacion de borrado.",
        example=f"CALL sp_{table.name}_delete(1, 1, 'cleanup');",
    )
    return (
        header
        + "DELIMITER $$\n\n"
        + f"DROP PROCEDURE IF EXISTS `sp_{table.name}_delete` $$\n"
        + f"CREATE PROCEDURE `sp_{table.name}_delete` (\n"
        + "  IN p_id BIGINT UNSIGNED,\n"
        + "  IN p_actor_user_id BIGINT UNSIGNED,\n"
        + "  IN p_reason TEXT\n"
        + ")\n"
        + "proc:BEGIN\n"
        + "  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;\n\n"
        + f"  {resolve_org_sql(table.name, 'update')}\n"
        + "  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);\n\n"
        + f"  DELETE FROM `{table.name}` WHERE id = p_id;\n"
        + "  IF ROW_COUNT() = 0 THEN\n"
        + "    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';\n"
        + "  END IF;\n\n"
        + f"  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'delete', '{table.name}', p_id, NULL, NULL, NULL, COALESCE(NULLIF(p_reason, ''), 'Deleted via stored procedure'));\n\n"
        + "  SELECT 'deleted' AS operation_status, p_id AS deleted_id;\n"
        + "END $$\n\n"
        + "DELIMITER ;\n\n"
    )


def render_helpers() -> str:
    parts = []
    parts.append(
        procedure_header(
            name="sp_bootstrap_state_data",
            purpose="Expone si el modo bootstrap sigue abierto por organizacion y a nivel global.",
            tables_touched="organization, user_account",
            security="Lectura. Sin actor.",
            outputs="Result set con conteo de usuarios y banderas de bootstrap.",
            example="CALL sp_bootstrap_state_data(NULL);",
        )
        + "DELIMITER $$\n\n"
        + "DROP PROCEDURE IF EXISTS `sp_bootstrap_state_data` $$\n"
        + "CREATE PROCEDURE `sp_bootstrap_state_data` (\n"
        + "  IN p_organization_id BIGINT UNSIGNED\n"
        + ")\n"
        + "proc:BEGIN\n"
        + "  SELECT\n"
        + "    p_organization_id AS organization_id,\n"
        + "    (SELECT COUNT(*) FROM user_account) AS total_users_global,\n"
        + "    CASE\n"
        + "      WHEN p_organization_id IS NULL OR p_organization_id = 0 THEN NULL\n"
        + "      ELSE (SELECT COUNT(*) FROM user_account WHERE organization_id = p_organization_id)\n"
        + "    END AS total_users_in_organization,\n"
        + "    CASE WHEN (SELECT COUNT(*) FROM user_account) = 0 THEN 1 ELSE 0 END AS bootstrap_open_global,\n"
        + "    CASE\n"
        + "      WHEN p_organization_id IS NULL OR p_organization_id = 0 THEN NULL\n"
        + "      WHEN (SELECT COUNT(*) FROM user_account WHERE organization_id = p_organization_id) = 0 THEN 1 ELSE 0\n"
        + "    END AS bootstrap_open_for_organization;\n"
        + "END $$\n\n"
        + "DELIMITER ;\n\n"
    )

    parts.append(
        procedure_header(
            name="sp_actor_assert",
            purpose="Valida actor y pertenencia organizacional, con soporte controlado para bootstrap.",
            tables_touched="user_account",
            security="Helper interno de seguridad para todos los writes.",
            outputs="No devuelve dataset. Lanza `SIGNAL 45000` si la validacion falla.",
            example="CALL sp_actor_assert(1, 1, 0);",
        )
        + "DELIMITER $$\n\n"
        + "DROP PROCEDURE IF EXISTS `sp_actor_assert` $$\n"
        + "CREATE PROCEDURE `sp_actor_assert` (\n"
        + "  IN p_actor_user_id BIGINT UNSIGNED,\n"
        + "  IN p_organization_id BIGINT UNSIGNED,\n"
        + "  IN p_allow_bootstrap TINYINT\n"
        + ")\n"
        + "proc:BEGIN\n"
        + "  DECLARE v_actor_org_id BIGINT UNSIGNED DEFAULT NULL;\n"
        + "  DECLARE v_total_users INT DEFAULT 0;\n"
        + "  DECLARE v_org_users INT DEFAULT 0;\n\n"
        + "  IF COALESCE(p_actor_user_id, 0) = 0 THEN\n"
        + "    IF COALESCE(p_allow_bootstrap, 0) = 0 THEN\n"
        + "      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Actor user id is required';\n"
        + "    END IF;\n"
        + "    SELECT COUNT(*) INTO v_total_users FROM user_account;\n"
        + "    IF p_organization_id IS NULL OR p_organization_id = 0 THEN\n"
        + "      IF v_total_users > 0 THEN\n"
        + "        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Bootstrap closed: actor required';\n"
        + "      END IF;\n"
        + "      LEAVE proc;\n"
        + "    END IF;\n"
        + "    SELECT COUNT(*) INTO v_org_users FROM user_account WHERE organization_id = p_organization_id;\n"
        + "    IF v_org_users > 0 THEN\n"
        + "      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Bootstrap closed for this organization';\n"
        + "    END IF;\n"
        + "    LEAVE proc;\n"
        + "  END IF;\n\n"
        + "  SELECT organization_id INTO v_actor_org_id\n"
        + "  FROM user_account\n"
        + "  WHERE id = p_actor_user_id AND is_active = 1\n"
        + "  LIMIT 1;\n"
        + "  IF v_actor_org_id IS NULL THEN\n"
        + "    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Actor user not found or inactive';\n"
        + "  END IF;\n"
        + "  IF p_organization_id IS NOT NULL AND p_organization_id <> 0 AND v_actor_org_id <> p_organization_id THEN\n"
        + "    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Actor does not belong to the target organization';\n"
        + "  END IF;\n"
        + "END $$\n\n"
        + "DELIMITER ;\n\n"
    )

    parts.append(
        procedure_header(
            name="sp_audit_log_insert",
            purpose="Inserta un registro estandarizado en `audit_log`.",
            tables_touched="audit_log",
            security="Helper interno. No valida actor por si mismo.",
            outputs="Sin salida. Inserta una fila en auditoria.",
            example="CALL sp_audit_log_insert(1, 'update', 'project', 10, NULL, NULL, NULL, 'Updated project');",
        )
        + "DELIMITER $$\n\n"
        + "DROP PROCEDURE IF EXISTS `sp_audit_log_insert` $$\n"
        + "CREATE PROCEDURE `sp_audit_log_insert` (\n"
        + "  IN p_user_id BIGINT UNSIGNED,\n"
        + "  IN p_action_type VARCHAR(80),\n"
        + "  IN p_entity_type VARCHAR(50),\n"
        + "  IN p_entity_id BIGINT UNSIGNED,\n"
        + "  IN p_field_name VARCHAR(120),\n"
        + "  IN p_old_value TEXT,\n"
        + "  IN p_new_value TEXT,\n"
        + "  IN p_change_summary TEXT\n"
        + ")\n"
        + "proc:BEGIN\n"
        + "  INSERT INTO audit_log (\n"
        + "    user_id, action_type, entity_type, entity_id, field_name, old_value, new_value, change_summary\n"
        + "  ) VALUES (\n"
        + "    NULLIF(p_user_id, 0),\n"
        + "    COALESCE(NULLIF(p_action_type, ''), 'unknown'),\n"
        + "    COALESCE(NULLIF(p_entity_type, ''), 'unknown'),\n"
        + "    p_entity_id,\n"
        + "    NULLIF(p_field_name, ''),\n"
        + "    p_old_value,\n"
        + "    p_new_value,\n"
        + "    p_change_summary\n"
        + "  );\n"
        + "END $$\n\n"
        + "DELIMITER ;\n\n"
    )

    parts.append(
        procedure_header(
            name="sp_status_history_insert",
            purpose="Inserta un registro estandarizado en `status_history`.",
            tables_touched="status_history",
            security="Helper interno. Se usa solo cuando cambia un campo de estado real.",
            outputs="Sin salida. Inserta una fila en historial de estado.",
            example="CALL sp_status_history_insert('task', 10, 'pending', 'done', 1, 'Manual close');",
        )
        + "DELIMITER $$\n\n"
        + "DROP PROCEDURE IF EXISTS `sp_status_history_insert` $$\n"
        + "CREATE PROCEDURE `sp_status_history_insert` (\n"
        + "  IN p_entity_type VARCHAR(50),\n"
        + "  IN p_entity_id BIGINT UNSIGNED,\n"
        + "  IN p_old_status VARCHAR(50),\n"
        + "  IN p_new_status VARCHAR(50),\n"
        + "  IN p_changed_by_user_id BIGINT UNSIGNED,\n"
        + "  IN p_notes TEXT\n"
        + ")\n"
        + "proc:BEGIN\n"
        + "  IF COALESCE(p_old_status, '__NULL__') = COALESCE(p_new_status, '__NULL__') THEN\n"
        + "    LEAVE proc;\n"
        + "  END IF;\n"
        + "  INSERT INTO status_history (\n"
        + "    entity_type, entity_id, old_status, new_status, changed_by_user_id, notes\n"
        + "  ) VALUES (\n"
        + "    p_entity_type,\n"
        + "    p_entity_id,\n"
        + "    p_old_status,\n"
        + "    p_new_status,\n"
        + "    NULLIF(p_changed_by_user_id, 0),\n"
        + "    p_notes\n"
        + "  );\n"
        + "END $$\n\n"
        + "DELIMITER ;\n\n"
    )

    return "".join(parts)


def flow_header(name: str, purpose: str, tables_touched: str, example: str) -> str:
    return procedure_header(
        name=name,
        purpose=purpose,
        tables_touched=tables_touched,
        security="Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.",
        outputs="Result set por SELECT con el estado final del flujo.",
        example=example,
    )


def render_flows() -> str:
    parts: List[str] = []
    parts.append(
        flow_header(
            "sp_user_role_sync",
            "Sincroniza completamente los roles de un usuario.",
            "user_account, user_role, audit_log",
            "CALL sp_user_role_sync(1, '1,2,3', 1);",
        )
        + "DELIMITER $$\n\n"
        + "DROP PROCEDURE IF EXISTS `sp_user_role_sync` $$\n"
        + "CREATE PROCEDURE `sp_user_role_sync` (\n"
        + "  IN p_user_id BIGINT UNSIGNED,\n"
        + "  IN p_role_ids_csv TEXT,\n"
        + "  IN p_actor_user_id BIGINT UNSIGNED\n"
        + ")\n"
        + "proc:BEGIN\n"
        + "  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;\n"
        + "  DECLARE v_csv TEXT;\n\n"
        + "  SELECT organization_id INTO v_organization_id FROM user_account WHERE id = p_user_id LIMIT 1;\n"
        + "  IF v_organization_id IS NULL THEN\n"
        + "    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User not found';\n"
        + "  END IF;\n"
        + "  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 1);\n"
        + "  SET v_csv = REPLACE(COALESCE(p_role_ids_csv, ''), ' ', '');\n"
        + "  DELETE FROM user_role\n"
        + "  WHERE user_id = p_user_id\n"
        + "    AND (v_csv = '' OR FIND_IN_SET(CAST(role_id AS CHAR), v_csv) = 0);\n"
        + "  INSERT INTO user_role (user_id, role_id, is_primary)\n"
        + "  SELECT p_user_id, r.id, 0\n"
        + "  FROM role r\n"
        + "  WHERE v_csv <> ''\n"
        + "    AND FIND_IN_SET(CAST(r.id AS CHAR), v_csv) > 0\n"
        + "    AND NOT EXISTS (\n"
        + "      SELECT 1 FROM user_role ur WHERE ur.user_id = p_user_id AND ur.role_id = r.id\n"
        + "    );\n"
        + "  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'sync', 'user_role', p_user_id, NULL, NULL, NULL, 'Synced user roles');\n"
        + "  SELECT * FROM user_role WHERE user_id = p_user_id ORDER BY id;\n"
        + "END $$\n\n"
        + "DELIMITER ;\n\n"
    )

    def render_simple_sync(name: str, table_name: str, parent_column: str, related_column: str, related_table: str, actor_org_sql: str, description: str) -> str:
        return (
            flow_header(name, description, f"{table_name}, audit_log", f"CALL {name}(1, '1,2,3', 1);")
            + "DELIMITER $$\n\n"
            + f"DROP PROCEDURE IF EXISTS `{name}` $$\n"
            + f"CREATE PROCEDURE `{name}` (\n"
            + f"  IN p_{parent_column} BIGINT UNSIGNED,\n"
            + "  IN p_related_ids_csv TEXT,\n"
            + "  IN p_actor_user_id BIGINT UNSIGNED\n"
            + ")\n"
            + "proc:BEGIN\n"
            + "  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;\n"
            + "  DECLARE v_csv TEXT;\n\n"
            + f"  {actor_org_sql}\n"
            + "  IF v_organization_id IS NULL THEN\n"
            + "    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Parent record not found';\n"
            + "  END IF;\n"
            + "  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);\n"
            + "  SET v_csv = REPLACE(COALESCE(p_related_ids_csv, ''), ' ', '');\n"
            + f"  DELETE FROM `{table_name}`\n"
            + f"  WHERE `{parent_column}` = p_{parent_column}\n"
            + f"    AND (v_csv = '' OR FIND_IN_SET(CAST(`{related_column}` AS CHAR), v_csv) = 0);\n"
            + f"  INSERT INTO `{table_name}` (`{parent_column}`, `{related_column}`)\n"
            + f"  SELECT p_{parent_column}, r.id\n"
            + f"  FROM `{related_table}` r\n"
            + "  WHERE v_csv <> ''\n"
            + "    AND FIND_IN_SET(CAST(r.id AS CHAR), v_csv) > 0\n"
            + f"    AND NOT EXISTS (\n"
            + f"      SELECT 1 FROM `{table_name}` x WHERE x.`{parent_column}` = p_{parent_column} AND x.`{related_column}` = r.id\n"
            + "    );\n"
            + f"  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'sync', '{table_name}', p_{parent_column}, NULL, NULL, NULL, 'Synced relation set');\n"
            + f"  SELECT * FROM `{table_name}` WHERE `{parent_column}` = p_{parent_column} ORDER BY id;\n"
            + "END $$\n\n"
            + "DELIMITER ;\n\n"
        )

    parts.append(render_simple_sync("sp_project_member_sync", "project_member", "project_id", "user_id", "user_account", "SELECT organization_id INTO v_organization_id FROM project WHERE id = p_project_id LIMIT 1;", "Sincroniza miembros de un proyecto."))
    parts.append(render_simple_sync("sp_project_objective_link_sync", "project_objective_link", "project_id", "objective_record_id", "objective_record", "SELECT organization_id INTO v_organization_id FROM project WHERE id = p_project_id LIMIT 1;", "Sincroniza objetivos vinculados a un proyecto."))
    parts.append(render_simple_sync("sp_project_tag_sync", "project_tag_link", "project_id", "project_tag_id", "project_tag", "SELECT organization_id INTO v_organization_id FROM project WHERE id = p_project_id LIMIT 1;", "Sincroniza tags de un proyecto."))
    parts.append(render_simple_sync("sp_task_tag_sync", "task_tag_link", "task_id", "project_tag_id", "project_tag", "SELECT p.organization_id INTO v_organization_id FROM task t JOIN project p ON p.id = t.project_id WHERE t.id = p_task_id LIMIT 1;", "Sincroniza tags de una tarea."))

    parts.append(
        flow_header("sp_task_status_update", "Actualiza estado de tarea, registra auditoria e historial y opcionalmente crea un `task_update`.", "task, task_update, audit_log, status_history", "CALL sp_task_status_update(1, 'done', 100.00, 'Closed', 1);")
        + "DELIMITER $$\n\n"
        + "DROP PROCEDURE IF EXISTS `sp_task_status_update` $$\n"
        + "CREATE PROCEDURE `sp_task_status_update` (\n"
        + "  IN p_task_id BIGINT UNSIGNED,\n"
        + "  IN p_new_status VARCHAR(50),\n"
        + "  IN p_completion_percent DECIMAL(5,2),\n"
        + "  IN p_summary TEXT,\n"
        + "  IN p_actor_user_id BIGINT UNSIGNED\n"
        + ")\n"
        + "proc:BEGIN\n"
        + "  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;\n"
        + "  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;\n"
        + "  DECLARE v_project_id BIGINT UNSIGNED DEFAULT NULL;\n"
        + "  SELECT p.organization_id, t.current_status, t.project_id INTO v_organization_id, v_old_status, v_project_id FROM task t JOIN project p ON p.id = t.project_id WHERE t.id = p_task_id LIMIT 1;\n"
        + "  IF v_organization_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Task not found'; END IF;\n"
        + "  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);\n"
        + "  UPDATE task SET current_status = p_new_status, completion_percent = COALESCE(p_completion_percent, completion_percent), completed_at = CASE WHEN p_new_status IN ('done', 'completed', 'closed') THEN COALESCE(completed_at, NOW()) ELSE completed_at END, last_activity_at = NOW(), updated_by_user_id = NULLIF(p_actor_user_id, 0) WHERE id = p_task_id;\n"
        + "  CALL sp_status_history_insert('task', p_task_id, v_old_status, p_new_status, NULLIF(p_actor_user_id, 0), p_summary);\n"
        + "  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'status_update', 'task', p_task_id, 'current_status', v_old_status, p_new_status, COALESCE(NULLIF(p_summary, ''), 'Task status update'));\n"
        + "  IF p_summary IS NOT NULL AND TRIM(p_summary) <> '' THEN INSERT INTO task_update (task_id, project_id, user_id, update_type, progress_percent_after, summary) VALUES (p_task_id, v_project_id, NULLIF(p_actor_user_id, 0), 'status_update', COALESCE(p_completion_percent, 0), p_summary); END IF;\n"
        + "  SELECT * FROM task WHERE id = p_task_id LIMIT 1;\n"
        + "END $$\n\n"
        + "DELIMITER ;\n\n"
    )

    parts.append(
        flow_header("sp_project_status_update", "Actualiza estado de proyecto, registra auditoria e historial y opcionalmente crea un `project_update`.", "project, project_update, audit_log, status_history", "CALL sp_project_status_update(1, 'active', 20.00, 'Kickoff done', 'green', 1);")
        + "DELIMITER $$\n\n"
        + "DROP PROCEDURE IF EXISTS `sp_project_status_update` $$\n"
        + "CREATE PROCEDURE `sp_project_status_update` (\n"
        + "  IN p_project_id BIGINT UNSIGNED,\n"
        + "  IN p_new_status VARCHAR(50),\n"
        + "  IN p_completion_percent DECIMAL(5,2),\n"
        + "  IN p_summary TEXT,\n"
        + "  IN p_health_status VARCHAR(50),\n"
        + "  IN p_actor_user_id BIGINT UNSIGNED\n"
        + ")\n"
        + "proc:BEGIN\n"
        + "  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;\n"
        + "  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;\n"
        + "  SELECT organization_id, current_status INTO v_organization_id, v_old_status FROM project WHERE id = p_project_id LIMIT 1;\n"
        + "  IF v_organization_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Project not found'; END IF;\n"
        + "  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);\n"
        + "  UPDATE project SET current_status = p_new_status, completion_percent = COALESCE(p_completion_percent, completion_percent), health_status = COALESCE(NULLIF(p_health_status, ''), health_status), completed_at = CASE WHEN p_new_status IN ('done', 'completed', 'closed') THEN COALESCE(completed_at, NOW()) ELSE completed_at END, last_activity_at = NOW(), updated_by_user_id = NULLIF(p_actor_user_id, 0) WHERE id = p_project_id;\n"
        + "  CALL sp_status_history_insert('project', p_project_id, v_old_status, p_new_status, NULLIF(p_actor_user_id, 0), p_summary);\n"
        + "  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'status_update', 'project', p_project_id, 'current_status', v_old_status, p_new_status, COALESCE(NULLIF(p_summary, ''), 'Project status update'));\n"
        + "  IF p_summary IS NOT NULL AND TRIM(p_summary) <> '' THEN INSERT INTO project_update (project_id, user_id, summary, completion_percent_after, health_status_after) VALUES (p_project_id, NULLIF(p_actor_user_id, 0), p_summary, COALESCE(p_completion_percent, 0), COALESCE(NULLIF(p_health_status, ''), 'green')); END IF;\n"
        + "  SELECT * FROM project WHERE id = p_project_id LIMIT 1;\n"
        + "END $$\n\n"
        + "DELIMITER ;\n\n"
    )

    def render_status_flow(name: str, table_name: str, status_column: str, new_status: str, extra_sets: str, actor_sql: str, select_sql: str, notes_expr: str) -> str:
        return (
            flow_header(name, f"Actualiza `{status_column}` en `{table_name}` hacia `{new_status}` con auditoria e historial.", f"{table_name}, audit_log, status_history", f"CALL {name}(1, NULL, 1);")
            + "DELIMITER $$\n\n"
            + f"DROP PROCEDURE IF EXISTS `{name}` $$\n"
            + f"CREATE PROCEDURE `{name}` (\n"
            + "  IN p_id BIGINT UNSIGNED,\n"
            + "  IN p_notes TEXT,\n"
            + "  IN p_actor_user_id BIGINT UNSIGNED\n"
            + ")\n"
            + "proc:BEGIN\n"
            + "  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;\n"
            + "  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;\n"
            + f"  {actor_sql}\n"
            + "  IF v_organization_id IS NOT NULL THEN CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0); ELSE CALL sp_actor_assert(p_actor_user_id, NULL, 0); END IF;\n"
            + f"  UPDATE `{table_name}` SET `{status_column}` = '{new_status}'{extra_sets} WHERE id = p_id;\n"
            + "  IF ROW_COUNT() = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found'; END IF;\n"
            + f"  CALL sp_status_history_insert('{table_name}', p_id, v_old_status, '{new_status}', NULLIF(p_actor_user_id, 0), {notes_expr});\n"
            + f"  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'status_update', '{table_name}', p_id, '{status_column}', v_old_status, '{new_status}', {notes_expr});\n"
            + f"  {select_sql}\n"
            + "END $$\n\n"
            + "DELIMITER ;\n\n"
        )

    parts.append(render_status_flow("sp_blocker_resolve", "blocker", "status", "resolved", ", resolved_at = COALESCE(resolved_at, NOW()), resolution_notes = p_notes", "SELECT p.organization_id, b.status INTO v_organization_id, v_old_status FROM blocker b JOIN project p ON p.id = b.project_id WHERE b.id = p_id LIMIT 1;", "SELECT * FROM blocker WHERE id = p_id LIMIT 1;", "COALESCE(NULLIF(p_notes, ''), 'Blocker resolved')"))
    parts.append(render_status_flow("sp_meeting_close", "meeting", "status", "completed", ", actual_end_at = COALESCE(actual_end_at, NOW()), summary = COALESCE(NULLIF(p_notes, ''), summary), updated_by_user_id = NULLIF(p_actor_user_id, 0)", "SELECT organization_id, status INTO v_organization_id, v_old_status FROM meeting WHERE id = p_id LIMIT 1;", "SELECT * FROM meeting WHERE id = p_id LIMIT 1;", "COALESCE(NULLIF(p_notes, ''), 'Meeting closed')"))
    parts.append(render_status_flow("sp_decision_record_apply", "decision_record", "decision_status", "applied", ", effective_date = COALESCE(effective_date, CURDATE())", "SELECT COALESCE(m.organization_id, p.organization_id, a.organization_id), d.decision_status INTO v_organization_id, v_old_status FROM decision_record d LEFT JOIN meeting m ON m.id = d.meeting_id LEFT JOIN project p ON p.id = d.project_id LEFT JOIN business_area a ON a.id = d.business_area_id WHERE d.id = p_id LIMIT 1;", "SELECT * FROM decision_record WHERE id = p_id LIMIT 1;", "COALESCE(NULLIF(p_notes, ''), 'Decision applied')"))
    parts.append(render_status_flow("sp_follow_up_complete", "follow_up_item", "status", "completed", ", updated_by_user_id = NULLIF(p_actor_user_id, 0)", "SELECT COALESCE(m.organization_id, p.organization_id, a.organization_id), f.status INTO v_organization_id, v_old_status FROM follow_up_item f LEFT JOIN meeting m ON m.id = f.meeting_id LEFT JOIN task t ON t.id = f.task_id LEFT JOIN project p ON p.id = t.project_id LEFT JOIN decision_record d ON d.id = f.decision_record_id LEFT JOIN business_area a ON a.id = d.business_area_id WHERE f.id = p_id LIMIT 1;", "SELECT * FROM follow_up_item WHERE id = p_id LIMIT 1;", "COALESCE(NULLIF(p_notes, ''), 'Follow up completed')"))
    parts.append(render_status_flow("sp_knowledge_document_publish", "knowledge_document", "status", "published", "", "SELECT organization_id, status INTO v_organization_id, v_old_status FROM knowledge_document WHERE id = p_id LIMIT 1;", "SELECT * FROM knowledge_document WHERE id = p_id LIMIT 1;", "COALESCE(NULLIF(p_notes, ''), 'Knowledge document published')"))
    parts.append(render_status_flow("sp_policy_record_activate", "policy_record", "status", "active", "", "SELECT organization_id, status INTO v_organization_id, v_old_status FROM policy_record WHERE id = p_id LIMIT 1;", "SELECT * FROM policy_record WHERE id = p_id LIMIT 1;", "COALESCE(NULLIF(p_notes, ''), 'Policy activated')"))
    parts.append(render_status_flow("sp_sop_publish", "sop", "current_status", "published", "", "SELECT organization_id, current_status INTO v_organization_id, v_old_status FROM sop WHERE id = p_id LIMIT 1;", "SELECT * FROM sop WHERE id = p_id LIMIT 1;", "COALESCE(NULLIF(p_notes, ''), 'SOP published')"))
    parts.append(render_status_flow("sp_alert_event_resolve", "alert_event", "status", "resolved", ", resolved_at = COALESCE(resolved_at, NOW()), acknowledged_by_user_id = COALESCE(acknowledged_by_user_id, NULLIF(p_actor_user_id, 0)), acknowledged_at = COALESCE(acknowledged_at, NOW())", "SELECT ar.organization_id, ae.status INTO v_organization_id, v_old_status FROM alert_event ae LEFT JOIN alert_rule ar ON ar.id = ae.alert_rule_id WHERE ae.id = p_id LIMIT 1;", "SELECT * FROM alert_event WHERE id = p_id LIMIT 1;", "COALESCE(NULLIF(p_notes, ''), 'Alert resolved')"))

    parts.append(
        flow_header("sp_ai_suggestion_review", "Marca una sugerencia de IA como revisada y opcionalmente la vincula a una tarea de implementacion.", "ai_suggestion, audit_log, status_history", "CALL sp_ai_suggestion_review(1, 'accepted', 10, 'Accepted', 1);")
        + "DELIMITER $$\n\n"
        + "DROP PROCEDURE IF EXISTS `sp_ai_suggestion_review` $$\n"
        + "CREATE PROCEDURE `sp_ai_suggestion_review` (\n"
        + "  IN p_ai_suggestion_id BIGINT UNSIGNED,\n"
        + "  IN p_review_status VARCHAR(50),\n"
        + "  IN p_implementation_task_id BIGINT UNSIGNED,\n"
        + "  IN p_notes TEXT,\n"
        + "  IN p_actor_user_id BIGINT UNSIGNED\n"
        + ")\n"
        + "proc:BEGIN\n"
        + "  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;\n"
        + "  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;\n"
        + "  SELECT COALESCE(p.organization_id, a.organization_id), s.review_status INTO v_organization_id, v_old_status FROM ai_suggestion s LEFT JOIN project p ON p.id = s.project_id LEFT JOIN business_area a ON a.id = s.business_area_id WHERE s.id = p_ai_suggestion_id LIMIT 1;\n"
        + "  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);\n"
        + "  UPDATE ai_suggestion SET review_status = p_review_status, reviewed_by_user_id = NULLIF(p_actor_user_id, 0), reviewed_at = NOW(), implementation_task_id = p_implementation_task_id WHERE id = p_ai_suggestion_id;\n"
        + "  IF ROW_COUNT() = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'AI suggestion not found'; END IF;\n"
        + "  CALL sp_status_history_insert('ai_suggestion', p_ai_suggestion_id, v_old_status, p_review_status, NULLIF(p_actor_user_id, 0), p_notes);\n"
        + "  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'review', 'ai_suggestion', p_ai_suggestion_id, 'review_status', v_old_status, p_review_status, COALESCE(NULLIF(p_notes, ''), 'AI suggestion reviewed'));\n"
        + "  SELECT * FROM ai_suggestion WHERE id = p_ai_suggestion_id LIMIT 1;\n"
        + "END $$\n\n"
        + "DELIMITER ;\n\n"
    )

    parts.append(
        flow_header("sp_automation_run_finalize", "Finaliza una ejecucion de automatizacion.", "automation_run, audit_log, status_history", "CALL sp_automation_run_finalize(1, 'completed', 'ok', 1);")
        + "DELIMITER $$\n\n"
        + "DROP PROCEDURE IF EXISTS `sp_automation_run_finalize` $$\n"
        + "CREATE PROCEDURE `sp_automation_run_finalize` (\n"
        + "  IN p_automation_run_id BIGINT UNSIGNED,\n"
        + "  IN p_status VARCHAR(50),\n"
        + "  IN p_execution_summary TEXT,\n"
        + "  IN p_actor_user_id BIGINT UNSIGNED\n"
        + ")\n"
        + "proc:BEGIN\n"
        + "  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;\n"
        + "  CALL sp_actor_assert(p_actor_user_id, NULL, 0);\n"
        + "  SELECT status INTO v_old_status FROM automation_run WHERE id = p_automation_run_id LIMIT 1;\n"
        + "  UPDATE automation_run SET status = p_status, execution_summary = p_execution_summary, completed_at = COALESCE(completed_at, NOW()) WHERE id = p_automation_run_id;\n"
        + "  IF ROW_COUNT() = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Automation run not found'; END IF;\n"
        + "  CALL sp_status_history_insert('automation_run', p_automation_run_id, v_old_status, p_status, NULLIF(p_actor_user_id, 0), p_execution_summary);\n"
        + "  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'finalize', 'automation_run', p_automation_run_id, 'status', v_old_status, p_status, COALESCE(NULLIF(p_execution_summary, ''), 'Automation run finalized'));\n"
        + "  SELECT * FROM automation_run WHERE id = p_automation_run_id LIMIT 1;\n"
        + "END $$\n\n"
        + "DELIMITER ;\n\n"
    )

    parts.append(
        flow_header("sp_external_entity_link_sync", "Sincroniza los external ids asociados a una entidad interna dentro de un sistema externo.", "external_entity_link, audit_log", "CALL sp_external_entity_link_sync(1, 'project', 10, 'deal', 'A1,B2', 1);")
        + "DELIMITER $$\n\n"
        + "DROP PROCEDURE IF EXISTS `sp_external_entity_link_sync` $$\n"
        + "CREATE PROCEDURE `sp_external_entity_link_sync` (\n"
        + "  IN p_external_system_id BIGINT UNSIGNED,\n"
        + "  IN p_internal_entity_type VARCHAR(50),\n"
        + "  IN p_internal_entity_id BIGINT UNSIGNED,\n"
        + "  IN p_external_entity_type VARCHAR(50),\n"
        + "  IN p_external_ids_csv TEXT,\n"
        + "  IN p_actor_user_id BIGINT UNSIGNED\n"
        + ")\n"
        + "proc:BEGIN\n"
        + "  DECLARE v_csv TEXT;\n"
        + "  DECLARE v_token TEXT;\n"
        + "  DECLARE v_pos INT DEFAULT 0;\n"
        + "  CALL sp_actor_assert(p_actor_user_id, NULL, 0);\n"
        + "  SET v_csv = CONCAT(REPLACE(COALESCE(p_external_ids_csv, ''), ' ', ''), ',');\n"
        + "  DELETE FROM external_entity_link WHERE external_system_id = p_external_system_id AND internal_entity_type = p_internal_entity_type AND internal_entity_id = p_internal_entity_id AND external_entity_type = p_external_entity_type AND (TRIM(COALESCE(p_external_ids_csv, '')) = '' OR FIND_IN_SET(external_entity_id, REPLACE(COALESCE(p_external_ids_csv, ''), ' ', '')) = 0);\n"
        + "  WHILE LOCATE(',', v_csv) > 0 DO\n"
        + "    SET v_pos = LOCATE(',', v_csv);\n"
        + "    SET v_token = TRIM(SUBSTRING(v_csv, 1, v_pos - 1));\n"
        + "    SET v_csv = SUBSTRING(v_csv, v_pos + 1);\n"
        + "    IF v_token <> '' THEN\n"
        + "      INSERT INTO external_entity_link (external_system_id, internal_entity_type, internal_entity_id, external_entity_type, external_entity_id)\n"
        + "      SELECT p_external_system_id, p_internal_entity_type, p_internal_entity_id, p_external_entity_type, v_token\n"
        + "      FROM DUAL\n"
        + "      WHERE NOT EXISTS (\n"
        + "        SELECT 1 FROM external_entity_link x WHERE x.external_system_id = p_external_system_id AND x.internal_entity_type = p_internal_entity_type AND x.internal_entity_id = p_internal_entity_id AND x.external_entity_type = p_external_entity_type AND x.external_entity_id = v_token\n"
        + "      );\n"
        + "    END IF;\n"
        + "  END WHILE;\n"
        + "  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'sync', 'external_entity_link', p_internal_entity_id, NULL, NULL, NULL, 'Synced external ids');\n"
        + "  SELECT * FROM external_entity_link WHERE external_system_id = p_external_system_id AND internal_entity_type = p_internal_entity_type AND internal_entity_id = p_internal_entity_id AND external_entity_type = p_external_entity_type ORDER BY id;\n"
        + "END $$\n\n"
        + "DELIMITER ;\n\n"
    )

    return "".join(parts)


def render_domain_sql(tables: Dict[str, Table], domain_name: str) -> str:
    if domain_name == "helpers":
        return render_helpers()
    if domain_name == "90_composite_flows":
        return render_flows()

    parts: List[str] = []
    for table_name in DOMAIN_TABLES[domain_name]:
        table = tables[table_name]
        parts.append(render_data_proc(table))
        if is_read_only_table(table_name):
            continue
        parts.append(render_write_proc(table))
        if allow_delete(table_name):
            parts.append(render_delete_proc(table))
    return "".join(parts)


def build_proc_catalog(tables: Dict[str, Table]) -> List[Dict[str, str]]:
    catalog: List[Dict[str, str]] = []
    for domain_name, table_names in DOMAIN_TABLES.items():
        if domain_name in {"helpers", "90_composite_flows"}:
            continue
        for table_name in table_names:
            table = tables[table_name]
            catalog.append(
                {
                    "domain": domain_name,
                    "sp": f"sp_{table_name}_data",
                    "purpose": f"Consulta `{table_name}`.",
                    "inputs": "p_id, p_organization_id, p_search, p_only_active, p_limit_rows",
                    "writes": "-",
                    "output": "SELECT con filas de la entidad",
                    "routing": f"Lectura/listado de {table_name}.",
                }
            )
            if is_read_only_table(table_name):
                continue
            write_name = f"sp_{table_name}_{'create' if is_create_only_table(table_name) else 'upsert'}"
            inputs = ["p_id"] if not is_create_only_table(table_name) else []
            for column in table.columns:
                if is_mutable(column.name):
                    inputs.append(f"p_{column.name}")
            inputs.append("p_actor_user_id")
            catalog.append(
                {
                    "domain": domain_name,
                    "sp": write_name,
                    "purpose": "Alta controlada." if is_create_only_table(table_name) else "Alta o edicion controlada.",
                    "inputs": ", ".join(inputs),
                    "writes": table_name + (", audit_log" if table_name != "audit_log" else ""),
                    "output": "SELECT con el registro final",
                    "routing": f"Write base de {table_name}.",
                }
            )
            if allow_delete(table_name):
                catalog.append(
                    {
                        "domain": domain_name,
                        "sp": f"sp_{table_name}_delete",
                        "purpose": "Borrado fisico permitido solo en tabla puente/tecnica.",
                        "inputs": "p_id, p_actor_user_id, p_reason",
                        "writes": f"{table_name}, audit_log",
                        "output": "SELECT de confirmacion",
                        "routing": f"Eliminar fila de {table_name} cuando el modelo lo permite.",
                    }
                )

    helper_rows = [
        ("helpers", "sp_bootstrap_state_data", "Estado del bootstrap", "p_organization_id", "-", "SELECT de estado", "Detectar si aun se permite bootstrap."),
        ("helpers", "sp_actor_assert", "Valida actor", "p_actor_user_id, p_organization_id, p_allow_bootstrap", "-", "Sin result set", "Helper interno de seguridad."),
        ("helpers", "sp_audit_log_insert", "Inserta auditoria", "p_user_id, p_action_type, p_entity_type, p_entity_id, p_field_name, p_old_value, p_new_value, p_change_summary", "audit_log", "Sin result set", "Helper interno de write."),
        ("helpers", "sp_status_history_insert", "Inserta historial de estado", "p_entity_type, p_entity_id, p_old_status, p_new_status, p_changed_by_user_id, p_notes", "status_history", "Sin result set", "Helper interno de write."),
    ]
    for row in helper_rows:
        catalog.append(
            {
                "domain": row[0],
                "sp": row[1],
                "purpose": row[2],
                "inputs": row[3],
                "writes": row[4],
                "output": row[5],
                "routing": row[6],
            }
        )

    flow_meta = {
        "sp_user_role_sync": ("p_user_id, p_role_ids_csv, p_actor_user_id", "Sincronizar roles de un usuario."),
        "sp_project_member_sync": ("p_project_id, p_related_ids_csv, p_actor_user_id", "Sincronizar miembros de proyecto."),
        "sp_project_objective_link_sync": ("p_project_id, p_related_ids_csv, p_actor_user_id", "Sincronizar objetivos asociados a proyecto."),
        "sp_project_tag_sync": ("p_project_id, p_related_ids_csv, p_actor_user_id", "Sincronizar tags de proyecto."),
        "sp_task_tag_sync": ("p_task_id, p_related_ids_csv, p_actor_user_id", "Sincronizar tags de tarea."),
        "sp_task_status_update": ("p_task_id, p_new_status, p_completion_percent, p_summary, p_actor_user_id", "Cambiar estado de tarea y registrar update."),
        "sp_project_status_update": ("p_project_id, p_new_status, p_completion_percent, p_summary, p_health_status, p_actor_user_id", "Cambiar estado de proyecto y registrar update."),
        "sp_blocker_resolve": ("p_id, p_notes, p_actor_user_id", "Cerrar blocker."),
        "sp_meeting_close": ("p_id, p_notes, p_actor_user_id", "Cerrar reunion."),
        "sp_decision_record_apply": ("p_id, p_notes, p_actor_user_id", "Marcar decision como aplicada."),
        "sp_follow_up_complete": ("p_id, p_notes, p_actor_user_id", "Marcar follow-up como completado."),
        "sp_knowledge_document_publish": ("p_id, p_notes, p_actor_user_id", "Publicar documento de conocimiento."),
        "sp_policy_record_activate": ("p_id, p_notes, p_actor_user_id", "Activar politica."),
        "sp_sop_publish": ("p_id, p_notes, p_actor_user_id", "Publicar SOP."),
        "sp_alert_event_resolve": ("p_id, p_notes, p_actor_user_id", "Resolver alerta."),
        "sp_ai_suggestion_review": ("p_ai_suggestion_id, p_review_status, p_implementation_task_id, p_notes, p_actor_user_id", "Revisar sugerencia de IA."),
        "sp_automation_run_finalize": ("p_automation_run_id, p_status, p_execution_summary, p_actor_user_id", "Finalizar corrida de automatizacion."),
        "sp_external_entity_link_sync": ("p_external_system_id, p_internal_entity_type, p_internal_entity_id, p_external_entity_type, p_external_ids_csv, p_actor_user_id", "Sincronizar external ids vinculados."),
    }
    for proc in FLOW_FILES["90_composite_flows.sql"]:
        catalog.append(
            {
                "domain": "90_composite_flows",
                "sp": proc,
                "purpose": "Flujo compuesto de negocio.",
                "inputs": flow_meta[proc][0],
                "writes": "Multiples tablas segun el flujo",
                "output": "SELECT con estado final",
                "routing": flow_meta[proc][1],
            }
        )

    return catalog


def build_reference_md(tables: Dict[str, Table]) -> str:
    catalog = build_proc_catalog(tables)
    lines: List[str] = []
    lines.append("# Business Brain Stored Procedures Reference\n")
    lines.append("Ultima actualizacion: 2026-03-21\n")
    lines.append("## Objetivo\n")
    lines.append("Este documento es la referencia operativa principal para IA, backend y soporte al trabajar con los stored procedures de `vive_la_vibe_brain`.\n")
    lines.append("Resume entradas, salidas, seguridad, convenciones y rutas recomendadas antes de invocar cualquier SP.\n")
    lines.append("## Convenciones clave\n")
    lines.append("- Lectura estandar: `sp_<entidad>_data`.\n")
    lines.append("- Escritura base: `sp_<entidad>_upsert` o `sp_<entidad>_create`.\n")
    lines.append("- Delete fisico solo en tablas puente o tecnicas autorizadas.\n")
    lines.append("- Todos los writes usan `sp_actor_assert` y registran `audit_log`.\n")
    lines.append("- Cuando cambia un campo de estado real, el write registra `status_history`.\n")
    lines.append("- El bootstrap solo aplica a `organization`, `user_account`, `role` y `user_role`, y solo mientras no existan usuarios.\n")
    lines.append("## Routing rapido para IA\n")
    lines.append("- Si la intención es listar o buscar, usa primero `sp_<entidad>_data`.\n")
    lines.append("- Si la intención es crear o editar una fila base, usa `sp_<entidad>_upsert`.\n")
    lines.append("- Si la intención es registrar una nota o evento historico, usa los SP `*_create` append-only.\n")
    lines.append("- Si la intención es cambiar estado operativo, prefiere el flujo compuesto (`sp_task_status_update`, `sp_project_status_update`, etc.) antes que tocar el `upsert` base.\n")
    lines.append("- Si la intención es sincronizar relaciones many-to-many, usa `*_sync` en vez de llamar múltiples deletes/inserts sueltos.\n")
    lines.append("## Mapa resumido\n")
    lines.append("| Dominio | SP | Proposito | Inputs | Writes | Output | Cuando usarlo |\n")
    lines.append("|---|---|---|---|---|---|---|\n")
    for item in catalog:
        lines.append(
            f"| `{item['domain']}` | `{item['sp']}` | {item['purpose']} | `{item['inputs']}` | `{item['writes']}` | {item['output']} | {item['routing']} |\n"
        )
    lines.append("## Dominios y archivos fuente\n")
    lines.append("- `stored-procedures/helpers/001_core_helpers.sql`\n")
    lines.append("- `stored-procedures/domains/010_core_entities.sql`\n")
    lines.append("- `stored-procedures/domains/020_execution.sql`\n")
    lines.append("- `stored-procedures/domains/030_meetings_and_followup.sql`\n")
    lines.append("- `stored-procedures/domains/040_knowledge.sql`\n")
    lines.append("- `stored-procedures/domains/050_alerts_ai.sql`\n")
    lines.append("- `stored-procedures/domains/060_integrations_governance.sql`\n")
    lines.append("- `stored-procedures/domains/090_composite_flows.sql`\n")
    lines.append("## Notas de seguridad\n")
    lines.append("- Las tablas globales o tecnicas sin `organization_id` directo validan actor, pero no siempre pueden filtrar org-scope por SQL directo.\n")
    lines.append("- Las tablas append-only no exponen update/delete general aunque tengan `id`.\n")
    lines.append("- Las tablas `audit_log` y `status_history` son de lectura para consumo directo; sus writes deben pasar por helpers.\n")
    return "".join(lines)


def render_installer() -> str:
    source_files = [
        "helpers/001_core_helpers.sql",
        "domains/010_core_entities.sql",
        "domains/020_execution.sql",
        "domains/030_meetings_and_followup.sql",
        "domains/040_knowledge.sql",
        "domains/050_alerts_ai.sql",
        "domains/060_integrations_governance.sql",
        "domains/090_composite_flows.sql",
    ]
    lines = [
        "-- Master installer for vive_la_vibe_brain stored procedures\n",
        "USE `vive_la_vibe_brain`;\n\n",
    ]
    for file_name in source_files:
        lines.append(f"SOURCE {file_name};\n")
    return "".join(lines)


def ensure_dirs() -> None:
    (SP_ROOT / "helpers").mkdir(parents=True, exist_ok=True)
    (SP_ROOT / "domains").mkdir(parents=True, exist_ok=True)
    INDIVIDUAL_SP_ROOT.mkdir(parents=True, exist_ok=True)
    ROOT.joinpath("tools").mkdir(parents=True, exist_ok=True)


def split_procedures(sql_text: str) -> Dict[str, str]:
    blocks = re.findall(r"(/\*\*.*?DELIMITER ;\n)", sql_text, flags=re.S)
    procedures: Dict[str, str] = {}
    for block in blocks:
        match = re.search(r"Procedure:\s*(sp_[A-Za-z0-9_]+)", block)
        if not match:
            continue
        procedures[match.group(1)] = block
    return procedures


def write_files() -> None:
    tables = parse_schema(SCHEMA_PATH)
    ensure_dirs()
    helper_sql = render_domain_sql(tables, "helpers")
    (SP_ROOT / "helpers" / "001_core_helpers.sql").write_text(helper_sql, encoding="utf-8")
    domain_files = {
        "10_core_entities": "010_core_entities.sql",
        "20_execution": "020_execution.sql",
        "30_meetings_and_followup": "030_meetings_and_followup.sql",
        "40_knowledge": "040_knowledge.sql",
        "50_alerts_ai": "050_alerts_ai.sql",
        "60_integrations_governance": "060_integrations_governance.sql",
        "90_composite_flows": "090_composite_flows.sql",
    }
    individual_blocks: Dict[str, str] = {}
    individual_blocks.update(split_procedures(helper_sql))
    for domain_name, file_name in domain_files.items():
        domain_sql = render_domain_sql(tables, domain_name)
        (SP_ROOT / "domains" / file_name).write_text(domain_sql, encoding="utf-8")
        individual_blocks.update(split_procedures(domain_sql))
    (SP_ROOT / "000_install_all_business_brain_sps.sql").write_text(render_installer(), encoding="utf-8")
    DOCS_PATH.write_text(build_reference_md(tables), encoding="utf-8")
    for old_file in INDIVIDUAL_SP_ROOT.glob("sp_*.sql"):
        old_file.unlink()
    for proc_name, proc_sql in sorted(individual_blocks.items()):
        (INDIVIDUAL_SP_ROOT / f"{proc_name}.sql").write_text(proc_sql, encoding="utf-8")


if __name__ == "__main__":
    write_files()
