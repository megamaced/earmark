<template>
  <div class="loved">
    <h2>Loved tracks</h2>

    <NcLoadingIcon
      v-if="loading && !tracks.length"
      :size="32"
      class="loved__loading"
    />

    <p
      v-else-if="!tracks.length"
      class="muted"
    >
      No loved tracks yet. Import them from Last.fm in the Settings tab.
    </p>

    <ul
      v-else
      class="loved-list"
    >
      <li
        v-for="track in tracks"
        :key="track.id"
        class="loved-item"
      >
        <span class="loved-item__heart">♥</span>
        <span class="loved-item__main">
          <strong class="loved-item__track">{{ track.track }}</strong>
          <span class="loved-item__artist">{{ track.artist }}</span>
        </span>
        <span
          v-if="track.lovedAt"
          class="loved-item__when"
          :title="formatDateTime(track.lovedAt)"
        >{{ relativeTime(track.lovedAt) }}</span>
      </li>
    </ul>

    <div
      v-if="tracks.length && hasMore"
      class="loved__more"
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
import { ref, onMounted } from 'vue'
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import { getLoved } from '../api.js'
import { relativeTime, formatDateTime } from '../format.js'

const PAGE = 50
const tracks = ref([])
const offset = ref(0)
const hasMore = ref(true)
const loading = ref(false)

async function loadMore() {
  loading.value = true
  try {
    const batch = await getLoved(PAGE, offset.value)
    const rows = Array.isArray(batch) ? batch : []
    tracks.value.push(...rows)
    offset.value += rows.length
    hasMore.value = rows.length === PAGE
  } catch (e) {
    console.error('[Earmark] failed to load loved tracks', e)
    showError('Failed to load loved tracks')
  } finally {
    loading.value = false
  }
}

onMounted(loadMore)
</script>

<style scoped>
.loved {
  max-width: 760px;
  margin: 0 auto;
  padding: 24px 20px 64px;
}
.loved__loading {
  margin: 40px auto;
}
.loved-list {
  list-style: none;
  padding: 0;
  margin: 16px 0 0;
}
.loved-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 9px 0;
  border-bottom: 1px solid var(--color-border);
}
.loved-item__heart {
  flex-shrink: 0;
  color: var(--color-error, #c0392b);
  font-size: 1.1em;
}
.loved-item__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}
.loved-item__track {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.loved-item__artist {
  color: var(--color-text-maxcontrast);
  font-size: 0.9em;
}
.loved-item__when {
  flex-shrink: 0;
  color: var(--color-text-maxcontrast);
  font-size: 0.8em;
}
.loved__more {
  margin-top: 20px;
  text-align: center;
}
.muted {
  color: var(--color-text-maxcontrast);
}
</style>
