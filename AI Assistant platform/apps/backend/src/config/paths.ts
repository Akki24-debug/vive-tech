import fs from "node:fs";
import path from "node:path";

function resolveWorkspaceRoot(fromDirectory: string): string {
  let current = fromDirectory;

  for (let index = 0; index < 8; index += 1) {
    const packageJson = path.join(current, "package.json");
    const appsDirectory = path.join(current, "apps");
    const packagesDirectory = path.join(current, "packages");

    if (fs.existsSync(packageJson) && fs.existsSync(appsDirectory) && fs.existsSync(packagesDirectory)) {
      return current;
    }

    const parent = path.dirname(current);
    if (parent === current) {
      break;
    }

    current = parent;
  }

  return path.resolve(fromDirectory, "..", "..", "..", "..");
}

const workspaceRoot = resolveWorkspaceRoot(__dirname);
const backendRoot = path.join(workspaceRoot, "apps", "backend");

export const paths = {
  backendRoot,
  workspaceRoot,
  docsDirectory: path.join(workspaceRoot, "docs"),
  sharedDocsDirectory: path.join(workspaceRoot, "docs", "shared"),
  domainDocsDirectory: path.join(workspaceRoot, "docs", "domains"),
  runtimeDirectory: path.join(workspaceRoot, "storage", "runtime"),
  configFile: path.join(workspaceRoot, "storage", "runtime", "config", "runtime-config.json"),
  approvalsDirectory: path.join(workspaceRoot, "storage", "runtime", "approvals"),
  conversationsDirectory: path.join(workspaceRoot, "storage", "runtime", "conversations"),
  logsFile: path.join(workspaceRoot, "storage", "runtime", "logs", "application.jsonl")
};
