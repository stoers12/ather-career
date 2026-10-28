# EH-06 synthetic calibration

Calibrated before changing runtime rules. Only synthetic inputs were used. No raw evidence text is included.

Natural false positives: **0**. Adversarial catches: **6/6**. Cross-field mismatches at 0.80: **0**.

The selected defaults remain distinct ratio **4000 / minimum 12 tokens** and containment **0.80**. Storage-only rows pin storage validity; their completeness is not an acceptance requirement. Counts reported by 1.1.0 are effective counts.

## Ratio candidates

| Ratio bps | Minimum tokens | Natural false positives | Protected fixture false positives | Rotations caught |
|---|---:|---:|---:|---:|
| 3000 | 10 | 0 | 0 | 2/2 |
| 3000 | 12 | 0 | 0 | 2/2 |
| 3000 | 16 | 0 | 0 | 2/2 |
| 3500 | 10 | 0 | 0 | 2/2 |
| 3500 | 12 | 0 | 0 | 2/2 |
| 3500 | 16 | 0 | 0 | 2/2 |
| 4000 | 10 | 0 | 0 | 2/2 |
| 4000 | 12 | 0 | 0 | 2/2 |
| 4000 | 16 | 0 | 0 | 2/2 |
| 4500 | 10 | 0 | 1 | 2/2 |
| 4500 | 12 | 0 | 1 | 2/2 |
| 4500 | 16 | 0 | 1 | 2/2 |

## Cross-field containment candidates

| Synthetic project | 0.60 flagged fields | 0.70 | 0.80 | 0.90 |
|---|---|---|---|---|
| exact-en | personal_role, measurable_outcome | personal_role, measurable_outcome | personal_role, measurable_outcome | personal_role, measurable_outcome |
| exact-ar | personal_role, measurable_outcome | personal_role, measurable_outcome | personal_role, measurable_outcome | personal_role, measurable_outcome |
| contained-outcome | measurable_outcome | measurable_outcome | measurable_outcome | measurable_outcome |
| shared-phrase-en | none | none | none | none |
| shared-phrase-ar | none | none | none | none |
| few-shingles-contained | none | none | none | none |
| few-shingles-exact | measurable_outcome | measurable_outcome | measurable_outcome | measurable_outcome |

## adversarial

| Fixture id | 1.0.0 | 1.1.0 | 1.1.0 reasons | Longest letter run | Distinct / useful |
|---|---|---|---|---:|---:|
| ADVERSARIAL-rotation-en | complete | needs_attention | LOW_DISTINCT_TOKEN_RATIO | 2 | 5/25 |
| ADVERSARIAL-rotation-ar | complete | needs_attention | LOW_DISTINCT_TOKEN_RATIO | 1 | 5/25 |

## already harmless

| Fixture id | 1.0.0 | 1.1.0 | 1.1.0 reasons | Longest letter run | Distinct / useful |
|---|---|---|---|---:|---:|
| ADVERSARIAL-tatweel | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET | 1 | 4/4 |
| ADVERSARIAL-punctuation | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET | 1 | 4/4 |
| ADVERSARIAL-emoji | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET | 1 | 4/4 |

## existing policy

