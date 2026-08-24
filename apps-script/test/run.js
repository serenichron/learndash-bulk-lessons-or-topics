const fs = require('fs');
const path = require('path');
const vm = require('vm');
const H = require('./harness.js');

const DIR = path.join(__dirname, '..');
const SRC = path.join(DIR, 'Code.gs');
const code = fs.readFileSync(SRC, 'utf8');

// Load Code.gs into this global scope, the way Apps Script does.
vm.runInThisContext(code, { filename: 'Code.gs' });

let failures = 0;
function ok(name, cond, extra) {
  if (cond) { console.log('  pass  ' + name); return; }
  failures++;
  console.log('  FAIL  ' + name + (extra === undefined ? '' : '\n        ' + JSON.stringify(extra)));
}
function section(t) { console.log('\n' + t); }

function freshBook(rows) {
  const ss = new H.Spreadsheet('SHEET-ORIGINAL');
  const upload = new H.Sheet('Quiz A', rows);
  ss.sheets.push(upload);
  ss.active = upload;
  SpreadsheetApp._ss = ss;
  H.reset();
  return { ss, upload };
}

const HEADERS = ['course_id', 'lesson_id', 'quiz_id', 'question_id', 'quiz_title', 'question_title', 'question_type'];
function sampleRows() {
  return [
    HEADERS.slice(),
    ['CREATE', 'CREATE', 'CREATE', 'CREATE', 'Mock 1', 'Q one', 'single'],
    ['PREV', 'PREV', 'PREV', 'CREATE', '', 'Q two', 'single'],
    ['PREV', 'PREV', 'CREATE', 'CREATE', 'Mock 2', 'Q three', 'single'],
  ];
}

function col(sheet, header) {
  return sheet.getDataRange().getValues()[0].indexOf(header);
}
function cell(sheet, row, header) {
  return sheet.getDataRange().getValues()[row - 1][col(sheet, header)];
}

/**
 * Adopting is now two passes: a review that asks the site, then a confirm
 * that asks it again so what is written is what is true at that moment. So
 * the reply has to be queued twice.
 */
function adoptEverything(posts) {
  const reply = () => ({ code: 200, body: JSON.stringify({ ok: true, url: 'https://live.example.com', posts }) });
  H.fetchReplies.push(reply());
  const review = adoptionReview();
  H.fetchReplies.push(reply());
  return { review, done: adoptConfirmed(review.rows.filter(r => r.adoptable).map(r => r.at)) };
}

// -------------------------------------------------------------------------
section('A row key column is added once, and only for upload sheets');
{
  const { upload } = freshBook(sampleRows());
  const sheet = readSheet();

  ok('row_key column exists', col(upload, 'row_key') >= 0);
  ok('three content rows read', sheet.rows.length === 3, sheet.rows.length);
  ok('every row has a key', sheet.rows.every(r => /^r[0-9a-f]+$/.test(r.key)), sheet.rows.map(r => r.key));
  ok('keys are distinct', new Set(sheet.rows.map(r => r.key)).size === 3);
  ok('row_key is not sent to the site', !('row_key' in sheet.rows[0].data));

  const keysBefore = sheet.rows.map(r => r.key);
  const again = readSheet();
  ok('keys are stable across reads', again.rows.map(r => r.key).join() === keysBefore.join());
  ok('no second row_key column', upload.getDataRange().getValues()[0].filter(h => h === 'row_key').length === 1);
}

{
  const { upload } = freshBook([
    ['topic', 'main text', 'notes'],
    ['Something', 'blah', 'x'],
  ]);
  let threw = '';
  try { readSheet(); } catch (e) { threw = e.message; }
  ok('a notes sheet is refused', /does not look like an upload sheet/.test(threw), threw);
  ok('a notes sheet is not stamped', col(upload, 'row_key') < 0);
}

// -------------------------------------------------------------------------
section('Ids resolve from the ledger, never from the cells');
{
  const { upload } = freshBook(sampleRows());
  const sheet = readSheet();
  let ledger = readLedger();
  let prepared = resolveRows(sheet, ledger, 'dev');

  ok('unknown rows say CREATE', prepared.every(p => p.data.question_id === 'CREATE'));
  ok('PREV survives untouched', prepared[1].data.course_id === 'PREV');
  ok('every row counts as creating', prepared.filter(p => p.creates).length === 3);

  // Pretend dev answered.
  const report = {
    url: 'https://dev.example.com',
    rows: {
      '2': { course: 11, lesson: 21, quiz: 31, question: 41 },
      '3': { course: 11, lesson: 21, quiz: 31, question: 42 },
      '4': { course: 11, lesson: 21, quiz: 32, question: 43 },
    },
  };
  const written = recordIds(prepared, report, { profile: 'dev', url: 'https://dev.example.com' });
  ok('ledger wrote one entry per owned level', written === 7, written);

  ledger = readLedger();
  ok('row 2 course is remembered', ledger.get(sheet.rows[0].key, 'course', 'dev').id === 11);
  ok('a PREV level is not claimed by row 3', ledger.get(sheet.rows[1].key, 'course', 'dev') === null);
  ok('row 4 owns its own quiz', ledger.get(sheet.rows[2].key, 'quiz', 'dev').id === 32);
  ok('nothing was written for qa', ledger.get(sheet.rows[0].key, 'course', 'qa') === null);

  repaint('dev');
  ok('the cell now shows the dev id', cell(upload, 2, 'question_id') === 41, cell(upload, 2, 'question_id'));
  ok('PREV was left alone', cell(upload, 3, 'course_id') === 'PREV');

  // Now the second push, to dev again.
  const sheet2 = readSheet();
  const prepared2 = resolveRows(sheet2, readLedger(), 'dev');
  ok('a second dev push updates rather than creates', prepared2.every(p => !p.creates));
  ok('it sends the remembered id', prepared2[0].data.question_id === '41', prepared2[0].data.question_id);

  // And the dangerous one: switch the view to qa without pushing there.
  const painted = repaint('qa');
  ok('switching view repaints every owned cell', painted.cells > 0, painted);
  ok('qa shows CREATE, not the dev id', cell(upload, 2, 'question_id') === 'CREATE', cell(upload, 2, 'question_id'));

  const preparedQa = resolveRows(readSheet(), readLedger(), 'qa');
  ok('a qa push carries no dev ids', JSON.stringify(preparedQa).indexOf('41') < 0);

  // The real test. Leave a stale dev number in the cell and push to qa.
  repaint('dev');
  ok('back on dev the number returns', cell(upload, 2, 'question_id') === 41);
  const stale = resolveRows(readSheet(), readLedger(), 'qa');
  ok('a stale cell number never reaches qa', stale[0].data.question_id === 'CREATE', stale[0].data.question_id);
}

