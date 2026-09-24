# ADR 0010: Member wellness, coaching assignments and self-service

## Status

Accepted for the requested MVP extension.

## Context

Members and coaches were operational records without a login identity. Nutrition plans, food logs, workout logs and self check-in require an authenticated identity and record-level authorization in addition to module entitlement, role and permission checks.

## Decision

- `members.user_id` and `coaches.user_id` optionally connect an operational profile to one login inside a gym.
- A coach has one of `training`, `nutrition` or `both` specialties. `coach_member_assignments` explicitly grants a coach access to a member for one coaching domain.
- Nutrition plans are versioned records with structured meal rows. Both the assigned nutrition coach and assigned training coach may edit a member plan, while the member can only read it.
- Food logs are owned by the member. `coach_visibility` is opt-in and only assigned coaches can then read them.
- Workout sessions contain structured exercise sets and an optional note. The member and assigned coaches may create/update them.
- Self attendance can only affect the authenticated member profile. One open attendance is allowed; checkout closes that same record.
- All new gym-owned tables carry `gym_id`, use the central fail-closed scope and are protected by module middleware plus record-level checks.
- Datetimes are stored in UTC and rendered in the gym timezone. Free text is length-limited and treated as untrusted content.

## Consequences

The identity/profile link becomes a prerequisite for member and coach portals. Historical plans are retained by status instead of being overwritten. Cross-gym IDs remain inaccessible through scoped route binding.
