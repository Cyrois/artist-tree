# Frontend Implementation Patterns

## Artist-Tree Specific Frontend Patterns

This document contains Vue/Inertia/Tailwind patterns specific to Artist-Tree. Always consult `.gemini/guidelines/laravel-boost.md` for comprehensive framework guidelines.

---

## Search Autocomplete Pattern

### Business Requirements

- Artist search endpoint: `GET /api/artists/search?q={query}`
- Support **partial matching** on artist names
- Return maximum **20 results** for autocomplete
- Response time target: <500ms
- **Debounce frontend input** (300ms minimum) to reduce API calls
- Search across: artist name, genres (if user enables genre search)

### Implementation

```vue
<script setup>
import { ref, watch } from 'vue'
import { debounce } from 'lodash-es'

const searchQuery = ref('')
const results = ref([])

const searchArtists = debounce(async (query) => {
  if (!query || query.length < 2) return
  const response = await axios.get('/api/artists/search', { params: { q: query } })
  results.value = response.data.data
}, 300)

watch(searchQuery, (newValue) => searchArtists(newValue))
</script>

<template>
  <div>
    <input v-model="searchQuery" type="text" placeholder="Search artists..." class="w-full rounded border px-4 py-2" />
    <ul v-if="results.length" class="mt-2 rounded border bg-white shadow-lg dark:bg-gray-800">
      <li v-for="artist in results" :key="artist.id" class="cursor-pointer px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700">
        {{ artist.name }}
      </li>
    </ul>
  </div>
</template>
```

---

## Lineup Builder Component Pattern

### Drag-and-Drop Tier Management

**Requirements:**
- Visual tier sections (Headliner, Sub-Headliner, Mid-Tier, Undercard)
- Drag artists between tiers
- Visual indicator for manually placed artists (tier_override = true)
- "Reset to Suggested" button for manual overrides

**Component Structure:**

```vue
<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps<{
  lineup: {
    id: number
    name: string
    artists: Array<{
      id: number
      name: string
      score: number
      pivot: {
        tier: 'headliner' | 'sub_headliner' | 'mid_tier' | 'undercard'
        suggested_tier: string
        tier_override: boolean
      }
    }>
  }
}>()

const artistsByTier = computed(() => {
  const tiers = {
    headliner: [],
    sub_headliner: [],
    mid_tier: [],
    undercard: []
  }

  props.lineup.artists.forEach(artist => {
    tiers[artist.pivot.tier].push(artist)
  })

  return tiers
})

function resetToSuggested(artistId) {
  router.post(`/api/lineups/${props.lineup.id}/artists/${artistId}/reset-tier`)
}
</script>

<template>
  <div class="space-y-6">
    <div v-for="(tierName, key) in { headliner: 'Headliners', sub_headliner: 'Sub-Headliners', mid_tier: 'Mid-Tier', undercard: 'Undercard' }" :key="key" class="rounded border p-4">
      <h3 class="mb-3 text-lg font-semibold">{{ tierName }}</h3>
      <div class="space-y-2">
        <div v-for="artist in artistsByTier[key]" :key="artist.id" class="flex items-center justify-between rounded bg-gray-50 p-3 dark:bg-gray-800">
          <div class="flex items-center gap-3">
            <span class="font-medium">{{ artist.name }}</span>
            <span class="text-sm text-gray-600 dark:text-gray-400">Score: {{ artist.score }}</span>
            <span v-if="artist.pivot.tier_override" class="text-xs text-yellow-600" title="Manually placed">
              ⚠️ Manual
            </span>
          </div>
          <button v-if="artist.pivot.tier_override" @click="resetToSuggested(artist.id)" class="text-sm text-blue-600 hover:underline">
            Reset to Suggested
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
```

---

## Inertia Form Pattern for Lineup Creation

Use the `<Form>` component from Inertia v2 with Wayfinder integration:

```vue
<script setup>
import { Form } from '@inertiajs/vue3'
import { store } from '@/actions/App/Http/Controllers/LineupController'
</script>

<template>
  <Form
    v-bind="store.form()"
    #default="{ errors, processing, wasSuccessful }"
  >
    <div class="space-y-4">
      <div>
        <label for="name" class="block text-sm font-medium">Lineup Name</label>
        <input
          id="name"
          name="name"
          type="text"
          placeholder="Summer Festival 2024"
          class="mt-1 w-full rounded border px-4 py-2"
        />
        <div v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name }}</div>
      </div>

      <button
        type="submit"
        :disabled="processing"
        class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
      >
        {{ processing ? 'Creating...' : 'Create Lineup' }}
      </button>

      <div v-if="wasSuccessful" class="text-sm text-green-600">
        Lineup created successfully!
      </div>
    </div>
  </Form>
</template>
```

---

## Artist Metrics Display Pattern

### Show Score with Tier Badge