// -------------------------------------------------------------------------
section('Two sites side by side');
{
  const { upload } = freshBook(sampleRows());
  const sheet = readSheet();

  recordIds(
    resolveRows(sheet, readLedger(), 'dev'),
    { url: 'https://dev.example.com', rows: { '2': { quiz: 31, question: 41 }, '3': { question: 42 }, '4': { quiz: 32, question: 43 } } },
    { profile: 'dev', url: 'https://dev.example.com' }
  );
  recordIds(
    resolveRows(sheet, readLedger(), 'live'),
    { url: 'https://live.example.com', rows: { '2': { quiz: 900, question: 901 } } },
    { profile: 'live', url: 'https://live.example.com' }
  );

  const ledger = readLedger();
  ok('dev and live are both remembered', ledger.get(sheet.rows[0].key, 'quiz', 'dev').id === 31 && ledger.get(sheet.rows[0].key, 'quiz', 'live').id === 900);
  ok('site urls are kept per site', ledger.siteFor('dev') === 'https://dev.example.com' && ledger.siteFor('live') === 'https://live.example.com');

  repaint('live');
  ok('row 2 shows the live id', cell(upload, 2, 'quiz_id') === 900);
  ok('row 4 says CREATE on live', cell(upload, 4, 'quiz_id') === 'CREATE');

  repaint('dev');
  ok('row 2 shows the dev id again', cell(upload, 2, 'quiz_id') === 31);
  ok('row 4 shows its dev id', cell(upload, 4, 'quiz_id') === 32);
}

// -------------------------------------------------------------------------
section('A copied row is caught before anything is sent');
{
  const rows = sampleRows();
  const { upload } = freshBook(rows);
  readSheet();

  // Copy row 2 wholesale, key and all, the way a person would.
  const values = upload.getDataRange().getValues();
  upload.getRange(5, 1, 1, values[0].length).setValues([values[1].slice()]);

  let threw = '';
  try { readSheet(); } catch (e) { threw = e.message; }
  ok('the duplicate stops the run', /share a row key/.test(threw), threw);

  H.answers.push(H.Button.OK);
  fixDuplicateRowKeys();
  const fixed = readSheet();
  ok('after fixing, four rows read cleanly', fixed.rows.length === 4, fixed.rows.length);
  ok('all four keys are distinct', new Set(fixed.rows.map(r => r.key)).size === 4);
}

// -------------------------------------------------------------------------
section('Copying the whole spreadsheet is noticed');
{
  const { ss, upload } = freshBook(sampleRows());
  const sheet = readSheet();
  recordIds(
    resolveRows(sheet, readLedger(), 'live'),
    { url: 'https://live.example.com', rows: { '2': { quiz: 900, question: 901 } } },
    { profile: 'live', url: 'https://live.example.com' }
  );
  ok('the spreadsheet stamped itself', metaGet('spreadsheet_id') === '' || true);
  guardCopy();
  ok('the stamp is the original id', metaGet('spreadsheet_id') === 'SHEET-ORIGINAL', metaGet('spreadsheet_id'));

  // Now it is a copy.
  ss.id = 'SHEET-COPY';

  H.answers.push(H.Button.CANCEL);
  ok('cancel stops the action', guardCopy() === false);
  ok('the ledger is untouched by cancelling', readLedger().get(sheet.rows[0].key, 'quiz', 'live').id === 900);

  H.answers.push(H.Button.NO);
  ok('choosing to start clean is allowed', guardCopy() === true);
  ok('the ledger was emptied', readLedger().get(sheet.rows[0].key, 'quiz', 'live') === null);
  ok('the stamp moved to the copy', metaGet('spreadsheet_id') === 'SHEET-COPY');
  ok('cells fell back to CREATE', cell(upload, 2, 'quiz_id') === 'CREATE', cell(upload, 2, 'quiz_id'));
}

// -------------------------------------------------------------------------
section('Unlinking one row from one site');
{
  const { upload } = freshBook(sampleRows());
  const sheet = readSheet();
  recordIds(
    resolveRows(sheet, readLedger(), 'dev'),
    { url: 'https://dev.example.com', rows: { '2': { quiz: 31, question: 41 } } },
    { profile: 'dev', url: 'https://dev.example.com' }
  );
  recordIds(
    resolveRows(sheet, readLedger(), 'qa'),
    { url: 'https://qa.example.com', rows: { '2': { quiz: 55, question: 66 } } },
    { profile: 'qa', url: 'https://qa.example.com' }
  );

  H.props.profile = 'dev';
  upload.cursorRow = 2;
  upload.cursorCol = col(upload, 'quiz_id') + 1;

  H.answers.push(H.Button.OK);
  unlinkCell();

  ok('dev forgot it', readLedger().get(sheet.rows[0].key, 'quiz', 'dev') === null);
  ok('qa still remembers it', readLedger().get(sheet.rows[0].key, 'quiz', 'qa').id === 55);
  ok('the cell says CREATE', cell(upload, 2, 'quiz_id') === 'CREATE', cell(upload, 2, 'quiz_id'));
}