| Fixture id | 1.0.0 | 1.1.0 | 1.1.0 reasons | Longest letter run | Distinct / useful |
|---|---|---|---|---:|---:|
| TEXT-EMPTY | unavailable | unavailable | EMPTY_NORMALIZED_VALUE | 0 | n/a |
| TEXT-NON-SCALAR | unavailable | unavailable | NON_SCALAR_INPUT | 0 | n/a |
| TEXT-INVALID-UTF8 | unavailable | unavailable | INVALID_UTF8 | 0 | n/a |
| TEXT-CONCISE-OUTCOME-EN | complete | complete | TEXT_COMPLETE | 1 | 8/8 |
| TEXT-CONCISE-OUTCOME-AR | complete | complete | TEXT_COMPLETE | 1 | 8/8 |
| TEXT-MIXED-DIGITS | complete | complete | TEXT_COMPLETE | 1 | 8/8 |
| TEXT-ARABIC-PUNCTUATION | complete | complete | TEXT_COMPLETE | 1 | 8/8 |
| TEXT-EMOJI-PADDING | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 1 | 1/1 |
| TEXT-PUNCTUATION-PADDING | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 1 | 1/1 |
| TEXT-NFC-COMPOSED | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 1 | 1/1 |
| TEXT-NFC-DECOMPOSED | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 1 | 1/1 |
| TEXT-TATWEEL-REMOVED | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 1 | 1/1 |
| TEXT-DIACRITIC-TOKEN-IDENTITY | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 1 | 1/4 |
| TEXT-WHITESPACE-NORMALIZED | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, USEFUL_TOKEN_THRESHOLD_NOT_MET | 2 | 4/4 |
| TEXT-ALLOW-TAB-LF-CR | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, USEFUL_TOKEN_THRESHOLD_NOT_MET | 1 | 4/4 |
| TEXT-REJECT-C0 | unavailable | unavailable | PROHIBITED_CONTROL_CHARACTER | 0 | n/a |
| TEXT-REJECT-C1 | unavailable | unavailable | PROHIBITED_CONTROL_CHARACTER | 0 | n/a |
| TEXT-REJECT-ZWSP | unavailable | unavailable | ZERO_WIDTH_CHARACTER | 0 | n/a |
| TEXT-REJECT-WORD-JOINER | unavailable | unavailable | ZERO_WIDTH_CHARACTER | 0 | n/a |
| TEXT-REJECT-BOM | unavailable | unavailable | ZERO_WIDTH_CHARACTER | 0 | n/a |
| TEXT-REJECT-BIDI-OVERRIDE | unavailable | unavailable | PROHIBITED_DIRECTIONALITY_CHARACTER | 0 | n/a |
| TEXT-REJECT-BIDI-ISOLATE | unavailable | unavailable | PROHIBITED_DIRECTIONALITY_CHARACTER | 0 | n/a |
| TEXT-ALLOW-ZWNJ | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 1 | 1/1 |
| TEXT-ALLOW-ZWJ | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 1 | 1/1 |
| TEXT-PLACEHOLDER-EN-NORMALIZED | needs_attention | needs_attention | PLACEHOLDER_CONFIRMED | 1 | 1/1 |
| TEXT-PLACEHOLDER-EN-EXACT | needs_attention | needs_attention | PLACEHOLDER_CONFIRMED | 1 | 1/1 |
| TEXT-PLACEHOLDER-AR | needs_attention | needs_attention | PLACEHOLDER_CONFIRMED | 1 | 1/1 |
| TEXT-PLACEHOLDER-AR-EXACT | needs_attention | needs_attention | PLACEHOLDER_CONFIRMED | 1 | 1/1 |
| TEXT-PLACEHOLDER-EMBEDDED | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, USEFUL_TOKEN_THRESHOLD_NOT_MET | 1 | 6/6 |
| TEXT-DIVERSE-FILLER | complete | complete | TEXT_COMPLETE | 2 | 14/14 |
| TEXT-REPETITION-5900-BPS | complete | complete | TEXT_COMPLETE | 1 | 42/100 |
| TEXT-REPETITION-6000-BPS | needs_attention | needs_attention | REPETITION_SUSPECTED | 1 | 3/5 |
| TEXT-REPETITION-6100-BPS | needs_attention | needs_attention | REPETITION_SUSPECTED | 1 | 40/100 |
| TEXT-REPETITION-AR | needs_attention | needs_attention | REPETITION_SUSPECTED | 1 | 3/5 |
| TEXT-REPETITION-TATWEEL | needs_attention | needs_attention | REPETITION_SUSPECTED | 1 | 3/5 |
| TEXT-REPETITION-CASEFOLD | needs_attention | needs_attention | REPETITION_SUSPECTED | 1 | 3/5 |
| TEXT-REPETITION-DIACRITIC | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, REPETITION_SUSPECTED | 1 | 3/5 |
| INLINE-non-scalar | unavailable | unavailable | NON_SCALAR_INPUT | 0 | n/a |
| INLINE-invalid-utf8 | unavailable | unavailable | INVALID_UTF8 | 0 | n/a |
| INLINE-null | unavailable | unavailable | FIELD_NOT_AVAILABLE | 0 | n/a |
| INLINE-empty | unavailable | unavailable | EMPTY_NORMALIZED_VALUE | 0 | n/a |
| INLINE-composed | complete | complete | TEXT_COMPLETE | 1 | 8/8 |
| INLINE-decomposed | complete | complete | TEXT_COMPLETE | 1 | 8/8 |
| INLINE-tatweel | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 1 | 1/1 |
| INLINE-arabic-diacritics | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 1 | 1/1 |
| INLINE-arabic-bare | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 1 | 1/1 |
| INLINE-arabic-digits | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 0 | 1/1 |
| INLINE-digits | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 0 | 1/1 |
| INLINE-emoji-punctuation | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 0 | n/a |
| INLINE-whitespace | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, USEFUL_TOKEN_THRESHOLD_NOT_MET | 2 | 3/3 |
| INLINE-zwnj | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 1 | 1/1 |
| INLINE-zwj | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 1 | 1/1 |
| INLINE-zero-width | unavailable | unavailable | ZERO_WIDTH_CHARACTER | 0 | n/a |
| INLINE-word-joiner | unavailable | unavailable | ZERO_WIDTH_CHARACTER | 0 | n/a |
| INLINE-bom | unavailable | unavailable | ZERO_WIDTH_CHARACTER | 0 | n/a |
| INLINE-control | unavailable | unavailable | PROHIBITED_CONTROL_CHARACTER | 0 | n/a |
| INLINE-control85 | unavailable | unavailable | PROHIBITED_CONTROL_CHARACTER | 0 | n/a |
| INLINE-bidi | unavailable | unavailable | PROHIBITED_DIRECTIONALITY_CHARACTER | 0 | n/a |
| INLINE-isolate | unavailable | unavailable | PROHIBITED_DIRECTIONALITY_CHARACTER | 0 | n/a |
| INLINE-placeholder-en | needs_attention | needs_attention | PLACEHOLDER_CONFIRMED | 1 | 1/1 |
| INLINE-placeholder-ar | needs_attention | needs_attention | PLACEHOLDER_CONFIRMED | 1 | 1/1 |
| INLINE-placeholder-substring | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, USEFUL_TOKEN_THRESHOLD_NOT_MET | 1 | 6/6 |
| INLINE-repetition-ar | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET, REPETITION_SUSPECTED | 1 | 3/5 |
| INLINE-repetition-case | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET, REPETITION_SUSPECTED | 1 | 3/5 |
| INLINE-outcome-ar | complete | complete | TEXT_COMPLETE | 1 | 10/10 |
| INLINE-outcome-en | complete | complete | TEXT_COMPLETE | 2 | 10/10 |
| INLINE-diverse-nonsense | complete | complete | TEXT_COMPLETE | 1 | 11/11 |

