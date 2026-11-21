# Local development server

Purpose: Start a local checkout without requiring its default port to be free.
Scenario: Run `./v3 start` while another checkout uses port 8000. The launcher tries successive ports through 8009 and displays the actual URL after PHP binds. `--listen-all` keeps its all-interface host and uses the same range. An explicit port is required exactly; it never advances. Exhaustion reports failure and suggests an explicit alternate port. Release a port and rerun to recover.
Lifecycle: Only address-in-use failures permit retry. Each failed child is closed before another starts. Startup confirmation precedes the frontend registry, terminal header and task worker. Ctrl+C, SIGTERM and the launcher control pipe stop the selected listener and remove its registration. Bind failures exit nonzero without announcing success.
Sources: [CLI](../../v3), [signal owner](../../scripts/local_server_start.sh), [server lifecycle](../../scripts/dev_server.php), [frontend registry](../../src/ForumRewrite/Support/DevServerRegistry.php), [process checks](../../tests/DevServerStartTest.php), [CLI reference](../reference/v3_cli.md#start-the-local-dev-server).
