<!--
CI must be green before this PR can be reviewed. See CONTRIBUTING.md.
-->

## What & why

<!-- Describe the change and the motivation. Link any issue. -->

## Checklist (required)

- [ ] Tests added/updated **first** (TDD) — unit and/or integration
- [ ] `declare(strict_types=1);` in every new PHP file
- [ ] `composer cs:fix` run; `composer check` is green locally
- [ ] New `ST_*` function (if any) has a test and a `README.md` entry
- [ ] No application-/domain-specific code added (package stays generic)
