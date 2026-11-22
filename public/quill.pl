#!/usr/bin/env perl
our $QUILL_VERSION = '2026-11-22';

# quill.pl: portable forum client. Core Perl 5.10+, curl and GPG; no CPAN modules.
# One of three versions (quill.py, quill.php, quill.pl) with the same commands, output,
# saved state and exit codes, kept on purpose; see docs/reference/multi_language_scripts.md.
# Unix-like hosts only (processes run with fork and exec, never through a shell).
#
# INPUT:
# global options, a command and its options (perl quill.pl --help)
#
# PROCESS:
# read commands are one GET each #quill_read
# writes save an operation directory before anything is sent #quill_operation
#   signed protocol: client builds and signs the record, one request #quill_signed
#   prepared protocol: server prepares the record, client signs exact bytes #quill_prepared
#   lost or busy responses resume from the saved operation, never a second post #quill_resume
# sign-in commands hold the session cookie in memory for one run only #quill_session
# failed writes post a scrubbed trouble report when a report thread is set #quill_report
#
# Debug: QUILL_DEBUG=1 prints WriteLog() lines to stderr. They never carry drafts or keys.

use strict;
use warnings;
use 5.010;
use utf8;

use Cwd qw(realpath);
use Digest::SHA qw(sha256_hex);
use Encode qw(encode decode FB_CROAK);
use File::Basename qw(basename dirname);
use File::Path qw(make_path);
use File::Spec;
use File::Temp qw(tempdir);
use IO::Handle;
use JSON::PP;
use POSIX qw(strftime);
use Time::Local qw(timegm);
use Unicode::Normalize qw(NFC NFD);

binmode(STDOUT, ':encoding(UTF-8)');
binmode(STDERR, ':encoding(UTF-8)');
STDOUT->autoflush(1);
STDERR->autoflush(1);

# Trouble-report context for this run: last API route, busy retries, operation and identity.
my %TRACE = ('route' => '', 'busy' => 0, 'operation' => '', 'identity' => '');
# Update notices already printed this run; the server sends one on every response.
my %NOTICES;
my $USER_AGENT = 'quill-pl/' . $QUILL_VERSION;
my $REPORT_ERROR_LIMIT = 300;
my $STAGED_EXIT = 3; # exit status for a post the server kept but did not publish (0 published, 2 error)
my $REJECTED_GUIDANCE = 'Rejected: the server refused this write, so nothing was published. Correct the original draft and start a new write; do not resume.';
my @SESSION_COOKIES = ('__Host-v3_session', 'v3_session');

my %IMAGE_TYPES = ('png' => 'image/png', 'jpg' => 'image/jpeg');
# Same limits as the browser composer (public/assets/image_prepare.js) and the server's upload check.
my $IMAGE_SOURCE_BYTES = 32 * 1024 * 1024;
my $IMAGE_MAX_BYTES = 5 * 1024 * 1024;
my $IMAGE_MAX_SIDE = 2048;
# PNG chunks that only carry metadata (text, EXIF, timestamps); everything else is image data or colour.
my %PNG_METADATA_CHUNKS = map { $_ => 1 } ('tEXt', 'zTXt', 'iTXt', 'eXIf', 'tIME');

# Saved Key: value files list these first, in this order, so they read the same as quill.py's.
my @META_ORDER = ('Site', 'Protocol', 'Kind', 'State', 'Identity-ID', 'Thread-ID', 'Parent-ID', 'Fingerprint', 'Home', 'Generated');

sub WriteLog { # $text ; prints a debug line to stderr when QUILL_DEBUG=1
	my $text = shift;
	state $debugOn = ($ENV{'QUILL_DEBUG'} || '') eq '1';
	if (!$debugOn) {
		return;
	}
	if (!defined($text) || $text eq '') {
		$text = '(empty string)';
	}
	chomp $text;
	print STDERR 'Debug: ' . $text . "\n";
} # WriteLog()

sub Fail { # $message ; stops the current command with a message for the user
	my $message = shift;
	die $message . "\n";
} # Fail()

sub ErrorText { # $error ; the message of a caught error, without a trailing newline
	my $error = shift;
	$error = defined($error) ? "$error" : '';
	$error =~ s/\s+\z//;
	return $error;
} # ErrorText()

sub Program { # ; the name this copy runs under, so printed commands work whether it was renamed or not
	my $name = basename($0 || '');
	if ($name =~ m/\.pl\z/) {
		return $name;
	}
	return 'quill.pl';
} # Program()

sub Setting { # $name, $default ; QUILL_<name>, then the older FRM_<name>
	my $name = shift;
	my $default = shift;
	return $ENV{'QUILL_' . $name} || $ENV{'FRM_' . $name} || (defined($default) ? $default : '');
} # Setting()

sub ShellQuote { # $text ; quotes one argument for a printed command line, like Python's shlex.quote
	my $text = shift;
	if ($text eq '') {
		return "''";
	}
	if ($text =~ m/\A[A-Za-z0-9_\@%+=:,.\/-]+\z/) {
		return $text;
	}
	$text =~ s/'/'"'"'/g;
	return "'" . $text . "'";
} # ShellQuote()

sub StripDraft { # $text ; removes the characters quill.py strips from both ends of a draft
	my $text = shift;
	$text =~ s/\A[ \t\r\n\0\x0b]+//;
	$text =~ s/[ \t\r\n\0\x0b]+\z//;
	return $text;
} # StripDraft()

sub PyNumber { # $number ; formats a delay the way Python prints a float (10 -> 10.0)
	my $number = shift;
	if ($number == int($number) && abs($number) < 1e15) {
		return sprintf('%d.0', $number);
	}
	return "$number";
} # PyNumber()

