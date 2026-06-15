/**
 * Centralised Earmark API helpers. Each function performs the request and
 * returns the unwrapped OCS `data` payload, so components deal in plain data.
 */
import axios from '@nextcloud/axios'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'

const ocs = (path) => generateOcsUrl('/apps/earmark/api/v1' + path)
const data = (res) => res.data?.ocs?.data

/** Cover art URL for a release MBID (proxied + cached from Cover Art Archive). */
export const releaseArtUrl = (mbid) => generateUrl('/apps/earmark/art/release/' + encodeURIComponent(mbid))

/* ── Stats + listens ──────────────────────────────────────────────────── */
// `window` is an optional { from, to } (Unix seconds) object for a custom
// range; when present it overrides the `range` keyword server-side.
export const getTotals = async () => data(await axios.get(ocs('/stats/totals')))
export const getYears = async () => data(await axios.get(ocs('/stats/years')))
export const getTop = async (type, range, limit = 20, offset = 0, window = {}) =>
  data(await axios.get(ocs('/stats/top'), { params: { type, range, limit, offset, ...window } }))
export const getClock = async (range, window = {}) =>
  data(await axios.get(ocs('/stats/clock'), { params: { range, ...window } }))
export const getListens = async (limit = 50, offset = 0, window = {}) =>
  data(await axios.get(ocs('/listens'), { params: { limit, offset, ...window } }))
export const getLoved = async (limit = 50, offset = 0) =>
  data(await axios.get(ocs('/loved'), { params: { limit, offset } }))

/* ── Last.fm settings ─────────────────────────────────────────────────── */
export const getLastfm = async () => data(await axios.get(ocs('/settings/lastfm')))
// `lastfmUsername`, not `username`: Nextcloud reserves `username` as a request
// param (login/basic-auth), so it never reaches the controller.
export const setLastfm = async (username) => data(await axios.post(ocs('/settings/lastfm'), { lastfmUsername: username }))
export const setApiKey = async (apiKey) => data(await axios.post(ocs('/settings/lastfm/api-key'), { lastfmApiKey: apiKey }))
export const startImport = async () => data(await axios.post(ocs('/settings/lastfm/import')))
export const startLovedImport = async () => data(await axios.post(ocs('/settings/lastfm/import-loved')))

/* ── Scrobble tokens ──────────────────────────────────────────────────── */
export const listTokens = async () => data(await axios.get(ocs('/tokens')))
export const createToken = async (label) => data(await axios.post(ocs('/tokens'), { label }))
export const deleteToken = async (id) => data(await axios.delete(ocs('/tokens/' + id)))
