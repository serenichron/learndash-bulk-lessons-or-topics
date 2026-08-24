/**
 * Push an upload sheet into LearnDash, on any of three sites, without ever
 * letting an id from one site reach another.
 *
 * Lives inside the spreadsheet. Adds a LearnDash menu with a Check, one Push
 * per site, and a viewing control that decides whose ids the sheet is
 * showing you at the moment.
 *
 * The whole idea in one paragraph. Every content row carries a row_key that
 * the script writes once and nobody edits. A hidden ledger sheet remembers,
 * for each row_key and each level and each site, which post that row became.
 * The id columns you can see are a view of the ledger, repainted whenever
 * you change which site you are looking at. A push sends the ledger's ids
 * for the site it is pushing to and pays no attention to the numbers sitting
 * in the cells, which is what stops a QA id from ever landing on production.
 *
 * So a cell holds one of four things:
 *
 *   a number   this row is on the site you are viewing, push updates it
 *   CREATE     it is not on that site yet, push makes it
 *   PREV       same as the row above, on every site
 *   blank      this row has nothing at this level
 *
 * Numbers and CREATE belong to the script and move when you change the view.
 * PREV and blank are yours and never move.
 */

var LEVELS = ['group', 'course', 'lesson', 'topic', 'quiz', 'question'];

/** What each level is called in WordPress, for checking an id before adopting it. */
var POST_TYPES = {
  group: 'groups',
  course: 'sfwd-courses',
  lesson: 'sfwd-lessons',
  topic: 'sfwd-topic',
  quiz: 'sfwd-quiz',
  question: 'sfwd-question'
};

/**
 * The sites you can point at, in the order the settings screen shows them.
 *
 * `colour` and `suffix` are how a tab says which site it is showing without
 * anyone opening a menu. Red on production is the one that matters.
 */
var PROFILES = [
  { key: 'dev', label: 'Dev staging', colour: '#1e8e3e', suffix: '-D' },
  { key: 'qa', label: 'QA staging', colour: '#f9ab00', suffix: '-Q' },
  { key: 'live', label: 'Production', colour: '#d93025', suffix: '-P' }
];

/** Any of the suffixes above, at the end of a tab name, with its space. */
var SUFFIX_PATTERN = /\s*-[DQP]$/;

/** Above this many rows the sheet is sent in pieces, split between quizzes. */
var CHUNK_ABOVE = 200;

var ROW_KEY = 'row_key';
var LEDGER_SHEET = '_ldbc_ids';
var LEDGER_HEADERS = ['row_key', 'level', 'profile', 'site_url', 'post_id', 'first_pushed', 'last_pushed'];
var META_SHEET = '_ldbc_meta';

// ---------------------------------------------------------------- the menu

/**
 * Sheets calls this, and only this, when the spreadsheet opens.
 *
 * It is kept separate from ldbcMenu because a project may only have one
 * function called onOpen. Apps Script loads every file in a project into one
 * namespace, so a second onOpen anywhere silently replaces the first, and
 * whichever happens to load last is the one that runs. The other one is not
 * an error. It simply never happens, and nothing says so.
 *
 * If this project holds another script that already has an onOpen, delete
 * this function and add one line to that one:
 *
 *   ldbcMenu();
 *
 * A project wants exactly one onOpen, calling each feature's menu in turn.
 */
function onOpen() {
  ldbcMenu();
}

/**
 * The menu says nothing about which site you are on, on purpose.
 *
 * It is built once when the spreadsheet opens and cannot be rebuilt when you
 * click another tab, so any site name in it would be right at first and
 * wrong from the second tab onwards. A label that is usually true is worse
 * than no label, because you stop checking it.
 *
 * Which site a tab is showing is said by the tab colour, by the -D, -Q or -P
 * on its name, and by the site panel. All three are per tab and none of them
 * can go stale.
 */
function ldbcMenu() {
  var ui = SpreadsheetApp.getUi();

  var viewing = ui.createMenu('Change site');
  PROFILES.forEach(function (profile) {
    viewing.addItem(profile.label, 'view_' + profile.key);
  });

  // Flat rather than a submenu of its own. Tools is already one level down,
  // and one level is all this needs, so there is nothing to gain from
  // burying three items a click deeper.
  var tools = ui.createMenu('Tools').addItem('Adopt the ids in this sheet', 'adoptIds');

  PROFILES.forEach(function (profile) {
    tools.addItem('Try the ids from ' + profile.label, 'adopt_from_' + profile.key);
  });

  tools
    .addSeparator()
    .addItem('Link this cell to a post that already exists', 'linkCell')
    .addItem('Unlink this cell from this site', 'unlinkCell')
    .addSeparator()
    .addItem('Fix duplicate row keys', 'fixDuplicateRowKeys')
    .addItem('Repaint the id columns', 'repaintNow');

  var menu = ui.createMenu('LearnDash').addSubMenu(viewing).addSeparator().addItem('Check this sheet', 'checkSheet');

  PROFILES.forEach(function (profile) {
    menu.addItem('Push to ' + profile.label, 'push_' + profile.key);
  });

  menu
    .addSeparator()
    .addItem('Where is this sheet?', 'whereIsThisSheet')
    .addItem('Show the site panel', 'showViewer')
    .addSubMenu(tools)
    .addSeparator()
    .addItem('Settings', 'showSetup')
    .addItem('Test the connection', 'testConnection')
    .addToUi();
}

// A menu item needs a named function of its own, so here are the six.
function view_dev() {
  setView('dev');
}
function view_qa() {
  setView('qa');
}
function view_live() {
  setView('live');
}
function push_dev() {
  run(true, 'dev');
}
function push_qa() {
  run(true, 'qa');
}
function push_live() {
  run(true, 'live');
}

function checkSheet() {
  run(false, viewProfile());
}

// ------------------------------------------------------------- the viewing

/**
 * Which site a tab is showing.
 *
 * Kept per tab, not per spreadsheet. A tab and its id columns then always
 * agree, because nothing repaints a tab you are not looking at. The
 * spreadsheet-wide setting is still there underneath, as what a tab shows
 * before anyone has chosen anything for it.
 *
 * Keyed by sheet id rather than sheet name, because we rename tabs.
 */
function viewProfile(sheet) {
  var target = sheet || SpreadsheetApp.getActiveSheet();
  var mine = target ? metaGet('view:' + target.getSheetId()) : '';

  if (mine) {
    return profileOr(mine, 'dev');
  }

  return profileOr(PropertiesService.getDocumentProperties().getProperty('profile'), 'dev');
}

function rememberView(sheet, profileKey) {
  var chosen = profileOr(profileKey, 'dev');

  metaSet('view:' + sheet.getSheetId(), chosen);

  // Also kept spreadsheet-wide, so a tab nobody has chosen for opens on the
  // site you were last working with rather than always on dev.
  PropertiesService.getDocumentProperties().setProperty('profile', chosen);
}

/**
 * Say on the tab itself which site it is showing.
 *
 * Colour first, because it reads before you do. The suffix is for when two
 * tabs sit side by side and colour alone is not enough.
 *
 * Only upload sheets are touched. Renaming somebody's notes because they
 * happened to be looking at it would be rude, and formulas point at names.
 */
function paintTab(sheet, profileKey) {
  var profile = PROFILES.filter(function (p) {
    return p.key === profileOr(profileKey, 'dev');
  })[0];

  if (!profile || !sheet) {
    return;
  }

  var values = sheet.getDataRange().getValues();

  if (!values.length || !hasIdColumn(headersOf(values))) {
    return;
  }

  sheet.setTabColor(profile.colour);

  var wanted = String(sheet.getName()).replace(SUFFIX_PATTERN, '') + ' ' + profile.suffix;

  if (wanted !== sheet.getName() && wanted.length <= 100) {
    sheet.setName(wanted);
  }
}

