<template>
  <div class="settings">
    <h2>Settings</h2>

    <!-- Last.fm import -->
    <section class="card">
      <h3>Last.fm import</h3>
      <p class="muted">
        Enter your Last.fm username and your own API key (create one free at
        <a
          href="https://www.last.fm/api/account/create"
          target="_blank"
          rel="noopener noreferrer"
        >last.fm/api</a>), then start the import.
      </p>
      <div class="field">
        <NcTextField
          v-model="username"
          label="Last.fm username"
          placeholder="your-lastfm-username"
        />
      </div>
      <div class="field">
        <NcTextField
          v-model="apiKey"
          type="password"
          label="Last.fm API key"
          :placeholder="lastfm.hasApiKey ? '•••••••• (saved — paste to replace)' : 'your API key'"
        />
        <NcButton
          :disabled="saving"
          @click="saveLastfm"
        >
          Save
        </NcButton>
      </div>
      <div class="import-status">
        <span>Status: <strong>{{ stateLabel }}</strong></span>
        <span class="muted">· {{ formatNumber(lastfm.listenCount) }} listens stored</span>
      </div>
      <NcButton
        type="primary"
        :disabled="!canImport || starting"
        @click="triggerImport"
      >
        {{ lastfm.state === 'backfill' ? 'Import running…' : 'Start full import' }}
      </NcButton>

      <div class="import-status import-status--loved">
        <span>Loved tracks: <strong>{{ lovedStateLabel }}</strong></span>
        <span class="muted">· {{ formatNumber(lastfm.lovedCount) }} loved</span>
      </div>
      <NcButton
        :disabled="!canImportLoved || startingLoved"
        @click="triggerLovedImport"
      >
        {{ lastfm.lovedState === 'pending' ? 'Importing loved…' : 'Import loved tracks' }}
      </NcButton>
    </section>

    <!-- Scrobble tokens -->
    <section class="card">
      <h3>Scrobble tokens</h3>
      <p class="muted">
        Point a scrobble client (ListenBrainz or Last.fm-protocol) at this server using a token
        below as the password, with your Nextcloud username.
      </p>

      <div class="field">
        <NcTextField
          v-model="newLabel"
          label="Label (e.g. Pano Scrobbler)"
        />
        <NcButton
          :disabled="creating"
          @click="createNewToken"
        >
          Create
        </NcButton>
      </div>

      <div
        v-if="freshToken"
        class="fresh-token"
      >
        <p>Copy this token now — it won't be shown again:</p>
        <code class="fresh-token__value">{{ freshToken }}</code>
        <NcButton @click="copyToken">
          Copy
        </NcButton>
      </div>

      <ul
        v-if="tokens.length"
        class="token-list"
      >
        <li
          v-for="t in tokens"
          :key="t.id"
          class="token"
        >
          <span class="token__label">{{ t.label || 'Unnamed token' }}</span>
          <span class="muted token__meta">
            created {{ relativeTime(t.createdAt) }}<span v-if="t.lastUsedAt"> · last used {{ relativeTime(t.lastUsedAt) }}</span>
          </span>
          <NcButton
            type="tertiary"
            @click="revoke(t.id)"
          >
            Revoke
          </NcButton>
        </li>
      </ul>
      <p
        v-else
        class="muted"
      >
        No tokens yet.
      </p>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { NcButton, NcTextField } from '@nextcloud/vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import {
  getLastfm, setLastfm, setApiKey, startImport, startLovedImport,
  listTokens, createToken, deleteToken,
} from '../api.js'
import { formatNumber, relativeTime } from '../format.js'

const STATE_LABELS = { '': 'Not started', backfill: 'Importing…', done: 'Up to date' }
const LOVED_STATE_LABELS = { '': 'Not imported', pending: 'Importing…', done: 'Imported' }

const lastfm = ref({ username: '', state: '', hasApiKey: false, listenCount: 0, lovedState: '', lovedCount: 0 })
const username = ref('')
const apiKey = ref('')
const newLabel = ref('')
const tokens = ref([])
const freshToken = ref('')

const saving = ref(false)
const starting = ref(false)
const startingLoved = ref(false)
const creating = ref(false)

