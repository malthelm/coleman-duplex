# Coleman combined implementation goal

Merged at Malthe's request on 2026-10-26. This is the working scope for continued implementation: complete the existing direct-booking work and add the five agreed RentSpree-inspired rental-management features in the existing QloApps fork. Keep the demo private on Winona until Malthe chooses and authorizes a public launch.

The app's goal tools cannot edit or resume the existing goal record. Its earlier blocked status and original text are not a reliable summary of this newly authorized work. Do not mark unfinished work complete to replace that record.

## Work sequence

1. **Now: long-term enquiries and application tracking.** Separate the guest flows, support approximate move-in dates and intended duration, and add host stages, next actions, notes and dated history. Preserve short-stay reservation behavior. Follow the detailed acceptance criteria in [rentspree-implementation-goal.md](rentspree-implementation-goal.md).
2. **Lease and move-in records.** Private owner-approved documents, signature status and deposit/key-handover checklist. Manual status is not an electronic signing service.
3. **Rent ledger.** Due amounts, partial payments, outstanding balances, receipts and CSV export with clear manual/payment-provider provenance.
4. **Maintenance requests.** Tenant descriptions/photos, apartment access boundaries, open/scheduled/resolved stages and repair costs.
5. **Complete direct-booking delivery when dependencies are supplied.** Owner-confirmed terms, guest-message delivery, selected platform connections, off-host recovery and eventual authorized VPS/domain launch. Retain the implemented availability calendar, reserve/release controls, message drafts and one-time iCal exchange. Current evidence and dependencies remain in [launch-status.md](launch-status.md).

## Delivery boundary

Extend the existing module and shared design; preserve native authentication and existing data. Use targeted tests with synthetic records, private rollback copies, Dokploy deployment and desktop/mobile verification on Winona. No invented rates or owner rules. Screening, income verification, signing and actual payment collection remain provider choices; ask only when a missing choice materially blocks dependent work. Third-party messaging, fees, new production dependencies and public exposure require their corresponding authorization.

## Progress

- 2026-10-26: combined scope recorded; implementation started with long-term enquiry and host application tracking. No new feature deployment is claimed yet.
