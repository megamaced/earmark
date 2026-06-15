<template>
  <div class="recent">
    <h2>Recent listens</h2>

    <NcLoadingIcon
      v-if="loading && !listens.length"
      :size="32"
      class="recent__loading"
    />

    <p
      v-else-if="!listens.length"
      class="muted"
    >
      No listens yet. Connect Last.fm or point a scrobbler at Earmark from the Settings tab.
    </p>

    <ul
      v-else
      class="listen-list"
    >
      <li
        v-for="listen in listens"
        :key="listen.id"
        class="listen"
      >
        <span class="listen__art">
          <img
            v-if="listen.releaseMbid"
            :src="releaseArtUrl(listen.releaseMbid)"
            alt=""
            loading="lazy"
            @error="$event.target.style.visibility = 'hidden'"
          >
        </span>
        <div class="listen__main">
          <strong class="listen__track">{{ listen.track }}</strong>
          <span class="listen__artist">{{ listen.artist }}</span>
          <span
            v-if="listen.album"
            class="listen__album"
          >{{ listen.album }}</span>
        </div>
        <div class="listen__meta">
          <span :title="formatDateTime(listen.listenedAt)">{{ relativeTime(listen.listenedAt) }}</span>
          <span
            class="badge"
            :class="'badge--' + listen.source"
          >{{ listen.source }}</span>
          <span
            v-if="listen.resolutionState === 'resolved'"
            class="badge badge--mb"
            title="Resolved to MusicBrainz"
          >MB</span>
        </div>
      </li>
    </ul>

    <div
      v-if="listens.length && hasMore"
      class="recent__more"
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
import { getListens, releaseArtUrl } from '../api.js'
import { relativeTime, formatDateTime } from '../format.js'

const PAGE = 50
const listens = ref([])
const offset = ref(0)
const hasMore = ref(true)
const loading = ref(false)

async function loadMore() {
  loading.value = true
  try {
    const batch = await getListens(PAGE, offset.value)
    const rows = Array.isArray(batch) ? batch : []
    listens.value.push(...rows)
    offset.value += rows.length
    hasMore.value = rows.length === PAGE
  } catch (e) {
    console.error('[Earmark] failed to load listens', e)
    showError('Failed to load recent listens')
  } finally {
    loading.value = false
  }
}

onMounted(loadMore)
</script>

<style scoped>
.recent {
  max-width: 760px;
  margin: 0 auto;
  padding: 24px 20px 64px;
}
.recent__loading {
  margin: 40px auto;
}
.listen-list {
  list-style: none;
  padding: 0;
  margin: 16px 0 0;
}
.listen {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 9px 0;
  border-bottom: 1px solid var(--color-border);
}
.listen__art {
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  border-radius: 4px;
  overflow: hidden;
  background: var(--color-background-dark);
}
.listen__art img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.listen__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}
.listen__track {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.listen__artist {
  color: var(--color-text-maxcontrast);
  font-size: 0.9em;
}
.listen__album {
  color: var(--color-text-maxcontrast);
  font-size: 0.8em;
}
.listen__meta {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--color-text-maxcontrast);
  font-size: 0.8em;
}
.badge {
  border-radius: var(--border-radius-pill, 16px);
  padding: 1px 8px;
  background: var(--color-background-dark);
  text-transform: lowercase;
}
.badge--mb {
  background: var(--color-primary-element);
  color: var(--color-primary-element-text);
}
.recent__more {
  margin-top: 20px;
  text-align: center;
}
.muted {
  color: var(--color-text-maxcontrast);
}
</style>