const stateLabel = computed(() => STATE_LABELS[lastfm.value.state] ?? lastfm.value.state)
const lovedStateLabel = computed(() => LOVED_STATE_LABELS[lastfm.value.lovedState] ?? lastfm.value.lovedState)
// Username and key are both set in this panel, so requiring hasApiKey is safe
// (no cross-page staleness) and gives clear gating.
const canImport = computed(() =>
  (lastfm.value.username || '') !== '' && lastfm.value.hasApiKey && lastfm.value.state !== 'backfill',
)
const canImportLoved = computed(() =>
  (lastfm.value.username || '') !== '' && lastfm.value.hasApiKey && lastfm.value.lovedState !== 'pending',
)

async function refresh() {
  try {
    lastfm.value = await getLastfm()
    username.value = lastfm.value.username
  } catch (e) {
    console.error('[Earmark] failed to load settings', e)
  }
}

async function saveLastfm() {
  saving.value = true
  try {
    await setLastfm(username.value)
    // Only overwrite the key if a new one was typed (it's never prefilled).
    if (apiKey.value.trim() !== '') {
      await setApiKey(apiKey.value)
      apiKey.value = ''
    }
    await refresh()
    showSuccess('Last.fm settings saved')
  } catch {
    showError('Failed to save Last.fm settings')
  } finally {
    saving.value = false
  }
}

async function triggerImport() {
  starting.value = true
  try {
    lastfm.value = await startImport()
    showSuccess('Import started — it runs in the background')
  } catch (e) {
    showError(e?.response?.data?.ocs?.data?.error || 'Failed to start import')
  } finally {
    starting.value = false
  }
}

async function triggerLovedImport() {
  startingLoved.value = true
  try {
    lastfm.value = await startLovedImport()
    showSuccess('Loved-tracks import started — it runs in the background')
  } catch (e) {
    showError(e?.response?.data?.ocs?.data?.error || 'Failed to start loved import')
  } finally {
    startingLoved.value = false
  }
}

async function loadTokens() {
  try {
    tokens.value = await listTokens() ?? []
  } catch (e) {
    console.error('[Earmark] failed to load tokens', e)
  }
}

async function createNewToken() {
  creating.value = true
  try {
    const created = await createToken(newLabel.value)
    freshToken.value = created.token
    newLabel.value = ''
    await loadTokens()
  } catch {
    showError('Failed to create token')
  } finally {
    creating.value = false
  }
}

async function revoke(id) {
  try {
    await deleteToken(id)
    await loadTokens()
  } catch {
    showError('Failed to revoke token')
  }
}

async function copyToken() {
  try {
    await navigator.clipboard.writeText(freshToken.value)
    showSuccess('Token copied')
  } catch {
    showError('Could not copy — select and copy manually')
  }
}

onMounted(() => {
  refresh()
  loadTokens()
})
</script>

<style scoped>
.settings {
  max-width: 680px;
  margin: 0 auto;
  padding: 24px 20px 64px;
}
.card {
  margin-top: 20px;
  padding: 18px 20px;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large, 12px);
}
.card h3 {
  margin-top: 0;
}
.field {
  display: flex;
  align-items: flex-end;
  gap: 10px;
  margin: 12px 0;
}
.field :deep(.input-field) {
  flex: 1;
}
.import-status {
  margin: 10px 0 14px;
}
.import-status--loved {
  margin-top: 22px;
  padding-top: 16px;
  border-top: 1px solid var(--color-border);
}
.fresh-token {
  margin: 12px 0;
  padding: 12px;
  background: var(--color-background-hover);
  border-radius: var(--border-radius, 8px);
}
.fresh-token__value {
  display: block;
  margin: 8px 0;
  padding: 8px;
  background: var(--color-main-background);
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius, 8px);
  word-break: break-all;
  font-family: monospace;
}
.token-list {
  list-style: none;
  padding: 0;
  margin: 12px 0 0;
}
.token {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 0;
  border-bottom: 1px solid var(--color-border);
}
.token__label {
  font-weight: 600;
}
.token__meta {
  flex: 1;
  font-size: 0.8em;
}
.muted {
  color: var(--color-text-maxcontrast);
  font-weight: normal;
}
</style>
