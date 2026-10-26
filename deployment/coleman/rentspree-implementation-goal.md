# RentSpree-inspired implementation goal

Requested and clarified by Malthe on 2026-10-26. Implement the five features discussed for Coleman in the existing QloApps fork and verify them privately on Winona.

Merged with the earlier direct-booking goal at Malthe's request on 2026-10-26; see [implementation-goal.md](implementation-goal.md) for the combined working scope and progress. The app tools cannot edit the original unfinished goal record. This document retains the feature acceptance criteria and does not claim completed implementation.

## Implementation order and acceptance criteria

1. **Long-term applications.** Separate short-stay requests from long-term rental enquiries. Keep the initial form minimal: contact details, approximate move-in date, intended duration, occupants and pets. Allow a fuller application at a later stage. A synthetic submission must appear against the correct apartment in the authenticated host dashboard without creating a reservation or sending a message.
2. **Application progress.** Track enquiry, viewing, application, review, lease and move-in, with host notes, a dated history and a clear next action. Stage changes must persist, respect host permissions and reject stale edits. Preserve the distinction between approval and reserving dates; do not automate applicant eligibility decisions.
3. **Lease and move-in tracking.** Store owner-approved lease documents privately against the application or tenancy, record signature status, and track deposit and key-handover tasks. Verify authorized retrieval and denial of unauthorized document access. Manual signature status is not proof of an electronic signature; actual signing remains a provider decision.
4. **Rent ledger.** Record monthly amounts due, payment entries, outstanding balances, receipts and CSV export. Clearly distinguish manually recorded payments from provider-confirmed transactions. Derive overdue status from due dates and outstanding balances; only show processing when an actual pending payment supports it. Verify partial payments, corrected entries and totals using synthetic records. Do not collect money or populate unconfirmed owner rates.
5. **Maintenance requests.** Accept tenant descriptions and photos linked to the correct apartment or tenancy. Let hosts record open, scheduled and resolved status plus repair costs. Verify tenant access boundaries and safe photo handling. No automatic contractor dispatch, spending or third-party messages.

## Delivery requirements

- Extend the existing module and design patterns with the smallest reasonable changes. Keep public, tenant and host components visually consistent and usable on desktop and mobile.
- Inspect applicable instructions and the current serving checkout before implementation. Preserve existing records, authentication, unrelated work and the short-stay workflow.
- Use proportional regression checks and synthetic end-to-end scenarios. Before live changes, preserve a private rollback copy and use the existing Winona deployment and recovery procedures.
- Completion requires all five scoped workflows to function in the private Winona demo, relevant checks to pass, browser verification, and an accurate delivery record identifying the deployed revision and remaining provider-dependent limits.
- Leave actual rental terms blank until confirmed by Jim. Use explicitly labelled synthetic values only in isolated verification.
- Screening, income verification, payment processing and electronic signing use established providers if selected later. Do not store SSNs or bank credentials, build automated tenant rankings, or imply external services are free.
- Ask before new production dependencies or costs. External messages, public exposure, provider account commitments and VPS migration require their corresponding authorization. Keep the site private on Winona.

## Out of scope

Full RentSpree parity, automated listing syndication, a general multi-property CRM, tax preparation, automated rent estimation and new platform integrations are not part of these five features.
