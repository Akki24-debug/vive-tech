import { MariaDbPool } from "../db/mariadb-pool";
import { ConfigurationError } from "../shared/errors";

interface CacheEntry {
  organizationId: number;
  loadedAt: number;
}

export class BusinessBrainScopeResolver {
  private cache: CacheEntry | null = null;
  private readonly ttlMs = 30_000;

  constructor(private readonly mariaDbPool: MariaDbPool) {}

  async resolveDefaultOrganizationId(): Promise<number> {
    if (this.cache && Date.now() - this.cache.loadedAt < this.ttlMs) {
      return this.cache.organizationId;
    }

    const pool = await this.mariaDbPool.getPool("business_brain");
    const [rows] = await pool.query(
      "SELECT id FROM organization WHERE status = 'active' OR status IS NULL ORDER BY id ASC LIMIT 2"
    );

    const normalized = Array.isArray(rows)
      ? rows.filter((row) => Boolean(row) && typeof row === "object")
      : [];

    if (normalized.length !== 1) {
      throw new ConfigurationError(
        "Business Brain requires a single default organization scope for this console."
      );
    }

    const organizationId = Number((normalized[0] as { id?: number }).id);

    if (!Number.isFinite(organizationId) || organizationId <= 0) {
      throw new ConfigurationError(
        "Business Brain default organization scope could not be resolved."
      );
    }

    this.cache = {
      organizationId,
      loadedAt: Date.now()
    };

    return organizationId;
  }
}
