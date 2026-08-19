/**
 * The only file that goes in a spreadsheet.
 *
 * Everything else lives in one library script that all the spreadsheets
 * share, so a fix is made once rather than twelve times. This file exists
 * because Apps Script resolves a menu item, and anything a dialog calls, by
 * looking for a function of that name in the spreadsheet's own script. It
 * will not look inside a library. So each of those names gets a one line
 * function here that hands straight over.
 *
 * Nothing in this file has any opinions. It should never need editing again
 * once the library is attached, which is the whole point of it.
 *
 * To set it up, see README.md in the apps-script folder. In short: add the
 * library with the identifier LDBC, paste this file over Code.gs, save,
 * reload the spreadsheet.
 */

function onOpen() {
  LDBC.onOpen();
}

// The menu.
function checkSheet() {
  LDBC.checkSheet();
}
function whereIsThisSheet() {
  LDBC.whereIsThisSheet();
}
function showViewer() {
  LDBC.showViewer();
}
function showSetup() {
  LDBC.showSetup();
}
function testConnection() {
  LDBC.testConnection();
}

// One per site, for the viewing control and the push buttons.
function view_dev() {
  LDBC.view_dev();
}
function view_qa() {
  LDBC.view_qa();
}
function view_live() {
  LDBC.view_live();
}
function push_dev() {
  LDBC.push_dev();
}
function push_qa() {
  LDBC.push_qa();
}
function push_live() {
  LDBC.push_live();
}

// Tools.
function adoptIds() {
  LDBC.adoptIds();
}
function linkCell() {
  LDBC.linkCell();
}
function unlinkCell() {
  LDBC.unlinkCell();
}
function fixDuplicateRowKeys() {
  LDBC.fixDuplicateRowKeys();
}
function repaintNow() {
  LDBC.repaintNow();
}

// Called by the settings screen and the site panel, from the browser, so
// these have to return their answer rather than just doing something.
function currentView() {
  return LDBC.currentView();
}
function saveSettings(form) {
  return LDBC.saveSettings(form);
}
function forgetSecret(profile, what) {
  return LDBC.forgetSecret(profile, what);
}
