import mysql, { Pool } from "mysql2/promise";

import { RuntimeConfigService } from "../config/runtime-config-service";

export class MariaDbPool {
  private pool?: Pool;
  private signature?: string;

  constructor(private readonly runtimeConfigService: RuntimeConfigService) {}

  async getPool(): Promise<Pool> {
    const config = await this.runtimeConfigService.getDecryptedConfig();
    const nextSignature = JSON.stringify({
      host: config.database.host,
      port: config.database.port,
      user: config.database.user,
      database: config.database.database,
      connectionLimit: config.database.connectionLimit,
      ssl: config.database.ssl
    });

    if (!this.pool || this.signature !== nextSignature) {
      if (this.pool) {
        await this.pool.end();
      }

      this.pool = mysql.createPool({
        host: config.database.host,
        port: config.database.port,
        user: config.database.user,
        password: config.database.password,
        database: config.database.database,
        connectionLimit: config.database.connectionLimit,
        ssl: config.database.ssl ? {} : undefined,
        multipleStatements: true
      });
      this.signature = nextSignature;
    }

    return this.pool;
  }
}