function setView(profileKey) {
  var ui = SpreadsheetApp.getUi();
  var sheet = SpreadsheetApp.getActiveSheet();

  if (!guardCopy()) {
    return;
  }

  // The view moves first, and the repaint is allowed to refuse.
  //
  // Pointing at a site is how you say where a pile of pasted ids came from,
  // and adopting them is the only way to clear the refusal. Refusing to move
  // the view as well would leave nowhere to go.
  rememberView(sheet, profileKey);
  paintTab(sheet, profileKey);

  var painted;
  try {
    painted = repaint(profileKey);
  } catch (e) {
    ui.alert(
      'Now showing ' + labelOf(profileKey) + ', but the id columns were left alone',
      String(e.message || e),
      ui.ButtonSet.OK
    );
    return;
  }

  if (painted.skipped) {
    ui.alert(
      'Now showing ' + labelOf(profileKey),
      'This sheet has no id columns, so there was nothing to repaint. Open an upload sheet and the ids there will be ' +
        labelOf(profileKey) +
        ' ids.',
      ui.ButtonSet.OK
    );
    return;
  }

  ui.alert(
    'Now showing ' + labelOf(profileKey),
    painted.known +
      ' of the ' +
      painted.owned +
      ' things this sheet describes are already on ' +
      labelOf(profileKey) +
      '.\n\nThe other ' +
      (painted.owned - painted.known) +
      ' say CREATE, which means a push to that site would make them.',
    ui.ButtonSet.OK
  );
}

/**
 * The one thing that can follow you from tab to tab.
 *
 * A menu is built when the spreadsheet opens and cannot be rebuilt when you
 * click a different tab. A sidebar can ask, over and over, which tab is in
 * front of you and what it is showing. So this is where the answer is always
 * true rather than usually true.
 */
function showViewer() {
  SpreadsheetApp.getUi().showSidebar(
    HtmlService.createTemplateFromFile('Viewer').evaluate().setTitle('LearnDash')
  );
}

/** What the sidebar asks for, a couple of times a second. */
function currentView() {
  var sheet = SpreadsheetApp.getActiveSheet();
  var view = viewProfile(sheet);
  var saved = settings();
  var site = saved[view] || {};
  var values = sheet.getDataRange().getValues();
  var upload = values.length > 1 && hasIdColumn(headersOf(values));

  return {
    profile: view,
    label: labelOf(view),
    colour: (PROFILES.filter(function (p) {
      return p.key === view;
    })[0] || {}).colour,
    host: hostOf(site.url) || 'not set up',
    ready: !!(site.url && site.key),
    sheet: sheet.getName(),
    upload: upload,
    profiles: PROFILES.map(function (p) {
      return { key: p.key, label: p.label, colour: p.colour, current: p.key === view };
    })
  };
}

function repaintNow() {
  var ui = SpreadsheetApp.getUi();
  var view = viewProfile();
  var painted;

  try {
    painted = repaint(view);
  } catch (e) {
    ui.alert(String(e.message || e));
    return;
  }

  ui.alert('Repainted', painted.cells + ' id cells now match the ledger for ' + labelOf(view) + '.', ui.ButtonSet.OK);
}

// ------------------------------------------------------------- the actions

function run(write, profileKey) {
  var ui = SpreadsheetApp.getUi();
  var cfg, sheet, ledger;

  if (!guardCopy()) {
    return;
  }

  try {
    cfg = config(profileKey);
    sheet = readSheet();
    ledger = readLedger();
  } catch (e) {
    ui.alert(String(e.message || e));
    return;
  }

  var pending = unadoptedNumbers(sheet.rows, ledger);

  if (pending.length) {
    ui.alert('There are ids here I did not write', unadoptedMessage(pending), ui.ButtonSet.OK);
    return;
  }

  var prepared = resolveRows(sheet, ledger, cfg.profile);

  if (write && !confirmPush(ui, cfg, sheet, prepared, ledger)) {
    return;
  }

  var report;
  try {
    report = send(write ? 'push' : 'check', prepared, cfg);
  } catch (e) {
    ui.alert('Could not reach the site', String(e.message || e), ui.ButtonSet.OK);
    return;
  }

  var recorded = 0;
  if (write && report.rows) {
    recorded = recordIds(prepared, report, cfg);
    rememberView(sheet.sheet, cfg.profile);
    paintTab(sheet.sheet, cfg.profile);
    repaint(cfg.profile);
  }

  showReport(report, cfg, sheet, write, recorded);
}

/**
 * Say out loud what is about to happen, check we are talking to the site we
 * think we are, and make production say it twice.
 */
function confirmPush(ui, cfg, sheet, prepared, ledger) {
  var creating = prepared.filter(function (p) {
    return p.creates;
  }).length;

  var answer = ui.alert(
    'Push to ' + cfg.label + '?',
    sheet.rows.length +
      ' rows from "' +
      sheet.name +
      '" will be sent to ' +
      hostOf(cfg.url) +
      '.\n\n' +
      (sheet.rows.length - creating) +
      ' rows are already on that site and would be updated.\n' +
      creating +
      ' rows are not there yet and would be created.\n\n' +
      'Nothing is written unless the whole sheet passes its check first.',
    ui.ButtonSet.OK_CANCEL
  );

  if (answer !== ui.Button.OK) {
    return false;
  }

  var seen = ledger.siteFor(cfg.profile);
  var info;

  try {
    info = ping(cfg);
  } catch (e) {
    ui.alert('Could not reach the site', String(e.message || e), ui.ButtonSet.OK);
    return false;
  }

  // The ledger remembers which host each site's ids came from. If the address
  // in settings has since been pointed somewhere else, every id held for this
  // site belongs to a different WordPress and would land on the wrong posts.
  if (seen && hostOf(seen) !== hostOf(info.url)) {
    ui.alert(
      'These ids did not come from this site',
      labelOf(cfg.profile) +
        ' used to be ' +
        hostOf(seen) +
        '.\nIt is now ' +
        hostOf(info.url) +
        '.\n\nThe ids this sheet holds for ' +
        labelOf(cfg.profile) +
        ' belong to the old site and would land on the wrong posts here. Nothing was sent.\n\n' +
        'Either put the address back, or set this site up as a fresh one.',
      ui.ButtonSet.OK
    );
    return false;
  }

  if (cfg.profile !== 'live') {
    return true;
  }

  return (
    ui.alert(
      'This is production',
      info.site +
        '\n' +
        hostOf(info.url) +
        '\n\nReal learners see this site. ' +
        creating +
        ' rows would be created and ' +
        (sheet.rows.length - creating) +
        ' updated.\n\nGo ahead?',
      ui.ButtonSet.YES_NO
    ) === ui.Button.YES
  );
}

/**
 * Where does this sheet live, according to the ledger.
 *
 * Answered without touching any site, because the ledger already knows. It
 * says what has been pushed where, not whether the posts are still there.
 * Check does that, one site at a time.
 */
function whereIsThisSheet() {
  var ui = SpreadsheetApp.getUi();
  var sheet, ledger;

  try {
    sheet = readSheet();
    ledger = readLedger();
  } catch (e) {
    ui.alert(String(e.message || e));
    return;
  }

  var lines = [sheet.rows.length + ' rows in "' + sheet.name + '".'];

  PROFILES.forEach(function (profile) {
    var counts = [];
    var missing = 0;

    LEVELS.forEach(function (level) {
      var owned = 0;
      var known = 0;

      sheet.rows.forEach(function (r) {
        if (!ownsLevel(r, level)) {
          return;
        }
        owned++;
        if (ledger.get(r.key, level, profile.key)) {
          known++;
        }
      });

      if (owned) {
        counts.push(known + ' of ' + owned + ' ' + level + (owned === 1 ? '' : 's'));
        missing += owned - known;
      }
    });

    lines.push('');
    lines.push(profile.label + (profile.key === viewProfile() ? '  (showing)' : ''));

    if (!counts.length) {
      lines.push('  nothing from this sheet');
      return;
    }

    lines.push('  ' + counts.join('\n  '));
    lines.push(missing ? '  ' + missing + ' still to create there' : '  all of it is there');
  });

  ui.alert('Where is this sheet?', lines.join('\n'), ui.ButtonSet.OK);
}

