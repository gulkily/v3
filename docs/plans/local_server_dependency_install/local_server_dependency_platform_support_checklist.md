# Local-server dependency platform support checklist

Related: [installation assessment](./local_server_dependency_install_step1_solution_assessment.md) · [feature description](./local_server_dependency_install_step2_feature_description.md) · [dependency backlog](../dependency_fallback_checklist.md).

This is a categorized expansion backlog for local startup, dependency diagnostics, and opt-in installation. Unchecked entries are candidates, not support promises. Package-manager availability alone does not establish application compatibility. Categories and suggested order are engineering judgments based on the existing Bash/PHP launcher and the official references below; no additional platform was runtime-tested for this document.

## Baseline and support levels

The existing [implementation evidence](../local_server_dependency_checks/local_server_dependency_checks_step4_implementation_summary.md) covers Ubuntu 24.04 with Bash 5.2 and PHP 8.1/8.3. The implemented installer targets Ubuntu 24.04/APT with distribution PHP 8.3. Real installation and recovery are verified on Ubuntu Base 24.04.3 amd64 in a disposable chroot, Bash 5.2.21, PHP 8.3.6 (`8.3.6-0ubuntu0.24.04.11`), from official Noble main/updates/security archives: [installation evidence](./local_server_dependency_install_test_evidence.json). This does not independently certify native hosts, WSL integration, other architectures, mirrors or distributions.

Track three claims separately for every platform: **runtime verified** (start/read/write/stop), **manual setup verified** (accurate recovery), and **installer verified** (confirmed install/recheck). Record the exact OS release, architecture, Bash/PHP versions, and package source for each claim. Current platform detection explicitly recognizes Ubuntu 24.04; derivatives must not inherit that mapping merely because APT is present.

## A. Closest extensions: APT and Linux guest environments

- [ ] **Other maintained Ubuntu releases:** validate each release's distribution PHP and extension packages, minimum compatibility, and package-operation behavior; add explicit release mappings rather than reuse 24.04 names.
- [ ] **Debian stable:** validate its PHP package split and CLI configuration, then run the complete recovery suite. APT offers a plausible shared installation flow, but release/package mappings need separate evidence. [Debian APT guide](https://www.debian.org/doc/manuals/apt-guide/)
- [ ] **Ubuntu/Debian derivatives, such as Linux Mint:** identify the actual release and package sources; independently verify mappings and runtime behavior before enabling installation.
- [ ] **Windows through WSL 2, starting with Ubuntu 24.04:** treat installation as operating inside the Linux distribution, not the Windows host. Verify Linux PHP/Git selection, filesystem placement, host-browser access, terminal signals, and cleanup. Record WSL evidence separately from native Linux. [Microsoft WSL installation](https://learn.microsoft.com/en-us/windows/wsl/install)

Suggested first expansion: Debian and a specifically verified WSL 2 environment, followed by additional Ubuntu releases. This is a proposed order, not an approved release commitment.

## B. Linux platforms requiring another package manager

- [ ] **Fedora / DNF:** verify exact-release package names, PHP extensions, privilege handling, and package failure reporting; preserve the shared readiness and confirmation contracts. [Fedora package management](https://fedoraproject.org/wiki/Package_management_system)
- [ ] **RHEL-family candidates (RHEL, Rocky Linux, AlmaLinux):** assess each distribution/release independently; validate available PHP versions and repositories before choosing an installer path. Do not enable repositories or change runtime streams implicitly.
- [ ] **openSUSE Leap / Tumbleweed with Zypper:** validate each release model, package/extension mapping, transaction confirmation, and lifecycle behavior separately. [openSUSE Zypper documentation](https://doc.opensuse.org/documentation/tumbleweed/zypper/)
- [ ] **Arch Linux / pacman:** validate rolling-release PHP compatibility and package transaction requirements; defer to manual setup when dependency installation would require broader system maintenance outside the reviewed batch. [Arch pacman manual](https://man.archlinux.org/man/pacman.8)

## C. Platforms requiring additional launcher or runtime portability work

- [ ] **macOS with Homebrew, Apple Silicon and Intel:** verify Bash 4.1+ selection before the launcher, PHP extension availability, executable/configuration paths, BSD utility behavior, and child shutdown on each architecture. Respect Homebrew's own privilege model; do not reuse an APT-style root workflow. Package-manager installation remains an external prerequisite. [Homebrew installation and prefixes](https://docs.brew.sh/Installation)
- [ ] **Alpine Linux / apk:** verify Bash and pre-check utilities on minimal images, PHP executable naming and extension packages, shell behavior, and all process probes; avoid assuming Ubuntu paths or tool behavior. [Alpine package management](https://docs.alpinelinux.org/user-handbook/0.1a/Working/apk.html)
- [ ] **FreeBSD / pkg:** verify Bash setup, PHP/extension package selection, executable locations, shell commands, and terminal/signal cleanup before adding installation. [FreeBSD packages handbook](https://docs.freebsd.org/en/books/handbook/ports/)

## D. Separate setup models or feasibility studies

- [ ] **Nix/NixOS development environments:** assess a declared shell environment with the required PHP extensions instead of translating the APT workflow directly. Verify that the selected runtime and extensions belong to the same environment; plan declarative setup as a separate slice. [Nix shell reference](https://nix.dev/manual/nix/2.34/command-ref/nix-shell.html)
- [ ] **Linux containers and development containers:** qualify the base distribution and runtime independently. Decide whether dependency installation belongs in the image build or a disposable running container; verify non-root behavior, port access, persistence, and termination. Do not interpret container success as host-platform installer support.
- [ ] **Native Windows PHP with PowerShell, Git Bash, or MSYS2:** perform a feasibility review of the Bash launcher, Unix shell/null-device assumptions, path quoting, and process cleanup before choosing any package-manager integration. WSL support does not establish native Windows support. [PHP Windows installation](https://www.php.net/manual/en/install.windows.php)
- [ ] **Restricted/shared hosts and immutable environments:** qualify read-only diagnostics and manual administrator guidance first. Report missing privileges or prohibited process execution accurately; package installation cannot repair all restrictions.

## Acceptance checklist for each platform

- [ ] Define exact release, architecture, package source, and supported runtime versions; record which of the three support levels is being claimed.
- [ ] Verify Bash 4.1+, launcher utilities, Git, PHP compatibility, PDO/in-memory SQLite, mbstring, and required process execution in the selected CLI.
- [ ] Map only genuinely missing packages; preserve custom-runtime selection and separate PATH/configuration/host restrictions from installation needs.
- [ ] Verify optional intl, PCNTL, terminal shell execution, and `stty` degradation; preserve plain logs and reliable shutdown.
- [ ] Verify manual setup instructions against official release/package metadata; document the external setup path when Bash or the package manager is absent.
- [ ] For installer support, verify concrete batch review, separate optional selection, platform-appropriate privileges, decline, noninteractive refusal, missing packages, already-ready state, package-manager failures, interruption, and accurate partial-change reporting.
- [ ] Run both aliases, ordinary startup/checks, state-preservation checks, and install/recheck recovery on a real target environment; verify HTTP reads/writes and no surviving listener after shutdown with terminal and redirected output.
- [ ] Publish evidence and limits, retain generic guidance for unknown environments, and add the platform through its own approved feature slice before marking its candidate entry complete.
