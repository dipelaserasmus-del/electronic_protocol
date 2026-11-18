# R5b: winmail.dat (Outlook) opened in the inbox

| | |
|---|---|
| Status | ✅ Done |
| Branch | `task/R5b-winmail` (on top of R5d) |

## What was wrong
Outlook / Exchange, with "Rich Text" on, send the real message inside one attachment, `winmail.dat` (TNEF format).
The inbox showed only that file:
- no text;
- no way to open or store the documents inside it;
- registering stored `winmail.dat`, which nobody can open without special software.

The owner's example (2026-11-16) is a **read receipt** whose whole content is a `winmail.dat`, with no text at all.
The details are only in Outlook's properties: report text, original subject, sender, recipients, when sent and when
read, Greek in code page 1253.

## What changed
- **`App\Support\Mail\Tnef`** reads a winmail.dat ([MS-OXTNEF]):
  - the message class and subject;
  - the text: plain, or **compressed RTF** (LZFu / [MS-OXRTFCP]) turned into text. Paragraphs, tabs, Greek as `\'hh`
    in the document's code page, `\uN`; hidden parts, fonts and pictures skipped;
  - the HTML body when there is one;
  - each **file inside**, with its long (Unicode) name and its type;
  - for **read / delivery receipts**: a readable text in Greek, e.g.

    ```
    Αναφορά: Ανάγνωση μηνύματος:
    Θέμα: <original subject>
    Από / Προς: …
    Στάλθηκε: 12/11/2026 07:25:52
    Αναγνώστηκε: 12/11/2026 11:35:56
    ```

    Times are shown in the settings' time zone.
  - 8-bit strings are read in the message's code page with `iconv` (mbstring has no Windows-1253).
  - Anything unreadable gives `null`, and the inbox then shows `winmail.dat` as a plain file, as before.
- **Inbox** (`MailContent`):
  - the files inside are shown and stored **instead of** `winmail.dat`, with type and size, under keys like `0-1`;
  - the text, HTML and subject inside are used when the e-mail itself has none;
  - a notice says the e-mail came as `winmail.dat`, or that it is a read/delivery receipt.
  - A receipt has nothing to store but the e-mail itself; its details become *Περίληψη*.
  - The stored `.eml` copy stays exactly as received.
- Cached summaries of older versions are not reused (`Inbox::SUMMARY_VERSION` 2).
- Synthetic e-mail **11-outlook-winmail-dat.eml**: text as compressed RTF in code page 1253, a PDF and a CSV with long
  Greek names. It is built by `tests/Support/TnefBuilder.php`, which the tests use too.

## How verified
- PHPUnit:
  - `TnefTest`:
    - the read receipt with the owner's structure, made of invented values;
    - attachments with Unicode / 8.3 names and types;
    - compressed RTF in both forms (LZFu with a dictionary reference + end marker, MELA): Greek, `\uN`, `\par`, `\tab`,
      escapes, hidden groups;
    - HTML and a Unicode subject from properties;
    - not TNEF / cut short → `null`.
  - `InboxTest`:
    - a winmail with two files: shown instead of it, downloadable with the Greek name, one ticked → stored as
      `…_0-0_Αίτηση άδειας.pdf`;
    - a read receipt: notice, its text, nothing to store, registered;
    - a broken winmail: a plain file as before.
- Browser (`inbox.spec.js`): the synthetic Outlook e-mail shows its text (from the RTF) and both files, not
  `winmail.dat`. The PDF opens as a PDF; after registering, the protocol has the PDF and the CSV.
- On this computer only (nothing committed): the owner's read receipt now reads as six lines (report, subject, from,
  to, sent, read) and registers without a file. All 12 private e-mails still register.
- Full suites: PHPUnit 442, browser 68/68.

  ![winmail.dat opened](img/R5b/r5b-winmail.png)

## Owner actions
None. When an Outlook e-mail arrives, its files appear in the inbox instead of `winmail.dat`.