// ------------------------------------------------------------ sending it up

/**
 * Send the sheet, in one go if it is small enough.
 *
 * A large sheet is split between quizzes rather than at an arbitrary row,
 * because PREV chains a row to the one above it and a split in the middle of
 * a chain would break it. Splitting is refused outright if a PREV on any
 * other level would cross a seam.
 */
function send(mode, prepared, cfg) {
  if (prepared.length <= CHUNK_ABOVE) {
    return call(mode, cfg, {
      rows: prepared.map(payloadOf),
      first_row: prepared[0].row,
      detach_missing: cfg.detachMissing
    });
  }

  var chunks = splitBetweenQuizzes(prepared);
  var combined = null;

  for (var i = 0; i < chunks.length; i++) {
    var chunk = chunks[i];
    var part = call(mode, cfg, {
      rows: chunk.map(payloadOf),
      first_row: chunk[0].row,
      detach_missing: cfg.detachMissing
    });

    combined = combine(combined, part);

    // A failed chunk stops the rest. Whatever went in stays in, and the
    // report says exactly how far it got.
    if (!part.ok) {
      combined.stoppedAtChunk = i + 1;
      combined.totalChunks = chunks.length;
      break;
    }
  }

  return combined;
}

function payloadOf(prepared) {
  return prepared.data;
}

function splitBetweenQuizzes(prepared) {
  var carriers = ['course', 'lesson', 'topic'];
  var chunks = [];
  var current = [];

  prepared.forEach(function (r) {
    var quiz = String(r.data.quiz_id || '').trim();

    if (quiz !== 'PREV' && current.length > 0) {
      // A row that names its own quiz can start a new piece, as long as it
      // is not leaning on the row above for something else.
      var leaning = carriers.some(function (level) {
        return String(r.data[level + '_id'] || '').trim() === 'PREV';
      });

      if (!leaning) {
        chunks.push(current);
        current = [];
      }
    }

    current.push(r);
  });

  if (current.length) {
    chunks.push(current);
  }

  return chunks;
}

function combine(into, part) {
  if (!into) {
    return part;
  }

  into.ok = into.ok && part.ok;
  into.written = into.written || part.written;
  into.problems = (into.problems || []).concat(part.problems || []);
  into.warnings = (into.warnings || []).concat(part.warnings || []);
  into.detached = (into.detached || []).concat(part.detached || []);

  Object.keys(part.rows || {}).forEach(function (key) {
    into.rows[key] = part.rows[key];
  });

  Object.keys(part.summary || {}).forEach(function (key) {
    into.summary[key] = (into.summary[key] || 0) + part.summary[key];
  });

  return into;
}

// ---------------------------------------------------------- reading a sheet

function readSheet() {
  var sheet = SpreadsheetApp.getActiveSheet();
  var values = sheet.getDataRange().getValues();

  if (values.length < 2) {
    throw new Error('"' + sheet.getName() + '" has nothing in it below the header row.');
  }

  var headers = headersOf(values);

  // Any one id column is enough. A sheet of nothing but courses and lessons
  // is a perfectly good sheet. This runs before anything is written to the
  // sheet, so pointing the script at your notes leaves them untouched.
  if (!hasIdColumn(headers)) {
    throw new Error(
      '"' +
        sheet.getName() +
        '" does not look like an upload sheet. Its header row has none of these columns:\n' +
        idColumns().join(', ') +
        '\n\nOpen the sheet you want to send, then try again.'
    );
  }

  var prepared = ensureRowKeys(sheet, headers, values);
  headers = prepared.headers;
  values = prepared.values;

  var keyColumn = headers.indexOf(ROW_KEY);
  var rows = [];

  for (var i = 1; i < values.length; i++) {
    if (isBlankRow(values[i], headers)) {
      continue;
    }

    var data = {};
    for (var c = 0; c < headers.length; c++) {
      if (!headers[c] || headers[c] === ROW_KEY) {
        continue;
      }
      var raw = values[i][c];
      data[headers[c]] = raw == null ? '' : String(raw);
    }

    rows.push({ row: i + 1, key: String(values[i][keyColumn] || '').trim(), data: data });
  }

  if (!rows.length) {
    throw new Error('"' + sheet.getName() + '" has a header row but no content.');
  }

  return { sheet: sheet, name: sheet.getName(), headers: headers, rows: rows };
}

function headersOf(values) {
  return values[0].map(function (h) {
    return String(h == null ? '' : h).trim();
  });
}

function idColumns() {
  return LEVELS.map(function (level) {
    return level + '_id';
  });
}

function hasIdColumn(headers) {
  return idColumns().some(function (column) {
    return headers.indexOf(column) >= 0;
  });
}

function isBlankRow(row, headers) {
  for (var c = 0; c < headers.length; c++) {
    if (!headers[c] || headers[c] === ROW_KEY) {
      continue;
    }
    if (String(row[c] == null ? '' : row[c]).trim() !== '') {
      return false;
    }
  }
  return true;
}

/**
 * Give every content row a name of its own.
 *
 * The key is what ties a row to the posts it became on each site. Row
 * numbers move and titles get corrected, so neither can do that job. It is
 * written once, and after that it is nobody's business to change it.
 */
function ensureRowKeys(sheet, headers, values) {
  var column = headers.indexOf(ROW_KEY);

  if (column < 0) {
    // getLastColumn only counts columns with something in them, and a column
    // that has just been inserted has nothing in it, so remember where it
    // goes rather than asking afterwards.
    var at = sheet.getLastColumn() + 1;

    sheet.insertColumnAfter(at - 1);
    sheet.getRange(1, at).setValue(ROW_KEY);
    sheet.getRange(1, at, sheet.getMaxRows(), 1).setFontColor('#9aa0a6');
    sheet
      .getRange(1, at)
      .setNote('Written by the LearnDash script. It is how a row is recognised on each site. Do not edit or copy it.');

    values = sheet.getDataRange().getValues();
    headers = headersOf(values);
    column = headers.indexOf(ROW_KEY);
  }

  var keys = [];
  var seen = {};
  var duplicates = [];
  var missing = 0;

  for (var i = 1; i < values.length; i++) {
    var key = String(values[i][column] || '').trim();

    if (isBlankRow(values[i], headers)) {
      keys.push([key]);
      continue;
    }

    if (!key) {
      key = newRowKey();
      missing++;
    } else if (seen[key]) {
      duplicates.push(i + 1);
    }

    seen[key] = true;
    keys.push([key]);
  }

  if (duplicates.length) {
    throw new Error(
      duplicates.length +
        ' rows share a row key with a row above them, starting at row ' +
        duplicates[0] +
        '.\n\nThis happens when a row is copied. Two rows with one key both claim the same content on ' +
        'every site, so nothing can be sent until it is sorted.\n\nUse LearnDash, Tools, Fix duplicate row keys. ' +
        'That treats the copies as new content. If a copy was meant to be a duplicate, delete it instead.'
    );
  }

  if (missing) {
    sheet.getRange(2, column + 1, keys.length, 1).setValues(keys);
    values = sheet.getDataRange().getValues();
    headers = headersOf(values);
  }

  return { headers: headers, values: values };
}

function newRowKey() {
  return 'r' + Utilities.getUuid().replace(/-/g, '').slice(0, 12);
}

