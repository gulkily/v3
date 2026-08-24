# Multi Provider LLM Config Step 4 Implementation Summary

## Stage 1 - Provider-Neutral Config Contract
- Changes:
  - Added `LlmProviderConfig` to resolve provider-neutral settings with legacy Dedalus fallback.
  - Added `StructuredChatProvider` as the shared structured-chat provider contract for later adapters.
  - Extended private config loading and `./v3 private-config` defaults/view output for `LLM_*` settings with redacted extra headers.
  - Added focused tests for neutral config resolution, legacy fallback, stub-mode fallback, and redacted command output.
- Verification:
  - `php -l src/ForumRewrite/Llm/LlmProviderConfig.php && php -l src/ForumRewrite/Llm/StructuredChatProvider.php && php -l src/ForumRewrite/Support/PrivateConfig.php && php -l scripts/write_private_config.php && php -l tests/LlmProviderConfigTest.php && php -l tests/PrivateConfigCommandTest.php && php -l tests/run.php`
  - `php -d zend.assertions=1 -d assert.exception=1 -r 'require "tests/ApplicationServerTimingTest.php"; require "tests/LlmProviderConfigTest.php"; require "tests/PrivateConfigCommandTest.php"; foreach ([new LlmProviderConfigTest(), new PrivateConfigCommandTest()] as $test) { foreach (get_class_methods($test) as $method) { if (str_starts_with($method, "test")) { $test->{$method}(); echo "PASS " . $test::class . "::{$method}\n"; } } }'`
  - `php -d zend.assertions=1 -d assert.exception=1 -r 'require "tests/ApplicationServerTimingTest.php"; require "tests/LocalAppSmokeTest.php"; $test = new LocalAppSmokeTest(); $test->testPrivateConfigViewRedactsSecretAndShowsUpdateReminder(); echo "PASS LocalAppSmokeTest::testPrivateConfigViewRedactsSecretAndShowsUpdateReminder\n";'`
- Notes:
  - Stage 1 does not switch runtime provider behavior yet; later stages will consume the neutral config contract.
  - Existing `DEDALUS_*` settings remain valid through resolver fallback.

## Stage 2 - OpenAI-Compatible Provider Adapter
- Changes:
  - Added `OpenAiCompatibleStructuredChatProvider` for reusable `/v1/chat/completions` structured-output requests.
  - Added `StructuredChatCompletionDecoder` and kept `DedalusPostAnalyzer::decodeCompletionPayload()` as a compatibility wrapper.
  - Rewired `DedalusPostAnalyzer` to delegate provider transport through the shared adapter.
  - Added redacted provider diagnostics support for provider request exceptions and persisted failed analysis rows.
  - Added focused tests for payload shape, nested provider error extraction, header redaction, analyzer delegation, and persisted diagnostics.
- Verification:
  - `php -l src/ForumRewrite/Llm/OpenAiCompatibleStructuredChatProvider.php && php -l src/ForumRewrite/Llm/StructuredChatCompletionDecoder.php && php -l src/ForumRewrite/Analysis/DedalusPostAnalyzer.php && php -l tests/OpenAiCompatibleStructuredChatProviderTest.php && php -l tests/DedalusPostAnalyzerTest.php && php -l tests/run.php`
  - `php -d zend.assertions=1 -d assert.exception=1 -r 'require "tests/ApplicationServerTimingTest.php"; require "tests/OpenAiCompatibleStructuredChatProviderTest.php"; require "tests/DedalusPostAnalyzerTest.php"; foreach ([new OpenAiCompatibleStructuredChatProviderTest(), new DedalusPostAnalyzerTest()] as $test) { foreach (get_class_methods($test) as $method) { if (str_starts_with($method, "test") && ($test::class !== "DedalusPostAnalyzerTest" || in_array($method, ["testDecodeCompletionPayloadAcceptsStringJsonContent", "testDecodeCompletionPayloadAcceptsStructuredMessageContent", "testDecodeCompletionPayloadAcceptsTextBlockContent", "testDecodeCompletionPayloadPrefersParsedContent", "testAnalyzerDelegatesStructuredCompletionToProvider"], true))) { $test->{$method}(); echo "PASS " . $test::class . "::{$method}\n"; } } }'`
  - `php -d zend.assertions=1 -d assert.exception=1 -r 'require "tests/ApplicationServerTimingTest.php"; require "tests/DedalusPostAnalyzerTest.php"; $test = new DedalusPostAnalyzerTest(); foreach (get_class_methods($test) as $method) { if (str_starts_with($method, "test")) { $test->{$method}(); echo "PASS DedalusPostAnalyzerTest::{$method}\n"; } }'`
