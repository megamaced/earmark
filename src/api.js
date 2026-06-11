/**
 * Centralised Earmark API helpers. Each function performs the request and
 * returns the unwrapped OCS `data` payload, so components deal in plain data.
 */
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

const ocs = (path) => generateOcsUrl('/apps/earmark/api/v1' + path)
const data = (res) => res.data?.ocs?.data

/* ── Stats + listens ──────────────────────────────────────────────────── */
export const getTotals = async () => data(await axios.get(ocs('/stats/totals')))
export const getTop = async (type, range, limit = 20) =>
  data(await axios.get(ocs('/stats/top'), { params: { type, range, limit } }))
export const getClock = async (range) =>
  data(await axios.get(ocs('/stats/clock'), { params: { range } }))
export const getListens = async (limit = 50, offset = 0) =>
  data(await axios.get(ocs('/listens'), { params: { limit, offset } }))

/* ── Last.fm settings ─────────────────────────────────────────────────── */
export const getLastfm = async () => data(await axios.get(ocs('/settings/lastfm')))
// `lastfmUsername`, not `username`: Nextcloud reserves `username` as a request
// param (login/basic-auth), so it never reaches the controller.
export const setLastfm = async (username) => data(await axios.post(ocs('/settings/lastfm'), { lastfmUsername: username }))
export const startImport = async () => data(await axios.post(ocs('/settings/lastfm/import')))

/* ── Scrobble tokens ──────────────────────────────────────────────────── */
export const listTokens = async () => data(await axios.get(ocs('/tokens')))
export const createToken = async (label) => data(await axios.post(ocs('/tokens'), { label }))
export const deleteToken = async (id) => data(await axios.delete(ocs('/tokens/' + id)))