function fixDuplicateRowKeys() {
  var ui = SpreadsheetApp.getUi();
  var sheet = SpreadsheetApp.getActiveSheet();
  var values = sheet.getDataRange().getValues();
  var headers = headersOf(values);
  var column = headers.indexOf(ROW_KEY);

  if (column < 0) {
    ui.alert('This sheet has no row_key column yet, so there is nothing to fix.');
    return;
  }

  var seen = {};
  var keys = [];
  var fixed = [];

  for (var i = 1; i < values.length; i++) {
    var key = String(values[i][column] || '').trim();

    if (isBlankRow(values[i], headers)) {
      keys.push([key]);
      continue;
    }

    if (key && seen[key]) {
      key = newRowKey();
      fixed.push(i + 1);
    } else if (!key) {
      key = newRowKey();
      fixed.push(i + 1);
    }

    seen[key] = true;
    keys.push([key]);
  }

  if (!fixed.length) {
    ui.alert('Nothing to fix', 'Every row in this sheet already has a key of its own.', ui.ButtonSet.OK);
    return;
  }

  var answer = ui.alert(
    'Re-key ' + fixed.length + ' rows?',
    'These rows share a key with a row above: ' +
      fixed.slice(0, 12).join(', ') +
      (fixed.length > 12 ? ' and ' + (fixed.length - 12) + ' more' : '') +
      '.\n\nGiving them fresh keys treats them as new content. The next push to any site creates them ' +
      'rather than updating what the original row made.\n\nThis cannot be undone.',
    ui.ButtonSet.OK_CANCEL
  );

  if (answer !== ui.Button.OK) {
    return;
  }

  sheet.getRange(2, column + 1, keys.length, 1).setValues(keys);

  try {
    repaint(viewProfile());
  } catch (e) {
    ui.alert('Keys fixed, but the columns were left alone', String(e.message || e), ui.ButtonSet.OK);
    return;
  }

  ui.alert('Done', fixed.length + ' rows now have keys of their own.', ui.ButtonSet.OK);
}

// -------------------------------------------------- turning cells into ids

/** Does this row own something at this level, rather than borrowing or skipping it. */
function ownsLevel(row, level) {
  var authored = String(row.data[level + '_id'] == null ? '' : row.data[level + '_id']).trim();

  return authored !== '' && authored.toUpperCase() !== 'PREV';
}

/**
 * Numbers in the sheet that the ledger has never heard of.
 *
 * A number the script wrote always matches what the ledger holds for one of
 * the sites, because that is where it came from. A number that matches none
 * of them was typed or pasted by a person, and the script must not guess
 * what they meant by it. It cannot be sent, because nobody has said which
 * site it belongs to. It cannot be painted over either, because that would
 * throw away work someone just did.
 *
 * So it stops everything until it is adopted or cleared.
 */
function unadoptedNumbers(rows, ledger) {
  var found = [];

  rows.forEach(function (r) {
    LEVELS.forEach(function (level) {
      var raw = String(r.data[level + '_id'] == null ? '' : r.data[level + '_id']).trim();

      if (!/^\d+$/.test(raw)) {
        return;
      }

      var ours = ledger.entriesFor(r.key, level).some(function (entry) {
        return String(entry.id) === raw;
      });

      if (!ours) {
        found.push({ row: r.row, key: r.key, level: level, id: Number(raw) });
      }
    });
  });

  return found;
}

function unadoptedMessage(pending) {
  var sample = pending.slice(0, 8).map(function (p) {
    return 'row ' + p.row + ', ' + p.level + '_id ' + p.id;
  });

  return (
    pending.length +
    ' id cells hold a number this spreadsheet has never written.\n\n' +
    sample.join('\n') +
    (pending.length > sample.length ? '\nand ' + (pending.length - sample.length) + ' more' : '') +
    '\n\nThese look pasted in. Nothing can be sent or repainted until you say which site they belong to, ' +
    'because guessing would either make a duplicate or overwrite the wrong post.\n\n' +
    'Set the viewing control to the site those ids came from, then use LearnDash, Tools, Adopt the ids in ' +
    'this sheet. If they were pasted in by mistake, delete them or put CREATE back.'
  );
}

/**
 * Build what actually gets sent.
 *
 * The number in a cell is never trusted, because it belongs to whichever
 * site you were last looking at. What the row owns comes from the ledger, or
 * from CREATE when the ledger has nothing for this site yet.
 */
function resolveRows(sheet, ledger, profileKey) {
  return sheet.rows.map(function (r) {
    var data = {};
    var creates = false;

    Object.keys(r.data).forEach(function (column) {
      data[column] = r.data[column];
    });

    LEVELS.forEach(function (level) {
      var column = level + '_id';

      if (!(column in r.data)) {
        return;
      }

      var authored = String(r.data[column] || '').trim();

      if (authored === '') {
        data[column] = '';
        return;
      }

      if (authored.toUpperCase() === 'PREV') {
        data[column] = 'PREV';
        return;
      }

      var known = ledger.get(r.key, level, profileKey);
      data[column] = known ? String(known.id) : 'CREATE';

      if (!known) {
        creates = true;
      }
    });

    return { row: r.row, key: r.key, data: data, creates: creates, source: r };
  });
}

/**
 * Write down what the site just made, so the next push updates it instead of
 * making it again.
 */
function recordIds(prepared, report, cfg) {
  var entries = [];

  prepared.forEach(function (p) {
    var resolved = report.rows[String(p.row)] || report.rows[p.row];

    if (!resolved) {
      return;
    }

    LEVELS.forEach(function (level) {
      if (!ownsLevel(p.source, level)) {
        return;
      }

      var id = resolved[level];
      if (!id) {
        return;
      }

      entries.push({
        key: p.key,
        level: level,
        profile: cfg.profile,
        siteUrl: report.url || cfg.url,
        id: id
      });
    });
  });

  return writeLedgerEntries(entries);
}

/**
 * Show the ids of one site in the id columns.
 *
 * PREV and blank are left exactly as written, because they are the same
 * answer on every site and they are the only part of these columns a person
 * authors. Everything else is the ledger talking.
 */
function repaint(profileKey, force) {
  var sheet = SpreadsheetApp.getActiveSheet();
  var values = sheet.getDataRange().getValues();

  if (values.length < 2) {
    return { cells: 0, known: 0, owned: 0, skipped: true };
  }

  var headers = headersOf(values);

  if (!hasIdColumn(headers)) {
    return { cells: 0, known: 0, owned: 0, skipped: true };
  }

  // An upload sheet nobody has run Check on yet has no row keys, and without
  // them no number in it can be recognised. Give it keys here rather than
  // walking away, because walking away is how a sheet full of ids someone
  // pasted in slips past the guard below.
  var ready = ensureRowKeys(sheet, headers, values);
  headers = ready.headers;
  values = ready.values;

  var keyColumn = headers.indexOf(ROW_KEY);
  var ledger = readLedger();
  var painted = 0;
  var known = 0;
  var owned = 0;
  var pending = [];
  var plan = [];

  // Work the whole sheet out before touching a cell, so a number nobody
  // recognises stops the repaint with the sheet exactly as it was.
  LEVELS.forEach(function (level) {
    var column = headers.indexOf(level + '_id');

    if (column < 0) {
      return;
    }

    var out = [];
    var changed = false;

    for (var i = 1; i < values.length; i++) {
      var current = String(values[i][column] == null ? '' : values[i][column]).trim();

      if (current === '' || current.toUpperCase() === 'PREV') {
        out.push([values[i][column]]);
        continue;
      }

      owned++;

      var key = String(values[i][keyColumn] || '').trim();

      if (!force && /^\d+$/.test(current)) {
        var ours = ledger.entriesFor(key, level).some(function (entry) {
          return String(entry.id) === current;
        });

        if (!ours) {
          pending.push({ row: i + 1, key: key, level: level, id: Number(current) });
        }
      }

      var found = key ? ledger.get(key, level, profileKey) : null;
      var next = found ? Number(found.id) : 'CREATE';

      if (found) {
        known++;
      }

      if (String(next) !== current) {
        changed = true;
        painted++;
      }

      out.push([next]);
    }

    if (changed) {
      plan.push({ column: column, values: out });
    }
  });

  if (pending.length) {
    throw new Error(unadoptedMessage(pending));
  }

  plan.forEach(function (step) {
    sheet.getRange(2, step.column + 1, step.values.length, 1).setValues(step.values);
  });

  return { cells: painted, known: known, owned: owned, skipped: false };
}

