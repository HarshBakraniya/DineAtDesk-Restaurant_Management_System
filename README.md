# DineAtDesk — Restaurant Management System

A full-stack restaurant management system for a single-location restaurant — staff-side floor management (tables, orders, kitchen workflow, billing) plus a customer-facing self-ordering and live order-tracking experience.

**Live demo:** `https://myprojects.site.je/restaurant-app/frontend/index.html`
*(Demo access is intentionally not published here — reach out if you'd like a walkthrough or credentials.)*

---

## A note on how this was built

The code in this repository was written by an AI assistant (Claude, by Anthropic) using its free tier — no paid subscription — through an extended conversation where I directed the architecture, features, priorities, and every fix — reviewing, testing, and deploying the result myself. Every deployment issue documented below was something I actually hit while deploying and testing this project live, then worked through with AI assistance to diagnose and fix.

I'm sharing this openly because I think it's a more honest account of how a project like this gets built with AI tools today: not "I typed this from scratch," but "I directed this, tested it against a real server, broke it in real ways, and fixed it with AI help until it worked." The architectural thinking, the debugging judgment calls, the decision to test my own security by actually trying to break it rather than assuming it worked, and the decision of what to build in what order were mine. The code itself was AI-written.

This repository is shared for portfolio and discussion purposes — to show the process and the outcome, not as a template to clone and run. Setup instructions, configuration details, and credentials are intentionally not included here.

---

## Why this exists

This started as a system architecture exercise for a local restaurant that needed something simple: table tracking, order management, billing, and eventually a way for customers to order without waiting on staff — without the overhead of a framework, a build pipeline, or infrastructure a one-branch restaurant doesn't need. The guiding principle throughout was **minimum viable complexity**: no microservices, no message queues, no ORM — just a clean PHP + MySQL monolith that a small team can actually maintain.

---

## Tech stack

| Layer | Choice |
|---|---|
| Backend | Core PHP (PDO, prepared statements throughout) |
| Database | MySQL |
| Frontend | HTML, Tailwind CSS, vanilla JavaScript — no build step |
| Auth | PHP native sessions, bcrypt password hashing |
| Hosting | Shared PHP/MySQL hosting |

---

## Features

### Staff-facing app
- Role-based login (Admin, Manager, Waiter, Kitchen), each seeing only the nav items relevant to their role
- Live dashboard — free/occupied tables, active orders, today's revenue
- Table management — visual grid, color-coded by status, tap a free table to start an order
- Kanban-style order board (Pending → Preparing → Served)
- Menu management — add, edit, delete, instant availability toggle, grouped by category
- Billing with tax/discount calculation, multi-method payment, duplicate-bill protection
- Admin-only data reset for demo purposes, without touching menu/tables/staff accounts
- Fully responsive, including a mobile hamburger-drawer navigation

### Customer-facing app
- No account required — pick a table, browse the menu, place an order
- Live 4-step order tracker (Placed → Cooking → Served → Paid) that polls automatically
- Self-service payment once an order is billed
- **Token-secured** — every guest order gets a random secret token at creation; viewing or paying an order requires that exact token or a logged-in staff session. Verified by deliberately attempting to access another order with a missing or incorrect token — confirmed blocked in every case.

---

## Architecture

```
Browser (staff or customer) → PHP REST-style API (session or token-authenticated) → MySQL
```

Single PHP codebase, single MySQL database, one hosting instance. Order and table status are plain enums, not a state machine. Roles are a single column check, not a permissions engine — enough structure to be correct, not so much that a small team can't reason about the whole system. 8-table schema covering users, menu, tables, orders, order items, bills, and inventory.

---

## The build journey — what actually happened

This project went through a real build-test-break-fix cycle. Documenting it here because the debugging process is arguably the more interesting part.

**1. Architecture first.** Before writing any code, the system was scoped as a deliberately simple 3-tier monolith, to avoid over-engineering a single-restaurant tool.

**2. Full build, tested before deployment.** Backend and frontend were built and tested end-to-end locally — a full order → kitchen status → bill generation → payment → automatic table release cycle, verified against a live database before ever touching a server.

**3. Local hosting troubleshooting.** Early deployment surfaced a chain of real issues: a JSON parsing error traced back to the frontend calling an absolute path that didn't match the actual server folder structure; a database connection failure traced to the local MySQL service simply not running; a session bug traced to one file still pointing at the wrong API path while another had already been fixed.

**4. Choosing a host.** Evaluated a full-control cloud VM against shared hosting, and chose shared hosting for faster deployment given the project's scale — a deliberate trade-off, not a default.

**5. The real setback: local success doesn't guarantee shared-host success.** The database setup script worked perfectly on a local server with full admin rights, but shared hosting doesn't permit that same operation — requiring the database to be created through the host's own control panel first. This was the single biggest gap between "works on localhost" and "works in production," and took the most time to properly diagnose.

**6. Post-deployment hardening, found and fixed in sequence:**
   - A full server error traced to outdated Apache configuration syntax that modern hosts no longer accept
   - A silent, blank server failure traced (after temporarily re-enabling error visibility) to a single-character typo in a configuration file
   - A revenue-reporting bug traced to a timezone mismatch between the browser and the server
   - A double-billing bug from accidental double-clicks, fixed with both backend and frontend safeguards

**7. Extending to customer self-service.** Required deliberately relaxing authentication on exactly three read-only endpoints, while keeping every staff and data-mutating endpoint fully protected — a scoped, reasoned exception rather than a blanket change.

**8. Security tested, not assumed.** Opening those endpoints to guests without protection would have meant anyone could view or pay any order by guessing a sequential ID. Fixed with a random secret token per guest order — then actually tested end-to-end against the live server by trying to break it: confirmed a wrong or missing token is rejected every time, for viewing an order, viewing a bill, and paying a bill.

**9. Mobile responsiveness — the longest iteration.** The navigation sidebar went through multiple approaches before landing on a proper slide-in drawer pattern. Along the way: a CSS specificity bug where a broader rule silently overrode a more specific one later in the same file, a duplicate rule in a second media query re-introducing a bug that had already been fixed once, and a browser cache serving a stale stylesheet well after the live file had already been corrected — each isolated and confirmed independently before moving to the next.

---

## What I'd build next

- Inventory tracking (schema exists, no UI yet)
- A dedicated reporting/analytics view beyond the dashboard summary
- Push notifications instead of polling for order status updates
- Staff password-reset flow

---

## License

This repository is shared for portfolio and discussion purposes. It is not licensed for reuse, redistribution, or deployment as-is.
