<template>
  <div class="library">
    <div class="library__header">
      <h2>Library</h2>
      <div class="range-tabs">
        <button
          v-for="r in RANGES"
          :key="r.value"
          class="range-tab"
          :class="{ 'range-tab--active': range === r.value }"
          @click="setRange(r.value)"
        >
          {{ r.label }}
        </button>
        <button
          class="range-tab"
          :class="{ 'range-tab--active': range === 'custom' }"
          @click="setRange('custom')"
        >
          Custom
        </button>
      </div>
    </div>

    <div
      v-if="range === 'custom'"
      class="custom-range"
    >
      <label>From <input
        v-model="customFrom"
        type="date"
        @change="reload"
      ></label>
      <label>To <input
        v-model="customTo"
        type="date"
        @change="reload"
      ></label>
    </div>

    <section
      v-if="years.length > 1"
      class="years"
    >
      <div class="years__chart">
        <button
          v-for="y in years"
          :key="y.year"
          class="years__bar-wrap"
          :title="`${y.year} — ${formatNumber(y.count)} scrobbles`"
          @click="selectYear(y.year)"
        >
          <span
            class="years__bar"
            :style="{ height: yearHeight(y.count) }"
          />
          <span
            v-if="y.year % 5 === 0 || years.length <= 12"
            class="years__label"
          >{{ String(y.year).slice(2) }}</span>
        </button>
      </div>
    </section>

    <div class="type-tabs">
      <button
        v-for="t in TYPES"
        :key="t.value"
        class="type-tab"
        :class="{ 'type-tab--active': type === t.value }"
        @click="setType(t.value)"
      >
        {{ t.label }}
      </button>
    </div>

    <NcLoadingIcon
      v-if="loading && !rows.length"
      :size="32"
      class="library__loading"
    />

    <p
      v-else-if="!rows.length"
      class="muted"
    >
      Nothing in this range yet.
    </p>

    <ol
      v-else
      class="rows"
    >
      <li
        v-for="(row, i) in rows"
        :key="i"
        class="row"
      >
        <span
          v-if="type !== 'scrobbles'"
          class="row__rank"
        >{{ i + 1 }}</span>
        <span
          v-if="type !== 'artist'"
          class="row__art"
        >
          <img
            v-if="row.releaseMbid"
            :src="releaseArtUrl(row.releaseMbid)"
            alt=""
            loading="lazy"
            @error="$event.target.style.visibility = 'hidden'"
          >
        </span>
        <span class="row__label">
          <strong>{{ primary(row) }}</strong>
          <span
            v-if="secondary(row)"
            class="row__secondary"
          >{{ secondary(row) }}</span>
        </span>
        <span
          v-if="type === 'scrobbles'"
          class="row__time"
          :title="formatDateTime(row.listenedAt)"
        >{{ relativeTime(row.listenedAt) }}</span>
        <span
          v-else
          class="row__count"
        >{{ formatNumber(row.count) }}</span>
      </li>
    </ol>

    <div
      v-if="rows.length && hasMore"
      class="library__more"
    >
      <NcButton
        :disabled="loading"
        @click="loadMore"
      >
        {{ loading ? 'Loading…' : 'Load more' }}
      </NcButton>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import { getTop, getListens, getYears, releaseArtUrl } from '../api.js'
import { formatNumber, formatDateTime, relativeTime } from '../format.js'

const RANGES = [
  { value: '7d', label: '7 days' },
  { value: '30d', label: '30 days' },
  { value: '90d', label: '90 days' },
  { value: '180d', label: '180 days' },
  { value: '365d', label: '1 year' },
  { value: 'all', label: 'All time' },
]
const TYPES = [
  { value: 'scrobbles', label: 'Scrobbles' },
  { value: 'artist', label: 'Artists' },
  { value: 'album', label: 'Albums' },
  { value: 'track', label: 'Tracks' },
]
const PAGE = 50

const range = ref('all')
const type = ref('scrobbles')
const customFrom = ref('')
const customTo = ref('')
const rows = ref([])
const offset = ref(0)
const hasMore = ref(true)
const loading = ref(false)
const years = ref([])

const maxYear = computed(() => Math.max(1, ...years.value.map((y) => y.count)))

/** Date string (YYYY-MM-DD) → Unix seconds, or 0 if empty/invalid. */
function toUnix(dateStr, endOfDay = false) {
  if (!dateStr) return 0
  const ms = Date.parse(dateStr + 'T00:00:00')
  if (Number.isNaN(ms)) return 0
  return Math.floor(ms / 1000) + (endOfDay ? 86400 : 0)
}

