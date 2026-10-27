#!/usr/bin/env perl
use strict;
use warnings;
use Encode qw(encode decode FB_CROAK);
use File::Temp qw(tempdir);
use Getopt::Long qw(GetOptions Configure);
use JSON::PP qw(decode_json);

# Core Perl modules, existing curl, and GPG for signed publication.
$| = 1;
sub input_error { print STDERR "Error: $_[0]\nUse --help for usage.\n"; exit 2 }
sub capture {
    # List-form process execution keeps user input out of a shell. GPG inherits
    # stdin/stderr for pinentry; only its public/status output is captured.
    my (@command) = @_;
    open my $pipe, '-|', @command or die "Cannot start $command[0]: $!\n";
    binmode $pipe;
    my $output = do { local $/; <$pipe> } // '';
    close $pipe;
    return ($? == 0, $output);
}
sub escape_form {
    # Escape bytes, not Unicode code points. Callers supply UTF-8 encoded values.
    my ($bytes) = @_;
    $bytes =~ s/([^A-Za-z0-9_.~-])/sprintf('%%%02X', ord($1))/ge;
    return $bytes;
}
sub post {
    my ($base, $route, $fields, $signed) = @_;
    # Put the encoded body in a private temporary file, avoiding command-line
    # size limits and disclosure of post contents in the process argument list.
    my $directory = tempdir('publish-text-XXXXXX', TMPDIR => 1, CLEANUP => 1);
    my $path = "$directory/request.txt";
    open my $file, '>:raw', $path or die "Cannot write request: $!\n";
    print {$file} join('&', map { escape_form($_) . '=' . escape_form($fields->{$_}) } sort keys %$fields)
        or die "Cannot write request: $!\n";
    close $file or die "Cannot close request: $!\n";
    # Ignore curlrc so user defaults cannot enable redirects, retries, or insecure
    # TLS. Append a status trailer to distinguish HTTP failures from API failures.
    my ($ok, $response) = capture('curl', '--disable', '--silent', '--show-error', '--max-time', '60',
        '--proto', '=http,https', '--request', 'POST', '--header', 'Content-Type: application/x-www-form-urlencoded',
        '--data-binary', "\@$path", '--write-out', "\n%{http_code}", '--url', "$base/api/$route");
    unlink $path;
    rmdir $directory;
    die "HTTP request failed; check curl and connectivity.\n" unless $ok;
    $response =~ s/\n(\d{3})\z// or die "Missing HTTP status.\n";
    my $status = $1;
    # Signed routes return JSON; anonymous routes return key=value lines.
    my $json = eval { decode_json($response) };
    my %lines = map { split /=/, $_, 2 } grep { /=/ } split /\r?\n/, $response;
    my $values = $signed ? $json : \%lines;
    my $error = ref($json) eq 'HASH' ? $json->{error} : $lines{error};
    die "HTTP $status: " . (defined($error) && !ref($error) ? $error : 'Check the instance URL and access policy.') . "\n"
        unless $status >= 200 && $status < 300;
    die ((ref($values) eq 'HASH' && defined($values->{error}) && !ref($values->{error})
        ? $values->{error} : 'Unexpected API response; check the instance URL and access policy.') . "\n")
        unless ref($values) eq 'HASH' && ($values->{status} // '') eq 'ok';
    # All other code handles UTF-8 bytes, including canonical signing input.
    if ($signed) {
        for my $key (keys %$values) {
            $values->{$key} = encode('UTF-8', $values->{$key}) if defined($values->{$key}) && !ref($values->{$key});
        }
    }
    return $values;
}
sub required_fields {
    # Incomplete prepare responses must fail before any signing or submission.
    my ($values, @keys) = @_;
    my %result;
    for my $key (@keys) {
        die "API response is missing $key.\n" unless defined($values->{$key}) && !ref($values->{$key}) && length($values->{$key});
        $result{$key} = $values->{$key};
    }
    return \%result;
}
sub sign_record {
    # Write the server's canonical UTF-8 bytes in raw mode to preserve all newlines.
    # File::Temp cleans up on failure too; secret keys never leave GPG's keyring.
    my ($record, $fingerprint) = @_;
    my $directory = tempdir('publish-text-XXXXXX', TMPDIR => 1, CLEANUP => 1);
    my $path = "$directory/record.txt";
    open my $file, '>:raw', $path or die "Cannot write signing record: $!\n";
    print {$file} $record or die "Cannot write signing record: $!\n";
    close $file or die "Cannot close signing record: $!\n";
    my ($ok) = capture('gpg', '--armor', '--detach-sign', '--local-user', $fingerprint, '--output', "$path.asc", $path);
    die "GPG signing failed. Check the secret key and GPG/pinentry.\n" unless $ok;
    open my $signature, '<:raw', "$path.asc" or die "Cannot read signature: $!\n";
    my $result = do { local $/; <$signature> };
    close $signature;
    unlink "$path.asc", $path;
    rmdir $directory;
    return $result;
}

Configure('permute', 'no_auto_abbrev', 'no_ignore_case');
# Accept options on either side of INSTANCE and FILE, as the Python version does.
my %options = ('subject' => '', 'board-tags' => 'general');
GetOptions(\%options, 'help|h', 'subject=s', 'board-tags=s', 'thread-id=s', 'parent-id=s', 'signing-key=s', 'generate-key=s')
    or input_error('Invalid options.');
if ($options{help}) {
    print <<'HELP';
Usage: perl scripts/publish_text.pl INSTANCE FILE [options]
Publish a UTF-8 file; without a key option, publish anonymously.
  --subject TEXT         New thread subject
  --board-tags TAGS      Comma-separated boards (default: general)
  --thread-id ID         Publish a reply
  --parent-id ID         Reply parent (defaults to thread ID)
  --signing-key FPR      Full primary fingerprint of a registered identity
  --generate-key NAME    Create persistent Ed25519 key, register identity, publish
Key options are mutually exclusive. GPG uses your keyring/GNUPGHOME and pinentry.
Requires Perl core modules and curl; signing requires GPG. No automatic retries.
HELP
    exit 0;
}
input_error('Provide an instance URL and text file.') unless @ARGV == 2;
# Complete validation and read the input before any persistent or remote changes.
my ($base, $path) = @ARGV;
$base =~ s{/+\z}{};
input_error('Instance must be an HTTP(S) base URL without credentials, query or fragment.')
    unless $base =~ m{\Ahttps?://(?:\[[0-9A-Fa-f:.]+\]|[^/:\s\@?#]+)(?::[0-9]+)?(?:/[^\s?#]*)?\z} && $base !~ /[\x00-\x20\x7f]/;
input_error('Choose --signing-key or --generate-key.') if exists($options{'signing-key'}) && exists($options{'generate-key'});
input_error('--signing-key requires a full primary fingerprint.')
    if exists($options{'signing-key'}) && $options{'signing-key'} !~ /\A(?:[A-Fa-f0-9]{40}|[A-Fa-f0-9]{64})\z/;
input_error('--generate-key requires a nonempty name without control characters.')
    if exists($options{'generate-key'}) && ($options{'generate-key'} !~ /\S/ || $options{'generate-key'} =~ /[\x00-\x1f\x7f]/);
input_error('--parent-id requires --thread-id.') if $options{'parent-id'} && !$options{'thread-id'};
input_error('--subject only applies to new threads.') if $options{'thread-id'} && length($options{subject});
input_error('Cannot read text file.') unless -f $path;
open my $file, '<:raw', $path or input_error("Cannot read text file: $!");
my $body = do { local $/; <$file> } // '';
close $file;
my $decoded = eval { decode('UTF-8', $body, FB_CROAK | Encode::LEAVE_SRC) };
# Decode only for validation; keep the original UTF-8 bytes in $body for transport.
input_error('Text file must contain nonempty UTF-8 text.') if $@ || $decoded !~ /\S/;
my $prepared_id;
my $success = eval {
    my $fingerprint = $options{'signing-key'};
    if (exists $options{'generate-key'}) {
        # GPG prompts via pinentry and stores a persistent key. Use machine status
        # to find its fingerprint; user IDs need not be unique in a keyring.
        my $name = $options{'generate-key'};
        $name =~ s/^\s+|\s+$//g;
        my ($ok, $output) = capture('gpg', '--status-fd', '1', '--quick-generate-key', $name, 'ed25519', 'sign', '0');
        $output =~ /^\[GNUPG:\] KEY_CREATED [BP] ([A-Fa-f0-9]{40,64})$/m
            or die "GPG did not report a new key. Check GPG/pinentry and your keyring before retrying.\n";
        $fingerprint = $1;
        # Print before network access, so failures still leave a usable key reference.
        print "Signing-key: $fingerprint\nKey-location: GPG keyring (GNUPGHOME if set)\n";
        die "GPG reported an error after key creation; inspect the retained key.\n" unless $ok;
        my ($exported, $public) = capture('gpg', '--armor', '--export', $fingerprint);
        die "Unable to export public key; the secret key remains in your keyring.\n" unless $exported && length($public);
        # Register only the public key, then sign the prepared ownership proof.
        my $payload = required_fields(post($base, 'prepare_identity', {public_key => $public}, 1), 'prepare_token', 'canonical_record');
        $payload->{detached_signature} = sign_record($payload->{canonical_record}, $fingerprint);
        my $registered = post($base, 'create_identity', $payload, 1);
        my $identity = 'openpgp:' . lc($fingerprint);
        die "Registration response identity does not match generated key.\n" unless ($registered->{identity_id} // '') eq $identity;
        print "Identity-ID: $identity\n";
    }
    my %fields = (body => $body, board_tags => $options{'board-tags'});
    my $kind = $options{'thread-id'} ? 'reply' : 'thread';
    if ($kind eq 'reply') {
        $fields{thread_id} = $options{'thread-id'};
        $fields{parent_id} = $options{'parent-id'} || $options{'thread-id'};
    } else {
        $fields{subject} = $options{subject};
    }
    my $values;
    if (defined $fingerprint) {
        # Preserve all prepared fields and sign the canonical record exactly.
        # The identity uses the lowercase primary fingerprint, not a user ID.
        $fields{author_identity_id} = 'openpgp:' . lc($fingerprint);
        my $payload = required_fields(post($base, "prepare_$kind", \%fields, 1), 'prepare_token', 'canonical_record', 'record_path', 'post_id');
        $prepared_id = $payload->{post_id};
        $payload->{author_identity_id} = $fields{author_identity_id};
        $payload->{detached_signature} = sign_record($payload->{canonical_record}, $fingerprint);
        $values = post($base, 'create_prepared_post', $payload, 1);
    } else {
        $values = post($base, "create_$kind", \%fields);
    }
    required_fields($values, 'post_id');
    print "Post-ID: $values->{post_id}\n";
    print "Thread-ID: $values->{thread_id}\n" if $values->{thread_id};
    print 'URL: ' . $base . '/posts/' . escape_form($values->{post_id}) . "\n";
    1;
};
if (!$success) {
    # A lost response can follow a successful commit. Never automatically retry;
    # the prepared post ID lets the caller check before making another request.
    print STDERR "Error: $@", "Check the instance before retrying; publication may have completed.\n";
    print STDERR "Post-ID: $prepared_id\n" if defined $prepared_id;
    exit 1;
}
