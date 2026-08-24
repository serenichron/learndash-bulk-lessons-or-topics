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
 * The menu is the one exception, built here rather than handed over.
 *
 * A menu is built by onOpen, and onOpen is a simple trigger. Those run for
 * anyone who opens the spreadsheet, without asking permission, and in
 * exchange they may only touch things that need no permission. Reaching into
 * a library is not one of those things, so an onOpen that called the library
 * would build nothing for a colleague who has not yet authorised the script,
 * and the menu they would have used to authorise it is the very thing that
 * failed to appear.
 *
 * Building the menu here needs no permission, so it appears for everyone.
 * Clicking an item still asks, once, which is nobody's to skip.
 *
 * The menu can be a fixed list because it says nothing about which site you
 * are on. See the note above onOpen in the library for why.
 *
 * To set it up, see README.md in the apps-script folder. In short: add the
 * library with the identifier LDBC, paste this file over Code.gs, save,
 * reload the spreadsheet.
 */

function onOpen() {
  var ui = SpreadsheetApp.getUi();

  var sites = ui
    .createMenu('Change site')
    .addItem('Dev staging', 'view_dev')
    .addItem('QA staging', 'view_qa')
    .addItem('Production', 'view_live');

  var tools = ui
    .createMenu('Tools')
    .addItem('Adopt the ids in this sheet', 'adoptIds')
    .addSeparator()
    .addItem('Link this cell to a post that already exists', 'linkCell')
    .addItem('Unlink this cell from this site', 'unlinkCell')
    .addSeparator()
    .addItem('Fix duplicate row keys', 'fixDuplicateRowKeys')
    .addItem('Repaint the id columns', 'repaintNow');

  ui
    .createMenu('LearnDash')
    .addSubMenu(sites)
    .addSeparator()
    .addItem('Check this sheet', 'checkSheet')
    .addItem('Push to Dev staging', 'push_dev')
    .addItem('Push to QA staging', 'push_qa')
    .addItem('Push to Production', 'push_live')
    .addSeparator()
    .addItem('Where is this sheet?', 'whereIsThisSheet')
    .addItem('Show the site panel', 'showViewer')
    .addSubMenu(tools)
    .addSeparator()
    .addItem('Settings', 'showSetup')
    .addItem('Test the connection', 'testConnection')
    .addToUi();
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

// Called by the settings screen, the site panel and the adopt screen, from
// the browser, so these have to return their answer rather than just doing
// something.
function currentView() {
  return LDBC.currentView();
}
function adoptConfirmed(chosen) {
  return LDBC.adoptConfirmed(chosen);
}
function saveSettings(form) {
  return LDBC.saveSettings(form);
}
function forgetSecret(profile, what) {
  return LDBC.forgetSecret(profile, what);
}