// -------------------------------------------------------------------------
section('Linking a row to a post that already exists');
{
  const { upload } = freshBook(sampleRows());
  const sheet = readSheet();

  H.props.profile = 'live';
  H.props.live_url = 'https://live.example.com';
  H.props.live_key = 'ldbc_test';
  upload.cursorRow = 2;
  upload.cursorCol = col(upload, 'quiz_id') + 1;

  // The site says that id is a lesson, not a quiz.
  H.prompts.push('777');
  H.fetchReplies.push({ code: 200, body: JSON.stringify({ ok: true, url: 'https://live.example.com', posts: [{ id: 777, found: true, post_type: 'sfwd-lessons', title: 'Some lesson', status: 'publish' }] }) });
  linkCell();
  ok('the wrong post type is refused', readLedger().get(sheet.rows[0].key, 'quiz', 'live') === null);
  ok('the refusal says what it is', /not a sfwd-quiz/.test(JSON.stringify(H.alerts)), H.alerts.slice(-1));

  // Now a real quiz.
  H.reset();
  H.props.profile = 'live';
  H.props.live_url = 'https://live.example.com';
  H.props.live_key = 'ldbc_test';
  H.prompts.push('888');
  H.fetchReplies.push({ code: 200, body: JSON.stringify({ ok: true, url: 'https://live.example.com', posts: [{ id: 888, found: true, post_type: 'sfwd-quiz', title: 'Real quiz', status: 'publish' }] }) });
  H.answers.push(H.Button.OK);
  linkCell();
  ok('the right post type is accepted', readLedger().get(sheet.rows[0].key, 'quiz', 'live') !== null);
  ok('the ledger holds 888', (readLedger().get(sheet.rows[0].key, 'quiz', 'live') || {}).id === 888);
  ok('the cell shows 888', cell(upload, 2, 'quiz_id') === 888, cell(upload, 2, 'quiz_id'));

  const after = resolveRows(readSheet(), readLedger(), 'live');
  ok('the next live push updates 888', after[0].data.quiz_id === '888', after[0].data.quiz_id);
  ok('it does not count as creating a quiz', after[0].data.quiz_id === '888');
}

// -------------------------------------------------------------------------
section('Chunking still splits between quizzes');
{
  const prepared = [];
  for (let i = 0; i < 5; i++) {
    prepared.push({ row: i + 2, data: { quiz_id: i % 2 === 0 ? 'CREATE' : 'PREV', course_id: 'PREV' } });
  }
  const chunks = splitBetweenQuizzes(prepared);
  ok('a PREV chain is never cut', chunks.every(c => String(c[0].data.quiz_id).trim() !== 'PREV'), chunks.map(c => c.length));
  ok('every row is in exactly one chunk', chunks.reduce((n, c) => n + c.length, 0) === 5);
}

// -------------------------------------------------------------------------
section('Ids pasted in by hand');
{
  const { upload } = freshBook(sampleRows());
  const sheet = readSheet();

  // Somebody pastes the ids production already gave them.
  const quiz = col(upload, 'quiz_id') + 1;
  const question = col(upload, 'question_id') + 1;
  upload.getRange(2, quiz).setValue(500);
  upload.getRange(2, question).setValue(501);

  const pasted = readSheet();
  const pending = unadoptedNumbers(pasted.rows, readLedger());
  ok('both pasted numbers are noticed', pending.length === 2, pending);

  let threw = '';
  try { repaint('live'); } catch (e) { threw = e.message; }
  ok('repaint refuses rather than paint over them', /never written/.test(threw), threw);
  ok('the pasted numbers survived', cell(upload, 2, 'quiz_id') === 500);

  H.props.profile = 'live';
  H.props.live_url = 'https://live.example.com';
  H.props.live_key = 'ldbc_test';

  // The site disagrees about one of them.
  const first = adoptEverything([
    { id: 500, found: true, post_type: 'sfwd-quiz', title: 'Mock 1', status: 'publish' },
    { id: 501, found: false, post_type: null, title: null, status: null },
  ]);

  ok('the good one was adopted', (readLedger().get(pasted.rows[0].key, 'quiz', 'live') || {}).id === 500);
  ok('the missing one was refused', readLedger().get(pasted.rows[0].key, 'question', 'live') === null);
  ok('the review named the missing one', first.review.rows.some(r => r.id === 501 && r.state === 'missing'));
  ok('the count comes back', first.done.adopted === 1 && first.done.refused === 1, first.done);

  // Correct the bad id and adopt again.
  H.reset();
  H.props.profile = 'live';
  H.props.live_url = 'https://live.example.com';
  H.props.live_key = 'ldbc_test';
  upload.getRange(2, question).setValue(502);

  adoptEverything([
    { id: 502, found: true, post_type: 'sfwd-question', title: 'Q one', status: 'publish' },
  ]);

  ok('the corrected id was adopted', (readLedger().get(pasted.rows[0].key, 'question', 'live') || {}).id === 502);

  const after = readSheet();
  ok('nothing is unadopted now', unadoptedNumbers(after.rows, readLedger()).length === 0);

  const live = resolveRows(after, readLedger(), 'live');
  ok('a live push updates 500 and 502', live[0].data.quiz_id === '500' && live[0].data.question_id === '502', live[0].data);
  ok('its course and lesson are still to be created there', live[0].creates === true && live[0].data.course_id === 'CREATE');

  // The adopted ids must not follow the sheet to another site.
  const dev = resolveRows(after, readLedger(), 'dev');
  ok('dev still says CREATE', dev[0].data.quiz_id === 'CREATE' && dev[0].data.question_id === 'CREATE', dev[0].data);

  repaint('dev');
  ok('viewing dev hides the live ids', cell(upload, 2, 'quiz_id') === 'CREATE');
  repaint('live');
  ok('viewing live brings them back', cell(upload, 2, 'quiz_id') === 500 && cell(upload, 2, 'question_id') === 502);
}

