import path from "node:path";

const backendRoot = path.resolve(__dirname, "..", "..");
const workspaceRoot = path.resolve(backendRoot, "..", "..");

export const paths = {
  backendRoot,
  workspaceRoot,
  docsDirectory: path.join(workspaceRoot, "docs"),
  runtimeDirectory: path.join(workspaceRoot, "storage", "runtime"),
  configFile: path.join(workspaceRoot, "storage", "runtime", "config", "runtime-config.json"),
  approvalsDirectory: path.join(workspaceRoot, "storage", "runtime", "approvals"),
  conversationsDirectory: path.join(workspaceRoot, "storage", "runtime", "conversations"),
  logsFile: path.join(workspaceRoot, "storage", "runtime", "logs", "application.jsonl")
};
