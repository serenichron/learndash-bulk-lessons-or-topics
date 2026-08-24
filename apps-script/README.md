# Pushing a sheet straight into LearnDash

This is the spreadsheet half. It adds a LearnDash menu with a Check, one Push
per site, and a site panel. No downloading a CSV, no opening WordPress, no
copying ID numbers back by hand.

Three sites sit side by side: Dev staging, QA staging and Production. The same
sheet can be pushed to all three, and the ids of one can never reach another.

## What a cell in an id column means

| Cell | Meaning | Who writes it |
| --- | --- | --- |
| a number | this row is on the site the tab is showing, a push updates it | the script |
| `CREATE` | it is not on that site yet, a push makes it | the script |
| `PREV` | same as the row above, on every site | you |
| blank | this row has nothing at this level | you |

Change which site a tab shows and only the numbers and the `CREATE`s move.
`PREV` and blank never move, because they mean the same thing on every site and
they are the only part of these columns a person writes.

Authoring a new row has not changed. You type `CREATE` in the levels the row
involves, and it stays `CREATE` on every site until a push turns it into a
number for that site.

## Which site a tab is showing

Each tab remembers its own site. Two tabs can sit side by side showing Dev and
Production, and neither disturbs the other.

Three things say which, and all three are always right:

**The tab colour.** Green for Dev staging, amber for QA staging, red for
Production.

**The tab name.** It gains ` -D`, ` -Q` or ` -P`. Only upload sheets are
renamed, and the suffix is replaced rather than stacked.

**The site panel**, under LearnDash. It sits beside the sheet, names the site
and the host in words, and follows you from tab to tab. It also has the three
site buttons, so you rarely need the menu.

The LearnDash menu deliberately says none of this. A menu is built when the
spreadsheet opens and cannot be rebuilt when you click a tab, so any site name
in it would be right at first and wrong from the second tab onwards. A label
that is usually true is worse than no label, because you stop checking it.

## How it remembers

Two hidden sheets appear the first time you use the menu.

`_ldbc_ids` is the ledger. One line per row, per level, per site, saying which
post that row became there. It is the truth, and the id columns you can see are
a view of it.

`_ldbc_meta` holds which spreadsheet the ledger belongs to, so a copy of the
spreadsheet is noticed rather than quietly editing the original's content. It
also holds which site each tab is showing.

Every content row gets a `row_key` column, written once and greyed out. That
key is how a row is recognised on each site. Row numbers move and titles get
corrected, so neither could do that job. Do not edit it, and do not copy it
between rows.

A push sends the ledger's ids for the site it is pushing to. It pays no
attention to the numbers in the cells, which is what stops a QA id from landing
on production. Pushing to Production while a tab shows QA is safe: the push uses
Production's ids, then switches the tab to Production so you see what you just
touched.

## Setting it up

Two ways. If you have more than one or two spreadsheets, use the library. A fix
then happens in one place instead of in every spreadsheet.

### The library way, for many spreadsheets

**1. Make the library.**

Go to script.google.com and press New project. Name it something like
LearnDash bulk push.

It has to be a standalone project, made from that page. Do not use the script
that Apps Script creates inside a spreadsheet. A script bound to a sheet belongs
to that sheet: move or delete the sheet and every spreadsheet using the library
breaks, and anyone who needs the library needs access to that sheet as well.

It needs five files:

- paste `Code.gs` over the editor's own Code.gs
- press the plus next to Files, choose HTML, name it `Setup`, paste `Setup.html` into it
- again, choose HTML, name it `Results`, paste `Results.html` into it
- again, choose HTML, name it `Viewer`, paste `Viewer.html` into it
- again, choose HTML, name it `Adopt`, paste `Adopt.html` into it

Apps Script adds the `.html` itself, so name them `Setup`, `Results`, `Viewer`
and `Adopt`, not `Setup.html`. The names are case sensitive.

Save.

**2. Declare the permissions.**

Open Project Settings and tick "Show appsscript.json manifest file". A manifest
appears under Files. It will not have an `oauthScopes` list in it, and running
the code will not put one there. Apps Script works permissions out as it goes
and leaves the manifest alone, so this list is something you write yourself.