## existing repetition

| Fixture id | 1.0.0 | 1.1.0 | 1.1.0 reasons | Longest letter run | Distinct / useful |
|---|---|---|---|---:|---:|
| INLINE-repetition-59-100 | needs_attention | needs_attention | DISTINCT_TOKEN_THRESHOLD_NOT_MET, LOW_DISTINCT_TOKEN_RATIO | 1 | 2/100 |
| INLINE-repetition-3-5 | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET, REPETITION_SUSPECTED | 1 | 2/5 |
| INLINE-repetition-61-100 | needs_attention | needs_attention | DISTINCT_TOKEN_THRESHOLD_NOT_MET, REPETITION_SUSPECTED, LOW_DISTINCT_TOKEN_RATIO | 1 | 2/100 |

## existing synthetic boundary

| Fixture id | 1.0.0 | 1.1.0 | 1.1.0 reasons | Longest letter run | Distinct / useful |
|---|---|---|---|---:|---:|
| TEXT-EN-PROBLEM-GRAPHEME-BELOW | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 52 | 4/7 |
| TEXT-EN-PROBLEM-TOKENS-BELOW | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 54 | 4/6 |
| TEXT-EN-PROBLEM-DISTINCT-BELOW | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 53 | 3/7 |
| TEXT-AR-PROBLEM-GRAPHEME-BELOW | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 52 | 4/7 |
| TEXT-AR-PROBLEM-TOKENS-BELOW | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 54 | 4/6 |
| TEXT-AR-PROBLEM-DISTINCT-BELOW | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 53 | 3/7 |
| TEXT-EN-ROLE-GRAPHEME-BELOW | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 25 | 3/4 |
| TEXT-EN-ROLE-TOKENS-BELOW | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 27 | 3/3 |
| TEXT-EN-ROLE-DISTINCT-BELOW | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 26 | 2/4 |
| TEXT-AR-ROLE-GRAPHEME-BELOW | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 25 | 3/4 |
| TEXT-AR-ROLE-TOKENS-BELOW | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 27 | 3/3 |
| TEXT-AR-ROLE-DISTINCT-BELOW | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 26 | 2/4 |
| TEXT-EN-OUTCOME-GRAPHEME-BELOW | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 16 | 2/3 |
| TEXT-EN-OUTCOME-TOKENS-BELOW | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 18 | 2/2 |
| TEXT-EN-OUTCOME-DISTINCT-BELOW | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 17 | 1/3 |
| TEXT-AR-OUTCOME-GRAPHEME-BELOW | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 16 | 2/3 |
| TEXT-AR-OUTCOME-TOKENS-BELOW | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 18 | 2/2 |
| TEXT-AR-OUTCOME-DISTINCT-BELOW | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 17 | 1/3 |
| INLINE-problem_statement-en-graphemes--1 | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, CHARACTER_RUN_SUSPECTED | 52 | 7/7 |
| INLINE-problem_statement-en-tokens--1 | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, USEFUL_TOKEN_THRESHOLD_NOT_MET | 66 | 6/6 |
| INLINE-problem_statement-en-distinct--1 | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 12 | n/a |
| INLINE-problem_statement-ar-graphemes--1 | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, CHARACTER_RUN_SUSPECTED | 52 | 7/7 |
| INLINE-problem_statement-ar-tokens--1 | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, USEFUL_TOKEN_THRESHOLD_NOT_MET | 66 | 6/6 |
| INLINE-problem_statement-ar-distinct--1 | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 12 | n/a |
| INLINE-personal_role-en-graphemes--1 | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, CHARACTER_RUN_SUSPECTED | 25 | 4/4 |
| INLINE-personal_role-en-tokens--1 | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 39 | 3/3 |
| INLINE-personal_role-en-distinct--1 | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 12 | n/a |
| INLINE-personal_role-ar-graphemes--1 | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, CHARACTER_RUN_SUSPECTED | 25 | 4/4 |
| INLINE-personal_role-ar-tokens--1 | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 39 | 3/3 |
| INLINE-personal_role-ar-distinct--1 | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 12 | n/a |
| INLINE-measurable_outcome-en-graphemes--1 | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, CHARACTER_RUN_SUSPECTED | 16 | 3/3 |
| INLINE-measurable_outcome-en-tokens--1 | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 30 | 2/2 |
| INLINE-measurable_outcome-en-distinct--1 | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 12 | n/a |
| INLINE-measurable_outcome-ar-graphemes--1 | needs_attention | needs_attention | GRAPHEME_THRESHOLD_NOT_MET, CHARACTER_RUN_SUSPECTED | 16 | 3/3 |
| INLINE-measurable_outcome-ar-tokens--1 | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 30 | 2/2 |
| INLINE-measurable_outcome-ar-distinct--1 | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 12 | n/a |

