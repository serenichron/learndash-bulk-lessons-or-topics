/**
 * A small fake of the bits of Apps Script this script actually touches, so
 * the ledger, the resolving and the repainting can be run rather than read.
 */

function makeGrid(rows) {
  return rows.map(function (r) { return r.slice(); });
}

var nextSheetId = 1000;

function Sheet(name, rows) {
  this.name = name;
  this.grid = makeGrid(rows || [[]]);
  this.notes = {};
  this.hidden = false;
  this.maxRows = 200;
  this.id = nextSheetId++;
  this.tabColor = null;
}

Sheet.prototype._cell = function (r, c) {
  while (this.grid.length < r) this.grid.push([]);
  var row = this.grid[r - 1];
  while (row.length < c) row.push('');
  return row;
};

Sheet.prototype.getName = function () { return this.name; };
Sheet.prototype.setName = function (n) { this.name = n; return this; };
Sheet.prototype.getSheetId = function () { return this.id; };
Sheet.prototype.setTabColor = function (c) { this.tabColor = c; return this; };
Sheet.prototype.getTabColor = function () { return this.tabColor; };
Sheet.prototype.getMaxRows = function () { return this.maxRows; };

Sheet.prototype.getLastRow = function () {
  var last = 0;
  for (var r = 0; r < this.grid.length; r++) {
    for (var c = 0; c < this.grid[r].length; c++) {
      if (String(this.grid[r][c] == null ? '' : this.grid[r][c]).trim() !== '') { last = r + 1; break; }
    }
  }
  return last;
};

Sheet.prototype.getLastColumn = function () {
  var last = 0;
  for (var r = 0; r < this.grid.length; r++) {
    for (var c = 0; c < this.grid[r].length; c++) {
      if (String(this.grid[r][c] == null ? '' : this.grid[r][c]).trim() !== '' && c + 1 > last) last = c + 1;
    }
  }
  return last;
};

Sheet.prototype.getRange = function (row, col, numRows, numCols) {
  return new Range(this, row, col, numRows === undefined ? 1 : numRows, numCols === undefined ? 1 : numCols);
};

Sheet.prototype.getDataRange = function () {
  return new Range(this, 1, 1, Math.max(this.getLastRow(), 1), Math.max(this.getLastColumn(), 1));
};

Sheet.prototype.getActiveCell = function () {
  return new Range(this, this.cursorRow || 1, this.cursorCol || 1, 1, 1);
};

Sheet.prototype.insertColumnAfter = function (col) {
  for (var r = 0; r < this.grid.length; r++) {
    var row = this.grid[r];
    while (row.length < col) row.push('');
    row.splice(col, 0, '');
  }
};

Sheet.prototype.setFrozenRows = function () { return this; };
Sheet.prototype.hideSheet = function () { this.hidden = true; return this; };

function Range(sheet, row, col, numRows, numCols) {
  this.sheet = sheet; this.row = row; this.col = col; this.numRows = numRows; this.numCols = numCols;
}

Range.prototype.getRow = function () { return this.row; };
Range.prototype.getColumn = function () { return this.col; };

Range.prototype.getValues = function () {
  var out = [];
  for (var r = 0; r < this.numRows; r++) {
    var line = [];
    for (var c = 0; c < this.numCols; c++) {
      var row = this.sheet._cell(this.row + r, this.col + c);
      var v = row[this.col + c - 1];
      line.push(v === undefined ? '' : v);
    }
    out.push(line);
  }
  return out;
};

Range.prototype.setValues = function (values) {
  if (values.length !== this.numRows) throw new Error('setValues rows ' + values.length + ' != range ' + this.numRows);
  for (var r = 0; r < values.length; r++) {
    if (values[r].length !== this.numCols) throw new Error('setValues cols mismatch');
    for (var c = 0; c < values[r].length; c++) {
      var row = this.sheet._cell(this.row + r, this.col + c);
      row[this.col + c - 1] = values[r][c];
    }
  }
  return this;
};