Paste the `oauthScopes` block from `spreadsheet-appsscript.json` in this folder
into it. Keep the `timeZone` the manifest already has.

Three permissions, one per thing the script touches. `spreadsheets` for reading
and writing the sheet, `script.container.ui` for the menu, the dialogs and the
panel, `script.external_request` for talking to your WordPress site. If a
permission error later names something not on this list, add what it names.

Then pick `hostOf` from the function dropdown and press Run. It does nothing
useful, and that is the point. It makes the permission screen appear so you can
approve it once, here, rather than in the middle of a push.

**3. Publish it as a library.**

Deploy, then New deployment. Choose Library as the type, write a description,
and press Deploy. That makes version 1.

Then Project Settings, and copy the Script ID. It is the long string starting
with `1`, not the `AKfycb...` deployment id.

**4. Share it.**

Everyone who uses the spreadsheets needs access to the library project, or their
menu breaks the first time they use it. Share it from Drive.

Viewer access is enough to run a numbered version. Editor access is required to
run HEAD, which is what Development mode means, because HEAD is unreleased code.

Sharing it is safe. The site addresses and the keys live in each spreadsheet's
own document properties, not in the code.

**5. In each spreadsheet.**

Extensions, then Apps Script. Paste `Shim.gs` over the editor's Code.gs. That
file builds the menu and then hands every click over to the library.

It builds the menu itself rather than asking the library to, and that is not
tidiness. A menu is raised by `onOpen`, which is a simple trigger. Simple
triggers run for anyone who opens the spreadsheet without asking permission,
and in exchange may only touch things that need none. Reaching into a library
is not one of those things. An `onOpen` that called the library would build
nothing at all for a colleague who has not yet authorised the script, and the
menu they would have used to authorise it is the very thing that failed to
appear.

So the menu is a fixed list in the shim, which it can be because it names no
site. Change the menu and all twelve shims need repasting. That is the price
of everyone seeing it.

Press the plus next to Libraries, paste the Script ID, press Look up. Set the
identifier to `LDBC`, exactly that, because the shim calls it by name. It will
have pre-filled the project name, so type over it. Pick a version, and Add.

Now the step that catches people. In Project Settings, tick "Show
appsscript.json manifest file", open it, and paste in the same `oauthScopes`
block you put in the library. Paste the array only. Leave `timeZone` and
`dependencies` as you find them, because `dependencies` now holds the library
entry the last step wrote.

Apps Script decides what permissions to ask for by reading the code in front of
it. The code in front of it is eighty lines of `LDBC.something()`. It cannot see
that the library calls out to your WordPress site, so it never asks for
permission to, and the first push fails with a permissions error that names
nothing useful. `spreadsheet-appsscript.json` in this folder is what that file
should end up looking like.

Save, then reload the spreadsheet.

**6. Releasing a change.**

Edit the library and save. On Development mode that is the whole job.

On a numbered version, go to Deploy, then Manage deployments, edit the library
deployment and give it a new version. Each spreadsheet then picks up the new
number in its Libraries panel.

Numbered versions mean each spreadsheet stays where you put it, and a release
means changing the number in every one of them. Development mode means every
spreadsheet runs the latest code the moment you save, and nobody touches them,
but everyone using those spreadsheets needs editor access to the library.

A reasonable middle: your own spreadsheet on Development mode while you are
working on the script, everyone else's pinned to a version.

### The paste-it-in way, for one spreadsheet

Open the spreadsheet. Extensions, then Apps Script. Paste `Code.gs` over
whatever is there, add `Setup`, `Results`, `Viewer` and `Adopt` as HTML files as in step
1, save, reload the spreadsheet.

Ignore `Shim.gs` and `spreadsheet-appsscript.json`. Both exist only for the
library way. Apps Script can read this code directly, so it works out the
permissions on its own.

### Then, in every case

Make a key in WordPress. Go to LearnDash, then Bulk Create/Update, and scroll to
Spreadsheet keys. Name the key after the site it is for, and press Make a key.
Copy it straight away, because it is shown once and never again. If you lose it,
cancel that key and make another. Nothing breaks.

Open LearnDash, then Settings. Put in the address and key for each site you use.
There is room for a username and password too, for staging sites that ask for
one before the page loads.

