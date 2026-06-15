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
          name="Recent"
          :active="view === 'recent'"
          @click="view = 'recent'"
        />
        <NcAppNavigationItem
          name="Library"
          :active="view === 'library'"
          @click="view = 'library'"
        />
        <NcAppNavigationItem
          name="Loved"
          :active="view === 'loved'"
          @click="view = 'loved'"
        />
      </template>
      <template #footer>
        <NcAppNavigationItem
          name="Settings"
          :active="view === 'settings'"
          @click="view = 'settings'"
        />
      </template>
    </NcAppNavigation>

    <NcAppContent>
      <OverviewView v-if="view === 'overview'" />
      <RecentView v-else-if="view === 'recent'" />
      <LibraryView v-else-if="view === 'library'" />
      <LovedView v-else-if="view === 'loved'" />
      <SettingsView v-else-if="view === 'settings'" />
    </NcAppContent>
  </NcContent>
</template>

<script setup>
import { ref } from 'vue'
import { NcContent, NcAppContent, NcAppNavigation, NcAppNavigationItem } from '@nextcloud/vue'
import OverviewView from './components/OverviewView.vue'
import RecentView from './components/RecentView.vue'
import LibraryView from './components/LibraryView.vue'
import LovedView from './components/LovedView.vue'
import SettingsView from './components/SettingsView.vue'

const view = ref('overview')
</script>
