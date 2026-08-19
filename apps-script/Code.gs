/**
 * Push an upload sheet straight into LearnDash.
 *
 * Lives inside the spreadsheet. Adds a LearnDash menu with two things you
 * will use, Check and Push, and a settings screen for the site address and
 * the key.
 *
 * Three sites are kept side by side, dev staging, QA staging and production,
 * and the menu always says which one you are pointed at. Switching between
 * them is a deliberate choice you make in settings, never a side effect of
 * anything else.
 *
 * The two staging sites sit behind the browser's own username and password
 * box, so each site can carry one of those alongside its key.
 */

var LEVELS = ['group', 'course', 'lesson', 'topic', 'quiz', 'question'];

/** The sites you can point at, in the order the settings screen shows them. */
var PROFILES = [
  { key: 'dev', label: 'Dev staging' },
  { key: 'qa', label: 'QA staging' },
  { key: 'live', label: 'Production' }
];

/** Above this many rows the sheet is sent in pieces, split between quizzes. */
var CHUNK_ABOVE = 200;

// ---------------------------------------------------------------- the menu

function onOpen() {
  var target = 'not set up';
  try {
    var cfg = config();
    target = cfg.label + ', ' + hostOf(cfg.url);
  } catch (e) {}

  SpreadsheetApp.getUi()
    .createMenu('LearnDash')
    .addItem('Check this sheet', 'checkSheet')
    .addItem('Push this sheet', 'pushSheet')
    .addSeparator()
    .addItem('Settings (' + target + ')', 'showSetup')
    .addItem('Test the connection', 'testConnection')
    .addToUi();
}

// ------------------------------------------------------------- the actions

function checkSheet() {
  run(false);
}

function pushSheet() {
  run(true);
}

function run(write) {
  var ui = SpreadsheetApp.getUi();
  var cfg, sheet;

  try {
    cfg = config();
    sheet = readSheet();
  } catch (e) {
    ui.alert(String(e.message || e));
    return;
  }

  if (write) {
    var answer = ui.alert(
      'Push to ' + cfg.label + '?',
      sheet.rows.length +
        ' rows from "' +
        sheet.name +
        '" will be sent to ' +
        hostOf(cfg.url) +
        '.\n\nNothing is written unless the whole sheet passes its check first.',
      ui.ButtonSet.OK_CANCEL
    );
    if (answer !== ui.Button.OK) {
      return;
    }
  }

  var report;
  try {
    report = send(write ? 'push' : 'check', sheet, cfg);
  } catch (e) {
    ui.alert('Could not reach the site', String(e.message || e), ui.ButtonSet.OK);
    return;
  }

  var written = 0;
  if (write && report.ok) {
    written = writeBackIds(sheet, report.rows);
  }

  showReport(report, cfg, sheet, write, written);
}

/**
 * Send the sheet, in one go if it is small enough.
 *
 * A large sheet is split between quizzes rather than at an arbitrary row,
 * because PREV chains a row to the one above it and a split in the middle of
 * a chain would break it. Splitting is refused outright if a PREV on any
 * other level would cross a seam.
 */
function send(mode, sheet, cfg) {
  var payloadRows = sheet.rows.map(function (r) {
    return r.data;
  });

  if (payloadRows.length <= CHUNK_ABOVE) {
    return call(mode, cfg, {
      rows: payloadRows,
      first_row: sheet.rows[0].row,
      detach_missing: cfg.detachMissing
    });
  }

  var chunks = splitBetweenQuizzes(sheet.rows);
  var combined = null;

  for (var i = 0; i < chunks.length; i++) {
    var chunk = chunks[i];
    var part = call(mode, cfg, {
      rows: chunk.map(function (r) {
        return r.data;
      }),
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

function splitBetweenQuizzes(rows) {
  var carriers = ['course', 'lesson', 'topic'];
  var chunks = [];
  var current = [];

  rows.forEach(function (r) {
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

  var headers = values[0].map(function (h) {
    return String(h == null ? '' : h).trim();
  });

  // Any one id column is enough. A sheet of nothing but courses and lessons
  // is a perfectly good sheet.
  var idColumns = ['group_id', 'course_id', 'lesson_id', 'topic_id', 'quiz_id', 'question_id'];
  var hasIdColumn = idColumns.some(function (column) {
    return headers.indexOf(column) >= 0;
  });

  if (!hasIdColumn) {
    throw new Error(
      '"' +
        sheet.getName() +
        '" does not look like an upload sheet. Its header row has none of these columns:\n' +
        idColumns.join(', ') +
        '\n\nOpen the sheet you want to send, then try again.'
    );
  }

  var rows = [];

  for (var i = 1; i < values.length; i++) {
    var data = {};
    var empty = true;

    for (var c = 0; c < headers.length; c++) {
      if (!headers[c]) {
        continue;
      }
      var raw = values[i][c];
      var text = raw == null ? '' : String(raw);
      data[headers[c]] = text;
      if (text.trim() !== '') {
        empty = false;
      }
    }

    if (empty) {
      continue;
    }

    rows.push({ row: i + 1, data: data });
  }

  if (!rows.length) {
    throw new Error('"' + sheet.getName() + '" has a header row but no content.');
  }

  return { sheet: sheet, name: sheet.getName(), headers: headers, rows: rows };
}

/**
 * Fill in the ids of anything that was just made.
 *
 * Only cells that said CREATE are touched. A cell holding a number, or PREV,
 * is left exactly as you wrote it.
 */
function writeBackIds(sheet, responseRows) {
  if (!responseRows) {
    return 0;
  }

  var written = 0;

  sheet.rows.forEach(function (r) {
    var resolved = responseRows[String(r.row)] || responseRows[r.row];
    if (!resolved) {
      return;
    }

    LEVELS.forEach(function (level) {
      var column = sheet.headers.indexOf(level + '_id');
      if (column < 0) {
        return;
      }

      if (String(r.data[level + '_id'] || '').trim() !== 'CREATE') {
        return;
      }

      var id = resolved[level];
      if (!id) {
        return;
      }

      sheet.sheet.getRange(r.row, column + 1).setValue(id);
      written++;
    });
  });

  return written;
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
    throw new Error(json.message || 'The site said no (HTTP ' + code + ').');
  }

  return json;
}

function testConnection() {
  var ui = SpreadsheetApp.getUi();
  var cfg;

  try {
    cfg = config();
  } catch (e) {
    ui.alert(String(e.message || e));
    return;
  }

  try {
    var url = cfg.url.replace(/\/+$/, '') + '/wp-json/ldbc/v1/ping';
    var info = parse(
      UrlFetchApp.fetch(url, {
        method: 'get',
        headers: headers(cfg),
        muteHttpExceptions: true,
        followRedirects: true
      }),
      url
    );

    ui.alert(
      'Connected',
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

function showReport(report, cfg, sheet, wrote, written) {
  var template = HtmlService.createTemplateFromFile('Results');
  template.report = report;
  template.cfg = { profile: cfg.label, host: hostOf(cfg.url) };
  template.sheetName = sheet.name;
  template.wrote = wrote;
  template.written = written;

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

function config() {
  var saved = settings();
  var active = saved[saved.profile];

  if (!active.url || !active.key) {
    throw new Error(
      'The ' +
        active.label +
        ' site is not set up yet.\n\nUse LearnDash then Settings to add its address and key.'
    );
  }

  return {
    profile: saved.profile,
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
