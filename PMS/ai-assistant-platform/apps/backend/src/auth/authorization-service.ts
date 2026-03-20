import { AssistantRequest } from "@vlv-ai/shared";

import { AuthorizationError } from "../shared/errors";

export class AuthorizationService {
  assertActionAllowed(request: AssistantRequest, requiredPermissions: string[]): void {
    if (requiredPermissions.length === 0) {
      return;
    }

    const roles = new Set(request.roles);
    if (roles.has("owner") || roles.has("admin")) {
      return;
    }

    const permissions = new Set(request.permissions);
    const missing = requiredPermissions.filter((permission) => !permissions.has(permission));

    if (missing.length > 0) {
      throw new AuthorizationError("The user is not allowed to execute this action.", {
        missingPermissions: missing
      });
    }
  }
}
