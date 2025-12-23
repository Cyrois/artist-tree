# Artist-Tree Documentation Structure

Welcome to the Artist-Tree project documentation. This guide helps you navigate the project's comprehensive documentation.

---

## Quick Start

**Are you an AI agent working on this project?**
- Start with **CLAUDE.md** in the project root for business domain rules
- Check your agent role in `.claude/agents/` for your specific responsibilities
- Reference `.claude/guidelines/` for framework-specific implementation patterns

**Are you a human developer?**
- Read **CLAUDE.md** first for project overview and business logic
- Review `requirements/prd.md` for product requirements
- Check `.claude/guidelines/` for coding patterns and conventions

---

## Documentation Files

### Project Root

**`/CLAUDE.md`** - Main business domain documentation

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

### Agent Instructions (`.claude/agents/`)

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

### Framework Guidelines (`.claude/guidelines/`)

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

1. **Identify your role:** Check `.claude/agents/[your-role].md`
2. **Understand the domain:** Read `/CLAUDE.md` for business rules
3. **Learn implementation patterns:** Reference `.claude/guidelines/` for your technology area
4. **Search documentation:** Use `search-docs` MCP tool for version-specific guidance

**Example Workflow:**

```
1. User asks: "Add artist search autocomplete"
2. Read CLAUDE.md → Search implementation requirements
3. Read frontend-patterns.md → Search autocomplete pattern
4. Read workflow.md → Quality checklist
5. Implement feature following patterns
6. Read testing-patterns.md → Write tests
7. Run tests and format code
```

### For Human Developers

1. **Onboarding:** Read `/CLAUDE.md` and `requirements/prd.md`
2. **Development:** Reference `.claude/guidelines/` for coding patterns
3. **Testing:** Follow `.claude/guidelines/testing-patterns.md`
4. **Workflow:** Use `.claude/guidelines/workflow.md` as daily checklist

**Example Workflow:**

```
1. Pick task from requirements/asana-tasks.md
2. Check CLAUDE.md for business rules
3. Check backend-patterns.md or frontend-patterns.md for patterns
4. Write code following conventions
5. Write tests using testing-patterns.md
6. Follow workflow.md quality checklist
7. Commit with conventional commit message
```

### For Product/Project Managers

1. **Requirements:** See `requirements/prd.md`
2. **Tasks:** Manage via `requirements/asana-tasks.md`
3. **Architecture:** Understand decisions in `/CLAUDE.md`
4. **Agent coordination:** Review `.claude/agents/asana-project-manager.md`

---

## Documentation Principles

### 1. Separation of Concerns

- **Business rules** → `/CLAUDE.md`
- **Implementation patterns** → `.claude/guidelines/`
- **Role responsibilities** → `.claude/agents/`

### 2. Single Source of Truth

- Framework guidelines live ONLY in `.claude/guidelines/laravel-boost.md`
- Business logic lives ONLY in `/CLAUDE.md`
- Cross-references used to avoid duplication

### 3. Modular & Discoverable

- Each file has a clear, focused purpose
- Navigation is explicit (this README)
- Related files cross-reference each other

---

## Quick Reference

### Need Laravel/PHP patterns?
→ `.claude/guidelines/backend-patterns.md`

### Need Vue/Inertia/Tailwind patterns?
→ `.claude/guidelines/frontend-patterns.md`

### Need to write tests?
→ `.claude/guidelines/testing-patterns.md`

### Need development workflow guidance?
→ `.claude/guidelines/workflow.md`

### Need complete Laravel Boost rules?
→ `.claude/guidelines/laravel-boost.md`

### Need business rules for scoring?
→ `/CLAUDE.md` (Domain-Specific Rules section)

### Need database schema?
→ `/CLAUDE.md` (Database Schema Rules section)

### Need architecture decisions?
→ `/CLAUDE.md` (Architecture Decision section)

---

## Maintenance

When updating documentation:

- **Business rule changes:** Update `/CLAUDE.md`
- **New Laravel pattern:** Update `.claude/guidelines/backend-patterns.md`
- **New Vue pattern:** Update `.claude/guidelines/frontend-patterns.md`
- **New test requirement:** Update `.claude/guidelines/testing-patterns.md`
- **Workflow improvement:** Update `.claude/guidelines/workflow.md`
- **Framework upgrade:** Update `.claude/guidelines/laravel-boost.md`

---

## Questions?

If you can't find what you're looking for:

1. Check this README for navigation
2. Use your code editor's search across all `.md` files
3. Ask in project communication channels

---

Last Updated: December 22, 2024
Version: 1.0.0