Range.prototype.setValue = function (value) { return this.setValues([[value]]); };
Range.prototype.clearContent = function () {
  var blank = [];
  for (var r = 0; r < this.numRows; r++) {
    var line = [];
    for (var c = 0; c < this.numCols; c++) line.push('');
    blank.push(line);
  }
  return this.setValues(blank);
};
Range.prototype.setFontWeight = function () { return this; };
Range.prototype.setFontColor = function () { return this; };
Range.prototype.setNote = function (n) { this.sheet.notes[this.row + ',' + this.col] = n; return this; };

function Spreadsheet(id) {
  this.id = id;
  this.sheets = [];
  this.active = null;
}

Spreadsheet.prototype.getId = function () { return this.id; };
Spreadsheet.prototype.getSheetByName = function (name) {
  return this.sheets.filter(function (s) { return s.name === name; })[0] || null;
};
Spreadsheet.prototype.insertSheet = function (name) {
  var sheet = new Sheet(name, [[]]);
  this.sheets.push(sheet);
  this.active = sheet;
  return sheet;
};
Spreadsheet.prototype.getActiveSheet = function () { return this.active; };
Spreadsheet.prototype.setActiveSheet = function (s) { this.active = s; return s; };

var alerts = [];
var answers = [];
var prompts = [];

var Button = { OK: 'OK', CANCEL: 'CANCEL', YES: 'YES', NO: 'NO' };

var ui = {
  Button: Button,
  ButtonSet: { OK: 'OK', OK_CANCEL: 'OK_CANCEL', YES_NO: 'YES_NO', YES_NO_CANCEL: 'YES_NO_CANCEL' },
  alert: function (a, b, c) {
    alerts.push(c === undefined ? { title: '', body: a } : { title: a, body: b });
    return answers.length ? answers.shift() : Button.OK;
  },
  prompt: function (a, b) {
    var reply = prompts.shift();
    return { getSelectedButton: function () { return reply ? Button.OK : Button.CANCEL; },
             getResponseText: function () { return reply || ''; } };
  },
  createMenu: function () {
    var m = { addItem: function () { return m; }, addSeparator: function () { return m; },
              addSubMenu: function () { return m; }, addToUi: function () {} };
    return m;
  },
  showModalDialog: function () {}
};

var props = {};

global.SpreadsheetApp = {
  _ss: null,
  getActive: function () { return this._ss; },
  getActiveSheet: function () { return this._ss.getActiveSheet(); },
  getUi: function () { return ui; }
};

global.PropertiesService = {
  getDocumentProperties: function () {
    return {
      getProperty: function (k) { return k in props ? props[k] : null; },
      setProperty: function (k, v) { props[k] = String(v); },
      setProperties: function (o) { Object.keys(o).forEach(function (k) { props[k] = String(o[k]); }); },
      deleteProperty: function (k) { delete props[k]; }
    };
  }
};

var uuidCounter = 0;
global.Utilities = {
  getUuid: function () {
    uuidCounter++;
    return ('00000000' + uuidCounter).slice(-8) + '-aaaa-bbbb-cccc-dddddddddddd';
  },
  base64Encode: function (s) { return Buffer.from(s).toString('base64'); }
};

global.HtmlService = { createTemplateFromFile: function () { return { evaluate: function () { return { setWidth: function () { return this; }, setHeight: function () { return this; } }; } }; } };

var fetches = [];
var fetchReplies = [];
global.UrlFetchApp = {
  fetch: function (url, opts) {
    fetches.push({ url: url, opts: opts });
    var reply = fetchReplies.shift() || { code: 200, body: '{"ok":true}' };
    return { getResponseCode: function () { return reply.code; }, getContentText: function () { return reply.body; } };
  }
};

module.exports = {
  Sheet: Sheet, Spreadsheet: Spreadsheet, ui: ui, Button: Button,
  props: props, alerts: alerts, answers: answers, prompts: prompts,
  fetches: fetches, fetchReplies: fetchReplies,
  reset: function () {
    alerts.length = 0; answers.length = 0; prompts.length = 0;
    fetches.length = 0; fetchReplies.length = 0;
    Object.keys(props).forEach(function (k) { delete props[k]; });
  }
};
