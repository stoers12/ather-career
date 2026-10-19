# Private Profile catalog candidates

Current revision: `ather-profile-launch-2026-10-19-r2`. The owner approved the
ordered **13-country product selection** for Profile, private draft edits and first
completion validation: `JO PS SA AE QA KW BH OM IQ LB SY YE EG`. Jordan is first,
never automatically selected or inferred. The full pinned source still has 249
assigned ISO entries; ISO membership is not reduced to 13. No IP/geolocation or
account-access restriction is added. Historical revision r1 offered all 249.

Country coverage for this launch subset is accepted;
display labels and the six status meanings below are reviewable owner decisions;
they do not approve completion requirements, publication or eligibility rules.

The separately authorized [draft-editor integration](../../docs/auth-onboarding/25-ONBOARDING-PROFILE-CATALOG-INTEGRATION.md)
now reuses these same candidates for newly supplied private draft values. Historical draft
values and exact accepted retries remain compatible. This amendment changes product membership/revision only, not the pinned sources.
Unchanged old Profile countries are omitted from patches; old draft values and exact
receipts remain readable. Unsupported new selections and first completion reject
values outside the launch subset. No stored country is deleted, remapped or replaced.
See [handoff 26](../../docs/auth-onboarding/26-LAUNCH-COUNTRY-CATALOG.md).

## Reproducible membership and labels

- ISO is the maintenance authority: [ISO 3166 overview](https://www.iso.org/iso-3166-country-codes.html).
  Membership is independently sourced from Debian's maintained `iso-codes`
  `data/iso_3166-1.json`, tag `v4.20.1`, commit
  `078b9578822fcd68386d92347089e7e45a8bf47d`. Its `3166-1[].alpha_2` contains
  249 unique assigned country/territory codes. This is an open machine-readable
  snapshot of ISO assignments, not an ISO endorsement or a copied ISO standard.
- Labels use [Unicode CLDR 48.2](https://cldr.unicode.org/downloads/cldr-48), JSON
  release `48.2.0`, commit `bb334e8d6250c9363e957e131bf7e6d08ec72f91` in
  `unicode-org/cldr-json`. Exact source paths/URLs, bytes and SHA-256 checksums
  for every input and notice are in [provenance.json](provenance.json).
- Verify all 249 unique independent ISO `alpha_2` source entries, then select only
  the exact owner-approved ordered 13 codes above. For each selected code, take the exact primary `main.{ar|en}.localeDisplayNames.territories[code]`
  label. No fallback, alt/short label, case conversion, translation or inference.
  Both languages must have a nonempty label for every selected code.
- CLDR has 306 Arabic / 316 English territory label keys; those are **not** ISO
  membership lists. Exclude all keys absent from the ISO snapshot, including
  numeric macroregions, alternative labels and `AC CP CQ DG EA EU EZ IC QO TA UN
  XA XB XK ZZ`. Also exclude reserved/user-assigned and retired examples such as
  `UK SU AN BU TP YU`. `AX AQ BQ TW` remain assigned in the source but are outside the product list;
  `PS` is included. All other assigned source entries outside the list are excluded
  from new product selections, while historical stored values remain preservable.
- Exactly 13 supported product countries/territories (from 249 ISO source entries), 2 language tokens (`ar`, `en`) and 6 status
  tokens. Language labels in [choices.json](choices.json) must exactly match the
  same pinned CLDR release's primary `languages` labels; status labels are project-authored.
  No preview country/status examples are imported. Country labels are displayed
  together in EN/AR; code is the stored identity, not a translated label.
- Generated [catalog.json](catalog.json) SHA-256:
  `9ea1bd7f2e9ec1223a905a95509ebc43f4b9cc5f3bce471d35bb1d8fe9116f07`.
  `.gitattributes` preserves raw source bytes and generated JSON LF endings.

Offline reproduction/check (Python standard library only):

```sh
python3 scripts/build-profile-catalog.py
python3 scripts/build-profile-catalog.py --check
```

The tool checks all pinned input hashes, counts, labels, exact membership and the
reviewed output checksum. Runtime reads only the generated local JSON. No runtime
downloads, dependency package, service or scheduler. A maintainer changing a
source/choice must review its assignment/label changes, retain notices, update
revision/provenance/checksum and regenerate/test deliberately. A removed token
must remain preservable when unchanged in an existing profile; new selections
must use the current candidate catalog. No automatic reinterpretation of history.

## Proposed single self-description, awaiting owner review

| Token | EN / AR label | Proposed meaning |
| --- | --- | --- |
| `student` | Student / طالب / طالبة | Currently describes themself as pursuing study or training. No institution or enrollment verification. |
| `graduate` | Graduate / خريج / خريجة | Chooses to describe themself through completed study/training; no recency, degree or employment inference. |
| `employed` | Employed / موظف / موظفة | Describes currently working for an employer; no hours, contract or legal classification is inferred. |
| `self_employed` | Self-employed / يعمل لحسابه الخاص | Describes working independently/on their own account, including freelance work; no legal/tax classification is inferred. |
| `seeking_work` | Seeking work / باحث / باحثة عن عمل | Describes currently seeking work; may also be studying, employed or self-employed. |
| `other` | Other / وصف آخر | None of the offered descriptions is the one they wish to select. No additional explanation is demanded. |

One optional choice, with no priority between overlapping descriptions and no
exclusive real-world categories. The owner chooses the single description that
fits their intended self-presentation, or leaves it unspecified. These tokens
never change account access, readiness, eligibility, publication or permissions.
Owner review remains for these meanings, labels and future catalog maintenance;
no automatic mapping from `early`, `experienced`, `independent` or old free text.

Country means self-declared **professional location**, not nationality, residence
verification or geopolitical eligibility. Preferred language is a **profile
preference**, not interface/policy language or automatic translation. Specialization
is an optional field/discipline, distinct from role `professional_title`; country
never overwrites legacy `location`. All four fields remain outside public output.

## Notices

Vendored files are unmodified, with complete source and redistribution notices:

- [Unicode License V3](sources/Unicode-LICENSE.txt): Unicode data copyright
  © 2015–2024 Unicode, Inc.; retain its copyright/permission notice with copies.
- [iso-codes REUSE metadata](sources/iso-REUSE.toml) attributes JSON data to
  © 2016 Dr. Tobias Quathamer, under LGPL-2.1-or-later, and records the Markdown
  notices. [Full LGPL text](sources/LGPL-2.1-or-later.txt) and the unmodified
  [upstream README](sources/iso-README.md) are included beside the complete input
  [ISO JSON](sources/iso-3166-1.json). The generator and project choices remain
  separately reviewable; no upstream names/labels are presented as owner approval.
