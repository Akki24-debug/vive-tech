import { AssistantTarget } from "@vlv-ai/shared";

type FailureKind = "docs" | "db" | "timeout" | "schema" | "preflight";

interface FailureRecord {
  kind: FailureKind;
  reason: string;
  timestamp: number;
}

const HEALTH_WINDOW_MS = 5 * 60 * 1000;

export class DomainHealthService {
  private readonly failures = new Map<AssistantTarget, FailureRecord[]>();

  markFailure(target: AssistantTarget, kind: FailureKind, reason: string): void {
    const records = this.failures.get(target) ?? [];
    records.push({
      kind,
      reason,
      timestamp: Date.now()
    });
    this.failures.set(target, this.prune(records));
  }

  clearFailures(target: AssistantTarget, kind?: FailureKind): void {
    const records = this.failures.get(target) ?? [];
    if (!kind) {
      this.failures.delete(target);
      return;
    }

    this.failures.set(
      target,
      this.prune(records.filter((record) => record.kind !== kind))
    );
  }

  getHealth(target: AssistantTarget): {
    stable: boolean;
    activeFailures: FailureRecord[];
    reason?: string;
  } {
    const activeFailures = this.prune(this.failures.get(target) ?? []);
    const stable = activeFailures.length === 0;

    return {
      stable,
      activeFailures,
      reason: stable
        ? undefined
        : activeFailures.map((failure) => `${failure.kind}:${failure.reason}`).join("; ")
    };
  }

  shouldBlockBroadReads(target: AssistantTarget): boolean {
    return !this.getHealth(target).stable;
  }

  private prune(records: FailureRecord[]): FailureRecord[] {
    const cutoff = Date.now() - HEALTH_WINDOW_MS;
    return records.filter((record) => record.timestamp >= cutoff);
  }
}
