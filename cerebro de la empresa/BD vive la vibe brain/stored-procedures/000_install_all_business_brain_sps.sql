-- Master installer for vive_la_vibe_brain stored procedures
USE `vive_la_vibe_brain`;

SOURCE helpers/001_core_helpers.sql;
SOURCE domains/010_core_entities.sql;
SOURCE domains/020_execution.sql;
SOURCE domains/030_meetings_and_followup.sql;
SOURCE domains/040_knowledge.sql;
SOURCE domains/050_alerts_ai.sql;
SOURCE domains/060_integrations_governance.sql;
SOURCE domains/090_composite_flows.sql;
