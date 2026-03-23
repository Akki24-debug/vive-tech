import { createApp } from "./app";
import { createApplicationServices } from "./shared/service-container";

async function main(): Promise<void> {
  const services = createApplicationServices();
  const app = createApp(services);
  const port = Number(process.env.PORT ?? 3001);

  if (!process.env.APP_ENCRYPTION_KEY) {
    await services.activityLogService.warn(
      "startup.encryption_key_missing",
      "APP_ENCRYPTION_KEY is not set. Falling back to the development encryption secret."
    );
  }

  app.listen(port, async () => {
    await services.activityLogService.info("startup.ready", "Backend server is running.", {
      port
    });
  });
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
