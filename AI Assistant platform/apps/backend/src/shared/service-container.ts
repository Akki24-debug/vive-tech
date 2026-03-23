import { ActionExecutionService } from "../actions/action-execution-service";
import { ActionPolicyEngine } from "../actions/action-policy-engine";
import { listActionCatalog } from "../actions/action-registry";
import { BusinessBrainScopeResolver } from "../actions/business-brain-scope-resolver";
import { AssistantTarget } from "@vlv-ai/shared";
import { ActionProposalService } from "../ai/action-proposal-service";
import { AiTelemetryService } from "../ai/ai-telemetry-service";
import { AiWorkflowPreflightService } from "../ai/ai-workflow-preflight-service";
import { AssistantOrchestrator } from "../ai/assistant-orchestrator";
import { CostControlService } from "../ai/cost-control-service";
import { DomainHealthService } from "../ai/domain-health-service";
import { BrainAdminService } from "../brain-admin/brain-admin-service";
import { FinalResponseService } from "../ai/final-response-service";
import { OpenAIClientFactory } from "../ai/openai-client";
import { ResponseTemplateService } from "../ai/response-template-service";
import { ApprovalService } from "../approvals/approval-service";
import { AuthorizationService } from "../auth/authorization-service";
import { MetaCloudWhatsAppClient } from "../channels/whatsapp/meta-cloud-client";
import { WhatsAppService } from "../channels/whatsapp/whatsapp-service";
import { ConnectionTestService } from "../config/connection-test-service";
import { RuntimeConfigService } from "../config/runtime-config-service";
import { ConversationStore } from "../conversations/conversation-store";
import { MariaDbPool } from "../db/mariadb-pool";
import { ProcedureExecutor } from "../db/procedure-executor";
import { DocumentationService } from "../docs/documentation-service";
import { ActivityLogService } from "../logging/activity-log-service";
import { PmsMobileService } from "../mobile/pms-mobile-service";

export interface ApplicationServices {
  runtimeConfigService: RuntimeConfigService;
  documentationService: DocumentationService;
  activityLogService: ActivityLogService;
  conversationStore: ConversationStore;
  authorizationService: AuthorizationService;
  actionPolicyEngine: ActionPolicyEngine;
  approvalService: ApprovalService;
  procedureExecutor: ProcedureExecutor;
  pmsMobileService: PmsMobileService;
  brainAdminService: BrainAdminService;
  assistantOrchestrator: AssistantOrchestrator;
  whatsappService: WhatsAppService;
  connectionTestService: ConnectionTestService;
  listActionCatalog: (target: AssistantTarget) => ReturnType<typeof listActionCatalog>;
}

export function createApplicationServices(): ApplicationServices {
  const runtimeConfigService = new RuntimeConfigService();
  const documentationService = new DocumentationService(runtimeConfigService);
  const activityLogService = new ActivityLogService();
  const conversationStore = new ConversationStore();
  const authorizationService = new AuthorizationService();
  const approvalService = new ApprovalService();
  const mariaDbPool = new MariaDbPool(runtimeConfigService);
  const costControlService = new CostControlService(runtimeConfigService);
  const domainHealthService = new DomainHealthService();
  const aiTelemetryService = new AiTelemetryService(activityLogService);
  const preflightService = new AiWorkflowPreflightService(
    runtimeConfigService,
    documentationService,
    mariaDbPool
  );
  const responseTemplateService = new ResponseTemplateService();
  const businessBrainScopeResolver = new BusinessBrainScopeResolver(mariaDbPool);
  const actionPolicyEngine = new ActionPolicyEngine(
    runtimeConfigService,
    authorizationService,
    businessBrainScopeResolver,
    costControlService,
    domainHealthService
  );
  const procedureExecutor = new ProcedureExecutor(mariaDbPool);
  const pmsMobileService = new PmsMobileService(mariaDbPool, activityLogService);
  const brainAdminService = new BrainAdminService(
    runtimeConfigService,
    mariaDbPool,
    procedureExecutor,
    businessBrainScopeResolver
  );
  const actionExecutionService = new ActionExecutionService(procedureExecutor);
  const openAIClientFactory = new OpenAIClientFactory(runtimeConfigService);
  const actionProposalService = new ActionProposalService(
    openAIClientFactory,
    activityLogService,
    costControlService,
    preflightService,
    domainHealthService,
    aiTelemetryService
  );
  const finalResponseService = new FinalResponseService(
    openAIClientFactory,
    costControlService,
    preflightService,
    domainHealthService,
    aiTelemetryService,
    responseTemplateService
  );
  const assistantOrchestrator = new AssistantOrchestrator(
    conversationStore,
    actionProposalService,
    actionPolicyEngine,
    approvalService,
    actionExecutionService,
    finalResponseService,
    activityLogService
  );
  const metaCloudWhatsAppClient = new MetaCloudWhatsAppClient();
  const connectionTestService = new ConnectionTestService(
    runtimeConfigService,
    metaCloudWhatsAppClient
  );
  const whatsappService = new WhatsAppService(
    runtimeConfigService,
    metaCloudWhatsAppClient,
    assistantOrchestrator,
    activityLogService
  );

  return {
    runtimeConfigService,
    documentationService,
    activityLogService,
    conversationStore,
    authorizationService,
    actionPolicyEngine,
    approvalService,
    procedureExecutor,
    pmsMobileService,
    brainAdminService,
    assistantOrchestrator,
    whatsappService,
    connectionTestService,
    listActionCatalog
  };
}
