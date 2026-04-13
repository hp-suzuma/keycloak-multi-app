# AGENTS.md

## ■ Project Overview
This project is a web application using:
- Frontend: Nuxt 3
- Backend: Laravel 13
- Auth: JWT (planned migration to Keycloak SSO)

---

## ■ Tech Stack
- Node.js / npm
- PHP 8.x / Laravel
- Docker (required)
- MySQL

---

## ■ Directory Structure
- frontend/ → Nuxt
- backend/ → Laravel
- docker/ → container config

---

## ■ Dev Commands

### Frontend
- dev: `npm run dev`
- build: `npm run build`

### Backend
- start: `php artisan serve`
- test: `php artisan test`

### Docker
- start: `docker compose up -d`
- exec: `docker exec -it app bash`

---

## ■ Coding Rules

### General
- Follow existing code style strictly
- Do NOT introduce new frameworks
- Keep changes minimal (diff-based)

### Laravel
- Use FormRequest for validation
- Use Service layer for business logic
- Do NOT put logic in Controller

### Nuxt
- Use composables for API calls
- Do NOT duplicate API logic
- Use TypeScript where possible

---

## ■ Testing Rules
- All changes must pass existing tests
- Add tests for new features
- Do not remove tests

---

## ■ Security Rules
- NEVER output secrets
- Do NOT log tokens/passwords
- Validate all inputs

---

## ■ Git Rules
- Small commits only
- One feature per PR
- Commit message format:
  feat: xxx
  fix: xxx

---

## ■ AI Agent Behavior

- Always:
  1. Understand the goal
  2. Identify affected files
  3. Propose minimal changes

- Before coding:
  - Explain plan in bullet points

- When coding:
  - Modify only necessary files
  - Follow existing patterns

- After coding:
  - Ensure build/test passes

---

## ■ Forbidden
- Large refactors without instruction
- Changing architecture
- Adding dependencies without reason