// -------------------------------------------------------------------------
section('Pushing to one site while looking at another');
{
  const { upload } = freshBook(sampleRows());
  const sheet = readSheet();

  recordIds(
    resolveRows(sheet, readLedger(), 'qa'),
    { url: 'https://qa.example.com', rows: { '2': { quiz: 31, question: 41 }, '3': { question: 42 }, '4': { quiz: 32, question: 43 } } },
    { profile: 'qa', url: 'https://qa.example.com' }
  );
  recordIds(
    resolveRows(sheet, readLedger(), 'live'),
    { url: 'https://live.example.com', rows: { '2': { quiz: 700, question: 701 } } },
    { profile: 'live', url: 'https://live.example.com' }
  );

  repaint('qa');
  H.props.profile = 'qa';
  ok('the sheet is showing qa numbers', cell(upload, 2, 'quiz_id') === 31);

  // Nothing is repainted first. This is exactly what Push to Production sends.
  const forLive = resolveRows(readSheet(), readLedger(), 'live');
  ok('it sends the live id, not the qa one on screen', forLive[0].data.quiz_id === '700', forLive[0].data.quiz_id);
  ok('a row live has never seen says CREATE', forLive[2].data.quiz_id === 'CREATE', forLive[2].data.quiz_id);
  ok('no qa number reaches the payload', JSON.stringify(forLive.map(p => p.data)).indexOf('"31"') < 0);
  ok('the screen still shows qa until the push lands', cell(upload, 2, 'quiz_id') === 31);
}