// ------------------------------------------------------------- the ledger

function ledgerKeyOf(key, level, profile) {
  return String(key) + '|' + String(level) + '|' + String(profile);
}

/** Make a hidden sheet without stealing the view from whatever you were on. */
function hiddenSheet(name, headers) {
  var ss = SpreadsheetApp.getActive();
  var sheet = ss.getSheetByName(name);

  if (sheet) {
    return sheet;
  }

  var wasOn = ss.getActiveSheet();

  sheet = ss.insertSheet(name);
  sheet.getRange(1, 1, 1, headers.length).setValues([headers]).setFontWeight('bold');
  sheet.setFrozenRows(1);
  sheet.hideSheet();

  ss.setActiveSheet(wasOn);

  return sheet;
}

function ledgerSheet() {
  return hiddenSheet(LEDGER_SHEET, LEDGER_HEADERS);
}

function ledgerValues() {
  var sheet = ledgerSheet();
  var last = sheet.getLastRow();

  return last > 1 ? sheet.getRange(2, 1, last - 1, LEDGER_HEADERS.length).getValues() : [];
}

function readLedger() {
  var values = ledgerValues();
  var index = {};
  var pairs = {};
  var sites = {};

  values.forEach(function (row) {
    var key = String(row[0] || '').trim();
    var level = String(row[1] || '').trim();
    var profile = String(row[2] || '').trim();
    var id = Number(row[4]);

    if (!key || !level || !profile || !id) {
      return;
    }

    index[ledgerKeyOf(key, level, profile)] = { id: id, siteUrl: String(row[3] || '') };

    var pair = key + ' ' + level;
    pairs[pair] = pairs[pair] || [];
    pairs[pair].push({ profile: profile, id: id });

    if (row[3]) {
      sites[profile] = String(row[3]);
    }
  });

  return {
    get: function (key, level, profile) {
      return index[ledgerKeyOf(key, level, profile)] || null;
    },
    /** Every site this row and level is known on, which is how a number in a cell is recognised as ours. */
    entriesFor: function (key, level) {
      return pairs[key + ' ' + level] || [];
    },
    siteFor: function (profile) {
      return sites[profile] || '';
    }
  };
}

function writeLedgerEntries(entries) {
  if (!entries.length) {
    return 0;
  }

  var sheet = ledgerSheet();
  var values = ledgerValues();
  var index = {};

  values.forEach(function (row, i) {
    index[ledgerKeyOf(row[0], row[1], row[2])] = i;
  });

  var stamp = new Date();

  entries.forEach(function (entry) {
    var at = ledgerKeyOf(entry.key, entry.level, entry.profile);

    if (at in index) {
      var row = values[index[at]];
      row[3] = entry.siteUrl;
      row[4] = entry.id;
      row[6] = stamp;
      return;
    }

    values.push([entry.key, entry.level, entry.profile, entry.siteUrl, entry.id, stamp, stamp]);
    index[at] = values.length - 1;
  });

  sheet.getRange(2, 1, values.length, LEDGER_HEADERS.length).setValues(values);

  return entries.length;
}

function forgetLedgerEntry(key, level, profile) {
  var sheet = ledgerSheet();
  var values = ledgerValues();
  var at = ledgerKeyOf(key, level, profile);

  var kept = values.filter(function (row) {
    return ledgerKeyOf(row[0], row[1], row[2]) !== at;
  });

  if (kept.length === values.length) {
    return false;
  }

  sheet.getRange(2, 1, values.length, LEDGER_HEADERS.length).clearContent();

  if (kept.length) {
    sheet.getRange(2, 1, kept.length, LEDGER_HEADERS.length).setValues(kept);
  }

  return true;
}

function clearLedger() {
  var sheet = ledgerSheet();
  var last = sheet.getLastRow();

  if (last > 1) {
    sheet.getRange(2, 1, last - 1, LEDGER_HEADERS.length).clearContent();
  }
}

// --------------------------------------------------------- the meta corner

function metaSheet() {
  return hiddenSheet(META_SHEET, ['key', 'value']);
}

function metaGet(key) {
  var sheet = metaSheet();
  var last = sheet.getLastRow();

  if (last < 2) {
    return '';
  }

  var values = sheet.getRange(2, 1, last - 1, 2).getValues();

  for (var i = 0; i < values.length; i++) {
    if (String(values[i][0]).trim() === key) {
      return String(values[i][1] || '');
    }
  }

  return '';
}

function metaSet(key, value) {
  var sheet = metaSheet();
  var last = sheet.getLastRow();
  var values = last > 1 ? sheet.getRange(2, 1, last - 1, 2).getValues() : [];

  for (var i = 0; i < values.length; i++) {
    if (String(values[i][0]).trim() === key) {
      sheet.getRange(i + 2, 2).setValue(value);
      return;
    }
  }

  sheet.getRange(values.length + 2, 1, 1, 2).setValues([[key, value]]);
}

/**
 * Notice when this spreadsheet is a copy of the one the ledger was built in.
 *
 * A copy inherits every link, so pushing from it edits the very same posts
 * the original edits. That is almost never what someone making a backup
 * expects, and it is the one mistake here that reaches production quietly.
 */
function guardCopy() {
  var id = SpreadsheetApp.getActive().getId();
  var stamped = metaGet('spreadsheet_id');

  if (!stamped) {
    metaSet('spreadsheet_id', id);
    return true;
  }

  if (stamped === id) {
    return true;
  }

  var ui = SpreadsheetApp.getUi();
  var answer = ui.alert(
    'This is a copy of another spreadsheet',
    'The links in this copy point at the same posts as the spreadsheet it was copied from. ' +
      'Pushing from here would edit that same content on your sites.\n\n' +
      'Yes keeps the links, which is right if this copy is taking over from the original.\n\n' +
      'No throws them away, so the next push creates everything fresh. That cannot be undone.',
    ui.ButtonSet.YES_NO_CANCEL
  );

  if (answer === ui.Button.YES) {
    metaSet('spreadsheet_id', id);
    return true;
  }

  if (answer === ui.Button.NO) {
    clearLedger();
    metaSet('spreadsheet_id', id);

    // Forced, because throwing the ledger away leaves every number in the
    // sheet unrecognised by definition. They all become CREATE, which is
    // exactly what starting clean means.
    repaint(viewProfile(), true);
    return true;
  }

  return false;
}

// ------------------------------------------------- linking by hand, rarely