## natural

| Fixture id | 1.0.0 | 1.1.0 | 1.1.0 reasons | Longest letter run | Distinct / useful |
|---|---|---|---|---:|---:|
| NATURAL-long-en | complete | complete | TEXT_COMPLETE | 2 | 202/236 |
| NATURAL-long-ar | complete | complete | TEXT_COMPLETE | 2 | 254/276 |
| NATURAL-special | complete | complete | TEXT_COMPLETE | 3 | 23/24 |
| NATURAL-stretch-en | complete | complete | TEXT_COMPLETE | 5 | 18/18 |
| NATURAL-stretch-ar | complete | complete | TEXT_COMPLETE | 5 | 19/20 |

## storage only

| Fixture id | 1.0.0 | 1.1.0 | 1.1.0 reasons | Longest letter run | Distinct / useful |
|---|---|---|---|---:|---:|
| TEXT-MAX-PROBLEM | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 2000 | n/a |
| TEXT-MAX-PLUS-ONE-PROBLEM | unavailable | unavailable | MAXIMUM_LENGTH_EXCEEDED | 0 | n/a |
| TEXT-MAX-ROLE | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 1500 | n/a |
| TEXT-MAX-PLUS-ONE-ROLE | unavailable | unavailable | MAXIMUM_LENGTH_EXCEEDED | 0 | n/a |
| TEXT-MAX-OUTCOME | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 1000 | n/a |
| TEXT-MAX-PLUS-ONE-OUTCOME | unavailable | unavailable | MAXIMUM_LENGTH_EXCEEDED | 0 | n/a |
| INLINE-problem_statement-max-0 | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 2000 | n/a |
| INLINE-problem_statement-max-1 | unavailable | unavailable | MAXIMUM_LENGTH_EXCEEDED | 0 | n/a |
| INLINE-personal_role-max-0 | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 1500 | n/a |
| INLINE-personal_role-max-1 | unavailable | unavailable | MAXIMUM_LENGTH_EXCEEDED | 0 | n/a |
| INLINE-measurable_outcome-max-0 | needs_attention | needs_attention | CHARACTER_RUN_SUSPECTED, USEFUL_TOKEN_THRESHOLD_NOT_MET, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 1000 | n/a |
| INLINE-measurable_outcome-max-1 | unavailable | unavailable | MAXIMUM_LENGTH_EXCEEDED | 0 | n/a |