- Notes:
  - Runtime provider selection is still Dedalus-specific until Stage 4.
  - The OpenAI-compatible adapter is intended for OpenAI, OpenRouter, Dedalus, LiteLLM, and local compatible gateways.

## Stage 3 - Native Anthropic Provider Adapter
- Changes:
  - Added `AnthropicStructuredChatProvider` for native Anthropic Messages API requests.
  - Mapped provider-neutral structured-chat calls to Anthropic `output_config.format` JSON schema payloads.
  - Added Anthropic-specific response decoding, error extraction, header redaction, and persisted provider diagnostics.
  - Updated Anthropic default config to `claude-haiku-4-5-20251001`.
  - Added focused tests for Anthropic payload shape, text JSON decoding, error diagnostics, key redaction, and default model resolution.
- Verification:
  - `php -l src/ForumRewrite/Llm/AnthropicStructuredChatProvider.php && php -l tests/AnthropicStructuredChatProviderTest.php && php -l src/ForumRewrite/Llm/LlmProviderConfig.php && php -l tests/LlmProviderConfigTest.php && php -l tests/run.php`
  - `php -d zend.assertions=1 -d assert.exception=1 -r 'require "tests/ApplicationServerTimingTest.php"; require "tests/AnthropicStructuredChatProviderTest.php"; $test = new AnthropicStructuredChatProviderTest(); $test->testProviderBuildsAnthropicStructuredOutputPayload(); $test->testProviderDecodesTextJsonResponse(); $test->testProviderExtractsAnthropicErrorMessageAndRedactsKey();'`
  - `php -d zend.assertions=1 -d assert.exception=1 -r 'require "tests/ApplicationServerTimingTest.php"; require "tests/LlmProviderConfigTest.php"; $test = new LlmProviderConfigTest(); $test->testAnthropicDefaultsUseCurrentHaikuModel();'`
- Notes:
  - Anthropic runtime selection is wired in Stage 4.
  - The adapter removes unsupported JSON schema validation keywords before sending schemas to Anthropic structured output.

## Stage 4 - Runtime Provider Selection
- Changes:
  - Added `PostAnalyzerFactory` to create post analyzers from provider-neutral private config.
  - Wired `Application::postAnalysisService()` through the factory for `stub`, legacy Dedalus, OpenAI-compatible, OpenRouter, and Anthropic providers.
  - Reused the same factory in `scripts/backfill_unicode_risk.php` so backfills and web/API analysis resolve providers consistently.
  - Kept missing API key handling graceful and changed the public failure message to provider-neutral wording.
  - Added focused factory tests for stub, missing key, legacy Dedalus, OpenRouter extra headers, and Anthropic adapter selection.
- Verification:
  - `php -l src/ForumRewrite/Analysis/PostAnalyzerFactory.php && php -l src/ForumRewrite/Application.php && php -l src/ForumRewrite/Analysis/PostAnalysisService.php && php -l scripts/backfill_unicode_risk.php && php -l tests/PostAnalyzerFactoryTest.php && php -l tests/WriteApiSmokeTest.php && php -l tests/run.php`
  - `php -d zend.assertions=1 -d assert.exception=1 -r 'require "tests/ApplicationServerTimingTest.php"; require "tests/PostAnalyzerFactoryTest.php"; $test = new PostAnalyzerFactoryTest(); foreach (get_class_methods($test) as $method) { if (str_starts_with($method, "test")) { $test->{$method}(); echo "PASS PostAnalyzerFactoryTest::{$method}\n"; } }'`
  - `php -d zend.assertions=1 -d assert.exception=1 -r 'require "tests/ApplicationServerTimingTest.php"; require "tests/PrivateConfigCommandTest.php"; require "tests/WriteApiSmokeTest.php"; $test = new WriteApiSmokeTest(); foreach (["testPostAnalysisEndpointReportsMissingConfigWithoutStoringAnalysis", "testPostAnalysisEndpointStoresStubResultIdempotently", "testGenerateAgentReplyReportsFailedAnalysisAsRequired"] as $method) { $test->{$method}(); } echo "PASS selected WriteApiSmokeTest methods\n";'`
  - `php -d zend.assertions=1 -d assert.exception=1 tests/run.php`
- Notes:
  - The full test runner passed, but emitted existing asset fingerprint warnings for already-fingerprinted temporary asset names becoming too long during smoke tests.
  - Unknown non-Anthropic provider names are treated as OpenAI-compatible gateways when a base URL and API key are configured.