/** Work out which row and level the cursor is sitting on. */
function cellTarget() {
  var sheet = SpreadsheetApp.getActiveSheet();
  var cell = sheet.getActiveCell();
  var values = sheet.getDataRange().getValues();

  if (values.length < 2) {
    throw new Error('"' + sheet.getName() + '" has nothing in it below the header row.');
  }

  var headers = headersOf(values);
  var column = cell.getColumn() - 1;
  var row = cell.getRow();
  var header = headers[column] || '';
  var level = header.replace(/_id$/, '');

  if (!/_id$/.test(header) || LEVELS.indexOf(level) < 0) {
    throw new Error(
      'Put the cursor on the id cell you mean first.\n\nThat is one of: ' +
        idColumns().join(', ') +
        '.\n\nThis cursor is on "' +
        (header || 'a column with no header') +
        '".'
    );
  }

  if (row < 2 || row > values.length) {
    throw new Error('Row ' + row + ' is not a content row.');
  }

  var keyColumn = headers.indexOf(ROW_KEY);

  if (keyColumn < 0) {
    throw new Error('This sheet has no row_key column yet. Run Check once and the script will add it.');
  }

  var key = String(values[row - 1][keyColumn] || '').trim();

  if (!key) {
    throw new Error('Row ' + row + ' has no row key yet. Run Check once and the script will give it one.');
  }

  return { sheet: sheet, row: row, column: column, level: level, key: key };
}

/**
 * Take the ids already sitting in this sheet as the ids of one site.
 *
 * This is how content that was uploaded before any of this existed comes
 * under the ledger's care. You paste the ids that site gave you, point the
 * tab at the site they came from, and run this.
 *
 * Two things are checked and only two. Does the post exist there, and is it
 * the right kind of thing. A title is not a check, because you adopt an id
 * precisely so you can overwrite that content, and the sheet holding a newer
 * title than the site is the normal case rather than a fault. So the titles
 * are put side by side and you decide, which is the only thing that catches
 * a real id pointing at an entirely different quiz.
 */
function adoptIds() {
  openAdoption(null);
}

/**
 * Try the ids one site already holds against the site this tab is showing.
 *
 * Cloned sites carry the same ids, so what production calls quiz 2159694 is
 * very often what QA calls quiz 2159694. This asks, row by row, rather than
 * making you paste the same numbers in twice.
 *
 * It asks the target site about every one of them, so an id that is not
 * there, or holds something else, is reported and cannot be ticked. A title
 * that disagrees starts unticked here, because these are numbers the script
 * proposed rather than numbers you chose.
 */
function adopt_from_dev() {
  openAdoption('dev');
}
function adopt_from_qa() {
  openAdoption('qa');
}
function adopt_from_live() {
  openAdoption('live');
}

function openAdoption(from) {
  var ui = SpreadsheetApp.getUi();
  var review;

  if (!guardCopy()) {
    return;
  }

  if (from && from === viewProfile()) {
    ui.alert(
      'That is the site you are on',
      'This tab is already showing ' +
        labelOf(from) +
        '. Pick one of the other two, or use Adopt the ids in this sheet to take what is in the cells.',
      ui.ButtonSet.OK
    );
    return;
  }

  try {
    review = adoptionReview(from);
  } catch (e) {
    ui.alert(String(e.message || e));
    return;
  }

  if (!review.rows.length) {
    ui.alert(
      'Nothing to adopt',
      from
        ? 'This sheet holds no ' +
          labelOf(from) +
          ' ids that are not already known as ' +
          review.label +
          ' ids.'
        : 'Every number in this sheet is already known as a ' + review.label + ' id.',
      ui.ButtonSet.OK
    );
    return;
  }

  var template = HtmlService.createTemplateFromFile('Adopt');
  template.review = review;

  ui.showModalDialog(
    template.evaluate().setWidth(920).setHeight(640),
    'Adopt ' + review.rows.length + ' ids as ' + review.label
  );
}

/**
 * Everything the adopt screen needs, worked out fresh.
 *
 * Built again when you press the button as well as when the screen opens, so
 * what is written is what the sheet and the site say at that moment rather
 * than what they said while you were reading.
 */
function adoptionReview(from) {
  var view = viewProfile();
  var cfg = config(view);
  var sheet = readSheet();
  var ledger = readLedger();

  // Where the numbers to try come from. The cells, which is a person having
  // pasted them in, or another site's ids, which is the script proposing
  // them. The difference matters at the end, where proposed numbers with a
  // title that disagrees start unticked.
  var source = from && from !== view ? profileOr(from, null) : null;

  var candidates = [];
  var elsewhere = 0;

  sheet.rows.forEach(function (r) {
    LEVELS.forEach(function (level) {
      var raw = source
        ? String((ledger.get(r.key, level, source) || {}).id || '')
        : String(r.data[level + '_id'] == null ? '' : r.data[level + '_id']).trim();

      if (!/^\d+$/.test(raw)) {
        return;
      }

      var here = ledger.get(r.key, level, view);

      if (here && String(here.id) === raw) {
        return;
      }

      if (
        !source &&
        ledger.entriesFor(r.key, level).some(function (entry) {
          return String(entry.id) === raw;
        })
      ) {
        elsewhere++;
      }

      candidates.push({
        row: r.row,
        key: r.key,
        level: level,
        id: Number(raw),
        replaces: here ? here.id : null,
        sheetTitle: String(r.data[level + '_post_title'] == null ? '' : r.data[level + '_post_title']).trim()
      });
    });
  });

  var rows = [];

  if (candidates.length) {
    var wanted = {};
    candidates.forEach(function (c) {
      wanted[c.id] = true;
    });

    var posts = lookupPosts(cfg, Object.keys(wanted));

    rows = candidates.map(function (c) {
      var post = posts[String(c.id)] || null;
      var state;

      if (!post || !post.found) {
        state = 'missing';
      } else if (post.post_type !== POST_TYPES[c.level]) {
        state = 'wrong-type';
      } else if (sameTitle(post.title, c.sheetTitle)) {
        state = 'same';
      } else {
        state = 'differs';
      }

      return {
        at: c.row + '|' + c.level + '|' + c.id,
        row: c.row,
        key: c.key,
        level: c.level,
        id: c.id,
        replaces: c.replaces,
        sheetTitle: c.sheetTitle,
        siteTitle: post && post.found ? post.title : '',
        siteType: post && post.found ? post.post_type : '',
        status: post && post.found ? post.status : '',
        state: state,
        adoptable: state === 'same' || state === 'differs',
        // A number you pasted in, you chose. A number taken from another
        // site, the script chose, so a title that disagrees has to be ticked
        // by hand. This is the one action that could tie fifty rows to the
        // wrong content in a single press.
        ticked: state === 'same' || (state === 'differs' && !source)
      };
    });

    // Worst first. What cannot be adopted at all, then what disagrees, then
    // the long tail that matches and needs no thought.
    var order = { missing: 0, 'wrong-type': 1, differs: 2, same: 3 };
    rows.sort(function (a, b) {
      return order[a.state] - order[b.state] || a.row - b.row || (a.level < b.level ? -1 : 1);
    });
  }

  return {
    profile: view,
    label: labelOf(view),
    host: hostOf(cfg.url),
    url: cfg.url,
    sheet: sheet.name,
    source: source,
    sourceLabel: source ? labelOf(source) : '',
    elsewhere: elsewhere,
    rows: rows,
    counts: {
      missing: countState(rows, 'missing'),
      wrongType: countState(rows, 'wrong-type'),
      differs: countState(rows, 'differs'),
      same: countState(rows, 'same'),
      replacing: rows.filter(function (r) {
        return r.replaces !== null && r.adoptable;
      }).length
    }
  };
}

function countState(rows, state) {
  return rows.filter(function (r) {
    return r.state === state;
  }).length;
}

/** Close enough to be the same title, once WordPress's typesetting is undone. */
function sameTitle(a, b) {
  return normaliseTitle(a) !== '' && normaliseTitle(a) === normaliseTitle(b);
}

/** The few entities a WordPress title comes back with, spelled by name. */
var NAMED_ENTITIES = {
  amp: '&',
  lt: '<',
  gt: '>',
  quot: '"',
  apos: "'",
  nbsp: ' ',
  ndash: '-',
  mdash: '-',
  hellip: '...',
  lsquo: "'",
  rsquo: "'",
  ldquo: '"',
  rdquo: '"'
};

