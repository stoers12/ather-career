<?php

declare(strict_types=1);

final class EvidenceHubRecommendationActionTest
{
    public static function run(TestEnvironment $environment): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_recommendation_action_tokens.php';

        $fixtures = json_decode((string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/tests/phase2/fixtures/evidence-hub-action-fixtures.json'), true, 512, JSON_THROW_ON_ERROR);
        phase2Assert(is_array($fixtures), 'Evidence Hub action fixtures are invalid.');
        $bytes = hex2bin((string) $fixtures['token_bytes_hex']);
        phase2AssertSame($fixtures['expected_token'], evidenceHubRecommendationActionTokenEncode($bytes), 'Action-token encoding is not unpadded base64url.');
        phase2Assert(is_string($bytes), 'Synthetic action-token bytes are invalid.');
        $_SESSION = [];
        $ownerA = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(1), 10);
        $ownerB = AuthorizedPortfolioContext::fromValidatedOwnership(AuthenticatedUserContext::fromValidatedUser(2), 20);
        $candidate = self::candidate();
        $token = issueEvidenceHubRecommendationActionToken($ownerA, $candidate, 'snooze', static fn (int $length): string => $bytes);
        phase2AssertSame($fixtures['expected_token'], $token, 'Injected action-token source did not produce the expected token.');
        $records = $_SESSION[EVIDENCE_HUB_RECOMMENDATION_ACTION_TOKEN_SESSION_KEY] ?? [];
        phase2Assert(is_array($records) && !str_contains(serialize($records), $token), 'Raw action token was retained in the Owner session.');
        phase2AssertSame(null, consumeEvidenceHubRecommendationActionToken($ownerA, 'dismiss', $token), 'Action-specific token was accepted for the wrong action.');
        phase2AssertSame(null, consumeEvidenceHubRecommendationActionToken($ownerA, 'snooze', $token), 'Wrong-action attempt did not consume the token.');

        $token = issueEvidenceHubRecommendationActionToken($ownerA, $candidate, 'dismiss', static fn (int $length): string => str_repeat(chr(1), $length));
        phase2AssertSame(null, consumeEvidenceHubRecommendationActionToken($ownerB, 'dismiss', $token), 'Foreign Owner context accepted an action token.');
        $token = issueEvidenceHubRecommendationActionToken($ownerA, $candidate, 'dismiss', static fn (int $length): string => str_repeat(chr(2), $length));
        $record = consumeEvidenceHubRecommendationActionToken($ownerA, 'dismiss', $token);
        phase2Assert(is_array($record) && $record['recommendation_key'] === $candidate['recommendation_key'], 'Same-tenant action token did not resolve its server binding.');
        phase2AssertSame(null, consumeEvidenceHubRecommendationActionToken($ownerA, 'dismiss', $token), 'Consumed action token was replayed.');
        self::feedbackContract();
        self::presentationAndRouteContracts();
    }

    /** @return array<string,mixed> */
    private static function candidate(): array
    {
        return [
            'recommendation_key' => str_repeat('a', 64),
            'rule_version' => '1.0.0',
            'evidence_fingerprint' => str_repeat('b', 64),
        ];
    }

