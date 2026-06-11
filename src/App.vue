<template>
  <NcContent app-name="earmark">
    <NcAppNavigation>
      <template #list>
        <NcAppNavigationItem
          name="Overview"
          :active="view === 'overview'"
          @click="view = 'overview'"
        />
        <NcAppNavigationItem
          name="Recent listens"
          :active="view === 'recent'"
          @click="view = 'recent'"
        />
      </template>
      <template #footer>
        <NcAppNavigationSettings name="Settings" />
      </template>
    </NcAppNavigation>

    <NcAppContent>
      <div class="earmark-placeholder">
        <h2>🎧 Earmark</h2>
        <p>
          Your scrobble store is ready. Connect a Last.fm account or point a
          scrobble client at Earmark to start recording what you play.
        </p>
        <p class="earmark-placeholder__hint">
          Scaffold only — stats, import and the inbound scrobble API land in later phases.
        </p>
      </div>
    </NcAppContent>
  </NcContent>
</template>

<script setup>
import { ref } from 'vue'
import {
  NcContent, NcAppContent, NcAppNavigation, NcAppNavigationItem,
  NcAppNavigationSettings,
} from '@nextcloud/vue'

const view = ref('overview')
</script>

<style scoped>
.earmark-placeholder {
  max-width: 540px;
  margin: 15vh auto 0;
  text-align: center;
  color: var(--color-text-maxcontrast);
  padding: 0 20px;
}

.earmark-placeholder h2 {
  font-size: 1.6em;
  margin-bottom: 0.4em;
  color: var(--color-main-text);
}

.earmark-placeholder__hint {
  margin-top: 1.5em;
  font-size: 0.85em;
  font-style: italic;
}
</style>