/**
 * Undo what WordPress did to a title on its way out.
 *
 * `get_the_title` runs the `the_title` filter, and that texturizes: a plain
 * hyphen between spaces becomes an en dash, straight quotes become curly
 * ones, and some of it arrives as entities rather than characters. So a site
 * says "SB-L &#8211; Listen to a Conversation 1" for a sheet that says
 * "SB-L - Listen to a Conversation 1".
 *
 * None of that is a difference in the content. It is a difference in how
 * WordPress prints it. Undoing it here is what stops every row of an adopt
 * screen reading as a mismatch, which would make the one column worth
 * reading worth ignoring.
 */
function normaliseTitle(text) {
  return decodeEntities(String(text == null ? '' : text))
    .replace(/[‒–—―−]/g, '-')
    .replace(/[‘’‛]/g, "'")
    .replace(/[“”‟]/g, '"')
    .replace(/[   ]/g, ' ')
    .replace(/…/g, '...')
    .replace(/\s+/g, ' ')
    .trim()
    .toLowerCase();
}

function decodeEntities(text) {
  return text
    .replace(/&#(\d+);/g, function (whole, code) {
      return codePoint(parseInt(code, 10), whole);
    })
    .replace(/&#x([0-9a-fA-F]+);/g, function (whole, code) {
      return codePoint(parseInt(code, 16), whole);
    })
    .replace(/&([a-zA-Z]+);/g, function (whole, name) {
      var known = NAMED_ENTITIES[name.toLowerCase()];
      return known === undefined ? whole : known;
    });
}

function codePoint(number, whole) {
  if (!isFinite(number) || number < 1 || number > 0x10ffff) {
    return whole;
  }

  return String.fromCharCode(number);
}

/**
 * Write down the ones the adopt screen was left ticked.
 *
 * `chosen` is a list of "row|level|id". The sheet and the site are asked
 * again rather than trusting what the screen was showing, and anything that
 * no longer lines up is simply not in the new list and so is not written.
 */
function adoptConfirmed(chosen, from) {
  var wanted = {};
  (chosen || []).forEach(function (at) {
    wanted[String(at)] = true;
  });

  var review = adoptionReview(from);
  var entries = [];
  var skipped = 0;

  review.rows.forEach(function (r) {
    if (!r.adoptable) {
      return;
    }

    if (!wanted[r.at]) {
      skipped++;
      return;
    }

    entries.push({ key: r.key, level: r.level, profile: review.profile, siteUrl: review.url, id: r.id });
  });

  if (entries.length) {
    writeLedgerEntries(entries);
  }

  var trouble = '';
  try {
    repaint(review.profile);
  } catch (e) {
    trouble = String(e.message || e);
  }

  return {
    adopted: entries.length,
    skipped: skipped,
    refused: review.counts.missing + review.counts.wrongType,
    label: review.label,
    trouble: trouble
  };
}

/**
 * Take a post that already exists on a site under this row's wing.
 *
 * Typing a number into a cell will not do this, because a push ignores the
 * cells. Adopting is a thing you say out loud, and the site is asked what
 * that id actually is before it is written down.
 */
function linkCell() {
  var ui = SpreadsheetApp.getUi();
  var view = viewProfile();
  var target, cfg;

  try {
    target = cellTarget();
    cfg = config(view);
  } catch (e) {
    ui.alert(String(e.message || e));
    return;
  }

  var answer = ui.prompt(
    'Link row ' + target.row + ' to an existing ' + target.level,
    'Which ' +
      target.level +
      ' on ' +
      labelOf(view) +
      ' (' +
      hostOf(cfg.url) +
      ') should this row take over?\n\nType its id.',
    ui.ButtonSet.OK_CANCEL
  );

  if (answer.getSelectedButton() !== ui.Button.OK) {
    return;
  }

  var id = String(answer.getResponseText() || '').trim();

  if (!/^\d+$/.test(id)) {
    ui.alert('"' + id + '" is not an id. It has to be a plain number.');
    return;
  }

  var found;
  try {
    found = lookupPost(cfg, id);
  } catch (e) {
    ui.alert('Could not ask the site', String(e.message || e), ui.ButtonSet.OK);
    return;
  }

  if (!found.found) {
    ui.alert('There is no post with id ' + id + ' on ' + hostOf(cfg.url) + '.');
    return;
  }

  if (found.post_type !== POST_TYPES[target.level]) {
    ui.alert(
      'That is not a ' + target.level,
      'Post ' +
        id +
        ' on ' +
        hostOf(cfg.url) +
        ' is a ' +
        found.post_type +
        ', not a ' +
        POST_TYPES[target.level] +
        '.\n\nNothing was linked.',
      ui.ButtonSet.OK
    );
    return;
  }

  var confirmed = ui.alert(
    'Link row ' + target.row + ' to "' + found.title + '"?',
    'From now on, a push to ' +
      labelOf(view) +
      ' updates that post rather than making a new one. The next push overwrites its ' +
      'content with what this row says.\n\n' +
      found.title +
      '\n' +
      found.post_type +
      ' ' +
      id +
      ', ' +
      found.status +
      '\n' +
      hostOf(cfg.url),
    ui.ButtonSet.OK_CANCEL
  );

  if (confirmed !== ui.Button.OK) {
    return;
  }

  writeLedgerEntries([
    { key: target.key, level: target.level, profile: view, siteUrl: cfg.url, id: Number(id) }
  ]);

  repaint(view);

  ui.alert('Linked', 'Row ' + target.row + ' now points at ' + target.level + ' ' + id + ' on ' + labelOf(view) + '.', ui.ButtonSet.OK);
}

/**
 * Break the link between one row and one site, usually because somebody
 * deleted the post in WordPress by hand.
 */
function unlinkCell() {
  var ui = SpreadsheetApp.getUi();
  var view = viewProfile();
  var target;

  try {
    target = cellTarget();
  } catch (e) {
    ui.alert(String(e.message || e));
    return;
  }

  var entry = readLedger().get(target.key, target.level, view);

  if (!entry) {
    ui.alert('Row ' + target.row + ' is not linked to any ' + target.level + ' on ' + labelOf(view) + ' yet.');
    return;
  }

  var answer = ui.alert(
    'Unlink row ' + target.row + ' from ' + labelOf(view) + '?',
    'This row currently points at ' +
      target.level +
      ' ' +
      entry.id +
      ' on ' +
      labelOf(view) +
      '.\n\nAfter this the cell says CREATE, and the next push to that site makes a new ' +
      target.level +
      ' instead. Nothing is deleted in WordPress, and the other sites are not touched.',
    ui.ButtonSet.OK_CANCEL
  );

  if (answer !== ui.Button.OK) {
    return;
  }

  forgetLedgerEntry(target.key, target.level, view);
  repaint(view);

  ui.alert('Unlinked', 'Row ' + target.row + ' will create a new ' + target.level + ' on its next push to ' + labelOf(view) + '.', ui.ButtonSet.OK);
}

// ------------------------------------------------------------ talking to WP

function call(path, cfg, payload) {
  var url = cfg.url.replace(/\/+$/, '') + '/wp-json/ldbc/v1/' + path;

  var response = UrlFetchApp.fetch(url, {
    method: 'post',
    contentType: 'application/json',
    headers: headers(cfg),
    payload: JSON.stringify(payload),
    muteHttpExceptions: true,
    followRedirects: true
  });

  return parse(response, url);
}

function get(path, cfg, query) {
  var url = cfg.url.replace(/\/+$/, '') + '/wp-json/ldbc/v1/' + path + (query ? '?' + query : '');

  var response = UrlFetchApp.fetch(url, {
    method: 'get',
    headers: headers(cfg),
    muteHttpExceptions: true,
    followRedirects: true
  });

  return parse(response, url);
}