## synthetic padding (intended)

| Fixture id | 1.0.0 | 1.1.0 | 1.1.0 reasons | Longest letter run | Distinct / useful |
|---|---|---|---|---:|---:|
| TEXT-EN-PROBLEM-GRAPHEME-AT | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 53 | 4/7 |
| TEXT-EN-PROBLEM-GRAPHEME-ABOVE | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 54 | 4/7 |
| TEXT-EN-PROBLEM-TOKENS-AT | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 53 | 4/7 |
| TEXT-EN-PROBLEM-TOKENS-ABOVE | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 52 | 4/8 |
| TEXT-EN-PROBLEM-DISTINCT-AT | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 53 | 4/7 |
| TEXT-EN-PROBLEM-DISTINCT-ABOVE | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 53 | 5/7 |
| TEXT-AR-PROBLEM-GRAPHEME-AT | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 53 | 4/7 |
| TEXT-AR-PROBLEM-GRAPHEME-ABOVE | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 54 | 4/7 |
| TEXT-AR-PROBLEM-TOKENS-AT | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 53 | 4/7 |
| TEXT-AR-PROBLEM-TOKENS-ABOVE | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 52 | 4/8 |
| TEXT-AR-PROBLEM-DISTINCT-AT | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 53 | 4/7 |
| TEXT-AR-PROBLEM-DISTINCT-ABOVE | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 53 | 5/7 |
| TEXT-EN-ROLE-GRAPHEME-AT | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 26 | 3/4 |
| TEXT-EN-ROLE-GRAPHEME-ABOVE | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 27 | 3/4 |
| TEXT-EN-ROLE-TOKENS-AT | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 26 | 3/4 |
| TEXT-EN-ROLE-TOKENS-ABOVE | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 25 | 3/5 |
| TEXT-EN-ROLE-DISTINCT-AT | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 26 | 3/4 |
| TEXT-EN-ROLE-DISTINCT-ABOVE | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 26 | 4/4 |
| TEXT-AR-ROLE-GRAPHEME-AT | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 26 | 3/4 |
| TEXT-AR-ROLE-GRAPHEME-ABOVE | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 27 | 3/4 |
| TEXT-AR-ROLE-TOKENS-AT | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 26 | 3/4 |
| TEXT-AR-ROLE-TOKENS-ABOVE | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 25 | 3/5 |
| TEXT-AR-ROLE-DISTINCT-AT | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 26 | 3/4 |
| TEXT-AR-ROLE-DISTINCT-ABOVE | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 26 | 4/4 |
| TEXT-EN-OUTCOME-GRAPHEME-AT | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 17 | 2/3 |
| TEXT-EN-OUTCOME-GRAPHEME-ABOVE | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 18 | 2/3 |
| TEXT-EN-OUTCOME-TOKENS-AT | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 17 | 2/3 |
| TEXT-EN-OUTCOME-TOKENS-ABOVE | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 16 | 2/4 |
| TEXT-EN-OUTCOME-DISTINCT-AT | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 17 | 2/3 |
| TEXT-EN-OUTCOME-DISTINCT-ABOVE | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 17 | 3/3 |
| TEXT-AR-OUTCOME-GRAPHEME-AT | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 17 | 2/3 |
| TEXT-AR-OUTCOME-GRAPHEME-ABOVE | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 18 | 2/3 |
| TEXT-AR-OUTCOME-TOKENS-AT | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 17 | 2/3 |
| TEXT-AR-OUTCOME-TOKENS-ABOVE | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 16 | 2/4 |
| TEXT-AR-OUTCOME-DISTINCT-AT | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 17 | 2/3 |
| TEXT-AR-OUTCOME-DISTINCT-ABOVE | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 17 | 3/3 |
| INLINE-problem_statement-en-graphemes-0 | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 53 | 7/7 |
| INLINE-problem_statement-en-tokens-0 | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 65 | 7/7 |
| INLINE-problem_statement-en-distinct-0 | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 12 | n/a |
| INLINE-problem_statement-en-graphemes-1 | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 54 | 7/7 |
| INLINE-problem_statement-en-tokens-1 | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 64 | 8/8 |
| INLINE-problem_statement-en-distinct-1 | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 12 | n/a |
| INLINE-problem_statement-ar-graphemes-0 | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 53 | 7/7 |
| INLINE-problem_statement-ar-tokens-0 | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 65 | 7/7 |
| INLINE-problem_statement-ar-distinct-0 | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 12 | n/a |
| INLINE-problem_statement-ar-graphemes-1 | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 54 | 7/7 |
| INLINE-problem_statement-ar-tokens-1 | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 64 | 8/8 |
| INLINE-problem_statement-ar-distinct-1 | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 12 | n/a |
| INLINE-personal_role-en-graphemes-0 | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 26 | 4/4 |
| INLINE-personal_role-en-tokens-0 | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 38 | 4/4 |
| INLINE-personal_role-en-distinct-0 | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 12 | n/a |
| INLINE-personal_role-en-graphemes-1 | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 27 | 4/4 |
| INLINE-personal_role-en-tokens-1 | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 37 | 5/5 |
| INLINE-personal_role-en-distinct-1 | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 12 | n/a |
| INLINE-personal_role-ar-graphemes-0 | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 26 | 4/4 |
| INLINE-personal_role-ar-tokens-0 | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 38 | 4/4 |
| INLINE-personal_role-ar-distinct-0 | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 12 | n/a |
| INLINE-personal_role-ar-graphemes-1 | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 27 | 4/4 |
| INLINE-personal_role-ar-tokens-1 | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 37 | 5/5 |
| INLINE-personal_role-ar-distinct-1 | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 12 | n/a |
| INLINE-measurable_outcome-en-graphemes-0 | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 17 | 3/3 |
| INLINE-measurable_outcome-en-tokens-0 | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 29 | 3/3 |
| INLINE-measurable_outcome-en-distinct-0 | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 12 | n/a |
| INLINE-measurable_outcome-en-graphemes-1 | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 18 | 3/3 |
| INLINE-measurable_outcome-en-tokens-1 | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 28 | 4/4 |
| INLINE-measurable_outcome-en-distinct-1 | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 12 | n/a |
| INLINE-measurable_outcome-ar-graphemes-0 | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 17 | 3/3 |
| INLINE-measurable_outcome-ar-tokens-0 | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 29 | 3/3 |
| INLINE-measurable_outcome-ar-distinct-0 | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 12 | n/a |
| INLINE-measurable_outcome-ar-graphemes-1 | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 18 | 3/3 |
| INLINE-measurable_outcome-ar-tokens-1 | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 28 | 4/4 |
| INLINE-measurable_outcome-ar-distinct-1 | complete | needs_attention | CHARACTER_RUN_SUSPECTED, DISTINCT_TOKEN_THRESHOLD_NOT_MET | 12 | n/a |
| ADVERSARIAL-stretch-ar | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 14 | 4/4 |
| ADVERSARIAL-stretch-en | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 13 | 5/5 |
| ADVERSARIAL-run-only-ar | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 16 | 3/3 |
| ADVERSARIAL-run-only-en | complete | needs_attention | CHARACTER_RUN_SUSPECTED | 16 | 3/3 |
