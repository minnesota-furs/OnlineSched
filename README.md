# OnlineSched
FM Online schedule

## Todo
* Move things out of the main theme to here
* Put a build system to minify/compress files
* Make a more flexible admin
* Support dynamic tag color and text areas
* Verify js is being used in here


------------------------------------------------------------------------
HOW TO RUN THE AUTOMATED TESTS
------------------------------------------------------------------------

These tests check that the schedule page on the website still works
correctly. Think of them like a robot that clicks around the site and
makes sure nothing is broken.

BEFORE YOU START - you need these things working:
  1. Docker is running (the little whale icon in your taskbar)
  2. The website is up at https://furrymigration.local
  3. You are inside this folder in your terminal:
       cd /path/to/OnlineSched

------------------------------------------------------------------------
FIRST TIME SETUP (only do this once)
------------------------------------------------------------------------

Step 1 - Install everything the tests need:

    npm install

Step 2 - Download the test browser and add fake events to the database:

    npm run test:setup

That's it! You only need to do those two steps once. The fake events
stay in the database until someone wipes it.

------------------------------------------------------------------------
RUNNING THE TESTS (do this every time you want to check things)
------------------------------------------------------------------------

Just type this:

    npm test

The robot will open a browser in the background, click around the
schedule page, and tell you if anything is broken. It takes about
3-4 minutes to finish.

When it is done you will see something like:

    96 passed   <-- good! everything works
    20 skipped  <-- these are tests for future features, ignore them
     0 failed   <-- you want this to say 0

If you see "failed" tests, scroll up to see which ones broke and what
went wrong.

------------------------------------------------------------------------
OTHER WAYS TO RUN THE TESTS
------------------------------------------------------------------------

Watch the browser while it runs (good for debugging):

    npm run test:headed

Open a fancy interactive test dashboard:

    npm run test:ui

Run just one test file (swap in any filename from tests/e2e/):

    npx playwright test --config=tests/playwright.config.js tests/e2e/05-modals.spec.js

Run only the mobile-size tests:

    npx playwright test --config=tests/playwright.config.js --project=mobile

See a nice HTML report after a run:

    npx playwright show-report tests/playwright-report

------------------------------------------------------------------------
IF THE TESTS SUDDENLY START FAILING (seed data expired)
------------------------------------------------------------------------

The fake test events are set one week in the future. After that week
passes, the schedule page looks empty and some tests will fail saying
they found 0 events.

Fix it by re-running the seed script:

    npm run test:seed

This adds a fresh batch of fake events for next week. You can run it
as many times as you want -- old expired events just get ignored.

------------------------------------------------------------------------
IF YOU WIPED THE DATABASE
------------------------------------------------------------------------

If someone ran "docker compose down -v" (which erases everything), the
test events are gone. Run the seed script again:

    npm run test:seed

Then run the tests normally:

    npm test

------------------------------------------------------------------------
WHAT THE TESTS ARE CHECKING
------------------------------------------------------------------------

01 - Page loads      Does the schedule page even open without errors?
02 - Tabs            Do the Programming / Essentials / Hours tabs work?
03 - Filters         Does search, day picker, tag picker, room picker work?
04 - Favorites       Can you star an event? Does it save when you reload?
05 - Modals          Do the popup windows open, show the right info, close?
06 - Calendar        Do the "add to calendar" buttons have the right links?
07 - Hash routing    Does going to /schedule/#hour or /schedule/#evt-123 work?
08 - Responsive      Does the page look right on a phone-sized screen?
09 - No jQuery       (skipped for now -- this runs after the big refactor)

------------------------------------------------------------------------