function ping(cfg) {
  return get('ping', cfg, '');
}

/**
 * Ask the site what a pile of ids actually are, keyed by id.
 *
 * Asked in batches, because a sheet of a thousand pasted ids is one URL the
 * site would refuse to read.
 */
function lookupPosts(cfg, ids) {
  var found = {};
  var batch = 100;

  for (var at = 0; at < ids.length; at += batch) {
    var reply = get('lookup', cfg, 'ids=' + encodeURIComponent(ids.slice(at, at + batch).join(',')));

    (reply.posts || []).forEach(function (post) {
      found[String(post.id)] = post;
    });
  }

  return found;
}

function lookupPost(cfg, id) {
  return lookupPosts(cfg, [id])[String(id)] || { id: id, found: false };
}

/**
 * The key goes in its own header, never in Authorization.
 *
 * Authorization belongs to the web server on the staging sites, which stops
 * the request at the door before WordPress ever sees it. The two never share
 * a header, so a site can want both and get both.
 */
function headers(cfg) {
  var out = { 'X-LDBC-Key': cfg.key };

  if (cfg.user) {
    out.Authorization = 'Basic ' + Utilities.base64Encode(cfg.user + ':' + cfg.pass);
  }

  return out;
}

function parse(response, url) {
  var code = response.getResponseCode();
  var body = response.getContentText();
  var json;

  try {
    json = JSON.parse(body);
  } catch (e) {
    // A door we never got through looks nothing like a WordPress answer, so
    // say what it actually is rather than quoting a login page back.
    if (code === 401) {
      throw new Error(
        'The site asked for a username and password before letting us in.\n\n' +
          url +
          '\n\nThis is the web server, not WordPress. Open Settings and fill in the ' +
          'site password box for this site, or correct what is in it.'
      );
    }

    throw new Error(
      'The site replied with something that is not an answer we understand (HTTP ' +
        code +
        ').\n\n' +
        url +
        '\n\n' +
        body.slice(0, 400)
    );
  }

  if (code >= 400) {
    // WordPress is answering, so the address is right and we got through the
    // door. It is the plugin's half that is not there.
    if (json.code === 'rest_no_route') {
      throw new Error(
        'This site does not have the bulk create routes.\n\n' +
          url +
          '\n\nThe address and any site password are fine, WordPress answered. Either the ' +
          'plugin is not active here, or it is an older copy from before the spreadsheet ' +
          'routes existed, or something on the site hides the REST API from callers who ' +
          'are not logged in.\n\nOpen that address in a browser to tell which. If it says ' +
          'the key is not valid, the routes are there and only the key is wrong. If it ' +
          'says no route again, the plugin needs installing or updating on this site.'
      );
    }

    throw new Error(json.message || 'The site said no (HTTP ' + code + ').');
  }

  return json;
}

function testConnection() {
  var ui = SpreadsheetApp.getUi();
  var view = viewProfile();
  var cfg;

  try {
    cfg = config(view);
  } catch (e) {
    ui.alert(String(e.message || e));
    return;
  }

  try {
    var info = ping(cfg);

    ui.alert(
      'Connected to ' + labelOf(view),
      'Site: ' +
        info.site +
        '\n' +
        info.url +
        '\n\nKey: ' +
        (info.key_name || 'unnamed') +
        '\nLearnDash: ' +
        (info.learndash ? 'yes' : 'NOT FOUND') +
        '\nQuestion types plugin: ' +
        (info.question_types ? 'yes' : 'NOT FOUND'),
      ui.ButtonSet.OK
    );
  } catch (e) {
    ui.alert('Could not connect', String(e.message || e), ui.ButtonSet.OK);
  }
}

// -------------------------------------------------------------- the results

function showReport(report, cfg, sheet, wrote, recorded) {
  var template = HtmlService.createTemplateFromFile('Results');
  template.report = report;
  template.cfg = { profile: cfg.label, host: hostOf(cfg.url) };
  template.sheetName = sheet.name;
  template.wrote = wrote;
  template.written = recorded;

  SpreadsheetApp.getUi().showModalDialog(
    template.evaluate().setWidth(620).setHeight(520),
    wrote ? 'Push result' : 'Check result'
  );
}

// ----------------------------------------------------------------- settings

function showSetup() {
  var template = HtmlService.createTemplateFromFile('Setup');
  template.saved = settingsForForm();

  SpreadsheetApp.getUi().showModalDialog(template.evaluate().setWidth(560).setHeight(680), 'LearnDash settings');
}

function settings() {
  var store = PropertiesService.getDocumentProperties();

  var saved = {
    profile: profileOr(store.getProperty('profile'), 'dev'),
    detachMissing: store.getProperty('detach_missing') === 'yes'
  };

  PROFILES.forEach(function (profile) {
    saved[profile.key] = {
      label: profile.label,
      url: store.getProperty(profile.key + '_url') || '',
      key: store.getProperty(profile.key + '_key') || '',
      user: store.getProperty(profile.key + '_user') || '',
      pass: store.getProperty(profile.key + '_pass') || ''
    };
  });

  return saved;
}

function profileOr(value, fallback) {
  var known = PROFILES.some(function (profile) {
    return profile.key === value;
  });

  return known ? value : fallback;
}

function labelOf(profileKey) {
  var match = PROFILES.filter(function (profile) {
    return profile.key === profileKey;
  })[0];

  return match ? match.label : String(profileKey);
}

/** The settings screen never gets to see a saved secret, only whether there is one. */
function settingsForForm() {
  var saved = settings();

  var form = {
    profile: saved.profile,
    detachMissing: saved.detachMissing,
    profiles: []
  };

  PROFILES.forEach(function (profile) {
    var site = saved[profile.key];
    form.profiles.push({
      key: profile.key,
      label: profile.label,
      url: site.url,
      hasKey: !!site.key,
      user: site.user,
      hasPass: !!site.pass
    });
  });

  return form;
}

function saveSettings(form) {
  var props = {
    profile: profileOr(form.profile, 'dev'),
    detach_missing: form.detachMissing ? 'yes' : 'no'
  };

  var sites = form.sites || {};

  PROFILES.forEach(function (profile) {
    var typed = sites[profile.key] || {};

    props[profile.key + '_url'] = String(typed.url || '').trim();
    props[profile.key + '_user'] = String(typed.user || '').trim();

    // An empty secret box means keep what is already saved, so opening
    // settings to change something else cannot quietly wipe it.
    ['key', 'pass'].forEach(function (secret) {
      var value = String(typed[secret] || '').trim();
      if (value) {
        props[profile.key + '_' + secret] = value;
      }
    });
  });

  PropertiesService.getDocumentProperties().setProperties(props);

  return settingsForForm();
}

/** `what` is 'key' for the spreadsheet key, 'pass' for the site password. */
function forgetSecret(profile, what) {
  PropertiesService.getDocumentProperties().deleteProperty(
    profileOr(profile, 'dev') + '_' + (what === 'pass' ? 'pass' : 'key')
  );

  return settingsForForm();
}

function config(profileKey) {
  var saved = settings();
  var key = profileOr(profileKey, saved.profile);
  var active = saved[key];

  if (!active.url || !active.key) {
    throw new Error(
      'The ' +
        active.label +
        ' site is not set up yet.\n\nUse LearnDash then Settings to add its address and key.'
    );
  }

  return {
    profile: key,
    label: active.label,
    url: active.url,
    key: active.key,
    user: active.user,
    pass: active.pass,
    detachMissing: saved.detachMissing
  };
}

function hostOf(url) {
  return String(url || '')
    .replace(/^https?:\/\//, '')
    .replace(/\/.*$/, '');
}
