<?php

declare(strict_types=1);

// Compatibility wrappers: the implementation lives in src/EvidenceHub/Text.

require_once __DIR__ . '/autoload.php';

use Ather\EvidenceHub\Text\EvidenceTextPolicy;

const EVIDENCE_TEXT_RULE_VERSION = EvidenceTextPolicy::RULE_VERSION;
const EVIDENCE_TEXT_LIVE_RULE_VERSION = EvidenceTextPolicy::LIVE_RULE_VERSION;
const EVIDENCE_TEXT_SUPPORTED_RULE_VERSIONS = EvidenceTextPolicy::SUPPORTED_RULE_VERSIONS;
const EVIDENCE_TEXT_FIELD_POLICIES = EvidenceTextPolicy::FIELD_POLICIES;
const EVIDENCE_TEXT_PLACEHOLDERS = EvidenceTextPolicy::PLACEHOLDERS;

/** @return array{maximum_unicode_scalars: int, minimum_content_graphemes: int, minimum_useful_tokens: int, minimum_distinct_tokens: int} */
function evidenceTextFieldPolicy(string $field): array
{
    return EvidenceTextPolicy::fieldPolicy($field);
}

/** @return list<string> */
function evidenceTextFieldNames(): array
{
    return EvidenceTextPolicy::fieldNames();
}