Then use Test the connection. It should name the site back to you and confirm
that LearnDash and the question types plugin are both there.

The first time each person uses the menu, Google asks whether the script may
talk to your site. It has to, so say yes. Everyone who uses the spreadsheet
answers that once, for themselves. Nobody can answer it on their behalf.

## Using it

**Check this sheet** reads the sheet and tells you what is wrong, row by row and
column by column. It talks to the site this tab is showing. Nothing is written.

**Push to Dev staging**, **Push to QA staging**, **Push to Production** each say
where they are going, how many rows would be updated and how many created, and
then run the same check before writing a single row. Production asks twice.

**Where is this sheet?** answers from the ledger, without touching any site. It
says how much of the sheet is on each of the three, and how much is still to
create.

**Show the site panel** opens the panel beside the sheet.

**Change site** decides which site this tab shows, and which site Check talks
to. It is not where a push goes.

## Ids you already have

If content was uploaded before any of this existed, paste its ids into the id
columns, point the tab at the site they came from, and use LearnDash, Tools,
Adopt the ids in this sheet.

A review screen opens first. One line per number, showing the row, the level,
the id, what the site calls that post, what your sheet calls it, and its status.
Trouble sorts to the top and the ones that agree sit at the bottom.

Two things are checked for you, and only two. Does the post exist on that site,
and is it the right kind of thing. A number in `quiz_id` must be a quiz, not a
lesson. Anything else cannot be ticked and is not written.

The titles are the part you check. They are not a test the script can run,
because you adopt an id precisely so you can overwrite that content, so the
sheet holding a newer title than the site is the normal case rather than a
fault. What the two titles catch is the id that points at an entirely different
quiz, and those do not read as near-misses of each other.

Untick anything that looks wrong. Unticked rows keep their numbers in the sheet
and are simply not written.

Until they are adopted, nothing can be pushed or repainted. A number the script
did not write could mean two different things, and guessing wrong means either a
duplicate or the wrong post overwritten, so it stops and asks.

Adopting needs the `lookup` route, which arrived in plugin 1.5.0. Update the
plugin on a site before adopting ids there. Check and push work either way.

For a single row there is Tools, Link this cell to a post that already exists.
Put the cursor on the id cell first.

## When something goes wrong

**"The Dev staging site is not set up yet"** means the address or the key is
missing. Open Settings.

**"That key is not valid for this site"** usually means the key belongs to a
different site. Check which one you are pushing to.

**"Too many failed attempts"** means ten bad keys in a row from your connection.
Wait fifteen minutes.

**"There are ids here I did not write"** means somebody pasted or typed a
number. Adopt them, or replace them with `CREATE`.

**"Rows share a row key"** means a row was copied. Use Tools, Fix duplicate row
keys, which treats the copies as new content. If a copy was meant to be a
duplicate, delete it instead.

**"These ids did not come from this site"** means a site's address in Settings
now points somewhere else than it did when those ids were recorded. Put the
address back, or set that site up as a fresh one.

**"There is no post with id 412 on this site"** usually means somebody deleted
it in WordPress. Use Tools, Unlink this cell from this site, and the next push
makes a new one.

**"This is a copy of another spreadsheet"** means the ledger was built in a
different file. Keep the links if this copy is taking over. Throw them away if
it is a backup, and the next push creates everything fresh.

**The push times out** on a very large sheet. Sheets over 200 rows are sent in
pieces automatically, split between quizzes so no `PREV` chain is cut. If it
still times out, split the sheet itself.

Nothing here deletes a quiz. If you need that, it is still on the WordPress
admin page, behind the red button, where you have to type an id by hand.

## Keeping a quiz matching the sheet

There is a setting called "Keep quizzes matching the sheet", off by default.

When it is on, a question that sits in a quiz on the site but is no longer in
your sheet gets taken out of the quiz. The question itself stays in WordPress,
so nothing is lost and you can put it back by hand.

Check always tells you which questions this would affect, whether the setting is
on or off.

## Running the tests

The ledger, the resolving, the repainting and the tab painting run against a
small fake of Apps Script, so they can be checked without a spreadsheet.

```
npm run test:apps-script
```