// Query params for the active range: explicit from/to for a custom range,
// otherwise the preset keyword.
const queryWindow = computed(() => {
  if (range.value !== 'custom') return { range: range.value }
  const w = {}
  const from = toUnix(customFrom.value)
  const to = toUnix(customTo.value, true)
  if (from) w.from = from
  if (to) w.to = to
  return w
})

function primary(row) {
  if (type.value === 'artist') return row.name
  if (type.value === 'scrobbles') return row.track
  return row.artist
}
function secondary(row) {
  if (type.value === 'album') return row.album
  if (type.value === 'track') return row.track
  if (type.value === 'scrobbles') return row.artist
  return ''
}
function yearHeight(count) {
  return Math.round((count / maxYear.value) * 100) + '%'
}

async function fetchPage() {
  if (type.value === 'scrobbles') {
    const batch = await getListens(PAGE, offset.value, queryWindow.value)
    return Array.isArray(batch) ? batch : []
  }
  const win = range.value === 'custom' ? queryWindow.value : {}
  const batch = await getTop(type.value, range.value, PAGE, offset.value, win)
  return Array.isArray(batch) ? batch : []
}

async function load(append) {
  loading.value = true
  try {
    const batch = await fetchPage()
    rows.value = append ? rows.value.concat(batch) : batch
    offset.value += batch.length
    hasMore.value = batch.length === PAGE
  } catch (e) {
    console.error('[Earmark] failed to load library', e)
    showError('Failed to load library')
  } finally {
    loading.value = false
  }
}

function reload() {
  offset.value = 0
  hasMore.value = true
  load(false)
}
function loadMore() {
  load(true)
}
function setRange(value) {
  range.value = value
  if (value !== 'custom') reload()
}
function setType(value) {
  type.value = value
  reload()
}
function selectYear(year) {
  customFrom.value = `${year}-01-01`
  customTo.value = `${year}-12-31`
  range.value = 'custom'
  reload()
}

onMounted(async () => {
  try {
    years.value = await getYears() ?? []
  } catch (e) {
    console.error('[Earmark] failed to load yearly breakdown', e)
  }
  reload()
})
</script>

<style scoped>
.library {
  max-width: 760px;
  margin: 0 auto;
  padding: 24px 20px 64px;
}
.library__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
}
.range-tabs, .type-tabs {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}
.type-tabs {
  margin-top: 20px;
}
.range-tab, .type-tab {
  background: var(--color-background-hover);
  border: none;
  border-radius: var(--border-radius-pill, 16px);
  padding: 4px 12px;
  cursor: pointer;
  color: var(--color-main-text);
  font-size: 0.85em;
}
.range-tab--active, .type-tab--active {
  background: var(--color-primary-element);
  color: var(--color-primary-element-text);
}
.custom-range {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
  margin-top: 12px;
  font-size: 0.85em;
  color: var(--color-text-maxcontrast);
}
.custom-range input {
  margin-left: 4px;
}

.years {
  margin-top: 20px;
}
.years__chart {
  display: flex;
  align-items: flex-end;
  gap: 3px;
  height: 90px;
}
.years__bar-wrap {
  flex: 1;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  align-items: center;
  position: relative;
  background: none;
  border: none;
  cursor: pointer;
  padding: 0 0 16px;
}
.years__bar {
  width: 100%;
  min-height: 2px;
  background: var(--color-primary-element);
  border-radius: 2px 2px 0 0;
  opacity: 0.75;
}
.years__bar-wrap:hover .years__bar {
  opacity: 1;
}
.years__label {
  position: absolute;
  bottom: 0;
  font-size: 0.7em;
  color: var(--color-text-maxcontrast);
}

.rows {
  list-style: none;
  padding: 0;
  margin: 16px 0 0;
}
.row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 0;
  border-bottom: 1px solid var(--color-border);
}
.row__rank {
  width: 1.8em;
  text-align: right;
  color: var(--color-text-maxcontrast);
  font-variant-numeric: tabular-nums;
}
.row__art {
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  border-radius: 4px;
  overflow: hidden;
  background: var(--color-background-dark);
}
.row__art img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.row__label {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}
.row__label strong {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.row__secondary {
  color: var(--color-text-maxcontrast);
  font-size: 0.85em;
}
.row__count, .row__time {
  flex-shrink: 0;
  font-variant-numeric: tabular-nums;
  color: var(--color-text-maxcontrast);
  font-size: 0.85em;
}
.library__loading {
  margin: 40px auto;
}
.library__more {
  margin-top: 20px;
  text-align: center;
}
.muted {
  color: var(--color-text-maxcontrast);
}
</style>
