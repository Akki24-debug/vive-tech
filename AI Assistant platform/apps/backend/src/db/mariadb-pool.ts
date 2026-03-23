import mysql, { Pool } from "mysql2/promise";

import { AssistantTarget } from "@vlv-ai/shared";

import { RuntimeConfigService } from "../config/runtime-config-service";
import { ConfigurationError } from "../shared/errors";

interface PoolEntry {
  pool: Pool;
  signature: string;
}

export class MariaDbPool {
  private readonly pools = new Map<AssistantTarget, PoolEntry>();

  constructor(private readonly runtimeConfigService: RuntimeConfigService) {}

  async getPool(target: AssistantTarget): Promise<Pool> {
    const config = await this.runtimeConfigService.getDecryptedTargetConfig(target);

    if (!config.enabled) {
      throw new ConfigurationError(`Domain ${target} is disabled in runtime configuration.`);
    }

    const nextSignature = JSON.stringify({
      host: config.database.host,
      port: config.database.port,
      user: config.database.user,
      database: config.database.database,
      connectionLimit: config.database.connectionLimit,
      ssl: config.database.ssl
    });
    const existing = this.pools.get(target);

    if (!existing || existing.signature !== nextSignature) {
      if (existing) {
        await existing.pool.end();
      }

      const pool = mysql.createPool({
        host: config.database.host,
        port: config.database.port,
        user: config.database.user,
        password: config.database.password,
        database: config.database.database,
        charset: "utf8mb4",
        connectionLimit: config.database.connectionLimit,
        ssl: config.database.ssl ? {} : undefined,
        multipleStatements: true
      });

      this.pools.set(target, {
        pool,
        signature: nextSignature
      });

      return pool;
    }

    return existing.pool;
  }
}
