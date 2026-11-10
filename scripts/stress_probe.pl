#!/usr/bin/env perl
# Small, stepped read-load probe for a v3 site. Perl 5.20+, core modules only; HTTPS also
# needs IO::Socket::SSL (Debian/Ubuntu: libio-socket-ssl-perl), as for any HTTP::Tiny use.
# Same options, output, record format and exit codes as stress_probe.py and stress_probe.php;
# see that script or docs/runbooks/server_stress_probe.md for the full description.
# Intentional multi-language set; see docs/reference/multi_language_scripts.md.
#
#   perl scripts/stress_probe.pl https://zenmemes.com                       # show the plan
#   perl scripts/stress_probe.pl https://zenmemes.com --confirm zenmemes.com
#
# Nothing is sent unless --confirm repeats the target's host name exactly. GET requests only.
use strict;
use warnings;
use Getopt::Long qw(GetOptionsFromArray);
use HTTP::Tiny;
use IO::Handle;
use File::Temp qw(tempdir);
use POSIX qw(floor strftime);
use Time::HiRes qw(time sleep);

$| = 1;
my $USER_AGENT = 'v3-stress-probe/1 (operator load test)';
my $MAX_STEP_SECONDS = 60;
my $MAX_CONCURRENCY = 32;
my $MAX_RPS = 200;

sub usage_error {
    print STDERR "usage: stress_probe.pl BASE_URL [--confirm HOST] [--mode cached|uncached|both] [options]\n";
    print STDERR "stress_probe.pl: error: $_[0]\n";
    exit 2;
}

sub help_text {
    return <<'TEXT';
usage: stress_probe.pl BASE_URL [options]

Small, stepped read-load probe for a v3 site. Sends nothing unless --confirm
repeats the target's host name exactly; without it the plan is printed.

options:
  --confirm HOST        repeat the target host name to actually send traffic
  --mode MODE           cached, uncached or both (default cached)
  --steps LIST          comma-separated concurrent readers per step (default 1,2,4,8; max 32)
  --seconds N           duration of each step (default 20, max 60)
  --max-rps N           cap on total requests per second (default 50, max 200)
  --pause N             rest between steps in seconds (default 5)
  --threads N           thread pages to discover from the home page (default 5)
  --path PATH           extra path to include (repeatable)
  --p95-ms N            stop when p95 exceeds this (default 2000)
  --max-error-rate N    stop when 5xx/network errors exceed this share (default 0.02)
  --max-shed-rate N     stop when 503 Retry-After responses exceed this share (default 0.25)
  --timeout N           per-request timeout in seconds (default 30)
  --progress N          print a progress line this often during each step; 0 turns it off (default 5)
  --record FILE         also write the result as a Key: value text record
TEXT
}

