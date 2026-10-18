# Sollo Framework Architecture Policy

This document defines the architectural rules that apply to every application
built with Sollo. It is the policy, not an inventory of a particular checkout.
Repository-specific paths, ownership assignments, and audit results belong in
the repository-specific `FILE_OWNERSHIP_REGISTRY.md`, when maintained.

## Purpose and scope

Keep reusable framework behavior independent of the business domain of any
application. Apply this policy to feature work, bug fixes, refactoring,
framework upgrades, and dependency changes. When a repository-specific
ownership question arises, consult its file ownership registry and record
uncertainty as `UNKNOWN` until verified.

## Ownership boundaries and placement

- `SOLLO_CORE` owns reusable runtime primitives and contracts that can serve
  multiple applications without domain-specific assumptions.
- `SOLLO_FRAMEWORK` owns framework-provided scaffolding, defaults, generators,
  and supporting assets outside the core runtime when applicable.
- `APPLICATION` owns domain behavior, user-facing workflows, controllers,
  middleware, models, migrations, seeders, routes, services, commands, and
  application configuration.
- `THIRD_PARTY` covers externally maintained dependencies. `GENERATED` covers
  build outputs and transient runtime artifacts. Use `UNKNOWN` when ownership
  cannot be established from evidence.

Place code according to its purpose and provenance, not its directory name or
namespace alone. A class that extends a framework base remains application
code if it implements application policy. Keep private runtime data outside
source and public web directories unless it is intentionally public.

## Framework core placement

Place behavior in framework core only when it has a stable reusable contract,
does not depend on one application's business rules, and can be supported
across consuming applications. Core must not import application classes,
read application tables, or require application-specific paths, environment
keys, URLs, configuration keys, or services. Generic command dispatch belongs
to the framework; business commands belong to the consuming application.

## Application placement

Keep domain rules and integrations in the consuming application. Application
code may depend on documented generic framework APIs. Do not move application
behavior into core merely to reuse a base class or simplify a single project.
If genuinely shared behavior emerges, define its contract and cross-application
need before proposing a framework change. Prefer a verified extension point or
an application-owned adapter around an existing generic contract; do not add a
core hook solely to avoid a small application-level integration.

## Configuration ownership

Configuration that selects or configures application features belongs to the
application. Framework defaults may be provided by Sollo, but core must not
require a particular consuming application's configuration file or feature.
Document configuration keys and compatibility expectations at the layer that
owns them.

## Extension points and command registration

Use only extension points verified in the checked-out Sollo version. In the
documented command mapping API, application commands are configured using the
`commands` mapping in application configuration (commonly exposed as
`app.commands`); each command class extends the framework's `BaseCommand`.
For example, an application may register
`'invoice:generate' => App\\Console\\Commands\\InvoiceGenerateCommand::class`.
An application may omit this mapping if it has no custom commands.

Do not assume or claim plugin loading, automatic service discovery, a
dependency injection container, or other extension mechanisms unless the
checked-out framework implementation verifies them. Treat proposed directory
layouts or APIs as recommendations, not existing capabilities. Scheduled
business tasks should invoke application commands through the deployment's
scheduler rather than coupling business behavior to web requests.

## Dependency direction and ownership

- Application code may depend on generic framework contracts; framework code
  must not depend on application implementation.
- Keep framework and application dependency requirements in the manifests
  owned by those layers. A dependency needed by one application is not by
  itself a reason to add it to framework requirements.
- Add interfaces, adapters, or indirection when they clarify a real boundary
  or required variation; avoid speculative abstractions.
- Record why a dependency is needed, who owns it, and any runtime requirements.

## Third-party dependencies

Do not edit package-manager-managed third-party source as an application
customization. Declare dependencies in the appropriate manifest, review
license and compatibility implications, and keep generated dependency trees
out of source ownership inventories except as documented exclusions or
patterns.

## Framework updates and migrations

For each framework update, identify the upstream version and review migration
notes and breaking changes. Review core diffs and local patches, run applicable
framework and application verification, and confirm that application code and
configuration remain intact. Preserve existing public behavior where practical;
when a breaking change is necessary, document its impact, migration steps, and
deprecation path where feasible. Reapply only intentional, documented patches.
Update architecture and API documentation when a change affects ownership,
extension points, configuration, compatibility, or required migration steps.
Record compatibility changes and any migration steps required of consuming
applications.

## Testing and architectural verification

Keep tests near the component or suite they exercise where the repository
supports that separation. Verify framework behavior independently of optional
application features, and verify application integrations through supported
extension points. Review changes for framework-to-application dependencies,
domain vocabulary or schema in core, app-only environment keys in core, and
undocumented framework patches. Report checks that could not be run accurately.

## Framework file ownership policy

Every repository file in the audited scope must have one ownership category:
`SOLLO_CORE`, `SOLLO_FRAMEWORK`, `APPLICATION`, `THIRD_PARTY`, `GENERATED`, or
`UNKNOWN`. Decide ownership from purpose and provenance, not path alone. A
repository's `FILE_OWNERSHIP_REGISTRY.md`, when maintained, is its current
snapshot; it is repository-specific and does not define this universal policy.

## File change tracking requirements

Every coding agent must follow these requirements on every task:

1. Read this architecture policy before modifying code.
2. Inspect the ownership registry before changing an existing file.
3. Classify every new file before or when creating it.
4. Update the registry when files are created, moved, renamed, deleted, or
   reclassified. No inventory update is needed when ownership and inventory
   have not changed; validate registry claims when a task could affect them.
5. Review framework-to-application dependencies and document intentional
   framework changes with their cross-application justification.
6. Inspect Git changes and preserve unrelated user modifications.
7. Validate the registry against the actual repository before reporting
   completion; record unresolved ownership as `UNKNOWN`.

These duties apply to feature development, bug fixes, refactoring, framework
upgrades, and dependency changes.

## Registry maintenance procedure

When an inventory change is needed, inspect tracked, untracked, ignored, and
generated paths as appropriate; compare with the prior registry and Git state;
record path, owner, purpose, tracking state, and relevant notes; and document
exclusions and unresolved ownership. Validate paths and tracking claims against
the working tree. Never claim complete coverage when any relevant paths have
not been inspected. Keep detailed repository-specific audit evidence in the
registry rather than this policy.

## Pre-merge checklist

- [ ] Core changes have a cross-application contract and no application-domain
      dependencies.
- [ ] Application behavior and dependencies remain application-owned and use
      verified framework extension points.
- [ ] Dependency ownership and intentional framework changes are documented.
- [ ] Applicable tests and architectural checks were run, or unavailable
      checks are accurately reported.
- [ ] Registry entries and exclusions are accurate for inventory-affecting
      changes.
- [ ] Git changes contain no unrelated user modifications or accidental
      generated secrets.
- [ ] Framework update and migration implications have been reviewed.
