# Coleman delivery and launch status

Updated 2026-10-26. Current owner direction: **direct booking first; external platforms undecided; keep private on Winona**. Jim has not confirmed rates, minimum stay, cleaning fees, capacity, house rules or primary-residence status. Blank values are intentional.

Subsequent combined-goal release: `01339e5` adds long-term enquiries and host application progress/history, deployed and verified privately on Winona. See [implementation-goal.md](implementation-goal.md) for the current merged work and evidence. The earlier completion audit below is retained as dated evidence, not the current application revision.

1. **Rental details:** host editor implemented; owner confirmation outstanding. Draft details stay private. A confirmed set requires at least one rate, cleaning fee, minimum stay, capacity and rules. No guessed values should be seeded.
2. **Availability:** per-apartment calendar, manual date blocks and separate reserve/release actions implemented. Approval alone does not allocate dates. The custom ledger does not create native hotel orders, take payment or form a rental agreement.
3. **Communication:** editable approval/decline and reserved-date confirmation drafts implemented, with saved `.eml` export and stale-request protection. Current wording is explicitly for the private demo. Actual guest delivery remains unconfigured and unverified; no automated sending is enabled. Explicit authorization is required before sending third-party messages.
4. **Platforms:** deferred connection choices; no external sync active. One-time all-day iCalendar file import/export implemented, with previews, conflict checks and private exports. Subscription URLs, scheduled refresh and source-aware reconciliation remain pending platform choices; platform access and charges remain independent of this open-source fork.
5. **Launch:** keep the private Winona deployment. Database and site backups are required before releases. An isolated Winona restore and rollback/return rehearsal passed on 2026-10-26; see [restore-runbook.md](restore-runbook.md). Off-host backup/image transfer, domain/VPS selection, migration verification and public-launch authorization remain outstanding.

## Property-specific requirements still to establish

These are launch dependencies, not a determination of this property's eligibility. Check the actual address and each unit with the City and Jim before offering short or long stays.

- Los Angeles Home-Sharing generally requires a primary residence; regular registration has a 120-day annual limit, and RSO/affordable units are ineligible. Jim's residence and unit eligibility are unknown. Source: [City Planning Home-Sharing FAQ](https://planning.lacity.gov/node/134625), reviewed 2026-10-26.
- Duplex units can fall under the Rent Stabilization Ordinance, depending on the property and other criteria. Check ZIMAS and LA Housing records rather than inferring from a listing. Registration/rent-registry requirements also need confirmation. Sources: [LA Housing RSO coverage](https://housing.lacity.gov/residents/what-is-covered-under-the-rso), [rent registry](https://housing.lacity.gov/rental-property-owners/rent-registry), reviewed 2026-10-26.
- Confirm insurance, applicable taxes, rental terms and permission to use the listing photographs before public launch. No legal determination, filings or payments have been made.

### Direct-booking and longer-stay distinctions

Official sources reviewed 2026-10-26; these are preparation notes, not a property-specific approval or legal determination.

- City Planning defines Home-Sharing short-term rentals as **30 consecutive days or less**. Rentals longer than 30 days fall outside that ordinance, but that does not establish an exemption from other housing rules. A calendar month can contain 28, 29, 30 or 31 days; do not use the label “monthly” as a legal classification or automatically authorize short stays. [City Planning FAQ](https://planning.lacity.gov/node/134625).
- The Office of Finance currently lists a **14% transient occupancy tax (TOT)** and includes direct owner/operator short-term rentals. Its guide says hosts who do not exclusively use platforms with City tax agreements must register with Finance. Direct booking therefore needs an explicit registration, collection and remittance process before taking taxable bookings. No tax calculation or collection is currently enabled. [TOT requirements](https://finance.lacity.gov/transient-occupancy-tax-requirements), [official compliance guide, pages 4–5](https://finance.lacity.gov/sites/g/files/wph1721/files/2025-11/TOT%20Compliance%20Guide%20-%20Rev%208.26.24.pdf).
- A planned longer stay does not by itself prove a TOT exemption. The guide describes documentation and payment conditions for non-transient occupancy, including a qualifying signed agreement at check-in. Confirm the treatment of the actual agreement and occupant with Finance before relying on an exemption; the date-request form and demo confirmation draft are not that agreement. Same [compliance guide, page 4](https://finance.lacity.gov/sites/g/files/wph1721/files/2025-11/TOT%20Compliance%20Guide%20-%20Rev%208.26.24.pdf).
- LAHD explicitly includes duplexes and multiple dwellings on one parcel among property types that may be subject to the RSO. Its property search warns that a “Yes” can mean **at least one** unit is covered, not necessarily every unit. Establish 560 and 562 separately rather than treating a property-level result or rental-listing description as sufficient. LAHD also notes that non-RSO units may still fall under the Just Cause Ordinance. [RSO coverage](https://housing.lacity.gov/residents/what-is-covered-under-the-rso), [property-search scope](https://housing.lacity.gov/rental-property-owners/rso-property-search).

The ZIMAS lookup reached an explicit Terms and Conditions acceptance screen. No terms were accepted, and no unit-specific record was retrieved. Neither unit's RSO status, permitted use or Home-Sharing eligibility has been established. Jim's primary-residence evidence and applicable City records remain required; no third-party listing facts were copied into rental settings.

## Completion audit — 2026-10-26

The preceding goal turn made progress by deploying and testing calendar file exchange. This audit preserves the full goal rather than treating the private demo as a completed public rental service.

| Goal requirement | Evidence inspected | Remaining dependency |
| --- | --- | --- |
| Owner-confirmed terms for both units | Live database has two blank, unconfirmed settings; owner said nothing is decided | Jim's actual rates, limits, fees, rules and relevant residence facts |
| Availability and reserve/release workflow | Calendar implementation and its recorded installed-runtime/live HTTP verification; current release `d8dfc0a` serving from Dokploy | Implemented for the custom direct-request ledger; no native hotel orders or actual rental agreements |
| Approval, decline and confirmation drafts | Persisted draft implementation and prior live save/download checks; mail remains disabled | A chosen delivery route and explicit recipient/content authorization before actual sending |
| Platform integration | Calendar-file parser, signed previews, transactional import and private export deployed/tested | Platform/account choices, supported feed/API access, scheduled refresh and reconciliation; no platform is connected |
| Backup, restoration and launch | Private restore report confirms dump/file matches, guest/host HTTP flows and compatible code rollback/return; rehearsal containers are stopped; live web/database healthy | Off-host recovery, owner-selected domain/VPS, migration verification, actual property eligibility/terms and public-launch authorization |

Live database audit: five pending synthetic requests, zero active date blocks, two blank/unconfirmed units, zero orders, mail disabled. The latest application matches `d8dfc0a`; the completed restore rehearsal tested `f067c9d` and rollback to `cca0d06`, not an off-host restoration of the latest version. UI evidence consists of desktop/mobile guest checks and genuine host-template previews, with live HTTP verification of host actions; it is not a claim of live browser acceptance for every host feature.

Current direction remains **direct booking first, platforms undecided, private Winona**. Do not invent owner inputs, send guest messages, connect accounts or launch publicly to close the outstanding stages. Malthe subsequently authorized merging the five RentSpree-inspired features into the implementation goal and starting work; see [implementation-goal.md](implementation-goal.md). This does not resolve the launch dependencies above.