    private static function presentationAndRouteContracts(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_presentation.php';
        $route = (string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/owner_evidence_hub.php');
        foreach (['csrf_token', 'action_token', "['snooze', 'dismiss']", 'consumeEvidenceHubRecommendationActionToken', 'executeAuthorizedEvidenceHubRecommendationAction', "httpRedirect('/owner/evidence-hub', 303)"] as $required) {
            phase2Assert(str_contains($route, $required), "Protected action route is missing {$required}.");
        }
        foreach (['مركز الأدلة', 'تم تأجيل التوصية لمدة 14 يومًا.', 'تم تجاهل التوصية.', 'لم تعد التوصية متاحة. أعد تحميل مركز الأدلة.', 'إجراء التوصية غير صالح.'] as $required) {
            phase2Assert(str_contains($route, $required), "Protected action route is missing Arabic Evidence Hub copy {$required}.");
        }
        phase2Assert(is_file(PHASE2_REPOSITORY_ROOT . '/tests/phase2/support/evidence-hub-owner-actions-visual.cjs'), 'R4 real-browser action support is missing.');
        phase2Assert(!str_contains($route, 'target_ref') && !str_contains($route, 'snoozed_until'), 'Action route accepts prohibited client recommendation fields.');
        $contract = self::contractWithRecommendation();
        $_SESSION = ['csrf_token' => str_repeat('c', 64)];
        ob_start();
        renderEvidenceHubOwnerPresentation($contract, [['snooze' => str_repeat('A', 43), 'dismiss' => str_repeat('B', 43)]], 'تم تجاهل التوصية.');
        $html = (string) ob_get_clean();
        foreach (['name="csrf_token"', 'name="action" value="snooze"', 'name="action_token"', 'تأجيل 14 يومًا', 'تجاهل التوصية', 'تم تجاهل التوصية.'] as $required) {
            phase2Assert(str_contains($html, $required), "Action presenter is missing {$required}.");
        }
        foreach ([str_repeat('a', 64), str_repeat('b', 64), 'opaque_target_ref', 'recommendation_key', 'evidence_fingerprint'] as $forbidden) {
            phase2Assert(!str_contains($html, $forbidden), "Action presenter disclosed {$forbidden}.");
        }
    }

    private static function feedbackContract(): void
    {
        $snooze = 'تم تأجيل التوصية لمدة 14 يومًا.';
        $dismiss = 'تم تجاهل التوصية.';

        $_SESSION = [];
        setEvidenceHubRecommendationActionFeedback($snooze);
        phase2AssertSame($snooze, takeEvidenceHubRecommendationActionFeedback(), 'Arabic Snooze feedback was not accepted.');
        phase2AssertSame('', takeEvidenceHubRecommendationActionFeedback(), 'Feedback was not consumed exactly once.');

        setEvidenceHubRecommendationActionFeedback($dismiss);
        phase2AssertSame($dismiss, takeEvidenceHubRecommendationActionFeedback(), 'Arabic Dismiss feedback was not accepted.');

        foreach (['Recommendation snoozed for 14 days.', 'Recommendation dismissed.', 'arbitrary feedback'] as $invalid) {
            setEvidenceHubRecommendationActionFeedback($invalid);
            phase2AssertSame('', takeEvidenceHubRecommendationActionFeedback(), "Non-contract feedback was accepted: {$invalid}");
            phase2Assert(!isset($_SESSION[EVIDENCE_HUB_RECOMMENDATION_ACTION_FEEDBACK_SESSION_KEY]), 'Rejected feedback was not consumed from the session.');
        }
    }

    /** @return array<string,mixed> */
    private static function contractWithRecommendation(): array
    {
        $golden = json_decode((string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/tests/phase2/fixtures/evidence-hub-golden-fixtures.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($golden['positive_payloads'] as $case) {
            if (($case['id'] ?? null) === 'PAYLOAD-ZERO') {
                $payload = $case['payload'];
                $payload['recommendations'] = [[
                    'rule_id' => 'add_first_project', 'rule_version' => '1.0.0', 'recommendation_key' => str_repeat('a', 64), 'evidence_fingerprint' => str_repeat('b', 64), 'lifecycle_state' => 'active', 'priority_rank' => 1, 'display_order' => 1, 'reason_codes' => ['NO_PROJECTS'], 'target' => ['route' => 'owner_evidence_hub', 'action_id' => 'add_first_project', 'opaque_target_ref' => 'a' . str_repeat('c', 63)], 'snoozed_until' => null,
                ]];
                return $payload;
            }
        }
        throw new RuntimeException('Action contract fixture is unavailable.');
    }
}
