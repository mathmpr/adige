# TODO

This file tracks the remaining ORM backlog after the `0.0.2` stabilization work documented in [`docs/STABILIZATION_0.0.2.md`](/home/mathmpr/PhpstormProjects/adige/docs/STABILIZATION_0.0.2.md).

## ORM backlog

- [ ] extract a formal public ORM contract from the stabilization document into a shorter end-user reference
- [ ] decouple `ActiveRecord` construction from immediate live schema access
- [ ] decide whether eager loading should remain limited to first-level `hasOne` / `hasMany` relations defined via `RelationDefinition` or be expanded intentionally
- [ ] consolidate the accepted query composition rules into a shorter public contract reference