sub PyRepr { # $text ; quotes a string the way Python's repr() does, for Character: lines
	my $text = shift;
	my $out = '';
	for my $char (split(//, $text)) {
		my $code = ord($char);
		if ($char eq "'" || $char eq "\\") {
			$out .= "\\" . $char;
		} elsif ($char eq "\n") {
			$out .= '\\n';
		} elsif ($char eq "\r") {
			$out .= '\\r';
		} elsif ($char eq "\t") {
			$out .= '\\t';
		} elsif ($char ne ' ' && $char =~ m/[\p{C}\p{Z}]/) {
			$out .= $code < 0x100 ? sprintf('\\x%02x', $code) : ($code < 0x10000 ? sprintf('\\u%04x', $code) : sprintf('\\U%08x', $code));
		} else {
			$out .= $char;
		}
	}
	return "'" . $out . "'";
} # PyRepr()

sub ReadBytes { # $path ; whole file as bytes
	my $path = shift;
	open(my $file, '<:raw', $path) or Fail("[Errno] Cannot read $path: $!");
	local $/;
	my $data = <$file>;
	close($file);
	return defined($data) ? $data : '';
} # ReadBytes()

sub ReadText { # $path ; whole file decoded as UTF-8
	my $path = shift;
	my $bytes = ReadBytes($path);
	my $text = eval { decode('UTF-8', $bytes, FB_CROAK) };
	if (!defined($text)) {
		Fail("'utf-8' codec can't decode $path");
	}
	return $text;
} # ReadText()

sub WriteBytes { # $path, $bytes ; writes a private file
	my $path = shift;
	my $bytes = shift;
	open(my $file, '>:raw', $path) or Fail("[Errno] Cannot write $path: $!");
	print {$file} $bytes or Fail("[Errno] Cannot write $path: $!");
	close($file) or Fail("[Errno] Cannot write $path: $!");
} # WriteBytes()

sub WriteText { # $path, $text ; writes text as UTF-8
	my $path = shift;
	my $text = shift;
	WriteBytes($path, encode('UTF-8', $text));
} # WriteText()

sub RandomHex { # $bytes ; hex string from the system's random source
	my $count = shift;
	open(my $random, '<:raw', '/dev/urandom') or Fail('No /dev/urandom; Quill needs a random source for post IDs.');
	my $data = '';
	read($random, $data, $count) == $count or Fail('Short read from /dev/urandom.');
	close($random);
	return unpack('H*', $data);
} # RandomHex()

sub ResolvePath { # $path ; absolute path with ~ expanded and symlinks resolved when it exists
	my $path = shift;
	if ($path =~ m/\A~(?=\/|\z)/) {
		my $home = $ENV{'HOME'} || (getpwuid($<))[7] || '';
		$path =~ s/\A~/$home/;
	}
	$path = File::Spec->rel2abs($path);
	if (-e $path) {
		my $real = realpath($path);
		if (defined($real)) {
			return $real;
		}
	}
	$path =~ s{/+\z}{} if length($path) > 1;
	return $path;
} # ResolvePath()

sub HomeDirectory { # ; the user's home directory
	return $ENV{'HOME'} || (getpwuid($<))[7] || '.';
} # HomeDirectory()

sub ListOperations { # $state ; operation.txt paths of saved operations, sorted
	my $state = shift;
	my @found;
	if (opendir(my $dir, $state)) {
		for my $name (sort readdir($dir)) {
			if ($name =~ m/\A(operation-[^\/]+)\z/ && -f "$state/$1/operation.txt") {
				push @found, "$state/$1/operation.txt";
			}
		}
		closedir($dir);
	}
	return @found;
} # ListOperations()

sub Scrub { # $text ; removes what a public report must never carry: key blocks, local paths, tokens
	my $text = shift;
	$text =~ s/-----BEGIN PGP[\s\S]*?(?:-----END PGP[^\n]*?-----|\z)/[key block]/g;
	$text =~ s{(?<![\w.:/])(?:~|/(?!api/))[^\s'",;)]*}{[path]}g;
	$text =~ s/\b[0-9A-Fa-f]{24,}\b/[hex]/g;
	$text = join(' ', split(' ', $text));
	if (length($text) <= $REPORT_ERROR_LIMIT) {
		return $text;
	}
	return substr($text, 0, $REPORT_ERROR_LIMIT - 3) . '...';
} # Scrub()

sub TroubleReport { # $command, $route, $error, $outcome, $busyRetries ; plain-text report from a fixed field list
	my $command = shift;
	my $route = shift;
	my $error = shift;
	my $outcome = shift;
	my $busyRetries = shift || 0;
	my $scrubbed = Scrub($error);
	my @lines = (
		'Automatic trouble report from Quill. Turn off with --no-report.', '',
		'Client: quill.pl ' . $QUILL_VERSION, 'Command: ' . $command,
		'Route: ' . ($route || 'none'), 'Outcome: ' . $outcome,
		'Busy-Retries: ' . $busyRetries, 'Error: ' . ($scrubbed ne '' ? $scrubbed : 'none'),
	);
	return join("\n", @lines) . "\n";
} # TroubleReport()

sub SiteUrl { # $value ; checks --site and returns it without a trailing slash
	my $value = shift;
	my $ok = $value =~ m{\A(https?)://([^/?#]*)([^?#]*)\z}i;
	my $authority = $ok ? $2 : '';
	if (!$ok || $authority eq '' || $authority =~ m/\@/ || $authority =~ m/\A:/ || $value =~ m/\s/) {
		Fail('Use --site with an http(s) instance URL without credentials, query or fragment.');
	}
	$value =~ s{/+\z}{};
	return $value;
} # SiteUrl()

sub HeaderValue { # $value ; refuses control characters in anything written as a header line
	my $value = shift;
	if ($value =~ m/[\x00-\x1f\x7f]/) {
		Fail('Header values cannot contain control characters.');
	}
	return $value;
} # HeaderValue()

sub FormEscape { # $text ; application/x-www-form-urlencoded, like Python's urlencode
	my $bytes = encode('UTF-8', shift);
	$bytes =~ s/([^A-Za-z0-9_.~ -])/sprintf('%%%02X', ord($1))/ge;
	$bytes =~ tr/ /+/;
	return $bytes;
} # FormEscape()

sub UrlEncode { # \%fields ; form body or query string, keys sorted
	my $fields = shift;
	return join('&', map { FormEscape($_) . '=' . FormEscape($fields->{$_}) } sort keys %$fields);
} # UrlEncode()

sub FindProgram { # $name ; true when an executable of that name is on PATH
	my $name = shift;
	for my $directory (split(/:/, $ENV{'PATH'} || '')) {
		if ($directory ne '' && -x "$directory/$name" && !-d "$directory/$name") {
			return 1;
		}
	}
	return 0;
} # FindProgram()

sub RunCommand { # \@command, $inputPath ; runs a program without a shell; returns (exit code, stdout bytes, stderr bytes)
	my $command = shift;
	my $inputPath = shift;
	my $directory = tempdir('quill-run-XXXXXX', TMPDIR => 1, CLEANUP => 1);
	my $errorPath = "$directory/stderr";
	my $pid = open(my $pipe, '-|');
	if (!defined($pid)) {
		Fail("Cannot start $command->[0]: $!");
	}
	if (!$pid) {
		open(STDIN, '<', defined($inputPath) ? $inputPath : '/dev/null') or POSIX::_exit(127);
		open(STDERR, '>', $errorPath) or POSIX::_exit(127);
		exec { $command->[0] } @$command or POSIX::_exit(127);
	}
	binmode($pipe);
	local $/;
	my $output = <$pipe>;
	close($pipe);
	my $code = $? >> 8;
	my $errors = -e $errorPath ? ReadBytes($errorPath) : '';
	return ($code, defined($output) ? $output : '', $errors);
} # RunCommand()

sub Request { # $site, $route, \%options ; one request; never forwards authored data via redirects
	# options: fields (form), files (field => [path, type], sends multipart), json (body),
	# headers (extra 'Name: value' lines), cookie ('name=value' held in memory for this run), timeout.
	# Returns ($status, $text, \%headers); headers carry every Set-Cookie under 'set-cookies'.
	my $site = shift;
	my $route = shift;
	my $options = shift || {};
	if (!FindProgram('curl')) {
		Fail('curl is required. Install it using your operating system tools.');
	}
	($TRACE{'route'}) = split(/\?/, $route, 2);
	WriteLog("Request($TRACE{'route'})");
	my $directory = tempdir('quill-http-XXXXXX', TMPDIR => 1, CLEANUP => 1);
	my $timeout = $options->{'timeout'} || 60;
	my @command = ('curl', '--disable', '--silent', '--show-error', '--max-time', $timeout,
		'--proto', '=http,https', '--output', "$directory/response", '--dump-header', "$directory/headers",
		'--write-out', '%{http_code}', '--user-agent', $USER_AGENT);
	if ($options->{'cookie'}) {
		push @command, '--header', 'Cookie: ' . HeaderValue($options->{'cookie'});
	}
	for my $line (@{$options->{'headers'} || []}) {
		push @command, '--header', HeaderValue($line);
	}
	my $input;
	if ($options->{'files'}) {
		# Field values go through files so curl never reads @ or < at the start of authored text.
		my $index = 0;
		for my $name (sort keys %{$options->{'fields'} || {}}) {
			my $part = "$directory/field-" . $index++;
			WriteText($part, $options->{'fields'}{$name});
			push @command, '--form', "$name=<$part";
		}
		for my $name (sort keys %{$options->{'files'}}) {
			my ($path, $kind) = @{$options->{'files'}{$name}};
			push @command, '--form', "$name=\@$path;type=$kind";
		}
	} elsif (defined($options->{'json'})) {
		$input = "$directory/request";
		WriteBytes($input, JSON::PP->new->utf8->canonical->encode($options->{'json'}));
		push @command, '--data-binary', '@-', '--header', 'Content-Type: application/json';
	} elsif (defined($options->{'fields'})) {
		$input = "$directory/request";
		WriteBytes($input, UrlEncode($options->{'fields'}));
		push @command, '--data-binary', '@-', '--header', 'Content-Type: application/x-www-form-urlencoded';
	}
	my ($code, $output, $errors) = RunCommand([@command, $site . $route], $input);
	if ($code) {
		my $message = decode('UTF-8', $errors);
		$message =~ s/\A\s+|\s+\z//g;
		Fail('Network outcome unknown: ' . $message);
	}
	my $text = eval { decode('UTF-8', ReadBytes("$directory/response"), FB_CROAK) };
	if ($output !~ m/\A\d+\z/ || !defined($text)) {
		Fail('Malformed response; keep any saved operation and check the instance URL.');
	}
	my %values;
	my @cookies;
	for my $line (split(/\r?\n/, ReadBytes("$directory/headers"))) {
		if ($line =~ m/\A([^:]*):(.*)\z/s) {
			my $key = lc($1);
			my $value = $2;
			$value =~ s/\A\s+|\s+\z//g;
			$values{$key} = $value;
			if ($key eq 'set-cookie') {
				push @cookies, $value;
			}
		}
	}
	$values{'set-cookies'} = \@cookies;
	my $notice = $values{'x-quill-update'};
	if ($notice && !$NOTICES{$notice}) {
		$NOTICES{$notice} = 1;
		print STDERR 'Update: ' . $notice . "\n";
	}
	return (int($output), $text, \%values);
} # Request()

sub JsonDecode { # $text ; decoded JSON value, or undef when the text is not JSON
	my $text = shift;
	my $value = eval { JSON::PP->new->allow_nonref->decode($text) };
	return $@ ? undef : $value;
} # JsonDecode()

sub ResponseError { # $status, $text ; the error message for an unexpected response
	my $status = shift;
	my $text = shift;
	my $payload = JsonDecode($text);
	my $message = ref($payload) eq 'HASH' ? $payload->{'error'} : undef;
	if (!defined($message) || ref($message) || $message eq '') {
		$message = 'Unexpected response. Check the instance URL, access policy and /api/.';
	}
	return "HTTP $status: $message";
} # ResponseError()

sub KeyValueLines { # $text ; key=value response lines as a hash
	my $text = shift;
	my %values;
	for my $line (split(/\r?\n/, $text)) {
		my ($key, $value) = split(/=/, $line, 2);
		if (defined($value)) {
			$values{$key} = $value;
		}
	}
	return \%values;
} # KeyValueLines()

sub ReadPost { # $site, $postId, $timeout ; post text, or undef when the site says it does not exist
	my $site = shift;
	my $postId = shift;
	my $timeout = shift;
	my ($status, $text) = Request($site, '/api/get_post?' . UrlEncode({'post_id' => $postId}), {'timeout' => $timeout});
	if ($status == 404 && StripDraft($text) eq 'post not found') {
		return undef;
	}
	if ($status != 200) {
		Fail(ResponseError($status, $text));
	}
	my ($head) = split(/\n\n/, $text, 2);
	if (index($text, "\n\n") < 0 || !grep { $_ eq 'Post-ID: ' . $postId } split(/\n/, $head)) {
		Fail('Unrecognized post response; publication status is unknown.');
	}
	return $text;
} # ReadPost()

sub Discover { # $site, $timeout ; 'signed' or 'prepared', from the site's route list
	my $site = shift;
	my $timeout = shift;
	my ($status, $text) = Request($site, '/api/', {'timeout' => $timeout});
	if ($status != 200) {
		Fail(ResponseError($status, $text));
	}
	if ($text =~ m/^POST \/api\/create_signed_post \[signed, json\]/m) {
		return 'signed';
	}
	my $prepared = 1;
	for my $route ('prepare_identity', 'create_identity', 'prepare_thread', 'prepare_reply', 'create_prepared_post') {
		$prepared = 0 if $text !~ m/^POST \/api\/$route \[signed, json\]/m;
	}
	if ($prepared) {
		return 'prepared';
	}
	Fail('Cannot identify a supported posting API. Inspect SITE/api/ before publishing; no write was sent.');
} # Discover()

sub SaveMeta { # $path, \%values ; writes a Key: value file atomically
	my $path = shift;
	my $values = shift;
	my %seen;
	my @keys = grep { exists($values->{$_}) && !$seen{$_}++ } (@META_ORDER, sort keys %$values);
	my $data = join('', map { HeaderValue($_) . ': ' . HeaderValue($values->{$_}) . "\n" } @keys);
	my $temporary = $path;
	$temporary =~ s/\.txt\z//;
	$temporary .= '.tmp';
	open(my $file, '>:raw', $temporary) or Fail("[Errno] Cannot write $temporary: $!");
	print {$file} encode('UTF-8', $data);
	$file->flush();
	$file->sync();
	close($file) or Fail("[Errno] Cannot write $temporary: $!");
	rename($temporary, $path) or Fail("[Errno] Cannot replace $path: $!");
} # SaveMeta()

sub LoadMeta { # $path ; a Key: value file as a hash
	my $path = shift;
	my %values;
	for my $line (split(/\n/, ReadText($path))) {
		my ($key, $value) = split(/: /, $line, 2);
		if (!defined($value)) {
			Fail("Malformed saved file: $path");
		}
		$values{$key} = $value;
	}
	return \%values;
} # LoadMeta()

sub GpgRun { # $home, \@arguments ; runs gpg and returns its output bytes
	my $home = shift;
	my $arguments = shift;
	if (!FindProgram('gpg')) {
		Fail('GPG is required for writing. Install it using your operating system tools.');
	}
	my @command = ('gpg', ($home ? ('--homedir', $home) : ()), @$arguments);
	my ($code, $output, $errors) = RunCommand(\@command);
	if ($code) {
		my $message = decode('UTF-8', $errors);
		$message =~ s/\A\s+|\s+\z//g;
		Fail('GPG failed. Check the key and unlock existing keys with GPG/pinentry: ' . $message);
	}
	return $output;
} # GpgRun()

sub Identity { # $args, $directory ; the key this state directory posts with, generating one on first use
	my $args = shift;
	my $directory = shift;
	my $file = "$directory/identity.txt";
	if (-e $file && !$args->{'signing_key'}) {
		return LoadMeta($file);
	}
	my $home = $args->{'gpg_home'} ? ResolvePath($args->{'gpg_home'}) : ($ENV{'GNUPGHOME'} || HomeDirectory() . '/.gnupg');
	my $selector = '';
	my $generated = 'no';
	if ($args->{'signing_key'}) {
		$selector = HeaderValue($args->{'signing_key'});
	} else {
		$home = "$directory/keyring";
		mkdir($home, 0700) if !-d $home;
		# A crash after generation must not cause a second key to be generated.
		my $existing = GpgRun($home, ['--batch', '--with-colons', '--list-secret-keys']);
		if (index($existing, 'sec:') < 0) {
			WriteLog('Identity: generating a key');
			GpgRun($home, ['--batch', '--pinentry-mode', 'loopback', '--passphrase', '',
				'--quick-generate-key', HeaderValue($args->{'name'}), 'ed25519', 'sign', '0']);
		}
		$generated = 'yes';
	}
	my $listing = decode('UTF-8', GpgRun($home, ['--batch', '--with-colons', '--list-secret-keys', '--', ($selector ne '' ? ($selector) : ())]));
	my @fingerprints;
	my $primary = 0;
	for my $line (split(/\n/, $listing)) {
		my @row = split(/:/, $line, -1);
		next if !@row;
		if ($row[0] eq 'sec' || $row[0] eq 'ssb') {
			$primary = $row[0] eq 'sec';
		} elsif ($row[0] eq 'fpr' && $primary) {
			push @fingerprints, $row[9];
			$primary = 0;
		}
	}
	if (@fingerprints != 1) {
		Fail('Select exactly one primary secret key with --signing-key FINGERPRINT.');
	}
	my %result = ('Fingerprint' => $fingerprints[0], 'Home' => $home, 'Generated' => $generated);
	SaveMeta($file, \%result);
	return \%result;
} # Identity()

sub Sign { # $folder, \%key, $source, $target ; detached armored signature of <source>.txt into <target>.txt
	my $folder = shift;
	my $key = shift;
	my $source = shift;
	my $target = shift;
	my @arguments = ('--batch', '--yes');
	if ($key->{'Generated'} eq 'yes') {
		push @arguments, '--pinentry-mode', 'loopback', '--passphrase', '';
	}
	push @arguments, '--local-user', $key->{'Fingerprint'}, '--armor', '--detach-sign',
		'--output', "$folder/$target.txt", "$folder/$source.txt";
	GpgRun($key->{'Home'}, \@arguments);
} # Sign()

sub SavedKey { # $args, $site ; the key this client already posts with; never generates one
	my $args = shift;
	my $site = shift;
	if ($args->{'signing_key'}) {
		my $directory = StateDirectory($args, $site);
		MakePath($directory);
		return Identity($args, $directory);
	}
	my $file = StateDirectory($args, $site) . '/identity.txt';
	if (!-e $file) {
		Fail('No key for this site yet. Post once with this state directory, or pass --signing-key FINGERPRINT.');
	}
	return LoadMeta($file);
} # SavedKey()

sub MakePath { # $path ; creates a private directory and its parents
	my $path = shift;
	if (-d $path) {
		return;
	}
	my $error;
	make_path($path, {'mode' => 0700, 'error' => \$error});
	if (!-d $path) {
		Fail("[Errno] Cannot create $path");
	}
} # MakePath()

sub SignIn { # $site, \%key, $timeout ; signs the server's challenge; returns the session for this run only
	# The cookie is never written to disk; each run signs in again.
	my $site = shift;
	my $key = shift;
	my $timeout = shift;
	my ($status, $text) = Request($site, '/api/auth_challenge', {'timeout' => $timeout});
	my $values = KeyValueLines($text);
	if ($status != 200 || !$values->{'challenge'}) {
		Fail("HTTP $status: " . ($values->{'error'} || 'no sign-in challenge'));
	}
	my $identityId = 'openpgp:' . lc($key->{'Fingerprint'});
	my $folder = tempdir('quill-signin-XXXXXX', TMPDIR => 1, CLEANUP => 1);
	WriteBytes("$folder/challenge.txt", encode('UTF-8', $values->{'challenge'}));
	Sign($folder, $key, 'challenge', 'challenge-signature');
	my $signature = ReadText("$folder/challenge-signature.txt");
	my $headers;
	($status, $text, $headers) = Request($site, '/api/authenticate_identity', {'timeout' => $timeout, 'fields' => {
		'challenge' => $values->{'challenge'}, 'identity_id' => $identityId, 'detached_signature' => $signature}});
	my $result = KeyValueLines($text);
	if ($status != 200 || ($result->{'status'} || '') ne 'ok') {
		Fail("Sign-in failed (HTTP $status): " . ($result->{'error'} || 'unexpected response'));
	}
	for my $value (@{$headers->{'set-cookies'}}) {
		my ($pair) = split(/;/, $value, 2);
		$pair =~ s/\A\s+|\s+\z//g;
		my ($name) = split(/=/, $pair, 2);
		if (index($pair, '=') >= 0 && grep { $_ eq $name } @SESSION_COOKIES) {
			return {'cookie' => $pair, 'identity_id' => $identityId, 'approved' => (($result->{'approved'} || '') eq '1')};
		}
	}
	Fail('Signed in, but the server set no session cookie.');
} # SignIn()

sub HttpDate { # $text ; seconds since the epoch for an RFC 1123 date, or undef
	my $text = shift;
	my %months = ('jan' => 0, 'feb' => 1, 'mar' => 2, 'apr' => 3, 'may' => 4, 'jun' => 5,
		'jul' => 6, 'aug' => 7, 'sep' => 8, 'oct' => 9, 'nov' => 10, 'dec' => 11);
	if ($text =~ m/\A\w{3}, (\d{1,2}) (\w{3}) (\d{4}) (\d{2}):(\d{2}):(\d{2}) GMT\z/ && exists($months{lc($2)})) {
		my $time = eval { timegm($6, $5, $4, $1, $months{lc($2)}, $3) };
		return $time;
	}
	return undef;
} # HttpDate()

sub Api { # $site, $route, \%fields, $args, $operation, \%files, $cookie ; JSON API call with bounded busy retries
	my $site = shift;
	my $route = shift;
	my $fields = shift;
	my $args = shift;
	my $operation = shift;
	my $files = shift;
	my $cookie = shift;
	for my $attempt (0 .. $args->{'retries'}) {
		my ($status, $text, $headers) = Request($site, $route, {'fields' => $fields, 'timeout' => $args->{'timeout'}, 'files' => $files, 'cookie' => $cookie});
		my $result = eval { JSON::PP->new->allow_nonref->decode($text) };
		if ($@) {
			Fail(ResponseError($status, $text));
		}
		if (ref($result) ne 'HASH') {
			Fail('Unrecognized API response; retain the saved operation.');
		}
		my $state = defined($result->{'status'}) ? $result->{'status'} : '';
		if ($status == 200 && $state eq 'ok') {
			return $result;
		}
		if ($status == 202 && $state eq 'staged' && $route eq '/api/create_signed_post') {
			PrintCharacters($result, $route, $fields, $operation);
			return $result;
		}
		if ($status == 503 && $state eq 'busy' && $attempt < $args->{'retries'}) {
			my $delay = defined($headers->{'retry-after'}) ? $headers->{'retry-after'} : (defined($result->{'retry_after'}) ? $result->{'retry_after'} : 10);
			if ($delay =~ m/\A\s*[-+]?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?\s*\z/) {
				$delay = $delay + 0;
			} else {
				my $time = HttpDate($delay);
				if (!defined($time)) {
					Fail('Invalid Retry-After. Keep the operation and retry later.');
				}
				$delay = $time - time();
			}
			if ($delay < 0 || $delay > 60) {
				Fail('Server requested a longer retry delay. Keep the operation and retry after: ' . (defined($headers->{'retry-after'}) ? $headers->{'retry-after'} : $delay));
			}
			$TRACE{'busy'}++;
			print 'Retry: busy; waiting ' . PyNumber($delay) . ' seconds (' . ($attempt + 1) . '/' . $args->{'retries'} . ")\n";
			select(undef, undef, undef, $delay);
			next;
		}
		my $code = defined($result->{'code'}) ? $result->{'code'} : '';
		if ($operation && ($status == 400 || $status == 403) && (($route eq '/api/create_signed_post' && ($code eq 'invalid_post' || $code eq 'access_denied')) || index($route, '/api/prepare_') == 0)) {
			my $meta = LoadMeta("$operation/operation.txt");
			$meta->{'State'} = 'rejected';
			SaveMeta("$operation/operation.txt", $meta);
		}
		PrintCharacters($result, $route, $fields, $operation);
		Fail(ResponseError($status, $text));
	}
} # Api()

sub PrintCharacters { # \%result, $route, \%fields, $operation ; reports each refused character at its position in the original draft
	my $result = shift;
	my $route = shift;
	my $fields = shift;
	my $operation = shift;
	my $chars = ref($result->{'chars'}) eq 'ARRAY' ? $result->{'chars'} : [];
	for my $char (@$chars) {
		next if ref($char) ne 'HASH';
		my $index = $char->{'index'};
		my $field = defined($result->{'field'}) ? $result->{'field'} : '';
		my $isInteger = defined($index) && !ref($index) && $index =~ m/\A-?\d+\z/;
		if ($operation && $route eq '/api/create_signed_post' && ($field eq 'body' || $field eq 'subject') && $isInteger) {
			my $original = ReadText("$operation/original-$field.txt");
			my $canonical = '';
			if ($field eq 'body') {
				(undef, $canonical) = split(/\n\n/, $fields->{'canonical_record'}, 2);
			} else {
				for my $line (split(/\n/, $fields->{'canonical_record'})) {
					if (index($line, 'Subject: ') == 0) {
						$canonical = substr($line, 9);
						last;
					}
				}
			}
			$index = SourceIndex($original, defined($canonical) ? $canonical : '', $index);
		}
		my $shown = (defined($char->{'char'}) && !ref($char->{'char'})) ? PyRepr($char->{'char'}) . ' ' : '';
		my $codepoint = defined($char->{'codepoint'}) ? $char->{'codepoint'} : 'None';
		print STDERR "Character: $shown$codepoint at " . ($field ne '' ? $field : 'None') . ' index ' . (defined($index) ? $index : 'None') . "\n";
	}
} # PrintCharacters()

sub SavePayload { # $operation, \%meta, $route, \%payload, $phase ; saves request fields as files before sending
	my $operation = shift;
	my $meta = shift;
	my $route = shift;
	my $payload = shift;
	my $phase = shift;
	for my $name (sort keys %$payload) {
		WriteText("$operation/$name.txt", $payload->{$name});
	}
	$meta->{'Route'} = $route;
	$meta->{'Fields'} = join(',', sort keys %$payload);
	$meta->{'State'} = $phase;
	SaveMeta("$operation/operation.txt", $meta);
} # SavePayload()

sub PayloadFrom { # $operation, \%meta ; request fields saved by SavePayload()
	my $operation = shift;
	my $meta = shift;
	my @names = split(/,/, $meta->{'Fields'});
	for my $name (@names) {
		if ($name !~ m/\A[a-z_]+\z/) {
			Fail('Invalid saved field list.');
		}
	}
	return {map { $_ => ReadText("$operation/$_.txt") } @names};
} # PayloadFrom()

sub Submit { # $operation, \%meta, $args ; sends the saved request and records the outcome
	my $operation = shift;
	my $meta = shift;
	my $args = shift;
	$meta->{'State'} = 'sending';
	SaveMeta("$operation/operation.txt", $meta);
	my $files;
	if ($meta->{'Image'} && $meta->{'Route'} eq '/api/create_prepared_post') {
		my ($extension) = $meta->{'Image'} =~ m/\.([a-z]+)\z/;
		$files = {'image' => ["$operation/$meta->{'Image'}", $IMAGE_TYPES{$extension}]};
	}
	my $result = Api($meta->{'Site'}, $meta->{'Route'}, PayloadFrom($operation, $meta), $args, $operation, $files);
	my $expected = $meta->{'Post-ID'};
	if ($expected && ($result->{'post_id'} || '') ne $expected) {
		Fail('Unexpected publication ID. Keep this operation and check its saved Post-ID.');
	}
	if (($result->{'status'} || '') eq 'staged') {
		$meta->{'State'} = 'staged';
		SaveMeta("$operation/operation.txt", $meta);
		print "Staged: the server kept this post but has not published it.\n";
		print 'Reason: ' . ($result->{'reason'} || 'unknown') . "\n";
		print 'Post-ID: ' . $result->{'post_id'} . "\n";
		print 'View: ' . $meta->{'Site'} . "/staged/\n";
		print "Fix the draft, then publish it under the same post ID:\n";
		print 'Replace: ' . ReplaceCommand($meta) . "\n";
		return $STAGED_EXIT;
	}
	if ($meta->{'Replaces'}) {
		my $stagedMeta = LoadMeta("$meta->{'Replaces'}/operation.txt");
		$stagedMeta->{'State'} = 'replaced';
		SaveMeta("$meta->{'Replaces'}/operation.txt", $stagedMeta);
	}
	$meta->{'State'} = 'accepted';
	SaveMeta("$operation/operation.txt", $meta);
	print "Status: accepted\nPost-ID: $result->{'post_id'}\n";
	if ($result->{'refresh_pending'}) {
		print "Refresh: pending; resume this exact operation to finish refresh.\n";
	}
	print 'URL: ' . $meta->{'Site'} . '/posts/' . $result->{'post_id'} . "\n";
	return 0;
} # Submit()

sub UtcStamp { # $time, $format ; strftime in UTC
	my $time = shift;
	my $format = shift;
	return strftime($format, gmtime($time));
} # UtcStamp()

sub NewPost { # $args, $site, $directory, $operation, \%savedMeta, \%savedKey ; builds, saves, signs and sends a write
	my $args = shift;
	my $site = shift;
	my $directory = shift;
	my $operation = shift;
	my $savedMeta = shift;
	my $savedKey = shift;
	my $original = ReadText($args->{'body_file'});
	if (StripDraft($original) eq '') {
		Fail('Body is required.');
	}
	my $protocol = $savedMeta ? $savedMeta->{'Protocol'} : Discover($site, $args->{'timeout'});
	my $image;
	if (!$savedMeta && $args->{'image'}) {
		if ($original =~ m/^Image-v1:/m) {
			Fail('Remove the Image-v1 line from the body; --image adds it.');
		}
		my $description = $args->{'image_description'};
		if (length(encode('UTF-8', $description)) > 500 || $description =~ m/[\x00-\x1f\x7f]/) {
			Fail('Image descriptions must be at most 500 bytes and contain no control characters.');
		}
		my ($prepared, $kind, $width, $height, $note) = PrepareImage($args->{'image'});
		$image = [$kind, ImageReference($prepared, $kind, $width, $height, $description), $prepared];
		print sprintf("Image: %d x %d %s, %d KiB, %s\n", $width, $height, uc($kind), int((length($prepared) + 1023) / 1024), $note);
		# Only the prepared publish accepts the image file.
		$protocol = 'prepared';
	}
	my $key = $savedKey || ($args->{'replaces'} ? LoadMeta("$args->{'replaces'}/key.txt") : Identity($args, $directory));
	my $fingerprint = $key->{'Fingerprint'};
	my $author = 'openpgp:' . lc($fingerprint);
	my $meta;
	if (!$operation) {
		$operation = "$directory/operation-" . RandomHex(16);
		mkdir($operation, 0700) or Fail("[Errno] Cannot create $operation: $!");
		WriteText("$operation/original-body.txt", $original);
		WriteText("$operation/original-subject.txt", $args->{'subject'});
		SaveMeta("$operation/key.txt", $key);
		$meta = {'Site' => $site, 'Protocol' => $protocol, 'Kind' => $args->{'command'}, 'State' => 'preparing', 'Identity-ID' => $author,
			'Thread-ID' => $args->{'thread'} || '', 'Parent-ID' => $args->{'parent'} || $args->{'thread'} || ''};
		if ($args->{'replaces'}) {
			if ($protocol ne 'signed') {
				Fail('This site does not take one-shot signed posts, so --replace is unavailable.');
			}
			$meta->{'Replaces'} = $args->{'replaces'};
			$meta->{'Replace-Post-ID'} = $args->{'replace'};
		}
		if ($image) {
			# The operation keeps its own copy, so a resume sends the bytes that were signed.
			WriteBytes("$operation/image.$image->[0]", $image->[2]);
			$meta->{'Image'} = 'image.' . $image->[0];
			$meta->{'Image-Line'} = $image->[1];
		}
		SaveMeta("$operation/operation.txt", $meta);
	} else {
		$meta = $savedMeta;
	}
	$TRACE{'operation'} = $operation;
	$TRACE{'identity'} = $author;
	print "Operation: $operation\n";
	print "Identity-ID: $author\n";
	my $outcome = eval {
		my $public = GpgRun($key->{'Home'}, ['--batch', '--armor', '--export', $fingerprint]);
		if ($protocol eq 'signed') {
			SignedPost($args, $site, $operation, $meta, $key, $author, $public, $original);
		} else {
			PreparedPost($operation, $meta, $args, $key, $public, $original);
		}
		return Submit($operation, $meta, $args);
	};
	if (!defined($outcome)) {
		my $error = $@;
		print STDERR "Saved-operation: $operation\n";
		if (IsRejected($operation)) {
			print STDERR $REJECTED_GUIDANCE . "\n";
			die $error;
		}
		print STDERR 'Resume: ' . RecoveryCommand($operation, $meta) . "\n";
		if ($meta->{'Post-ID'}) {
			print STDERR 'Check: perl ' . Program() . ' --site ' . ShellQuote($site) . ' check ' . ShellQuote($meta->{'Post-ID'}) . "\n";
		}
		die $error;
	}
	return $outcome;
} # NewPost()

sub SignedPost { # $args, $site, $operation, \%meta, \%key, $author, $public, $original ; builds and signs the one-request record
	my $args = shift;
	my $site = shift;
	my $operation = shift;
	my $meta = shift;
	my $key = shift;
	my $author = shift;
	my $public = shift;
	my $original = shift;
	my ($status, $text) = Request($site, '/api/text_rules', {'timeout' => $args->{'timeout'}});
	my $rules = $status == 200 ? JsonDecode($text) : {};
	if ($status != 200 && $status != 404) {
		Fail(ResponseError($status, $text));
	}
	if ($status == 200 && (ref($rules) ne 'HASH' || !defined($rules->{'mode'}) || ($rules->{'mode'} ne 'ascii' && $rules->{'mode'} ne 'unicode'))) {
		Fail('Unrecognized text rules; original draft retained.');
	}
	if ($status == 404) {
		print "Text-rules: unavailable; server validation still applies. No rejected characters will be replaced.\n";
	}
	my $unicodeMode = ($rules->{'mode'} || '') eq 'unicode';
	my $body = StripDraft($original);
	$body =~ s/\r\n/\n/g;
	my $subject = HeaderValue(StripDraft($args->{'subject'}));
	if ($unicodeMode) {
		$body =~ s/\r/\n/g;
		$body = NFC($body);
		$subject = NFC($subject);
	}
	$body .= "\n";
	if ($body ne $original || $subject ne $args->{'subject'}) {
		print 'Normalization: canonical whitespace/newlines' . ($unicodeMode ? ' and NFC' : '') . "; original files retained.\n";
	}
	my $now = time();
	my $timestamp = UtcStamp($now, '%Y-%m-%dT%H:%M:%SZ');
	my $prefix = UtcStamp($now, '%Y%m%d%H%M%S');
	my $postId = $args->{'command'} . '-' . $prefix . '-' . RandomHex(16);
	my $postTimestamp = $timestamp;
	if ($meta->{'Replaces'}) {
		# Same post ID and Created-At as the staged post; the server records when it arrived.
		$postId = $meta->{'Replace-Post-ID'};
		my $stamp = (split(/-/, $postId))[1];
		$postTimestamp = sprintf('%s-%s-%sT%s:%s:%sZ', substr($stamp, 0, 4), substr($stamp, 4, 2), substr($stamp, 6, 2),
			substr($stamp, 8, 2), substr($stamp, 10, 2), substr($stamp, 12, 2));
	}
	my $thread = $args->{'thread'} || '';
	my $canonical = Record($postId, $args->{'tags'}, $body, $subject, $thread, $args->{'parent'} || $thread, $postTimestamp, $author);
	my $bootstrap = Record('bootstrap-' . $prefix . '-' . RandomHex(16), 'identity internal',
		"Automatic account bootstrap anchor.\n", 'account bootstrap', '', '', $timestamp, $author);
	my %payload = ('canonical_record' => $canonical, 'public_key' => decode('UTF-8', $public), 'bootstrap_record' => $bootstrap);
	for my $name (sort keys %payload) {
		WriteText("$operation/$name.txt", $payload{$name});
	}
	Sign($operation, $key, 'canonical_record', 'detached_signature');
	Sign($operation, $key, 'bootstrap_record', 'bootstrap_signature');
	$payload{'detached_signature'} = ReadText("$operation/detached_signature.txt");
	$payload{'bootstrap_signature'} = ReadText("$operation/bootstrap_signature.txt");
	$meta->{'Post-ID'} = $postId;
	SavePayload($operation, $meta, '/api/create_signed_post', \%payload, 'ready');
} # SignedPost()

sub Record { # $id, $tags, $content, $title, $thread, $parent, $created, $author ; a canonical post record
	my $id = shift;
	my $tags = shift;
	my $content = shift;
	my $title = shift;
	my $thread = shift;
	my $parent = shift;
	my $created = shift;
	my $author = shift;
	my @lines = ('Post-ID: ' . $id, 'Created-At: ' . $created, 'Board-Tags: ' . $tags);
	if ($thread ne '') {
		push @lines, 'Thread-ID: ' . HeaderValue($thread), 'Parent-ID: ' . HeaderValue($parent);
	}
	push @lines, 'Author-Identity-ID: ' . $author;
	if ($title ne '') {
		push @lines, 'Subject: ' . $title;
	}
	return join("\n", @lines) . "\n\n" . $content;
} # Record()

sub ImageSize { # $data, $kind ; pixel width and height from a PNG IHDR or a JPEG start-of-frame segment
	my $data = shift;
	my $kind = shift;
	if ($kind eq 'png') {
		if (length($data) < 24 || substr($data, 12, 4) ne 'IHDR') {
			Fail('Image is not a readable PNG.');
		}
		return (unpack('N', substr($data, 16, 4)), unpack('N', substr($data, 20, 4)));
	}
	my %frames = map { $_ => 1 } (0xC0, 0xC1, 0xC2, 0xC3, 0xC5, 0xC6, 0xC7, 0xC9, 0xCA, 0xCB, 0xCD, 0xCE, 0xCF);
	for my $segment (JpegSegments($data)) {
		my ($marker, $bytes) = @$segment;
		if ($frames{$marker} && length($bytes) >= 9) {
			return (unpack('n', substr($bytes, 7, 2)), unpack('n', substr($bytes, 5, 2)));
		}
	}
	Fail('Image is not a readable JPEG.');
} # ImageSize()

sub JpegSegments { # $data ; [marker, bytes] for each header segment up to the start of scan; the last is the rest of the file
	my $data = shift;
	if (substr($data, 0, 2) ne "\xff\xd8") {
		Fail('Image is not a readable JPEG.');
	}
	my @segments;
	my $position = 2;
	while ($position + 4 <= length($data)) {
		if (ord(substr($data, $position, 1)) != 0xFF) {
			Fail('Image is not a readable JPEG.');
		}
		my $marker = ord(substr($data, $position + 1, 1));
		if ($marker == 0xFF) {
			$position += 1;
			next;
		}
		if ($marker == 0xDA) {
			push @segments, [$marker, substr($data, $position)];
			return @segments;
		}
		my $length = unpack('n', substr($data, $position + 2, 2));
		if ($length < 2 || $position + 2 + $length > length($data)) {
			Fail('Image is not a readable JPEG.');
		}
		push @segments, [$marker, substr($data, $position, 2 + $length)];
		$position += 2 + $length;
	}
	Fail('Image is not a readable JPEG.');
} # JpegSegments()

sub JpegOrientation { # $segment ; EXIF orientation from an APP1 segment, or 1 when it has none
	my $segment = shift;
	my $body = substr($segment, 4);
	if (substr($body, 0, 6) ne "Exif\x00\x00" || length($body) < 14) {
		return 1;
	}
	my $tiff = substr($body, 6);
	my $order = substr($tiff, 0, 2);
	if ($order ne 'II' && $order ne 'MM') {
		return 1;
	}
	my $short = $order eq 'II' ? 'v' : 'n';
	my $long = $order eq 'II' ? 'V' : 'N';
	my $offset = unpack($long, substr($tiff, 4, 4));
	if ($offset + 2 > length($tiff)) {
		return 1;
	}
	my $count = unpack($short, substr($tiff, $offset, 2));
	for my $index (0 .. $count - 1) {
		my $start = $offset + 2 + $index * 12;
		last if $start + 12 > length($tiff);
		my $entry = substr($tiff, $start, 12);
		if (unpack($short, substr($entry, 0, 2)) == 0x0112) {
			return unpack($short, substr($entry, 8, 2));
		}
	}
	return 1;
} # JpegOrientation()

sub StripImageMetadata { # $data, $kind ; drops metadata the way the browser's re-encode does, without changing any pixels
	# PNG: remove text, EXIF and timestamp chunks. JPEG: remove comments and APP segments other than
	# JFIF (APP0) and Adobe (APP14), which describe the colour data. A rotated photo can't be fixed
	# without re-encoding, so it is refused.
	my $data = shift;
	my $kind = shift;
	if ($kind eq 'png') {
		my $end = index($data, 'IDAT');
		$end = length($data) - 1 if $end < 0;
		if (index(substr($data, 0, $end), 'acTL') >= 0) {
			Fail('Animated PNGs are not supported here; export a still image, or use quill.py with Pillow to keep one frame.');
		}
		my $out = substr($data, 0, 8);
		my $position = 8;
		while ($position + 12 <= length($data)) {
			my $length = unpack('N', substr($data, $position, 4));
			my $chunk = substr($data, $position, 12 + $length);
			if (!$PNG_METADATA_CHUNKS{substr($chunk, 4, 4)}) {
				$out .= $chunk;
			}
			$position += 12 + $length;
		}
		return $out;
	}
	my $out = "\xff\xd8";
	for my $segment (JpegSegments($data)) {
		my ($marker, $bytes) = @$segment;
		if ($marker == 0xE1 && JpegOrientation($bytes) != 1) {
			Fail('This photo is stored rotated. Export an upright copy, or use quill.py with Pillow to turn it upright.');
		}
		next if $marker == 0xFE || ($marker >= 0xE1 && $marker <= 0xEF && $marker != 0xEE);
		$out .= $bytes;
	}
	return $out;
} # StripImageMetadata()

sub PrepareImage { # $path ; (bytes to upload, 'png'|'jpg', width, height, note) for --image
	# An already-prepared PNG or JPEG is sent with its metadata removed. Resizing and converting
	# other formats needs Pillow, which only quill.py can use.
	my $path = shift;
	my $data = ReadBytes($path);
	if ($data eq '' || length($data) > $IMAGE_SOURCE_BYTES) {
		Fail('Attach an image of at most 32 MiB.');
	}
	my $kind = substr($data, 0, 8) eq "\x89PNG\r\n\x1a\n" ? 'png' : (substr($data, 0, 3) eq "\xff\xd8\xff" ? 'jpg' : '');
	if ($kind eq '') {
		Fail('quill.pl sends PNG or JPEG only. Export the image as PNG or JPEG, or use quill.py with Pillow to convert other formats.');
	}
	my ($width, $height) = ImageSize($data, $kind);
	if (!($width > 0 && $width <= $IMAGE_MAX_SIDE && $height > 0 && $height <= $IMAGE_MAX_SIDE)) {
		Fail(sprintf('quill.pl sends images of at most %d pixels per side (this one is %d x %d). Export a smaller copy, or use quill.py with Pillow to resize automatically.', $IMAGE_MAX_SIDE, $width, $height));
	}
	my $prepared = StripImageMetadata($data, $kind);
	if (length($prepared) > $IMAGE_MAX_BYTES) {
		Fail('quill.pl sends images of at most 5 MiB. Export a smaller copy, or use quill.py with Pillow to shrink automatically.');
	}
	return ($prepared, $kind, $width, $height, 'sent as is, metadata removed');
} # PrepareImage()

sub ImageReference { # $data, $kind, $width, $height, $description ; the signed Image-v1 line for prepared bytes
	my $data = shift;
	my $kind = shift;
	my $width = shift;
	my $height = shift;
	my $description = shift;
	my $bytes = encode('UTF-8', $description);
	if (length($bytes) > 500 || $description =~ m/[\x00-\x1f\x7f]/) {
		Fail('Image descriptions must be at most 500 bytes and contain no control characters.');
	}
	my $line = sprintf('Image-v1: %s %s %d %d %d', sha256_hex($data), $kind, length($data), $width, $height);
	if ($description ne '') {
		$bytes =~ s/([^A-Za-z0-9_.~-])/sprintf('%%%02X', ord($1))/ge;
		$line .= ' ' . $bytes;
	}
	return $line;
} # ImageReference()

sub BoardTags { # $value ; normalized --tags, or undef when invalid
	my $value = join(' ', split(' ', shift));
	if ($value !~ m/\A[a-z0-9-]+(?: [a-z0-9-]+)*\z/ || grep { $_ eq 'identity' } split(/ /, $value)) {
		return undef;
	}
	return $value;
} # BoardTags()

sub PreparedPost { # $operation, \%meta, $args, \%key, $public, $body ; registers if needed, then signs the server's prepared record
	my $operation = shift;
	my $meta = shift;
	my $args = shift;
	my $key = shift;
	my $public = shift;
	my $body = shift;
	my $site = $meta->{'Site'};
	my $author = $meta->{'Identity-ID'};
	(my $slug = $author) =~ s/:/-/g;
	my ($status, $text) = Request($site, '/api/get_profile?' . UrlEncode({'profile_slug' => $slug}), {'timeout' => $args->{'timeout'}});
	if ($status == 404 && StripDraft($text) eq 'profile not found') {
		my $prepared = Api($site, '/api/prepare_identity', {'public_key' => decode('UTF-8', $public)}, $args, $operation);
		my %payload = map { $_ => $prepared->{$_} } ('prepare_token', 'canonical_record');
		SavePayload($operation, $meta, '/api/create_identity', \%payload, 'registration-ready');
		Sign($operation, $key, 'canonical_record', 'detached_signature');
		$payload{'detached_signature'} = ReadText("$operation/detached_signature.txt");
		SavePayload($operation, $meta, '/api/create_identity', \%payload, 'registration-sending');
		my $result = Api($site, '/api/create_identity', \%payload, $args, $operation);
		if (($result->{'identity_id'} || '') ne $author) {
			Fail('Unexpected registration identity. Reconcile the saved identity before continuing.');
		}
		$meta->{'State'} = 'registered';
		SaveMeta("$operation/operation.txt", $meta);
	} elsif ($status != 200 || !grep { $_ eq 'Identity-ID: ' . $author } split(/\n/, $text)) {
		Fail('Cannot resolve existing registration. Check instance access and the saved identity.');
	}
	if ($meta->{'Image-Line'}) {
		$body =~ s/\s+\z//;
		$body .= "\n\n" . $meta->{'Image-Line'};
	}
	my %fields = ('author_identity_id' => $author, 'body' => $body);
	if ($args->{'command'} eq 'thread') {
		$fields{'subject'} = $args->{'subject'};
		$fields{'board_tags'} = $args->{'tags'};
	} else {
		$fields{'thread_id'} = $args->{'thread'};
		$fields{'parent_id'} = $args->{'parent'} || $args->{'thread'};
	}
	my $prepared = Api($site, '/api/prepare_' . $args->{'command'}, \%fields, $args, $operation);
	my %payload = map { $_ => $prepared->{$_} } ('prepare_token', 'canonical_record', 'record_path', 'post_id');
	$payload{'author_identity_id'} = $author;
	$meta->{'Post-ID'} = $prepared->{'post_id'};
	SavePayload($operation, $meta, '/api/create_prepared_post', \%payload, 'ready');
	Sign($operation, $key, 'canonical_record', 'detached_signature');
	$payload{'detached_signature'} = ReadText("$operation/detached_signature.txt");
	SavePayload($operation, $meta, '/api/create_prepared_post', \%payload, 'ready');
	print "Normalization: signed exact server canonical bytes; original files retained.\n";
} # PreparedPost()

sub ReplaceCommand { # \%meta ; the printed command that publishes a fix for a staged post
	my $meta = shift;
	my $command = 'perl ' . Program() . ' --site ' . ShellQuote($meta->{'Site'}) . ' ' . $meta->{'Kind'} . ' --replace ' . ShellQuote($meta->{'Post-ID'}) . ' --body-file FIXED_DRAFT';
	return $command . ($meta->{'Kind'} eq 'thread' ? ' --subject SUBJECT' : '');
} # ReplaceCommand()

sub StagedOperation { # $state, $site, $kind, $postId ; the saved operation that staged this post on this site
	my $state = shift;
	my $site = shift;
	my $kind = shift;
	my $postId = shift;
	for my $file (ListOperations($state)) {
		my $meta = LoadMeta($file);
		if (($meta->{'Post-ID'} || '') eq $postId && ($meta->{'Site'} || '') eq $site && ($meta->{'State'} || '') eq 'staged') {
			if (($meta->{'Kind'} || '') ne $kind) {
				Fail("Post $postId was staged as a $meta->{'Kind'}; use that command to replace it.");
			}
			return (dirname($file), $meta);
		}
	}
	Fail("No staged operation for $postId in this state directory. Replace it from the client that sent it.");
} # StagedOperation()

sub IsRejected { # $operation ; true when the server refused this operation
	my $operation = shift;
	my $meta = eval { LoadMeta("$operation/operation.txt") };
	return $meta && ($meta->{'State'} || '') eq 'rejected';
} # IsRejected()

sub RecoveryCommand { # $operation, \%meta ; the printed command that resumes this operation
	my $operation = shift;
	my $meta = shift;
	return 'perl ' . Program() . ' --site ' . ShellQuote($meta->{'Site'}) . ' ' . $meta->{'Kind'} . ' --resume ' . ShellQuote($operation);
} # RecoveryCommand()

sub SourceIndex { # $original, $canonical, $index ; maps an index in the canonical text back to the original draft
	# NFD lengths align NFC output to original code points without rewriting input.
	my $original = shift;
	my $canonical = shift;
	my $index = shift;
	my $trimmed = StripDraft($original);
	(my $leading = $original) =~ s/\A([ \t\r\n\0\x0b]*).*\z/$1/s;
	my $offset = length($leading);
	my $target = length(NFD(substr($canonical, 0, $index)));
	my $at = 0;
	my @chars = split(//, $trimmed);
	for my $i (0 .. $#chars) {
		if ($chars[$i] eq "\r" && $i + 1 <= $#chars && $chars[$i + 1] eq "\n") {
			next;
		}
		if ($at >= $target) {
			return $offset + $i;
		}
		$at += length(NFD($chars[$i]));
	}
	return $index;
} # SourceIndex()

sub ResumePost { # $operation, \%meta, $args ; continues a saved operation from its recorded state
	my $operation = shift;
	my $meta = shift;
	my $args = shift;
	if ($meta->{'Protocol'} ne 'signed' && $meta->{'Protocol'} ne 'prepared') {
		Fail('Unknown saved protocol; no request sent.');
	}
	my $route = $meta->{'Route'};
	if (defined($route) && !grep { $_ eq $route } ('/api/create_signed_post', '/api/create_prepared_post', '/api/create_identity')) {
		Fail('Unknown saved route; no request sent.');
	}
	my $key = LoadMeta("$operation/key.txt");
	my $state = $meta->{'State'};
	my $protocol = $meta->{'Protocol'};
	if ($state eq 'staged' || $state eq 'replaced') {
		Fail('This post was staged, not lost; resending the same draft stages it again. ' . ($state eq 'staged' ? 'Publish a fix with: ' . ReplaceCommand($meta) : 'It was replaced by a fixed version.'));
	}
	if ($protocol eq 'signed' && ($state eq 'ready' || $state eq 'sending' || $state eq 'accepted')) {
		return Submit($operation, $meta, $args);
	}
	if ($state eq 'rejected') {
		Fail('This operation was rejected. Correct the original draft and start a new write; do not edit signed files.');
	}
	if ($protocol eq 'prepared' && $state eq 'sending') {
		my $text = ReadPost($meta->{'Site'}, $meta->{'Post-ID'}, $args->{'timeout'});
		if (defined($text)) {
			my (undef, $published) = split(/\n\n/, $text, 2);
			my (undef, $saved) = split(/\n\n/, ReadText("$operation/canonical_record.txt"), 2);
			if (($published // '') ne ($saved // '')) {
				Fail('Published body differs from the saved operation; ask the operator to reconcile it.');
			}
			$meta->{'State'} = 'accepted';
			SaveMeta("$operation/operation.txt", $meta);
			print "Status: found\nPost-ID: $meta->{'Post-ID'}\n";
			return 0;
		}
		Fail('Prepared publication outcome is unresolved. Keep this operation; ask the operator to confirm its Post-ID and token before making another post. A missing read is not proof it failed.');
	}
	if ($protocol eq 'prepared' && $state eq 'accepted') {
		print "Status: accepted\nPost-ID: $meta->{'Post-ID'}\n";
		return 0;
	}
	if ($protocol eq 'prepared' && $state eq 'ready') {
		my $payload = PayloadFrom($operation, $meta);
		if (!exists($payload->{'detached_signature'})) {
			Sign($operation, $key, 'canonical_record', 'detached_signature');
			$payload->{'detached_signature'} = ReadText("$operation/detached_signature.txt");
			SavePayload($operation, $meta, '/api/create_prepared_post', $payload, 'ready');
		}
		return Submit($operation, $meta, $args);
	}
	if ($protocol eq 'prepared' && ($state eq 'registration-ready' || $state eq 'registration-sending')) {
		my $author = $meta->{'Identity-ID'};
		(my $slug = $author) =~ s/:/-/g;
		my ($status, $text) = Request($meta->{'Site'}, '/api/get_profile?' . UrlEncode({'profile_slug' => $slug}), {'timeout' => $args->{'timeout'}});
		if ($status == 200 && grep { $_ eq 'Identity-ID: ' . $author } split(/\n/, $text)) {
			$meta->{'State'} = 'registered';
			SaveMeta("$operation/operation.txt", $meta);
		} elsif ($state eq 'registration-ready' && $status == 404 && StripDraft($text) eq 'profile not found') {
			my $payload = PayloadFrom($operation, $meta);
			Sign($operation, $key, 'canonical_record', 'detached_signature');
			$payload->{'detached_signature'} = ReadText("$operation/detached_signature.txt");
			SavePayload($operation, $meta, '/api/create_identity', $payload, 'registration-sending');
			my $result = Api($meta->{'Site'}, '/api/create_identity', $payload, $args, $operation);
			if (($result->{'identity_id'} || '') ne $author) {
				Fail('Registration identity mismatch; reconcile with the operator.');
			}
			$meta->{'State'} = 'registered';
			SaveMeta("$operation/operation.txt", $meta);
		} else {
			Fail('Registration outcome unresolved. Keep this key and operation; ask the operator to reconcile the saved identity/token. No new registration sent.');
		}
	}
	# These states have not submitted an ordinary post. Restore original inputs.
	$args->{'body_file'} = "$operation/original-body.txt";
	$args->{'subject'} = ReadText("$operation/original-subject.txt");
	$args->{'thread'} = $meta->{'Thread-ID'} || undef;
	$args->{'parent'} = $meta->{'Parent-ID'} || undef;
	if ($protocol eq 'signed' && -e "$operation/canonical_record.txt" && -e "$operation/bootstrap_record.txt") {
		my %payload = map { $_ => ReadText("$operation/$_.txt") } ('canonical_record', 'bootstrap_record', 'public_key');
		for my $pair (['canonical_record', 'detached_signature'], ['bootstrap_record', 'bootstrap_signature']) {
			my ($source, $target) = @$pair;
			if (!-e "$operation/$target.txt") {
				Sign($operation, $key, $source, $target);
			}
			$payload{$target} = ReadText("$operation/$target.txt");
		}
		my ($first) = split(/\n/, $payload{'canonical_record'});
		$meta->{'Post-ID'} = (split(/: /, $first, 2))[1];
		SavePayload($operation, $meta, '/api/create_signed_post', \%payload, 'ready');
		return Submit($operation, $meta, $args);
	}
	if ($meta->{'State'} ne 'preparing' && $meta->{'State'} ne 'registered') {
		Fail('Unrecognized operation state; no request sent.');
	}
	return NewPost($args, $meta->{'Site'}, dirname($operation), $operation, $meta, $key);
} # ResumePost()

sub DefaultStateDir { # ; ~/.local/state/quill, unless only frm.py's old directory exists: keys and saved operations live there
	my $base = HomeDirectory() . '/.local/state';
	if (!-e "$base/quill" && -d "$base/frm") {
		print STDERR "State: using $base/frm, kept from frm.py.\n";
		return "$base/frm";
	}
	return "$base/quill";
} # DefaultStateDir()

sub StateDirectory { # $args, $site ; this site's private directory: keys, saved operations, queued reports
	my $args = shift;
	my $site = shift;
	if ($args->{'resume'}) {
		return dirname(ResolvePath($args->{'resume'}));
	}
	if (!$args->{'state_dir'}) {
		$args->{'state_dir'} = DefaultStateDir();
	}
	return ResolvePath($args->{'state_dir'}) . '/' . sha256_hex(encode('UTF-8', $site));
} # StateDirectory()

sub PostTroubleReport { # $site, $threadId, $body, $identityId, $timeout ; unsigned reply, so a broken key or registration can still be reported
	my $site = shift;
	my $threadId = shift;
	my $body = shift;
	my $identityId = shift;
	my $timeout = shift;
	my %fields = ('thread_id' => $threadId, 'parent_id' => $threadId, 'body' => $body, 'body_format' => 'plain');
	if ($identityId) {
		$fields{'author_identity_id'} = $identityId;
	}
	while (1) {
		my ($status, $text) = Request($site, '/api/create_reply', {'fields' => \%fields, 'timeout' => $timeout});
		my $values = KeyValueLines($text);
		if ($status == 200 && ($values->{'status'} || '') eq 'ok' && $values->{'post_id'}) {
			return $values->{'post_id'};
		}
		if ($status == 400 && exists($fields{'author_identity_id'})) {
			delete $fields{'author_identity_id'}; # unregistered identity: report anonymously instead
			next;
		}
		Fail("HTTP $status: " . ($values->{'error'} || 'unexpected reply response'));
	}
} # PostTroubleReport()

sub ReportTrouble { # $args, $site, $error ; after a write: posts queued reports, then this run's report. Never dies
	my $args = shift;
	my $site = shift;
	my $error = shift;
	my $ok = eval { ReportTroubleNow($args, $site, $error); 1 };
	if (!$ok) {
		# A report must never hide or replace the write's own outcome.
		print STDERR "Trouble-report: not sent (error).\n";
	}
} # ReportTrouble()

sub ReportTroubleNow { # $args, $site, $error ; the body of ReportTrouble()
	my $args = shift;
	my $site = shift;
	my $error = shift;
	if ($args->{'no_report'}) {
		return;
	}
	my $operation = $TRACE{'operation'};
	my $meta = {};
	if ($operation) {
		$meta = eval { LoadMeta("$operation/operation.txt") } || {};
	}
	my $outcome;
	if (!defined($error) && !$TRACE{'busy'}) {
		$outcome = undef;
	} elsif (!defined($error)) {
		$outcome = 'accepted after busy retries';
	} elsif (($meta->{'State'} || '') eq 'rejected') {
		$outcome = 'rejected';
	} elsif (%$meta && ($meta->{'State'} || '') ne 'accepted') {
		$outcome = 'unresolved';
	} else {
		$outcome = 'failed';
	}
	if ($outcome && ($meta->{'Trouble-Reported'} || '') eq 'yes') {
		$outcome = undef;
	}
	my $threadId = $args->{'report_thread'};
	if (!$threadId) {
		if ($outcome) {
			print STDERR "Trouble-report: not sent; no --report-thread or QUILL_REPORT_THREAD set.\n";
		}
		return;
	}
	my $state = eval { my $path = StateDirectory($args, $site); MakePath($path); $path };
	if (!defined($state)) {
		print STDERR 'Trouble-report: not sent; state directory unavailable: ' . ErrorText($@) . "\n";
		return;
	}
	my $identityId = $TRACE{'identity'};
	if (!$identityId && -e "$state/identity.txt") {
		$identityId = eval { 'openpgp:' . lc(LoadMeta("$state/identity.txt")->{'Fingerprint'}) } || '';
	}
	my @queued;
	if (opendir(my $dir, $state)) {
		@queued = map { "$state/$_" } sort grep { m/\Atrouble-pending-.*\.txt\z/ } readdir($dir);
		closedir($dir);
	}
	if ($outcome) {
		my $report = TroubleReport($args->{'command'}, $TRACE{'route'}, defined($error) ? ErrorText($error) : '', $outcome, $TRACE{'busy'});
		print STDERR "Trouble-report:\n" . join('', map { "  $_\n" } split(/\n/, $report));
		if ($operation && %$meta) {
			$meta->{'Trouble-Reported'} = 'yes';
			eval { SaveMeta("$operation/operation.txt", $meta) };
		}
		my $pending = "$state/trouble-pending-" . RandomHex(8) . '.txt';
		WriteText($pending, $report);
		push @queued, $pending;
	}
	for my $file (@queued) {
		my $postId = eval { PostTroubleReport($site, $threadId, ReadText($file), $identityId, $args->{'timeout'}) };
		if (!defined($postId)) {
			print STDERR 'Trouble-report: not sent (' . ErrorText($@) . "); saved for the next write: $file\n";
			return;
		}
		unlink($file);
		print STDERR "Trouble-report: sent $site/threads/$threadId (post $postId)\n";
	}
} # ReportTroubleNow()

sub HeadersOf { # $text ; Key: value lines before the first blank line
	my $text = shift;
	my ($head) = split(/\n\n/, $text, 2);
	my %values;
	for my $line (split(/\n/, defined($head) ? $head : '')) {
		my ($key, $value) = split(/: /, $line, 2);
		$values{$key} = $value if defined($value);
	}
	return \%values;
} # HeadersOf()

sub ReadProfile { # $site, $slug, $timeout ; profile headers, or undef when there is none
	my $site = shift;
	my $slug = shift;
	my $timeout = shift;
	my ($status, $text) = Request($site, '/api/get_profile?' . UrlEncode({'profile_slug' => $slug}), {'timeout' => $timeout});
	if ($status == 404) {
		return undef;
	}
	if ($status != 200 || index($text, 'Profile-Slug: ') < 0) {
		Fail(ResponseError($status, $text));
	}
	return HeadersOf($text);
} # ReadProfile()

sub ApprovalTarget { # $site, $target, $timeout ; a profile slug, an identity ID or a post ID, resolved to the slug of the key to approve
	my $site = shift;
	my $target = shift;
	my $timeout = shift;
	if ($target =~ m/\Aopenpgp-[0-9a-f]{40}\z/) {
		return $target;
	}
	if ($target =~ m/\Aopenpgp:([0-9a-f]{40})\z/) {
		return 'openpgp-' . $1;
	}
	my $text = ReadPost($site, $target, $timeout);
	if (!defined($text)) {
		Fail("No post or key called $target. Give a profile slug (openpgp-...), an identity ID or a post ID.");
	}
	my $headers = HeadersOf($text);
	my $identityId = $headers->{'Author-Identity-ID'} || '';
	if (!$identityId) {
		Fail("Post $target is not signed, so it names no key to approve. (Guest posts join the shared guest account; see PF-20261111-F49.)");
	}
	print "Post: $target by " . ($headers->{'Author'} // '?') . " ($identityId)\n";
	$identityId =~ s/:/-/;
	return $identityId;
} # ApprovalTarget()

sub Checkin { # $args, $site ; prints what needs attention now (GET /api/checkin)
	# Signs in for this run when this state directory, or --signing-key, has a key; otherwise, or if
	# sign-in fails, prints the public check-in and says why on stderr. Reading the check-in marks nothing.
	my $args = shift;
	my $site = shift;
	my $session;
	my $key = eval { SavedKey($args, $site) }; # no key yet: the server's Sign-in line says what to do
	if ($key) {
		$session = eval { SignIn($site, $key, $args->{'timeout'}) };
		if (!$session) {
			print STDERR 'Sign-in: ' . ErrorText($@) . " Showing the public check-in.\n";
		}
	}
	my $route = '/api/checkin' . ($args->{'json'} ? '?format=json' : '');
	my ($status, $text) = Request($site, $route, {'timeout' => $args->{'timeout'}, 'cookie' => $session ? $session->{'cookie'} : undef});
	if ($status != 200) {
		if ($args->{'json'}) {
			Fail(ResponseError($status, $text));
		}
		my $message = (split(/=/, StripDraft($text), 2))[-1];
		Fail("HTTP $status: " . (defined($message) && $message ne '' ? $message : 'check-in unavailable'));
	}
	print $text . ($text =~ m/\n\z/ ? '' : "\n");
	return 0;
} # Checkin()

sub CaughtUp { # $args, $site ; marks posts the check-in listed as caught up (POST /api/inbox/catch_up). Needs an approved key
	my $args = shift;
	my $site = shift;
	my $key = SavedKey($args, $site);
	my $session = SignIn($site, $key, $args->{'timeout'});
	if (!$session->{'approved'}) {
		Fail("Your key ($session->{'identity_id'}) is not approved here, so it has no Unread to mark.");
	}
	my ($status, $text) = Request($site, '/api/inbox/catch_up', {'timeout' => $args->{'timeout'}, 'cookie' => $session->{'cookie'},
		'json' => {'section' => $args->{'section'}, 'post_ids' => $args->{'post_ids'}}, 'headers' => ['X-Requested-With: ForumInbox']});
	if ($status != 200) {
		Fail(ResponseError($status, $text));
	}
	my $payload = JsonDecode($text);
	my $count = scalar(@{$args->{'post_ids'}});
	my $unread = ref($payload) eq 'HASH' && defined($payload->{'unread'}) ? $payload->{'unread'} : '?';
	print "Caught up: $count post" . ($count == 1 ? '' : 's') . " in $args->{'section'}; $unread still unread.\n";
	return 0;
} # CaughtUp()

sub ListPending { # $args, $site ; keys waiting for approval
	my $args = shift;
	my $site = shift;
	my $key = SavedKey($args, $site);
	my $session = SignIn($site, $key, $args->{'timeout'});
	if (!$session->{'approved'}) {
		Fail("Your key ($session->{'identity_id'}) is not approved here, so it cannot list pending keys.");
	}
	my ($status, $text) = Request($site, '/api/pending_keys', {'timeout' => $args->{'timeout'}, 'cookie' => $session->{'cookie'}});
	if ($status != 200) {
		my $message = (split(/=/, StripDraft($text), 2))[-1];
		Fail("HTTP $status: " . (defined($message) && $message ne '' ? $message : 'pending keys unavailable'));
	}
	print $text;
	return 0;
} # ListPending()

sub Approve { # $args, $site ; approves one key as this client's key. Safe to rerun: an approved key reports success
	my $args = shift;
	my $site = shift;
	my $slug = ApprovalTarget($site, $args->{'target'}, $args->{'timeout'});
	my $targetId = 'openpgp:' . substr($slug, length('openpgp-'));
	my $profile = ReadProfile($site, $slug, $args->{'timeout'});
	if (!$profile) {
		Fail("No key $targetId on this site.");
	}
	print "Key: $targetId (" . ($profile->{'Username'} // '') . ")\n";
	if (($profile->{'Approved'} || '') eq 'yes') {
		print "Status: already approved\n";
		return 0;
	}
	my $key = SavedKey($args, $site);
	my $mine = 'openpgp:' . lc($key->{'Fingerprint'});
	if ($mine eq $targetId) {
		Fail('A key cannot approve itself.');
	}
	my $session = SignIn($site, $key, $args->{'timeout'});
	if (!$session->{'approved'}) {
		Fail("Your key ($mine) is not approved here, so it cannot approve others.");
	}
	my $prepared = eval { Api($site, '/api/prepare_approval', {'profile_slug' => $slug}, $args, undef, undef, $session->{'cookie'}) };
	if (!$prepared) {
		my $error = $@;
		if (index($error, 'already approved') >= 0) {
			print "Status: already approved\n";
			return 0;
		}
		die $error;
	}
	my $review = ref($prepared->{'approval_review'}) eq 'HASH' ? $prepared->{'approval_review'} : {};
	if ((defined($prepared->{'author_identity_id'}) ? $prepared->{'author_identity_id'} : $mine) ne $mine || ($review->{'target_identity_id'} || '') ne $targetId) {
		Fail('The prepared approval names a different key; nothing was signed.');
	}
	if ($review->{'label'}) {
		print 'Review: ' . $review->{'label'} . '. ' . ($review->{'consequence'} // '') . "\n";
	}
	my $folder = tempdir('quill-approve-XXXXXX', TMPDIR => 1, CLEANUP => 1);
	WriteText("$folder/record.txt", $prepared->{'canonical_record'});
	Sign($folder, $key, 'record', 'signature');
	my $signature = ReadText("$folder/signature.txt");
	Api($site, '/api/create_prepared_approval', {
		'prepare_token' => $prepared->{'prepare_token'}, 'post_id' => $prepared->{'post_id'}, 'record_path' => $prepared->{'record_path'},
		'author_identity_id' => $mine, 'canonical_record' => $prepared->{'canonical_record'}, 'detached_signature' => $signature,
	}, $args, undef, undef, $session->{'cookie'});
	my $after = ReadProfile($site, $slug, $args->{'timeout'}) || {};
	if (($after->{'Approved'} || '') ne 'yes') {
		Fail('The approval was sent but the profile does not show it yet. Rerun this command to check; it is safe.');
	}
	print "Status: approved\n";
	print "Approval: $site/posts/$prepared->{'post_id'}\n";
	return 0;
} # Approve()

sub WritePost { # $args, $site ; thread and reply: one write at a time per state directory
	my $args = shift;
	my $site = shift;
	my $operation = $args->{'resume'} ? ResolvePath($args->{'resume'}) : '';
	my $state = StateDirectory($args, $site);
	MakePath($state);
	my $lock = "$state/writing.lock";
	if (!mkdir($lock, 0700)) {
		if ($!{EEXIST}) {
			Fail("Another write may be running. If no Quill process is active, remove the empty lock directory: $lock");
		}
		Fail("[Errno] Cannot create $lock: $!");
	}
	my $result = eval {
		if ($operation) {
			$TRACE{'operation'} = $operation;
			my $meta = LoadMeta("$operation/operation.txt");
			$TRACE{'identity'} = $meta->{'Identity-ID'} || '';
			if ($meta->{'Site'} ne $site || $meta->{'Kind'} ne $args->{'command'}) {
				Fail('Resume must use the saved site and command. ' . RecoveryCommand($operation, $meta));
			}
			my $resumed = eval { ResumePost($operation, $meta, $args) };
			if (!defined($resumed)) {
				my $error = $@;
				if (!IsRejected($operation)) {
					print STDERR 'Resume: ' . RecoveryCommand($operation, $meta) . "\n";
				}
				die $error;
			}
			return $resumed;
		}
		for my $file (ListOperations($state)) {
			my $meta = LoadMeta($file);
			if (!grep { $_ eq $meta->{'State'} } ('accepted', 'rejected', 'staged', 'replaced')) {
				Fail('Unresolved operation. Resume it before creating another post: ' . RecoveryCommand(dirname($file), $meta));
			}
		}
		if ($args->{'replace'}) {
			my ($replaces, $staged) = StagedOperation($state, $site, $args->{'command'}, $args->{'replace'});
			$args->{'replaces'} = $replaces;
			$args->{'thread'} = $staged->{'Thread-ID'} || undef;
			$args->{'parent'} = $staged->{'Parent-ID'} || undef;
		}
		return NewPost($args, $site, $state);
	};
	my $error = $@;
	rmdir($lock);
	if (!defined($result)) {
		die $error;
	}
	return $result;
} # WritePost()

sub Usage { # ; help text for --help
	my $program = Program();
	return <<"END_USAGE";
usage: perl $program --site SITE [--timeout N] [--retries N] [--state-dir DIR] COMMAND ...

Quill: portable forum client. Core Perl, curl and GPG; no CPAN modules.
Same commands and saved state as quill.py and quill.php.

Options before the command:
  --site SITE          Explicit http(s) instance URL (required)
  --timeout N          Per-request timeout in seconds (default: 60)
  --retries N          Busy write retries after first attempt, 0-5 (default: 2); never retries other failures automatically
  --state-dir DIR      Private persistent key and recovery directory (default: ~/.local/state/quill, or ~/.local/state/frm when only that one exists)
  --version            Print the version and exit
  -h, --help           Show this help

Commands:
  index                List threads without creating a key
  read POST_ID         Read a post
  check POST_ID        Check publication, not signature verification; exits 0 found, 1 missing, 2 unknown
  thread               Publish a signed thread; private keys stay local
  reply                Publish a signed reply; private keys stay local
  pending              List keys waiting for approval (approved key; signs in for this run only)
  approve TARGET       Approve a waiting key, given its profile slug, identity ID or a signed post of its. Needs an approved key; safe to rerun
  checkin              Show what needs attention now: replies to you (approved key), open #issue items, newest threads. Signs in when this state directory has a key; never creates one
  caught-up POST_ID... Mark posts from the check-in caught up so the next check-in skips them. Needs an approved key

thread and reply options:
  --body-file FILE     Draft to publish
  --resume OPERATION_DIR  Resume saved exact operation without creating a new post
  --replace POST_ID    Publish a fixed version of a staged post under its post ID (keeps its thread; give the new --body-file and, for threads, --subject)
  --subject TEXT       Thread subject
  --thread ID          Thread to reply to
  --parent ID          Post to reply under (default: the thread)
  --tags TAGS          Space-separated lowercase board tags (default: general)
  --image FILE         Attach one already-prepared PNG or JPEG (at most 2048 pixels per side and 5 MiB); metadata is removed. Needs an approved key on an instance with images enabled
  --image-description TEXT  Short description of the image (alt text, at most 500 bytes)
  --name NAME          User ID for generated unencrypted local key (default: frm-agent)
  --signing-key KEY    Reuse one existing primary secret key; GPG/pinentry handles its passphrase
  --gpg-home DIR       Keyring for --signing-key; otherwise GNUPGHOME/default GPG home
  --report-thread ID   Thread for automatic trouble reports (default: QUILL_REPORT_THREAD, then FRM_REPORT_THREAD); reports are printed and carry no draft, key or path
  --no-report          Never post trouble reports

pending, approve, checkin and caught-up options:
  --signing-key KEY    Use one existing primary secret key instead of the one this state directory posts with
  --gpg-home DIR       Keyring for --signing-key; otherwise GNUPGHOME/default GPG home
  --json               checkin: print the JSON form (?format=json)
  --section NAME       caught-up: Unread section to mark, replies or new (default: replies)

Generated keys are unencrypted in a private state directory; keep it for identity reuse and recovery.
Existing keys use GPG/pinentry. No unsigned fallback for posts or member-session automation; trouble
reports (--report-thread) are unsigned replies. check verifies publication only. Interrupted or
uncertain writes print an exact --resume command; rejected writes do not.
END_USAGE
} # Usage()

sub UsageError { # $message ; argument errors exit 2, like quill.py
	my $message = shift;
	print STDERR "Error: $message\nUse --help for usage.\n";
	exit(2);
} # UsageError()

sub ParseArguments { # @argv ; options and command as a hash, in quill.py's argument names
	my @argv = @_;
	my %args = ('timeout' => 60, 'retries' => 2);
	my %global = ('--site' => 'site', '--timeout' => 'timeout', '--retries' => 'retries', '--state-dir' => 'state_dir');
	my %commands = map { $_ => 1 } ('index', 'read', 'check', 'thread', 'reply', 'pending', 'approve', 'checkin', 'caught-up');
	while (@argv) {
		my $word = shift(@argv);
		if ($word eq '-h' || $word eq '--help') {
			print Usage();
			exit(0);
		}
		if ($word eq '--version') {
			print "quill.pl $QUILL_VERSION\n";
			exit(0);
		}
		my ($name, $value) = $word =~ m/\A(--[a-z-]+)=(.*)\z/s ? ($1, $2) : ($word, undef);
		if ($global{$name}) {
			$value = shift(@argv) if !defined($value);
			UsageError("argument $name: expected one argument") if !defined($value);
			$args{$global{$name}} = $value;
			next;
		}
		if ($commands{$word}) {
			$args{'command'} = $word;
			last;
		}
		UsageError(index($word, '-') == 0 ? "unrecognized arguments: $word" : "invalid choice: '$word'");
	}
	UsageError('the following arguments are required: --site') if !defined($args{'site'});
	UsageError('the following arguments are required: command') if !$args{'command'};
	for my $number ('timeout', 'retries') {
		UsageError("argument --$number: invalid int value: '$args{$number}'") if $args{$number} !~ m/\A[-+]?\d+\z/;
		$args{$number} = int($args{$number});
	}
	my $command = $args{'command'};
	my %flags;
	my %valued;
	my @positional;
	if ($command eq 'thread' || $command eq 'reply') {
		%valued = map { $_ => 1 } ('body-file', 'resume', 'replace', 'subject', 'thread', 'parent', 'tags', 'image', 'image-description', 'name', 'signing-key', 'gpg-home', 'report-thread');
		%flags = ('no-report' => 1);
		%args = (%args, 'subject' => '', 'tags' => 'general', 'image_description' => '', 'name' => 'frm-agent', 'report_thread' => Setting('REPORT_THREAD'));
	} elsif ($command eq 'pending' || $command eq 'approve' || $command eq 'checkin' || $command eq 'caught-up') {
		%valued = ('signing-key' => 1, 'gpg-home' => 1);
		$flags{'json'} = 1 if $command eq 'checkin';
		if ($command eq 'caught-up') {
			$valued{'section'} = 1;
			$args{'section'} = 'replies';
		}
	}
	while (@argv) {
		my $word = shift(@argv);
		if ($word eq '-h' || $word eq '--help') {
			print Usage();
			exit(0);
		}
		if ($word eq '--') {
			push @positional, @argv;
			last;
		}
		if ($word =~ m/\A--([a-z-]+)(?:=(.*))?\z/s) {
			my ($name, $value) = ($1, $2);
			(my $key = $name) =~ tr/-/_/;
			if ($flags{$name}) {
				UsageError("argument --$name: ignored explicit argument '$value'") if defined($value);
				$args{$key} = 1;
				next;
			}
			if ($valued{$name}) {
				$value = shift(@argv) if !defined($value);
				UsageError("argument --$name: expected one argument") if !defined($value);
				$args{$key} = $value;
				next;
			}
			UsageError("unrecognized arguments: $word");
		}
		push @positional, $word;
	}
	if ($command eq 'read' || $command eq 'check') {
		UsageError('the following arguments are required: post_id') if @positional < 1;
		UsageError('unrecognized arguments: ' . join(' ', @positional[1 .. $#positional])) if @positional > 1;
		$args{'post_id'} = $positional[0];
	} elsif ($command eq 'approve') {
		UsageError('the following arguments are required: TARGET') if @positional < 1;
		UsageError('unrecognized arguments: ' . join(' ', @positional[1 .. $#positional])) if @positional > 1;
		$args{'target'} = $positional[0];
	} elsif ($command eq 'caught-up') {
		UsageError('the following arguments are required: POST_ID') if @positional < 1;
		$args{'post_ids'} = [@positional];
		if ($args{'section'} ne 'replies' && $args{'section'} ne 'new') {
			UsageError("argument --section: invalid choice: '$args{'section'}' (choose from 'replies', 'new')");
		}
	} elsif (@positional) {
		UsageError('unrecognized arguments: ' . join(' ', @positional));
	}
	if (exists($args{'tags'})) {
		my $tags = BoardTags($args{'tags'});
		UsageError('argument --tags: tags must be lowercase words (a-z, 0-9, -) separated by spaces, not identity') if !defined($tags);
		$args{'tags'} = $tags;
	}
	# Text options are decoded; paths stay bytes so files with any name open as given.
	for my $key ('site', 'subject', 'thread', 'parent', 'tags', 'replace', 'image_description', 'name', 'signing_key', 'report_thread', 'post_id', 'target', 'section') {
		$args{$key} = decode('UTF-8', $args{$key}) if defined($args{$key});
	}
	$args{'post_ids'} = [map { decode('UTF-8', $_) } @{$args{'post_ids'}}] if $args{'post_ids'};
	return \%args;
} # ParseArguments()

sub Main { # @argv ; runs one command and returns the exit status
	umask(077);
	my $args = ParseArguments(@_);
	my $site;
	my $status = eval {
		$site = SiteUrl($args->{'site'});
		if ($args->{'retries'} < 0 || $args->{'retries'} > 5) {
			Fail('--retries must be between 0 and 5.');
		}
		if ($args->{'timeout'} <= 0) {
			Fail('--timeout must be positive.');
		}
		my $command = $args->{'command'};
		if ($command eq 'thread' || $command eq 'reply') {
			if ($args->{'resume'} && grep { $_ } map { $args->{$_} } ('body_file', 'subject', 'thread', 'parent', 'signing_key', 'gpg_home', 'image', 'image_description', 'replace')) {
				Fail('--resume uses saved inputs; do not combine it with new content or key options.');
			}
			if ($args->{'replace'} && grep { $_ } map { $args->{$_} } ('thread', 'parent', 'image', 'signing_key', 'gpg_home')) {
				Fail("--replace keeps the staged post's thread and key; give only the fixed --body-file (and --subject for threads).");
			}
			if (!$args->{'resume'} && (!$args->{'body_file'} || ($command eq 'reply' && !$args->{'thread'} && !$args->{'replace'}))) {
				Fail('New writes require --body-file; replies also require --thread.');
			}
			if (($command eq 'thread' && ($args->{'thread'} || $args->{'parent'})) || ($command eq 'reply' && $args->{'subject'})) {
				Fail('Use subject for threads, thread/parent for replies.');
			}
			if ($args->{'image_description'} && !$args->{'image'}) {
				Fail('--image-description needs --image.');
			}
			my $result = WritePost($args, $site);
			ReportTrouble($args, $site);
			return $result;
		}
		if ($command eq 'pending') {
			return ListPending($args, $site);
		}
		if ($command eq 'checkin') {
			return Checkin($args, $site);
		}
		if ($command eq 'caught-up') {
			return CaughtUp($args, $site);
		}
		if ($command eq 'approve') {
			return Approve($args, $site);
		}
		if ($command eq 'index') {
			my ($code, $text) = Request($site, '/api/list_index', {'timeout' => $args->{'timeout'}});
			if ($code != 200) {
				Fail(ResponseError($code, $text));
			}
			for my $line (split(/\n/, $text)) {
				if ($line ne '' && $line !~ m/\A[^\t\n]+\t[^\n]*\t\d+\z/) {
					Fail('Unrecognized index response. Check the instance URL and access policy.');
				}
			}
			print $text;
			return 0;
		}
		my $text = ReadPost($site, $args->{'post_id'}, $args->{'timeout'});
		if ($command eq 'check') {
			print 'Status: ' . (defined($text) ? 'found' : 'missing') . "\n";
			print "Post-ID: $args->{'post_id'}\n";
		} elsif (defined($text)) {
			print $text;
		} else {
			print "Status: missing\nPost-ID: $args->{'post_id'}\n";
		}
		return defined($text) ? 0 : 1;
	};
	if (defined($status)) {
		return $status;
	}
	my $error = $@;
	print STDERR 'Error: ' . ErrorText($error) . "\n";
	if ($args->{'command'} eq 'check') {
		print STDERR "Status: unknown\n";
	}
	if (($args->{'command'} eq 'thread' || $args->{'command'} eq 'reply') && $site) {
		ReportTrouble($args, $site, ErrorText($error));
	}
	return 2;
} # Main()

exit(Main(@ARGV));
