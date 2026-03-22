import { AssistantRequest } from "@vlv-ai/shared";

import { ActionDefinition } from "../actions/action-registry";
import { MariaDbPool } from "./mariadb-pool";
import { ValidationError } from "../shared/errors";

export interface ProcedureExecutionResult {
  procedureName: string;
  mode: "read" | "write";
  recordsets: unknown[];
  outputVariables?: Record<string, unknown>;
}

export class ProcedureExecutor {
  constructor(private readonly mariaDbPool: MariaDbPool) {}

  async execute(
    definition: ActionDefinition,
    parsedArguments: Record<string, unknown>,
    request: AssistantRequest
  ): Promise<ProcedureExecutionResult> {
    if (!definition.executable || !definition.procedure) {
      throw new ValidationError("This action cannot be executed as a stored procedure.");
    }

    const pool = await this.mariaDbPool.getPool(definition.target);
    const inputs = definition.procedure.mapArguments(parsedArguments, request);

    if (definition.procedure.kind === "standard") {
      const placeholders = inputs.map(() => "?").join(", ");
      const [rows] = await pool.query(`CALL ${definition.procedure.name}(${placeholders});`, inputs);
      return {
        procedureName: definition.procedure.name,
        mode: definition.mode === "none" ? "read" : definition.mode,
        recordsets: this.normalizeRows(rows)
      };
    }

    const outputVariables = definition.procedure.outputVariables ?? [];
    const sessionVariables = outputVariables.map((key) => `@${definition.procedure?.name}_${key}`);
    const resetStatement = sessionVariables
      .map((variable) => `SET ${variable} = NULL`)
      .join("; ");
    const selectStatement = `SELECT ${sessionVariables
      .map((variable, index) => `${variable} AS ${outputVariables[index]}`)
      .join(", ")}`;
    const callPlaceholders = [...inputs.map(() => "?"), ...sessionVariables].join(", ");
    const sql = `${resetStatement}; CALL ${definition.procedure.name}(${callPlaceholders}); ${selectStatement};`;

    const [resultSets] = await pool.query(sql, inputs);
    const normalized = this.normalizeRows(resultSets);
    const outputRow = normalized.at(-1);
    const procedureRecordsets = normalized.slice(0, -1);

    return {
      procedureName: definition.procedure.name,
      mode: definition.mode === "none" ? "read" : definition.mode,
      recordsets: procedureRecordsets,
      outputVariables:
        outputRow && !Array.isArray(outputRow) && typeof outputRow === "object"
          ? (outputRow as Record<string, unknown>)
          : Array.isArray(outputRow) && outputRow[0] && typeof outputRow[0] === "object"
            ? (outputRow[0] as Record<string, unknown>)
            : undefined
    };
  }

  private normalizeRows(rows: unknown): unknown[] {
    if (!Array.isArray(rows)) {
      return [rows];
    }

    return rows
      .filter((entry) => !(typeof entry === "object" && entry !== null && "affectedRows" in entry))
      .map((entry) => {
        if (!Array.isArray(entry)) {
          return entry;
        }

        return entry.map((row) => ({ ...(row as Record<string, unknown>) }));
      });
  }
}
