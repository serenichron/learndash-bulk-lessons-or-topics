# Pushing a sheet straight into LearnDash

This is the spreadsheet half. It adds a LearnDash menu to your spreadsheet
with two things in it, Check and Push. No downloading a CSV, no opening
WordPress, no copying ID numbers back by hand.

## Setting it up, once per spreadsheet

**1. Make a key in WordPress.**

Go to LearnDash, then Bulk Create/Update, and scroll to Spreadsheet keys.
Name the key after the spreadsheet it is for, and press Make a key.

Copy the key straight away. It is shown once and never again. If you lose
it, cancel that key and make another. Nothing breaks.

**2. Put the script in the spreadsheet.**

Open the spreadsheet. Extensions, then Apps Script.

Paste `Code.gs` over whatever is in the editor. Then add two HTML files,
using the plus button next to Files:

- one named `Setup`, holding `Setup.html`
- one named `Results`, holding `Results.html`

Apps Script adds the `.html` itself, so name them `Setup` and `Results`,
not `Setup.html`.

Save.

**3. Reload the spreadsheet.**

Close the tab and open it again. A LearnDash menu appears next to Help.
The first time you use it, Google asks whether the script may talk to your
site. It has to, so say yes.

**4. Fill in the settings.**

LearnDash, then Settings. Put in the address of the site and the key you
copied. There is room for three sites, dev staging, QA staging and
production, with a switch for which is in use. The menu always says which
one you are pointed at.

If a site pops up a username and password box in the browser before the
page even loads, put that pair in the site username and site password boxes
for it. Both staging sites do this; production does not. It is not your
WordPress login, it is the one the server asks for at the door.

Then use Test the connection. It should name the site back to you and
confirm that LearnDash and the question types plugin are both there.

## Using it

Open the sheet you want to send. It has to be an upload sheet, which means
its header row has a `quiz_id` or a `question_id` column. The script checks
that before it does anything, so pointing it at your notes is harmless.

**Check** reads the sheet and tells you what is wrong, row by row and
column by column. Nothing is sent to the site.

**Push** runs the same check first. If anything is wrong, it stops and shows
you the list, and nothing is written. If it is clean, it sends the sheet,
and then fills in the ID cells for you.

Only cells that said `CREATE` get filled in. A cell holding a number, or
`PREV`, is left exactly as you wrote it.

## What the ID cells mean

| You write | What happens |
| --- | --- |
| `CREATE` | Something new is made, and its ID is written back into this cell |
| `PREV` | Reuse whatever the row above resolved to |
| a number | Update the thing with that ID |
| empty | This row has nothing at this level |

`PREV` does not work on `question_id`. Every question needs its own row.

## Keeping a quiz matching the sheet

There is a setting called "Keep quizzes matching the sheet", off by default.

When it is on, a question that sits in a quiz on the site but is no longer
in your sheet gets taken out of the quiz. The question itself stays in
WordPress, so nothing is lost and you can put it back by hand.

Check always tells you which questions this would affect, whether the
setting is on or off.

## When something goes wrong

**"The dev staging site is not set up yet"** means the address or the key is
missing. Open Settings.

**"That key is not valid for this site"** usually means the key belongs to
one of the other sites. Check which one is selected. Each site needs its own
key, made on that site.

**"The site asked for a username and password before letting us in"** is the
web server, not WordPress. Fill in the site username and site password for
that site in Settings, or correct what is in them.

**"Too many failed attempts"** means ten bad keys in a row from your
connection. Wait fifteen minutes.

**The push times out** on a very large sheet. Sheets over 200 rows are sent
in pieces automatically, split between quizzes so no `PREV` chain is cut. If
it still times out, split the sheet itself.

Nothing here deletes a quiz. If you need that, it is still on the WordPress
admin page, behind the red button, where you have to type an ID by hand.