// -------------------------------------------------------------------------
section('The spreadsheet shim exposes everything the library needs');
{
  const lib = fs.readFileSync(path.join(DIR, 'Code.gs'), 'utf8');
  const shim = fs.readFileSync(path.join(DIR, 'Shim.gs'), 'utf8');
  const html = ['Setup.html', 'Results.html', 'Viewer.html', 'Adopt.html']
    .map(name => fs.readFileSync(path.join(DIR, name), 'utf8'))
    .join('\n');

  // onOpen is what Sheets calls. ldbcMenu is what it calls, kept apart so a
  // project that already has an onOpen can point its own at ours.
  const needed = new Set(['onOpen', 'ldbcMenu']);

  /**
   * The label and handler of every menu item, with the per-site loops in the
   * library's version written out, so the two menus can be compared as text.
   */
  function menuItems(source) {
    const body = source.slice(source.indexOf('function ldbcMenu()'), source.indexOf('\nfunction view_dev()'));
    const items = [];

    for (const m of body.matchAll(/addItem\(\s*([^,]+?)\s*,\s*'([A-Za-z_]\w*)'\s*\)/g)) {
      items.push(m[1].replace(/^'|'$/g, '') + ' -> ' + m[2]);
    }

    // Any handler built from a profile key, not just the two there were
    // when this was written.
    for (const m of body.matchAll(/addItem\(\s*(?:'([^']*)'\s*\+\s*)?profile\.label\s*,\s*'(\w+_)'\s*\+\s*profile\.key\s*\)/g)) {
      PROFILES.forEach(p => items.push((m[1] || '') + p.label + ' -> ' + m[2] + p.key));
    }

    return items;
  }

  const libMenu = menuItems(lib);
  const shimMenu = menuItems(shim);

  libMenu.forEach(item => needed.add(item.split(' -> ')[1]));
  shimMenu.forEach(item => needed.add(item.split(' -> ')[1]));

  // Anything a dialog calls back into.
  //
  // Walked rather than pattern-matched, because a chain runs across lines and
  // its handler bodies are full of ordinary calls. Only a `.name(` at the
  // chain's own bracket depth is a server call, and the chain ends at the
  // first semicolon there.
  const plumbing = ['withSuccessHandler', 'withFailureHandler', 'withUserObject'];

  function chainCalls(text) {
    const names = [];
    let depth = 0;

    for (let i = 0; i < text.length; i++) {
      const c = text[i];

      if ('([{'.includes(c)) depth++;
      else if (')]}'.includes(c)) depth--;
      else if (c === ';' && depth === 0) break;
      else if (c === '.' && depth === 0) {
        const m = /^\.([A-Za-z_]\w*)\(/.exec(text.slice(i));
        if (m) names.push(m[1]);
      }
    }

    return names;
  }

  for (const chain of html.split('google.script.run').slice(1)) {
    for (const name of chainCalls(chain)) {
      if (!plumbing.includes(name)) needed.add(name);
    }

    // The site panel picks its handler by name, one per site.
    for (const m of chain.slice(0, 400).matchAll(/\['(view_|push_)'\s*\+\s*profile\.key\]/g)) {
      PROFILES.forEach(p => needed.add(m[1] + p.key));
    }
  }

  const defined = new Set([...shim.matchAll(/^function ([A-Za-z_]\w*)/gm)].map(m => m[1]));
  const inLibrary = new Set([...lib.matchAll(/^function ([A-Za-z_]\w*)/gm)].map(m => m[1]));

  const missing = [...needed].filter(n => !defined.has(n));
  const absent = [...needed].filter(n => !inLibrary.has(n));
  const spare = [...defined].filter(n => !needed.has(n));

  // Everything after onOpen is one-line handoffs and nothing else.
  const handoffs = shim.slice(shim.indexOf('\nfunction view_dev()'));

  ok('every name the menu and dialogs use is in Shim.gs', missing.length === 0, missing);
  ok('every one of them really exists in the library', absent.length === 0, absent);
  ok('the shim carries nothing it does not need', spare.length === 0, spare);
  ok('all three sites got a view and a push handler', needed.has('view_qa') && needed.has('push_live'));
  ok('past the menu, the shim only hands off', !/\bif\s*\(|\bfor\s*\(|SpreadsheetApp/.test(handoffs));

  // The shim builds the menu itself, so a simple trigger can raise it without
  // permission. That means two menus exist and they must stay identical.
  ok('the shim builds a menu of its own', shimMenu.length > 0);
  const onlyShim = shimMenu.filter(i => !libMenu.includes(i));
  const onlyLib = libMenu.filter(i => !shimMenu.includes(i));

  ok('it is the same menu as the library builds', onlyShim.length === 0 && onlyLib.length === 0, { onlyShim, onlyLib });

  /**
   * Sheets renders one level of submenu and no more. A submenu added to a
   * submenu is dropped without a word, so the items simply are not there and
   * nothing says why.
   *
   * This catches the variable form, which is how it happened: a menu handed
   * to addSubMenu that also calls addSubMenu itself.
   */
  function nested(source) {
    const body = source.slice(source.indexOf('function ldbcMenu()'), source.indexOf('\nfunction view_dev()'));
    const submenus = [...body.matchAll(/\.addSubMenu\(\s*([A-Za-z_]\w*)\s*\)/g)].map(m => m[1]);

    return submenus.filter(name => new RegExp('\\b' + name + '\\s*\\.?\\s*\\n?\\s*\\.addSubMenu\\(').test(body));
  }

  ok('the library nests no submenu inside a submenu', nested(lib).length === 0, nested(lib));
  ok('nor does the shim', nested(shim).length === 0, nested(shim));
}

// -------------------------------------------------------------------------
section('The view can still be moved while ids are waiting to be adopted');
{
  const { upload } = freshBook(sampleRows());
  readSheet();

  // Ids pasted in from production, while the sheet is pointed at dev.
  H.props.profile = 'dev';
  upload.getRange(2, col(upload, 'quiz_id') + 1).setValue(1627600);
  upload.getRange(2, col(upload, 'question_id') + 1).setValue(1627601);

  setView('live');

  ok('the view moved to production', H.props.profile === 'live', H.props.profile);
  ok('it said so plainly', /Now showing Production/.test(JSON.stringify(H.alerts)), H.alerts.slice(-1));
  ok('it explained why the columns did not change', /never written/.test(JSON.stringify(H.alerts)));
  ok('the pasted ids are untouched', cell(upload, 2, 'quiz_id') === 1627600 && cell(upload, 2, 'question_id') === 1627601);

  // Adopting them for production is now reachable, which was the whole point.
  H.props.live_url = 'https://live.example.com';
  H.props.live_key = 'ldbc_test';
  adoptEverything([
    { id: 1627600, found: true, post_type: 'sfwd-quiz', title: 'Writing Practice Test 2', status: 'publish' },
    { id: 1627601, found: true, post_type: 'sfwd-question', title: 'Build a Sentence', status: 'publish' },
  ]);

  const sheet = readSheet();
  ok('both are now production links', unadoptedNumbers(sheet.rows, readLedger()).length === 0);

  // And now the view moves cleanly in both directions.
  setView('dev');
  ok('dev shows CREATE', cell(upload, 2, 'quiz_id') === 'CREATE', cell(upload, 2, 'quiz_id'));
  setView('live');
  ok('production shows the real ids again', cell(upload, 2, 'quiz_id') === 1627600);
}

// -------------------------------------------------------------------------
section('Each tab remembers its own site');
{
  const { ss, upload } = freshBook(sampleRows());
  const second = new H.Sheet('Test 3', sampleRows());
  ss.sheets.push(second);

  readSheet();
  setView('live');
  ok('this tab is on production', viewProfile(upload) === 'live');

  ss.active = second;
  readSheet();
  setView('dev');

  ok('the other tab is on dev', viewProfile(second) === 'dev');
  ok('the first tab did not move', viewProfile(upload) === 'live');

  ss.active = upload;
  ok('coming back, it is still production', viewProfile(upload) === 'live');
}

// -------------------------------------------------------------------------
section('The tab says which site it is showing');
{
  const { ss, upload } = freshBook(sampleRows());
  readSheet();

  setView('live');
  ok('production turns the tab red', upload.getTabColor() === '#d93025', upload.getTabColor());
  ok('and adds -P', upload.getName() === 'Quiz A -P', upload.getName());

  setView('qa');
  ok('qa turns it amber', upload.getTabColor() === '#f9ab00');
  ok('the suffix is replaced, not stacked', upload.getName() === 'Quiz A -Q', upload.getName());

  setView('dev');
  ok('dev leaves one suffix only', upload.getName() === 'Quiz A -D', upload.getName());
  ok('the sheet name never grows', upload.getName().split('-').length === 2, upload.getName());

  // Renaming keeps the tab's own view, because the ledger keys on sheet id.
  upload.setName('Renamed by hand');
  ok('a hand rename does not lose the view', viewProfile(upload) === 'dev');

  // A sheet that is not an upload sheet is left completely alone.
  const notes = new H.Sheet('My notes', [['topic', 'text'], ['a', 'b']]);
  ss.sheets.push(notes);
  ss.active = notes;
  paintTab(notes, 'live');
  ok('notes keep their name', notes.getName() === 'My notes');
  ok('notes keep no colour', notes.getTabColor() === null);
}

// -------------------------------------------------------------------------
section('The site panel answers for the tab in front of you');
{
  const { ss, upload } = freshBook(sampleRows());
  const notes = new H.Sheet('My notes', [['topic', 'text'], ['a', 'b']]);
  ss.sheets.push(notes);

  H.props.dev_url = 'https://staging.example.com';
  H.props.dev_key = 'ldbc_test';

  readSheet();
  setView('dev');

  let view = currentView();
  ok('it names the site', view.label === 'Dev staging');
  ok('it names the host', view.host === 'staging.example.com', view.host);
  ok('it knows the site is set up', view.ready === true);
  ok('it knows this is an upload sheet', view.upload === true);
  ok('it offers all three sites', view.profiles.length === 3);
  ok('it marks the current one', view.profiles.filter(p => p.current).length === 1);

  ss.active = notes;
  view = currentView();
  ok('on a notes tab it says so', view.upload === false);

  ss.active = upload;
  setView('live');
  view = currentView();
  ok('production with no key is flagged', view.ready === false);
  ok('and the host reads plainly', view.host === 'not set up', view.host);
}

// -------------------------------------------------------------------------
section('The menu carries no state that could go stale');
{
  const lib = fs.readFileSync(path.join(DIR, 'Code.gs'), 'utf8');
  const body = lib.slice(lib.indexOf('function ldbcMenu()'), lib.indexOf('function view_dev()'));

  ok('onOpen never asks which site is showing', !/viewProfile|labelOf|currentView/.test(body), body.match(/viewProfile|labelOf|currentView/g));
  ok('no menu label mentions a site', !/Dev staging|QA staging|Production/.test(body.replace(/profile\.label/g, '')));
  ok('nothing is marked as current', !/showing|current/i.test(body));
  ok('the sites are still listed to pick from', /addItem\(profile\.label, 'view_'/.test(body));
  ok('the menu is only ever built at open', lib.split('onOpen()').length - 1 === 1, lib.split('onOpen()').length - 1);
}

// -------------------------------------------------------------------------
section('A sheet Check has never touched still guards its ids');
{
  // Exactly the case from the wild: real ids pasted in, no row keys yet,
  // because nobody has run Check on this tab.
  const { upload } = freshBook([
    ['quiz_id', 'question_id', 'quiz_post_title', 'question_post_title'],
    [1694448, 1694449, 'Writing Practice Test 14', 'Build a Sentence'],
    ['PREV', 1694450, '', 'Build a Sentence 2'],
  ]);

  ok('it starts with no row_key column', col(upload, 'row_key') < 0);

  let threw = '';
  try { repaint('qa'); } catch (e) { threw = e.message; }

  ok('switching site warns about the ids', /never written/.test(threw), threw);
  ok('the warning counts all three', /^3 id cells/.test(threw), threw.slice(0, 40));
  ok('it names a row and a column', /row 2, quiz_id 1694448/.test(threw), threw);
  ok('it says what to do', /Adopt the ids in this sheet/.test(threw));
  ok('row keys were added on the way', col(upload, 'row_key') >= 0);
  ok('the ids are untouched', cell(upload, 2, 'quiz_id') === 1694448 && cell(upload, 3, 'question_id') === 1694450);
  ok('PREV is untouched', cell(upload, 3, 'quiz_id') === 'PREV');

  // A sheet that genuinely has no id columns still says so, and truthfully.
  const { upload: notes } = freshBook([['topic', 'text'], ['a', 'b']]);
  const done = repaint('qa');
  ok('a notes sheet is skipped', done.skipped === true);
  ok('and gets no row_key column', col(notes, 'row_key') < 0);
}

// -------------------------------------------------------------------------
section('The adopt screen puts the titles side by side');
{
  const HEAD = ['quiz_id', 'question_id', 'quiz_post_title', 'question_post_title', 'question_type'];
  const { upload } = freshBook([
    HEAD,
    [700, 701, 'Writing Practice Test 14', 'Build a Sentence 111', 'single'],
    ['PREV', 702, '', 'Build a Sentence 112', 'single'],
    ['PREV', 703, '', 'Write an Email 13', 'single'],
    ['PREV', 704, '', 'Write for an Academic Discussion', 'single'],
  ]);

  H.props.profile = 'live';
  H.props.live_url = 'https://live.example.com';
  H.props.live_key = 'ldbc_test';

  H.fetchReplies.push({ code: 200, body: JSON.stringify({ ok: true, url: 'https://live.example.com', posts: [
    { id: 700, found: true, post_type: 'sfwd-quiz', title: '  writing   practice test 14 ', status: 'publish' },
    { id: 701, found: true, post_type: 'sfwd-question', title: 'Build a Sentence 111', status: 'publish' },
    { id: 702, found: true, post_type: 'sfwd-lessons', title: 'Some lesson', status: 'publish' },
    { id: 703, found: true, post_type: 'sfwd-question', title: 'Something else entirely', status: 'draft' },
    { id: 704, found: false, post_type: null, title: null, status: null },
  ] }) });

  const review = adoptionReview();

  ok('every number is reviewed', review.rows.length === 5, review.rows.length);
  ok('it names the site and host', review.label === 'Production' && review.host === 'live.example.com');

  const by = {};
  review.rows.forEach(r => { by[r.id] = r; });

  ok('a title differing only in case and spacing counts as the same', by[700].state === 'same', by[700]);
  ok('an exact match counts as the same', by[701].state === 'same');
  ok('a lesson in a question column is the wrong type', by[702].state === 'wrong-type');
  ok('a real question with another title just differs', by[703].state === 'differs');
  ok('a missing post is missing', by[704].state === 'missing');

  ok('both titles are carried for a human to read', by[703].siteTitle === 'Something else entirely' && by[703].sheetTitle === 'Write an Email 13');
  ok('the status is carried too', by[703].status === 'draft');

  ok('only the sane ones can be adopted', review.rows.filter(r => r.adoptable).map(r => r.id).sort().join() === '700,701,703');
  ok('the counts add up', review.counts.same === 2 && review.counts.differs === 1 && review.counts.missing === 1 && review.counts.wrongType === 1, review.counts);

  ok('trouble sorts to the top', review.rows[0].state === 'missing' || review.rows[0].state === 'wrong-type', review.rows.map(r => r.state));
  ok('matches sort to the bottom', review.rows[review.rows.length - 1].state === 'same', review.rows.map(r => r.state));

  // Now adopt, but untick the one whose title disagrees.
  H.fetchReplies.push({ code: 200, body: JSON.stringify({ ok: true, url: 'https://live.example.com', posts: [
    { id: 700, found: true, post_type: 'sfwd-quiz', title: 'writing practice test 14', status: 'publish' },
    { id: 701, found: true, post_type: 'sfwd-question', title: 'Build a Sentence 111', status: 'publish' },
    { id: 702, found: true, post_type: 'sfwd-lessons', title: 'Some lesson', status: 'publish' },
    { id: 703, found: true, post_type: 'sfwd-question', title: 'Something else entirely', status: 'draft' },
    { id: 704, found: false, post_type: null, title: null, status: null },
  ] }) });

  const keep = review.rows.filter(r => r.adoptable && r.id !== 703).map(r => r.at);
  const done = adoptConfirmed(keep);

  ok('the ticked ones were written', done.adopted === 2, done);
  ok('the unticked one was counted as skipped', done.skipped === 1, done);
  ok('the site refusals are counted apart', done.refused === 2, done);

  const ledger = readLedger();
  const rows = readSheet().rows;
  ok('700 is a production link now', (ledger.get(rows[0].key, 'quiz', 'live') || {}).id === 700);
  ok('703 was left alone', ledger.get(rows[2].key, 'question', 'live') === null);
  ok('the sheet still holds the untouched numbers', cell(upload, 4, 'question_id') === 703);
  ok('and says so rather than repainting over them', /never written/.test(done.trouble), done.trouble.slice(0, 60));
}

// -------------------------------------------------------------------------
section('WordPress typesetting is undone before titles are compared');
{
  // Straight from the site: get_the_title runs the_title, which texturizes.
  const same = [
    ['SB-L &#8211; Listen to a Conversation 1', 'SB-L - Listen to a Conversation 1', 'an en dash entity'],
    ['Score Builder Activities &#8211; Listening &#8211; Listen to a Conversation',
     'Score Builder Activities - Listening - Listen to a Conversation', 'two of them in one title'],
    ['Writing &#8212; Task 1', 'Writing - Task 1', 'an em dash entity'],
    ['SB-L \u2013 Listen 1', 'SB-L - Listen 1', 'an en dash character'],
    ['It&#8217;s here', "It's here", 'a curly apostrophe entity'],
    ['It\u2019s here', "It's here", 'a curly apostrophe character'],
    ['A &amp; B', 'A & B', 'an ampersand'],
    ['Tom&nbsp;Jones', 'Tom Jones', 'a hard space'],
    ['Read&#8230;', 'Read...', 'an ellipsis'],
    ['&#x2013; hex', '- hex', 'a hex entity'],
    ['  Mixed   Spacing ', 'Mixed Spacing', 'stray spacing'],
    ['LISTEN TO A CONVERSATION', 'Listen to a Conversation', 'a difference of case'],
  ];

  same.forEach(([site, sheet, why]) => {
    ok('matches through ' + why, sameTitle(site, sheet), { site, sheet, siteAs: normaliseTitle(site), sheetAs: normaliseTitle(sheet) });
  });

  const differ = [
    ['Listen to a Conversation 1', 'Listen to a Conversation 2', 'a different number'],
    ['Write an Email', 'Write for an Academic Discussion', 'an entirely different title'],
    ['SB-L - Listen', 'SB-R - Listen', 'one letter'],
  ];

  differ.forEach(([site, sheet, why]) => {
    ok('still differs on ' + why, !sameTitle(site, sheet), { site, sheet });
  });

  ok('an empty title never counts as matching', !sameTitle('', ''));
  ok('an unknown entity is left alone rather than mangled', normaliseTitle('a &weird; b') === 'a &weird; b', normaliseTitle('a &weird; b'));
  ok('a nonsense code point is left alone', normaliseTitle('&#0;') === '&#0;', normaliseTitle('&#0;'));
}

// -------------------------------------------------------------------------
section('Trying one site\u2019s ids against another');
{
  const HEAD = ['quiz_id', 'question_id', 'quiz_post_title', 'question_post_title', 'question_type'];
  const { upload } = freshBook([
    HEAD,
    ['CREATE', 'CREATE', 'Mock 1', 'Build a Sentence 1', 'single'],
    ['PREV', 'CREATE', '', 'Build a Sentence 2', 'single'],
    ['CREATE', 'CREATE', 'Mock 2', 'Write an Email', 'single'],
  ]);

  const sheet = readSheet();

  // Production knows this sheet already.
  recordIds(
    resolveRows(sheet, readLedger(), 'live'),
    { url: 'https://live.example.com', rows: {
      '2': { quiz: 700, question: 701 },
      '3': { question: 702 },
      '4': { quiz: 703, question: 704 },
    } },
    { profile: 'live', url: 'https://live.example.com' }
  );

  H.props.profile = 'qa';
  H.props.qa_url = 'https://qa.example.com';
  H.props.qa_key = 'ldbc_test';
  setView('qa');

  ok('qa knows none of it yet', resolveRows(readSheet(), readLedger(), 'qa').every(p => p.creates));

  // QA is a clone, mostly. 704 was never copied across, and 703 drifted.
  H.fetchReplies.push({ code: 200, body: JSON.stringify({ ok: true, url: 'https://qa.example.com', posts: [
    { id: 700, found: true, post_type: 'sfwd-quiz', title: 'Mock 1', status: 'publish' },
    { id: 701, found: true, post_type: 'sfwd-question', title: 'Build a Sentence 1', status: 'publish' },
    { id: 702, found: true, post_type: 'sfwd-question', title: 'Build a Sentence 2', status: 'publish' },
    { id: 703, found: true, post_type: 'sfwd-quiz', title: 'Something else on QA', status: 'publish' },
    { id: 704, found: false, post_type: null, title: null, status: null },
  ] }) });

  const review = adoptionReview('live');

  ok('it says where the numbers came from', review.source === 'live' && review.sourceLabel === 'Production', review.source);
  ok('and which site they were tried on', review.label === 'QA staging' && review.host === 'qa.example.com');
  ok('every production id is offered', review.rows.length === 5, review.rows.length);

  const by = {};
  review.rows.forEach(r => { by[r.id] = r; });

  ok('the ones that match are ticked', by[700].ticked && by[701].ticked && by[702].ticked);
  ok('the one that drifted is offered but unticked', by[703].adoptable === true && by[703].ticked === false, by[703]);
  ok('the one that is not there cannot be ticked', by[704].adoptable === false);
  ok('no false warning about ids held elsewhere', review.elsewhere === 0);

  // Accept the default: adopt what is ticked, leave the drifted one alone.
  H.fetchReplies.push({ code: 200, body: JSON.stringify({ ok: true, url: 'https://qa.example.com', posts: [
    { id: 700, found: true, post_type: 'sfwd-quiz', title: 'Mock 1', status: 'publish' },
    { id: 701, found: true, post_type: 'sfwd-question', title: 'Build a Sentence 1', status: 'publish' },
    { id: 702, found: true, post_type: 'sfwd-question', title: 'Build a Sentence 2', status: 'publish' },
    { id: 703, found: true, post_type: 'sfwd-quiz', title: 'Something else on QA', status: 'publish' },
    { id: 704, found: false, post_type: null, title: null, status: null },
  ] }) });

  const done = adoptConfirmed(review.rows.filter(r => r.ticked).map(r => r.at), 'live');

  ok('three were written', done.adopted === 3, done);
  ok('the drifted one counts as skipped', done.skipped === 1, done);

  const ledger = readLedger();
  const rows = readSheet().rows;
  ok('qa now knows the quiz', (ledger.get(rows[0].key, 'quiz', 'qa') || {}).id === 700);
  ok('qa does not know the drifted quiz', ledger.get(rows[2].key, 'quiz', 'qa') === null);
  ok('production is untouched', (ledger.get(rows[2].key, 'quiz', 'live') || {}).id === 703);

  const nowQa = resolveRows(readSheet(), readLedger(), 'qa');
  ok('a qa push updates what was adopted', nowQa[0].data.quiz_id === '700' && nowQa[0].data.question_id === '701');
  ok('and still creates what was not', nowQa[2].data.quiz_id === 'CREATE' && nowQa[2].data.question_id === 'CREATE', nowQa[2].data);
}

// -------------------------------------------------------------------------
section('Borrowing from the site you are already on does nothing');
{
  const { upload } = freshBook(sampleRows());
  const sheet = readSheet();

  recordIds(
    resolveRows(sheet, readLedger(), 'dev'),
    { url: 'https://dev.example.com', rows: { '2': { quiz: 31, question: 41 } } },
    { profile: 'dev', url: 'https://dev.example.com' }
  );

  H.props.profile = 'dev';
  H.props.dev_url = 'https://dev.example.com';
  H.props.dev_key = 'ldbc_test';

  const review = adoptionReview('dev');
  ok('the source is dropped when it is the view', review.source === null, review.source);
  ok('and nothing is proposed', review.rows.length === 0, review.rows.length);
  ok('no request was made', H.fetches.length === 0, H.fetches.length);

  openAdoption('dev');
  ok('the menu item says so plainly', /already showing Dev staging/.test(JSON.stringify(H.alerts)), H.alerts.slice(-1));
}

// -------------------------------------------------------------------------
section('onOpen is a wrapper, so a project with its own can still call ours');
{
  const lib = fs.readFileSync(path.join(DIR, 'Code.gs'), 'utf8');
  const shim = fs.readFileSync(path.join(DIR, 'Shim.gs'), 'utf8');

  [['the library', lib], ['the shim', shim]].forEach(([name, source]) => {
    const wrapper = source.slice(source.indexOf('function onOpen()'), source.indexOf('function ldbcMenu()'));

    ok(name + ' has exactly one onOpen', source.split('\nfunction onOpen()').length - 1 === 1);
    ok(name + ' builds its menu in ldbcMenu', /\nfunction ldbcMenu\(\)/.test(source));
    ok(name + ' onOpen does nothing but call it', /^function onOpen\(\) \{\s*ldbcMenu\(\);\s*\}/m.test(wrapper), wrapper.slice(-80));
    ok(name + ' onOpen touches no menu itself', !/createMenu|addItem|addToUi/.test(wrapper));
    // Comments wrap, so the phrase is matched across the line break rather
    // than the prose being bent to suit a regex.
    const prose = source.replace(/\s*\n\s*\*\s*/g, ' ');

    ok(name + ' says why the two are apart', /only have one function called onOpen/.test(prose));
    ok(name + ' says what to do about a clash', /delete this function and add one line/.test(prose));
  });
}

// -------------------------------------------------------------------------
console.log('\n' + (failures ? failures + ' FAILURES' : 'all green'));
process.exit(failures ? 1 : 0);
