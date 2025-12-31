# Process Journal Guide

This file explains how to maintain the development process journal for documentation purposes.

## Purpose

The process journal captures your development journey for the required README.md documentation. It tracks:
- What you tried and why
- What worked well
- What didn't work and how you solved it
- Key learnings along the way
- How AI tools assisted

## Journal Location

Create and maintain: `.claude/process-journal.md`

## Journal Template

```markdown
# Hebrew Dates Plugin - Development Journal

## Project Started: YYYY-MM-DD

### Why Hebrew Dates?
[Document your reasoning for choosing this widget idea]

---

## Development Log

### YYYY-MM-DD: Project Initialization

#### What I did
- Set up repository structure
- Created initial CLAUDE.md
- Planned widget requirements

#### What worked
- [Describe successful approaches]

#### What didn't work
- [Describe any issues encountered]

#### What I learned
- [Key insights gained]

#### AI Assistance
- Used Claude Code (Opus 4.5) for: [specific tasks]

---

### YYYY-MM-DD: Core Implementation

#### What I did
- [Description of work]

#### What worked
- [Successful approaches]

#### What didn't work
- [Failed approaches]

#### What I learned
- [Key insights]

#### AI Assistance
- [How AI helped]

---

## Summary of Struggles and Solutions

| Struggle | How I Solved It |
|----------|-----------------|
| [Issue 1] | [Solution 1] |
| [Issue 2] | [Solution 2] |

## Key Learnings

1. [Learning 1]
2. [Learning 2]
3. [Learning 3]

## AI Tools Used

- **Primary**: Claude Code with Opus 4.5 model
- **Tasks AI helped with**:
  - [Task 1]
  - [Task 2]
  - [Task 3]
```

## What to Log

### Always Log:
- Major decisions and their reasoning
- Errors encountered and how they were resolved
- Unexpected behaviors and their causes
- Successful patterns worth remembering
- Times when AI assistance was particularly helpful

### Optional to Log:
- Minor typo fixes
- Routine file operations
- Standard boilerplate additions

## Example Entries

### Good Entry:
```markdown
### 2024-12-31: Hebrew Date Calculation

#### What I did
Implemented the Hebrew date conversion using PHP's calendar functions.

#### What worked
- `jdtojewish()` function provided accurate date conversion
- Breaking the class into a separate file improved code organization

#### What didn't work
- Initially tried to use a third-party library, but it required Composer
  and added unnecessary complexity for this simple use case
- First attempt at month names used wrong indexing (0-based vs 1-based)

#### What I learned
- PHP has built-in calendar conversion functions in the `calendar` extension
- Hebrew calendar months are numbered starting from Tishrei (month 1)
- Leap years add a 13th month (Adar II)

#### AI Assistance
- Claude Code helped identify the correct PHP functions for calendar conversion
- Suggested using WordPress's `current_time()` for timezone-aware dates
```

### Avoid:
```markdown
### 2024-12-31: Stuff

Did some work. It worked.
```

## Converting Journal to README

When ready to write the README, extract from your journal:

1. **Process description**: Summarize the development flow from your dated entries
2. **Struggles and solutions**: Pull from your "What didn't work" sections
3. **Learnings**: Compile your "What I learned" sections
4. **AI usage**: Aggregate your "AI Assistance" notes

This makes the README authentic and detailed without having to remember everything at the end.
