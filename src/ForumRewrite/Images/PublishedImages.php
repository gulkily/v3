<?php

declare(strict_types=1);

namespace ForumRewrite\Images;

use ForumRewrite\Support\DeploymentSettings;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;

/**
 * Direct image serving (FORUM_IMAGE_DIRECT_SERVING_ENABLED): a folder in the
 * web root holding copies of images the application has already served on a
 * public board, so Apache can answer the next request for the same address
 * without starting the application (see the rules in public/.htaccess).
 *
 * The stored originals stay outside the web root (ImageStore). This folder is
 * only a published copy, and it trades the per-request access checks for
 * speed: an address in it works for anyone who has it. So nothing is published
 * unless the flag is on, image attachments are on and the board is not
 * members-only, and the folder is emptied on the application's next request
 * once any of those stops being true. Removing an image removes its files here.
 * Everything is best effort: a failure leaves the application path serving.
 */
final class PublishedImages
{
    public const FOLDER = '.published-images';

    /** Names the application serves: an original, or a copy under thumbs/. */
    private const NAME = '/^(?:thumbs\/)?[a-f0-9]{64}(?:-[a-z0-9]+)?\.(?:png|jpg)(?:\.webp)?$/D';

    public function __construct(public readonly string $root) {}

    public static function forProject(): self
    {
        return new self(DeploymentSettings::value('FORUM_PUBLISHED_IMAGE_ROOT') ?: dirname(__DIR__, 3) . '/public/' . self::FOLDER);
    }

    /** Whether this site may publish at all. */
    public static function allowed(FeatureFlagEvaluator $flags): bool
    {
        return $flags->isEnabled(FeatureFlagRegistry::IMAGE_DIRECT_SERVING_ENABLED)
            && $flags->isEnabled(FeatureFlagRegistry::IMAGE_CONTENT_ENABLED)
            && !$flags->isEnabled(FeatureFlagRegistry::APPROVED_MEMBERS_ONLY);
    }

    /** Empties the folder when the site may no longer publish. One stat when there is no folder. */
    public function enforce(FeatureFlagEvaluator $flags): void
    {
        if (is_dir($this->root) && !self::allowed($flags)) {
            $this->clear();
        }
    }

    /** Places a served file under the address it was served at. Never throws. */
    public function publish(string $name, string $source): void
    {
        try {
            if (preg_match(self::NAME, $name) !== 1 || is_link($source) || !is_file($source) || is_link($this->root)) {
                return;
            }
            $target = $this->root . '/' . $name;
            if (is_file($target)) {
                return;
            }
            $directory = dirname($target);
            if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
                return;
            }
            $rules = $this->root . '/.htaccess';
            if (!is_file($rules)) {
                @file_put_contents($rules, self::rules());
            }
            $temp = @tempnam($directory, '.publish-');
            if ($temp === false) {
                return;
            }
            if (@copy($source, $temp) && @chmod($temp, 0644) && @rename($temp, $target)) {
                return;
            }
            @unlink($temp);
        } catch (\Throwable) {
            // The application keeps serving this image.
        }
    }

    /** Removes an image and every copy of it. Called wherever the stored image is removed. */
    public function removeImage(string $sha256): void
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1 || !is_dir($this->root) || is_link($this->root)) {
            return;
        }
        foreach ([...(glob($this->root . '/' . $sha256 . '.*') ?: []), ...(glob($this->root . '/thumbs/' . $sha256 . '-*') ?: [])] as $file) {
            if (is_file($file) && !is_link($file)) {
                @unlink($file);
            }
        }
    }

    /** Removes every published file and the folder. */
    public function clear(): void
    {
        if (!is_dir($this->root) || is_link($this->root)) {
            return;
        }
        foreach ([...(glob($this->root . '/thumbs/*') ?: []), ...(glob($this->root . '/{,.}[!.]*', GLOB_BRACE) ?: [])] as $path) {
            if (is_file($path) || is_link($path)) {
                @unlink($path);
            }
        }
        @rmdir($this->root . '/thumbs');
        @rmdir($this->root);
    }

    /** @return list<string> published names, for checks */
    public function names(): array
    {
        if (!is_dir($this->root)) {
            return [];
        }
        $names = array_map('basename', array_filter(glob($this->root . '/*') ?: [], 'is_file'));
        foreach (array_filter(glob($this->root . '/thumbs/*') ?: [], 'is_file') as $file) {
            $names[] = 'thumbs/' . basename($file);
        }
        sort($names);
        return $names;
    }

    /** Headers Apache sends for a published file: the same as ImageController's for a served image. */
    private static function rules(): string
    {
        return "Options -Indexes\n"
            . "<IfModule mod_mime.c>\nAddType image/webp .webp\n</IfModule>\n"
            . "<IfModule mod_headers.c>\nHeader set Cache-Control \"private, max-age=31536000, immutable\"\nHeader set X-Content-Type-Options \"nosniff\"\nHeader unset Vary\n</IfModule>\n";
    }
}
