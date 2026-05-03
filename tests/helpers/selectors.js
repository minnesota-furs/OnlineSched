// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
// Central selector map. Update this file when class names change between refactor phases.
//
// ── Phase 0-1: No changes needed ──
//
// ── Phase 2 (Grid, Layout, Visibility): ──
//   hiddenXs:           '.hidden-xs'             → '.os-hide-mobile'
//   visibleXs:          '.visible-xs'            → '.os-show-mobile'
//
// ── Phase 3 (Tabs): ──
//   tabList:            '.schedule-tabs'          → '.os-tabs'
//   tabLinks:           '.schedule-tabs a'        → '.os-tabs a'
//   tabPaneActive:      '.tab-pane.active'        → '.os-tab-pane--active'
//
// ── Phase 4 (Modals): ──
//   scheduleModalClose: '#modal-schedule .close'  → '#modal-schedule .os-close'
//
// ── Phase 5 (jQuery Removal): ──
//   clipboardEffect:    '.clipboard-effect'       → '.os-clipboard-effect'
//   Also flip test 01 "jQuery is not undefined" to expect false.
//
// ── Phase 6 (Final Cleanup): ──
//   Remove test.skip() from 09-no-jquery-bootstrap.spec.js
//
module.exports = {
  // Page structure
  schedule:             '#schedule',
  scheduleItem:         '.schedule-item',
  scheduleDay:          '.schedule-day',
  scheduleHour:         '.schedule-hour',
  scheduleTitle:        '.schedule-title a',

  // Tabs
  tabList:              '.os-tabs',
  tabLinks:             '.os-tabs a',
  tabPaneActive:        '.os-tab-pane--active',
  tabProgramming:       '#programming',
  tabHours:             '#hours',

  // Filters
  searchInput:          '#schedule-search-text',
  selectTags:           '#schedule-select-tags',
  selectDays:           '#schedule-select-days',
  selectRooms:          '#schedule-select-rooms',
  resetButton:          '#schedule-reset',
  favoritesToggle:      '#schedule-favorites-toggle',

  // Favorites
  favoriteBtn:          '.schedule-favorite-toggle',

  // Modals
  loginModalBtn:        '#login-modal-btn',
  loginModal:           '#login-modal',
  loginModalClose:      '#login-modal-close',
  infoModalBtn:         '#info-modal-btn',
  infoModal:            '#info-modal',
  infoModalClose:       '#info-modal-close',
  scheduleModal:        '#modal-schedule',
  scheduleModalTitle:   '#modal-schedule-title',
  scheduleModalClose:   '#modal-schedule .os-close',
  androidModal:         '#android-google-calendar-modal',

  // Calendar
  modalIcal:            '#modal-schedule-ical',
  modalGoogle:          '#modal-schedule-google',
  modalCopyUrl:         '#modal-copy-url',
  clipboard:            '.schedule-clipboard',
  clipboardEffect:      '.os-clipboard-effect',

  // Buttons
  logoutBtn:            '#logout-modal-btn',

  // Responsive
  hiddenXs:             '.os-hide-mobile',
  visibleXs:            '.os-show-mobile',

  // Kiosk mode
  kioskClass:           '.kiosk-schedule',
  standardClass:        '.standard-schedule',
  addToCalendarSection: '#schedule-add-to-calendar',
  scheduleDescription:  '.schedule-description',
  scheduleIcalLink:     '.schedule-ical',
  scheduleGoogleLink:   '.schedule-google',
  tabMap:               '#map',
  hoursBlock:           '.os-hours',
  hoursRow:             '.os-hours__row',
  hoursDepartment:      '.os-hours__dept',
  hoursDay:             '.os-hours__days dt',
  hoursTimes:           '.os-hours__days dd',

  // Clickable inline filters (room/tag text in event rows)
  filterLink:           '.schedule-filter-link',
  scheduleRoom:         '.schedule-room',
  scheduleTags:         '.schedule-tags',

  // Badges
  badge:                '.os-badge',
  badgeDanger:          '.os-badge--danger',
  badgeSensory:         '.os-badge--sensory',
  badgeVip:             '.os-badge--vip',
  badgeEssentials:      '.os-badge--essentials',
  badgeCancelled:       '.os-badge--cancelled',
  badgeStreaming:        '.os-badge--streaming',
  badgeGoh:             '.os-badge--goh',
  badgeSpecialGuest:    '.os-badge--specialguest',
  badgeIcon:            '.os-badge--icon',
};