```vue
<script setup>
import { computed } from 'vue'

const props = defineProps<{
  artist: {
    id: number
    name: string
    score: number
    pivot: {
      tier: 'headliner' | 'sub_headliner' | 'mid_tier' | 'undercard'
      tier_override: boolean
    }
  }
}>()

const tierColor = computed(() => {
  const colors = {
    headliner: 'bg-purple-500',
    sub_headliner: 'bg-blue-500',
    mid_tier: 'bg-green-500',
    undercard: 'bg-gray-500'
  }
  return colors[props.artist.pivot.tier]
})
</script>

<template>
  <div class="flex items-center gap-4">
    <span class="text-lg font-semibold">{{ artist.name }}</span>
    <span class="text-sm text-gray-600 dark:text-gray-400">
      Score: {{ artist.score }}
    </span>
    <span :class="[tierColor, 'rounded px-2 py-1 text-xs text-white']">
      {{ artist.pivot.tier }}
    </span>
    <span v-if="artist.pivot.tier_override" class="text-xs text-yellow-600">
      ⚠️ Manual
    </span>
  </div>
</template>
```

---

## Deferred Props with Skeleton Loading

For slow-loading artist metrics, use Inertia v2's deferred props with skeleton loaders:

```vue
<script setup>
import { computed } from 'vue'

const props = defineProps<{
  lineup: {
    id: number
    name: string
  }
  artists?: Array<{
    id: number
    name: string
    score: number
  }>
}>()

const isLoading = computed(() => !props.artists)
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold">{{ lineup.name }}</h1>

    <div v-if="isLoading" class="mt-4 space-y-4">
      <!-- Skeleton loader with pulsing animation -->
      <div v-for="i in 5" :key="i" class="animate-pulse">
        <div class="h-12 rounded bg-gray-200 dark:bg-gray-700"></div>
      </div>
    </div>

    <div v-else class="mt-4 space-y-3">
      <div v-for="artist in artists" :key="artist.id" class="rounded bg-white p-4 shadow dark:bg-gray-800">
        {{ artist.name }} - {{ artist.score }}
      </div>
    </div>
  </div>
</template>
```

---

## Tailwind Dark Mode Pattern

**Rule:** If existing pages support dark mode, new components MUST support it using `dark:` classes.

```vue
<template>
  <div class="bg-white dark:bg-gray-900">
    <h1 class="text-gray-900 dark:text-gray-100">Title</h1>
    <p class="text-gray-600 dark:text-gray-400">Description</p>
    <button class="bg-blue-600 text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600">
      Click Me
    </button>
  </div>
</template>
```

---

## Spacing with Gap Utilities

**Always use gap, never margin for list items:**

```vue
<template>
  <!-- ✅ GOOD: Use gap utilities -->
  <div class="flex gap-4">
    <div>Artist 1</div>
    <div>Artist 2</div>
    <div>Artist 3</div>
  </div>

  <!-- ❌ BAD: Don't use margin-right -->
  <div class="flex">
    <div class="mr-4">Artist 1</div>
    <div class="mr-4">Artist 2</div>
    <div>Artist 3</div>
  </div>
</template>
```

---

## Component Organization

### Pages Location

`resources/js/Pages/`

**Expected Page Components:**
- `Dashboard.vue` - Main dashboard
- `Lineups/Index.vue` - List all lineups
- `Lineups/Show.vue` - View single lineup with artists
- `Lineups/Create.vue` - Create new lineup
- `Organizations/Settings.vue` - Manage scoring weights
- `Artists/Search.vue` - Artist discovery interface

### Shared Components

`resources/js/Components/`

**Examples:**
- `ArtistCard.vue` - Display artist with score and tier
- `TierSection.vue` - Draggable tier section
- `MetricWeightEditor.vue` - Edit organization metric weights
- `SkeletonLoader.vue` - Reusable loading skeleton

---

## Wayfinder Usage

### Import Controller Actions

```typescript
import { show, store, update } from '@/actions/App/Http/Controllers/LineupController'
import { search } from '@/actions/App/Http/Controllers/ArtistController'

// Get lineup URL
const lineupUrl = show.url(lineupId) // "/lineups/123"

// Use with Inertia Form
<Form v-bind="store.form()">
```

### Named Routes

```typescript
import { show as lineupShow } from '@/routes/lineup'

// For route name 'lineup.show'
lineupShow(1) // { url: "/lineups/1", method: "get" }
```

---

## Best Practices

### 1. Component Naming

- Use PascalCase for component files: `ArtistCard.vue`, `LineupBuilder.vue`
- Use descriptive names that clearly indicate purpose

### 2. Props Typing

Always type your props with TypeScript:

```vue
<script setup lang="ts">
const props = defineProps<{
  lineup: Lineup
  artists: Artist[]
}>()
</script>
```

### 3. Composition API

Use Vue 3 Composition API with `<script setup>`:

```vue
<script setup>
import { ref, computed, watch } from 'vue'

const count = ref(0)
const doubled = computed(() => count.value * 2)
</script>
```

### 4. Avoid Inline Styles

Always use Tailwind classes instead of inline styles:

```vue
<!-- ✅ GOOD -->
<div class="flex items-center gap-4 rounded bg-white p-4">

<!-- ❌ BAD -->
<div style="display: flex; align-items: center; gap: 1rem;">
```

---

For comprehensive Vue/Inertia/Tailwind framework guidelines, see `.gemini/guidelines/laravel-boost.md`.
