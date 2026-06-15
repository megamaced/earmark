<template>
  <div class="overview">
    <div class="overview__header">
      <h2>Overview</h2>
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
      </div>
    </div>

    <div class="totals">
      <div class="totals__big">
        {{ formatNumber(totals.listens) }}
      </div>
      <div class="totals__sub">
        scrobbles<span v-if="totals.since"> · since {{ sinceLabel }}</span>
      </div>
    </div>

    <NcLoadingIcon
      v-if="loading"
      :size="32"
      class="overview__loading"
    />

    <template v-else>
      <section class="panel">
        <div class="panel__head">
          <h3>Top</h3>
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
        </div>

        <ol
          v-if="top.length"
          class="top-list"
        >
          <li
            v-for="(row, i) in top"
            :key="i"
            class="top-row"
          >
            <span class="top-row__rank">{{ i + 1 }}</span>
            <span
              v-if="type !== 'artist'"
              class="top-row__art"
            >
              <img
                v-if="row.releaseMbid"
                :src="releaseArtUrl(row.releaseMbid)"
                alt=""
                loading="lazy"
                @error="$event.target.style.visibility = 'hidden'"
              >
            </span>
            <span class="top-row__label">
              <strong>{{ primary(row) }}</strong>
              <span
                v-if="secondary(row)"
                class="top-row__secondary"
              >{{ secondary(row) }}</span>
            </span>
            <span class="top-row__count">{{ formatNumber(row.count) }}</span>
          </li>
        </ol>
        <p
          v-else
          class="muted"
        >
          No plays in this range yet.
        </p>
      </section>

      <section class="panel">
        <h3>Listening clock <span class="muted">(UTC)</span></h3>
        <div class="clock">
          <div
            v-for="(count, hour) in clock"
            :key="hour"
            class="clock__bar-wrap"
            :title="`${hour}:00 — ${formatNumber(count)} plays`"
          >
            <div
              class="clock__bar"
              :style="{ height: barHeight(count) }"
            />
            <span
              v-if="hour % 6 === 0"
              class="clock__label"
            >{{ hour }}</span>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { NcLoadingIcon } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import { getTotals, getTop, getClock, releaseArtUrl } from '../api.js'
import { formatNumber, formatDateTime } from '../format.js'

const RANGES = [
  { value: '7d', label: '7 days' },
  { value: '30d', label: '30 days' },
  { value: '90d', label: '90 days' },
  { value: 'year', label: 'Year' },
  { value: 'all', label: 'All time' },
]
const TYPES = [
  { value: 'artist', label: 'Artists' },
  { value: 'track', label: 'Tracks' },
  { value: 'album', label: 'Albums' },
]

const range = ref('30d')
const type = ref('artist')
const totals = ref({ listens: 0, since: null })
const top = ref([])
const clock = ref(new Array(24).fill(0))
const loading = ref(true)

const sinceLabel = computed(() => formatDateTime(totals.value.since).split(',')[0])
const maxClock = computed(() => Math.max(1, ...clock.value))

function primary(row) {
  return type.value === 'artist' ? row.name : row.artist
}
function secondary(row) {
  if (type.value === 'track') return row.track
  if (type.value === 'album') return row.album
  return ''
}
function barHeight(count) {
  return Math.round((count / maxClock.value) * 100) + '%'
}

async function loadStats() {
  loading.value = true
  try {
    const [t, c] = await Promise.all([
      getTop(type.value, range.value, 20),
      getClock(range.value),
    ])
    top.value = Array.isArray(t) ? t : []
    clock.value = Array.isArray(c) && c.length === 24 ? c : new Array(24).fill(0)
  } catch (e) {
    console.error('[Earmark] failed to load stats', e)
    showError('Failed to load statistics')
  } finally {
    loading.value = false
  }
}

function setRange(value) {
  range.value = value
  loadStats()
}
function setType(value) {
  type.value = value
  loadStats()
}

onMounted(async () => {
  try {
    totals.value = await getTotals() ?? { listens: 0, since: null }
  } catch (e) {
    console.error('[Earmark] failed to load totals', e)
  }
  await loadStats()
})
</script>

<style scoped>
.overview {
  max-width: 760px;
  margin: 0 auto;
  padding: 24px 20px 64px;
}

.overview__header {
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

.totals {
  margin: 20px 0 8px;
}
.totals__big {
  font-size: 2.6em;
  font-weight: 700;
  line-height: 1.1;
}
.totals__sub {
  color: var(--color-text-maxcontrast);
}

.overview__loading {
  margin: 40px auto;
}

.panel {
  margin-top: 28px;
}
.panel__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 8px;
}

.top-list {
  list-style: none;
  padding: 0;
  margin: 12px 0 0;
}
.top-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 7px 0;
  border-bottom: 1px solid var(--color-border);
}
.top-row__rank {
  width: 1.6em;
  text-align: right;
  color: var(--color-text-maxcontrast);
  font-variant-numeric: tabular-nums;
}
.top-row__art {
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  border-radius: 4px;
  overflow: hidden;
  background: var(--color-background-dark);
}
.top-row__art img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.top-row__label {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}
.top-row__secondary {
  color: var(--color-text-maxcontrast);
  font-size: 0.85em;
}
.top-row__count {
  font-variant-numeric: tabular-nums;
  color: var(--color-text-maxcontrast);
}

.clock {
  display: flex;
  align-items: flex-end;
  gap: 3px;
  height: 140px;
  margin-top: 16px;
}
.clock__bar-wrap {
  flex: 1;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  align-items: center;
  position: relative;
}
.clock__bar {
  width: 100%;
  min-height: 2px;
  background: var(--color-primary-element);
  border-radius: 2px 2px 0 0;
}
.clock__label {
  position: absolute;
  bottom: -18px;
  font-size: 0.7em;
  color: var(--color-text-maxcontrast);
}

.muted {
  color: var(--color-text-maxcontrast);
  font-weight: normal;
}
</style>
