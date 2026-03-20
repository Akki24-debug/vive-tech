import crypto from "node:crypto";

import { ConfigurationError } from "../shared/errors";

const algorithm = "aes-256-gcm";
const devFallbackSecret = "dev-only-local-encryption-key-change-me";

function getKeyMaterial(): Buffer {
  const configured = process.env.APP_ENCRYPTION_KEY ?? devFallbackSecret;
  return crypto.createHash("sha256").update(configured).digest();
}

export function encryptSecret(value: string): string {
  const iv = crypto.randomBytes(16);
  const cipher = crypto.createCipheriv(algorithm, getKeyMaterial(), iv);
  const encrypted = Buffer.concat([cipher.update(value, "utf8"), cipher.final()]);
  const tag = cipher.getAuthTag();
  return `${iv.toString("hex")}:${tag.toString("hex")}:${encrypted.toString("hex")}`;
}

export function decryptSecret(value?: string): string {
  if (!value) {
    return "";
  }

  const [ivHex, tagHex, encryptedHex] = value.split(":");

  if (!ivHex || !tagHex || !encryptedHex) {
    throw new ConfigurationError("Invalid encrypted secret format.");
  }

  const decipher = crypto.createDecipheriv(
    algorithm,
    getKeyMaterial(),
    Buffer.from(ivHex, "hex")
  );

  decipher.setAuthTag(Buffer.from(tagHex, "hex"));

  const decrypted = Buffer.concat([
    decipher.update(Buffer.from(encryptedHex, "hex")),
    decipher.final()
  ]);

  return decrypted.toString("utf8");
}
