> **Feature plan:** [Step 1](./test_runner_elapsed_time_step1_solution_assessment.md) · [Step 2](./test_runner_elapsed_time_step2_feature_description.md) · [Step 3](./test_runner_elapsed_time_step3_development_plan.md) · [Step 4](./test_runner_elapsed_time_step4_implementation_summary.md)

Debug mode: disabled

## Original Query

I want the test runner to output the elapsed time with each line so that it's easier for me to track, please. Write Step 1 of Railspike.

## Understood Intent

Make timing visible as the existing test runner reports progress. The user clarified that elapsed run time belongs at the beginning of each PASS/FAIL result line, with individual test duration at the end after completion, omitting both “elapsed” and “duration” from the output. Summary sections and output emitted directly by tests are separate from those result lines.

## Problem

The runner already measures individual test durations, but its live result lines omit timing and its later slow-test report only shows tests above a threshold.

## Option A — Individual test duration

Show how long each completed test took alongside its result, including failed and fast tests.

- Pros: Compact; makes slow tests immediately identifiable; builds on existing timing information.
- Cons: Does not show total elapsed run time, including startup and time between tests.

## Option B — Elapsed run time

Show time since the test runner started on every completed test's result line.

- Pros: Makes overall progress easy to follow and gives each result a position in the run's timeline.
- Cons: Readers must compare adjacent lines to estimate an individual test's cost; gaps can include other runner work.

## Option C — Both, in consistent positions

Show elapsed run time at the beginning and individual test duration at the end of each completed PASS/FAIL result line.

- Pros: Answers both “how long has this run taken?” and “how long did this test take?” without arithmetic or waiting for the summary.
- Cons: Longer lines; consistent positions and units must distinguish the two timings.

## Recommendation

Choose **Option C**, enabled by default for full and filtered runs. Print the completed result with elapsed run time on the far left and individual test duration on the far right, without the words “elapsed” or “duration”: **[12.34s] PASS ExampleTest::testSomething [0.56s]**. Preserve recognizable PASS/FAIL labels, failure details, exit statuses, and the existing slow-test summary. Timing should remain meaningful in redirected logs as well as terminals.

This is a small, complete developer-facing slice: invoke the existing runner and receive timing with every completed result. Verify passing and failing results, fast tests, filtered runs, and compatibility with existing summary/interruption behavior. Continuous updates while a test is running, estimated completion times, timing history, and changes to standalone browser/shell runners are outside this proposal.
