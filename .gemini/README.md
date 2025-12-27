# Artist-Tree Documentation Structure (Gemini)

Welcome to the Artist-Tree project documentation. This guide helps you navigate the project's comprehensive documentation, tailored for the Gemini workflow.

---

## Quick Start

**Are you an AI agent working on this project?**
- Start with **GEMINI.md** in the project root for business domain rules
- Check your agent role in `.gemini/agents/` for your specific responsibilities
- Reference `.gemini/guidelines/` for framework-specific implementation patterns

**Are you a human developer?**
- Read **GEMINI.md** (or `CLAUDE.md`) first for project overview and business logic
- Review `requirements/prd.md` for product requirements
- Check `.gemini/guidelines/` for coding patterns and conventions

---

## Documentation Files

### Project Root

**`/GEMINI.md`** - Main business domain documentation (synced with `CLAUDE.md`)

**What's inside:**
- Project overview & tech stack
- Architecture decisions (Inertia + API hybrid)
- Artist scoring algorithm (business rules)
- Tier classification (business logic)
- Organizations & multi-tenancy
- Database schema
- Performance & security requirements
- Prohibited actions

---

### Requirements (`/requirements/`)

**`requirements/prd.md`** - Product Requirements Document

**`requirements/asana-tasks.md`** - Task breakdown and Asana integration

---

### Agent Instructions (`.gemini/agents/`)

Role-specific instructions for AI agents:

**`laravel-backend-dev.md`** - Backend implementation specialist
- Laravel models, controllers, routes, APIs
- Database operations
- Service classes
- Testing requirements

**`vue-laravel-developer.md`** - Frontend implementation specialist
- Vue 3 components
- Inertia.js integration
- Tailwind CSS styling
- Client-side interactions

**`test-engineer.md`** - Testing specialist
- Pest test writing
- Browser testing
- Test coverage
- Quality assurance

**`solution-architect.md`** - Architecture planning
- System design
- Feature planning
- Technical decision-making

**`asana-project-manager.md`** - Project coordination
- Task management
- Progress tracking
- Stakeholder communication

---

### Framework Guidelines (`.gemini/guidelines/`)

Implementation patterns and framework-specific rules:

**`laravel-boost.md`** - Complete Laravel Boost guidelines
- Laravel 12 patterns
- PHP 8.4 conventions
- Inertia v2 features
- Pest v4 testing
- All framework-specific rules

**`backend-patterns.md`** - Laravel implementation patterns
- Service class structure
- Controller patterns
- External API integration
- Database query optimization
- Policy and authorization

**`frontend-patterns.md`** - Vue/Inertia/Tailwind patterns
- Component architecture
- Form handling
- Wayfinder usage
- Dark mode implementation
- Tailwind v4 conventions

**`testing-patterns.md`** - Pest testing guidelines
- Required test coverage
- Factory patterns
- API mocking
- Browser testing
- Performance testing

**`workflow.md`** - Development workflow & tooling
- Before/during/after coding checklists
- Artisan commands
- Laravel Boost MCP tools
- Code quality tools (Pint)
- Debugging strategies

---

## How to Use This Documentation

### For AI Agents

1. **Identify your role:** Check `.gemini/agents/[your-role].md`
2. **Understand the domain:** Read `/GEMINI.md` for business rules
3. **Learn implementation patterns:** Reference `.gemini/guidelines/` for your technology area
4. **Search documentation:** Use `search-docs` MCP tool for version-specific guidance

**Example Workflow:**

```
1. User asks: "Add artist search autocomplete"
2. Read GEMINI.md → Search implementation requirements
3. Read frontend-patterns.md → Search autocomplete pattern
4. Read workflow.md → Quality checklist
5. Implement feature following patterns
6. Read testing-patterns.md → Write tests
7. Run tests and format code
```

### For Human Developers

1. **Onboarding:** Read `/GEMINI.md` and `requirements/prd.md`
2. **Development:** Reference `.gemini/guidelines/` for coding patterns
3. **Testing:** Follow `.gemini/guidelines/testing-patterns.md`
4. **Workflow:** Use `.gemini/guidelines/workflow.md` as daily checklist

---

## Maintenance

When updating documentation:

- **Business rule changes:** Update `/GEMINI.md` (and `CLAUDE.md` to keep in sync)
- **New Laravel pattern:** Update `.gemini/guidelines/backend-patterns.md`
- **New Vue pattern:** Update `.gemini/guidelines/frontend-patterns.md`
- **New test requirement:** Update `.gemini/guidelines/testing-patterns.md`
- **Workflow improvement:** Update `.gemini/guidelines/workflow.md`
- **Framework upgrade:** Update `.gemini/guidelines/laravel-boost.md`