sub parse_args {
    my @argv = @_;
    my %args = (confirm => '', mode => 'cached', steps => '1,2,4,8', seconds => 20, 'max-rps' => 50, pause => 5,
        threads => 5, path => [], 'p95-ms' => 2000, 'max-error-rate' => 0.02, 'max-shed-rate' => 0.25,
        timeout => 30, progress => 5, record => undef);
    my $help = 0;
    my @errors;
    local $SIG{__WARN__} = sub { push @errors, $_[0] };
    my $ok = GetOptionsFromArray(\@argv, \%args, 'confirm=s', 'mode=s', 'steps=s', 'seconds=f', 'max-rps=f', 'pause=f',
        'threads=i', 'path=s@', 'p95-ms=f', 'max-error-rate=f', 'max-shed-rate=f', 'timeout=f', 'progress=f', 'record=s',
        'help|h' => \$help);
    if ($help) { print help_text(); exit 0 }
    if (!$ok) { my $message = $errors[0] // 'invalid arguments'; chomp $message; usage_error($message) }
    usage_error(@argv ? 'unrecognized arguments: ' . join(' ', @argv[1 .. $#argv]) : 'the following arguments are required: base_url')
        if @argv != 1;
    $args{base_url} = $argv[0];
    usage_error("argument --mode: invalid choice: '$args{mode}' (choose from 'cached', 'uncached', 'both')")
        unless $args{mode} =~ /\A(?:cached|uncached|both)\z/;

    # A bare origin: scheme, host, optional port, optional trailing slash.
    my ($scheme, $host, $port) = $args{base_url} =~ m{\A(https?)://(\[[0-9A-Fa-f:.]+\]|[^/:?#@\[\]]+)(?::(\d+))?/?\z}
        or usage_error('base_url must be a bare origin such as https://zenmemes.com');
    $host =~ s/\A\[|\]\z//g;
    @args{qw(scheme host port)} = ($scheme, lc $host, $port);

    my @steps;
    for my $part (split /,/, $args{steps}) {
        next if $part =~ /\A\s*\z/;
        usage_error('--steps must be comma-separated integers') unless $part =~ /\A\s*[+-]?\d+\s*\z/;
        push @steps, int $part;
    }
    usage_error("each step must be between 1 and $MAX_CONCURRENCY") if !@steps || grep { $_ < 1 || $_ > $MAX_CONCURRENCY } @steps;
    $args{step_list} = \@steps;
    usage_error("--seconds must be between 0 and $MAX_STEP_SECONDS") unless $args{seconds} > 0 && $args{seconds} <= $MAX_STEP_SECONDS;
    usage_error("--max-rps must be between 0 and $MAX_RPS") unless $args{'max-rps'} > 0 && $args{'max-rps'} <= $MAX_RPS;
    usage_error('--pause, --threads, --progress and --timeout must not be negative')
        if $args{pause} < 0 || $args{threads} < 0 || $args{timeout} <= 0 || $args{progress} < 0;
    for my $path (@{ $args{path} }) {
        usage_error('--path values must start with /') unless $path =~ m{\A/};
    }
    return \%args;
}

sub origin {
    my ($args) = @_;
    my $host = $args->{host} =~ /:/ ? "[$args->{host}]" : $args->{host};
    return "$args->{scheme}://$host" . (defined $args->{port} ? ":$args->{port}" : '');
}

sub client {
    my ($args) = @_;
    return HTTP::Tiny->new(agent => $USER_AGENT, timeout => $args->{timeout}, max_redirect => 0, keep_alive => 1,
        verify_SSL => 1, default_headers => { Accept => 'text/html' });
}

# Same rounding as the Python and PHP probes.
sub half_up { return int floor($_[0] + 0.5) }

sub percentile {
    my ($sorted, $fraction) = @_;
    return 0 unless @$sorted;
    my $index = half_up($fraction * (@$sorted - 1));
    $index = 0 if $index < 0;
    $index = $#$sorted if $index > $#$sorted;
    return $sorted->[$index];
}

sub discover_paths {
    my ($args) = @_;
    my $response = client($args)->get(origin($args) . '/');
    if ($response->{status} != 200) {
        print STDERR "The home page returned HTTP $response->{status}; not starting.\n";
        exit 1;
    }
    my (@found, %seen);
    my $body = $response->{content} // '';
    while ($body =~ m{href="(/threads/[A-Za-z0-9][A-Za-z0-9_-]{0,127})"}g) {
        push @found, $1 unless $seen{$1}++;
        last if @found >= $args->{threads};
    }
    return @found;
}

sub route_plan {
    my ($args, @threads) = @_;
    my @base = ('/', '/threads/', '/about/', @threads, @{ $args->{path} });
    my @plan;
    push @plan, map { ['cached', $_] } @base if $args->{mode} =~ /\A(?:cached|both)\z/;
    push @plan, map { ['uncached', $_] } @base, '/recent/', '/search?q=the' if $args->{mode} =~ /\A(?:uncached|both)\z/;
    return @plan;
}

sub concrete_path {
    my ($label, $path) = @_;
    return $path unless $label eq 'uncached';
    return $path . ($path =~ /\?/ ? '&' : '?') . 'stress=' . int(rand(1e12));
}

sub network_error_name {
    # HTTP::Tiny reports its own failures as status 599 with the message as content.
    my ($content) = @_;
    my ($line) = split /\n/, ($content // 'unknown error');
    $line =~ s/ at \S+ line \d+\.?\z//;
    $line =~ s/\s+\z//;
    return length $line > 80 ? substr($line, 0, 77) . '...' : $line;
}

sub reader {
    my ($args, $plan, $offset, $concurrency, $deadline, $start, $out) = @_;
    # Each reader takes every Nth slot of the shared schedule, so together they never exceed
    # --max-rps; a slow reader skips the slots it missed rather than bursting to catch up.
    my $period = $concurrency / $args->{'max-rps'};
    my $next = $start + $offset / $args->{'max-rps'};
    my $http = client($args);
    my $index = $offset;
    open my $fh, '>', $out or die "Cannot write $out: $!\n";
    $fh->autoflush(1);  # the parent reads these lines for progress while the step runs
    while (1) {
        my $now = time;
        $next += $period * (1 + int(($now - $next) / $period)) if $now > $next + $period;
        sleep($next - $now) if $next > $now;
        last if time >= $deadline;
        $next += $period;
        my ($label, $path) = @{ $plan->[$index++ % @$plan] };
        my $started = time;
        my $response = $http->get(origin($args) . concrete_path($label, $path));
        my $ms = (time - $started) * 1000;
        my ($status, $error) = ($response->{status}, '');
        if ($status == 599) {
            ($status, $error) = (0, network_error_name($response->{content}));
            $http = client($args);
        }
        my $cache = $response->{headers}{'x-v3-high-traffic'} // '';
        my $retry = $response->{headers}{'retry-after'} // '';
        $_ = ref $_ ? $_->[0] : $_ for $cache, $retry;
        s/[\t\n]/ /g for $cache, $retry, $error;
        print {$fh} join("\t", $label, $status, $ms, $cache, $retry, $error), "\n";
        last if $status == 429;
    }
    close $fh;
}

sub progress_line {
    # $recent: latencies of requests finished since the previous progress line.
    my ($concurrency, $elapsed, $seconds, $samples, $recent) = @_;
    my %statuses;
    $statuses{ $_->[1] }++ for @$samples;
    my $counts = join ' ', map { ($_ == 0 ? 'network' : $_) . ":$statuses{$_}" } sort { $a <=> $b } keys %statuses;
    my @sorted = sort { $a <=> $b } @$recent;
    my $p95 = @sorted ? half_up(percentile(\@sorted, .95)) . ' ms' : '-';
    my $rate = $elapsed > 0 ? @$samples / $elapsed : 0;
    return sprintf('  .. c=%d  %ds/%gs  %d req  %.1f/s  recent p95 %s  [%s]', $concurrency, int $elapsed, $seconds,
        scalar @$samples, $rate, $p95, $counts);
}

sub collect_lines {
    # Read whole lines appended to each reader's file since the last call.
    my ($dir, $concurrency, $offsets, $samples, $errors) = @_;
    for my $offset (0 .. $concurrency - 1) {
        open my $fh, '<', "$dir/$offset" or next;
        seek $fh, $offsets->[$offset] // 0, 0;
        while (my $line = <$fh>) {
            last unless $line =~ s/\n\z//;  # a reader is still writing this line
            $offsets->[$offset] = tell $fh;
            my ($label, $status, $ms, $cache, $retry, $error) = split /\t/, $line, -1;
            push @$samples, [$label, $status + 0, $ms + 0, $cache, $retry];
            $errors->{$error}++ if $status == 0;
        }
        close $fh;
    }
}

sub run_step {
    my ($args, $plan, $concurrency) = @_;
    my $dir = tempdir(CLEANUP => 1);
    my $started = time;
    my $deadline = $started + $args->{seconds};
    my @children;
    for my $offset (0 .. $concurrency - 1) {
        my $pid = fork;
        die "Cannot start a reader: $!\n" unless defined $pid;
        if ($pid == 0) {
            reader($args, $plan, $offset, $concurrency, $deadline, $started, "$dir/$offset");
            POSIX::_exit(0);
        }
        push @children, $pid;
    }
    my (@samples, %errors, @offsets);
    my %running = map { $_ => 1 } @children;
    my $next_tick = $started + $args->{progress};
    my $reported = 0;
    while (%running) {
        for my $pid (keys %running) {
            delete $running{$pid} if waitpid($pid, POSIX::WNOHANG()) != 0;
        }
        my $now = time;
        if ($args->{progress} > 0 && $now >= $next_tick) {
            if ($now < $deadline) {
                collect_lines($dir, $concurrency, \@offsets, \@samples, \%errors);
                print progress_line($concurrency, $now - $started, $args->{seconds}, \@samples,
                    [map { $_->[2] } @samples[$reported .. $#samples]]), "\n";
                $reported = @samples;
            }
            $next_tick += $args->{progress};
        }
        sleep 0.1 if %running;
    }
    my $wall = time - $started;
    collect_lines($dir, $concurrency, \@offsets, \@samples, \%errors);
    my (%statuses, $shed, $failures);
    ($shed, $failures) = (0, 0);
    for my $sample (@samples) {
        my ($status, $retry) = @$sample[1, 4];
        $statuses{$status}++;
        my $is_shed = $status == 503 && $retry ne '';
        $shed++ if $is_shed;
        $failures++ if $status == 0 || ($status >= 500 && !$is_shed);
    }
    my @latencies = sort { $a <=> $b } map { $_->[2] } @samples;
    my $total = @samples;
    my %routes;
    my %labels = map { $_->[0] => 1 } @samples;
    for my $label (sort keys %labels) {
        my @values = sort { $a <=> $b } map { $_->[2] } grep { $_->[0] eq $label } @samples;
        my $hits = grep { $_->[0] eq $label && $_->[3] eq 'hit' } @samples;
        $routes{$label} = { requests => scalar @values, p50_ms => half_up(percentile(\@values, .5)),
            p95_ms => half_up(percentile(\@values, .95)), cache_hits => $hits };
    }
    my @status_list = map { [$_ == 0 ? 'network' : $_, $statuses{$_}] } sort { $a <=> $b } keys %statuses;
    return {
        concurrency => $concurrency, seconds => $wall, requests => $total,
        requests_per_second => $wall > 0 ? $total / $wall : 0,
        p50_ms => half_up(percentile(\@latencies, .5)), p95_ms => half_up(percentile(\@latencies, .95)),
        p99_ms => half_up(percentile(\@latencies, .99)), max_ms => @latencies ? half_up($latencies[-1]) : 0,
        statuses => \@status_list, network_errors => [map { [$_, $errors{$_}] } sort keys %errors],
        shed_503 => $shed, failures => $failures,
        error_rate => $total ? $failures / $total : 1, shed_rate => $total ? $shed / $total : 0,
        routes => \%routes,
    };
}

sub status_count {
    my ($step, $name) = @_;
    for (@{ $step->{statuses} }) { return $_->[1] if $_->[0] eq $name }
    return 0;
}

sub stop_reason {
    my ($args, $step) = @_;
    return 'no requests completed' if $step->{requests} == 0;
    return 'the server answered 429 Too Many Requests' if status_count($step, '429');
    return sprintf('error rate %.1f%% is over %.1f%%', $step->{error_rate} * 100, $args->{'max-error-rate'} * 100)
        if $step->{error_rate} > $args->{'max-error-rate'};
    return sprintf('load-shedding 503s at %.1f%% are over %.1f%%', $step->{shed_rate} * 100, $args->{'max-shed-rate'} * 100)
        if $step->{shed_rate} > $args->{'max-shed-rate'};
    return sprintf('p95 %d ms is over %d ms', $step->{p95_ms}, $args->{'p95-ms'}) if $step->{p95_ms} > $args->{'p95-ms'};
    return '';
}

sub joined_counts { return join ', ', map { "$_->[0] $_->[1]" } @{ $_[0] } }

sub print_step {
    my ($step) = @_;
    my $statuses = join ' ', map { "$_->[0]:$_->[1]" } @{ $step->{statuses} };
    printf "  c=%-3d %6d req %7.1f/s  p50 %5d  p95 %5d  p99 %5d  max %5d ms  [%s]\n", $step->{concurrency}, $step->{requests},
        $step->{requests_per_second}, $step->{p50_ms}, $step->{p95_ms}, $step->{p99_ms}, $step->{max_ms}, $statuses;
    for my $label (sort keys %{ $step->{routes} }) {
        my $route = $step->{routes}{$label};
        my $hit = '';
        $hit = sprintf('  cache hits %d%%', half_up(100 * $route->{cache_hits} / $route->{requests}))
            if $label eq 'cached' && $route->{requests};
        printf "        %-8s %6d req  p50 %5d  p95 %5d ms%s\n", $label, $route->{requests}, $route->{p50_ms}, $route->{p95_ms}, $hit;
    }
    print '        network errors: ', joined_counts($step->{network_errors}), "\n" if @{ $step->{network_errors} };
}

sub record_text {
    my ($result) = @_;
    my $rules = $result->{stop_rules};
    my @lines = ("Target: $result->{target}", "Mode: $result->{mode}", "Started-At: $result->{started_at}",
        "User-Agent: $result->{user_agent}", sprintf('Max-Requests-Per-Second: %g', $result->{max_rps}),
        sprintf('Stop-Rules: p95 %d ms; errors %g%%; shed %g%%', $rules->{p95_ms}, $rules->{max_error_rate} * 100, $rules->{max_shed_rate} * 100),
        'Stopped: ' . ($result->{stopped} ne '' ? $result->{stopped} : 'no'));
    for my $step (@{ $result->{steps} }) {
        push @lines, '', sprintf('Step-Concurrency: %d', $step->{concurrency}), sprintf('Seconds: %.1f', $step->{seconds}),
            sprintf('Requests: %d', $step->{requests}), sprintf('Requests-Per-Second: %.1f', $step->{requests_per_second}),
            sprintf('Latency-Ms: p50 %d; p95 %d; p99 %d; max %d', @{$step}{qw(p50_ms p95_ms p99_ms max_ms)}),
            'Statuses: ' . joined_counts($step->{statuses}),
            sprintf('Error-Rate: %.4f', $step->{error_rate}), sprintf('Shed-Rate: %.4f', $step->{shed_rate});
        push @lines, 'Network-Errors: ' . joined_counts($step->{network_errors}) if @{ $step->{network_errors} };
        for my $label (sort keys %{ $step->{routes} }) {
            my $route = $step->{routes}{$label};
            push @lines, sprintf('Route-%s: %d requests; p50 %d ms; p95 %d ms; cache hits %d', ucfirst $label,
                @{$route}{qw(requests p50_ms p95_ms cache_hits)});
        }
    }
    return join("\n", @lines) . "\n";
}

sub main {
    my $args = parse_args(@_);
    my $confirmed = $args->{confirm} eq $args->{host};
    my @steps = @{ $args->{step_list} };
    my $total_seconds = @steps * $args->{seconds} + (@steps - 1) * $args->{pause};
    print 'Target:  ', origin($args), "\n";
    printf "Mode:    %s; steps %s readers, %gs each, %gs rest (at most %ds)\n", $args->{mode}, join(',', @steps),
        $args->{seconds}, $args->{pause}, $total_seconds;
    printf "Rate:    at most %g requests per second in total\n", $args->{'max-rps'};
    printf "Stops:   any 429; errors > %g%%; shed 503s > %g%%; p95 > %d ms\n", $args->{'max-error-rate'} * 100,
        $args->{'max-shed-rate'} * 100, $args->{'p95-ms'};
    print 'Report:  ', ($args->{progress} > 0 ? sprintf('progress every %gs', $args->{progress}) : 'step results only'), "\n";
    if (!$confirmed) {
        if ($args->{confirm} ne '') {
            printf "\n--confirm %s does not match the target host %s. Nothing was sent.\n", $args->{confirm}, $args->{host};
            return 2;
        }
        printf "\nPlan only; nothing was sent. Add --confirm %s to run it.\n", $args->{host};
        return 0;
    }
    if ($args->{scheme} eq 'https' && !HTTP::Tiny->can_ssl) {
        print STDERR "HTTPS needs IO::Socket::SSL and Net::SSLeay (Debian/Ubuntu: libio-socket-ssl-perl).\n";
        return 1;
    }

    my @threads;
    if ($args->{threads}) {
        printf "Reading / for up to %d thread links...\n", $args->{threads};
        @threads = discover_paths($args);
        printf "Found %d thread page%s.\n", scalar @threads, @threads == 1 ? '' : 's';
    }
    my @plan = route_plan($args, @threads);
    my %paths = map { $_->[1] => 1 } @plan;
    print 'Routes:  ', join(', ', sort keys %paths), "\n";
    my %result = (target => $args->{base_url}, mode => $args->{mode}, started_at => strftime('%Y-%m-%dT%H:%M:%S+00:00', gmtime),
        user_agent => $USER_AGENT, max_rps => $args->{'max-rps'},
        stop_rules => { p95_ms => $args->{'p95-ms'}, max_error_rate => $args->{'max-error-rate'}, max_shed_rate => $args->{'max-shed-rate'} },
        steps => [], stopped => '');
    for my $number (0 .. $#steps) {
        if ($number && $args->{pause} > 0) {
            printf "Resting %gs...\n", $args->{pause};
            sleep $args->{pause};
        }
        printf "Step %d/%d: %d reader%s for %gs\n", $number + 1, scalar @steps, $steps[$number], $steps[$number] == 1 ? '' : 's',
            $args->{seconds};
        my $step = run_step($args, \@plan, $steps[$number]);
        push @{ $result{steps} }, $step;
        print_step($step);
        my $reason = stop_reason($args, $step);
        if ($reason ne '') {
            $result{stopped} = sprintf('step c=%d: %s', $steps[$number], $reason);
            print "Stopped: $result{stopped}\n";
            last;
        }
    }
    print "Done: every step stayed within the stop rules.\n" if $result{stopped} eq '';
    if (defined $args->{record}) {
        open my $fh, '>:encoding(UTF-8)', $args->{record} or do { print STDERR "Cannot write $args->{record}: $!\n"; return 1 };
        print {$fh} record_text(\%result);
        close $fh;
        print "Saved:   $args->{record}\n";
    }
    return $result{stopped} ne '' ? 1 : 0;
}

exit main(@ARGV);
