<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Images\ImageReference;
use ForumRewrite\Images\ImageStore;
use ForumRewrite\Images\ThumbnailStore;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;

final class GalleryPageTest
{
    private const FLAGS = [FeatureFlagRegistry::IMAGE_CONTENT_ENABLED, FeatureFlagRegistry::IMAGE_GALLERY_ENABLED];

    public function testGalleryShowsStoredImagesFromThreadsAndReplies(): void
    {
        $this->withImages(function (string $repositoryRoot, string $databasePath): void {
            $wide = $this->storeImage($repositoryRoot, 960, 480);
            $tall = $this->storeImage($repositoryRoot, 200, 400, 'Tall test image');
            $missing = ['sha256' => str_repeat('b', 64), 'type' => 'png', 'bytes' => 10, 'width' => 20, 'height' => 30, 'description' => ''];
            $this->writePost($repositoryRoot, 'gallery-wide', "Subject: Wide picture\n\nLook.\n" . ImageReference::line($wide), '2026-07-01T00:00:00Z');
            $this->writePost($repositoryRoot, 'gallery-missing', "Subject: Lost picture\n\n" . ImageReference::line($missing), '2026-07-01T00:01:00Z');
            $plain = $this->storeImage($repositoryRoot, 300, 300);
            $this->writePost($repositoryRoot, 'gallery-plain', "\n" . ImageReference::line($plain), '2026-06-30T00:00:00Z');
            $this->writePost($repositoryRoot, 'gallery-text', "Subject: Words only\n\nNo image here.", '2026-07-01T00:02:00Z');
            $this->writePost($repositoryRoot, 'gallery-reply', "Thread-ID: gallery-wide\nParent-ID: gallery-wide\n\nA tall one.\n" . ImageReference::line($tall), '2026-07-01T00:03:00Z');
            $this->commit($repositoryRoot);

            $page = $this->markup($this->render(new Application(dirname(__DIR__), $repositoryRoot, $databasePath), '/gallery/'));
            assertSame(3, substr_count($page, 'class="gallery-tile"'), 'Only posts with a stored image become tiles.');
            assertTrue(preg_match('#id="post-gallery-plain"[^>]*>\\s*<img[^>]*>\\s*</a>#', $page) === 1, 'A post that is only a picture gets no caption, not "Image".');
            assertStringContains('<div class="zoom-buttons gallery-zoom" role="group" aria-label="Image size"><button type="button" class="zoom-button" data-gallery-zoom="out" aria-label="Smaller"', $page, 'The gallery has its own image-size buttons.');
            assertStringContains('data-gallery-zoom="in" aria-label="Larger"', $page);
            assertTrue(preg_match('#<script src="/assets/gallery_zoom[^"]*\.js" defer>#', $page) === 1, 'The page loads the gallery zoom script.');
            assertStringContains("localStorage.getItem('gallery-zoom')", $page, 'The saved tile size is restored before paint.');
            assertStringContains("if (galleryZoom < 0.7) {\n            document.documentElement.setAttribute('data-gallery-compact', '');", $page, 'Far zoomed out, captions are hidden before paint.');
            assertStringContains('href="/threads/gallery-wide#post-gallery-reply" style="--ratio: 0.6"', $page, 'Newest first; a tall image is clamped.');
            assertStringContains('<img src="/images/' . ImageReference::filename($tall) . '" alt="Tall test image" width="200" height="400"', $page, 'An image that already fits a tile is shown as is.');
            assertStringContains('<img src="/images/thumbs/' . ThumbnailStore::filename($wide, 'tile') . '"', $page);
            assertStringContains('href="/threads/gallery-wide" style="--ratio: 2"', $page);
            assertStringContains('<span class="gallery-tile__caption"><span class="gallery-tile__title">Wide picture</span></span>', $page, 'A titled post shows its title and nothing else.');
            assertStringNotContains('gallery-tile__meta', $page, 'No reply counts.');
            assertTrue(strpos($page, 'gallery-reply') < strpos($page, 'href="/threads/gallery-wide" '), 'The newer reply comes first.');
            assertStringNotContains('Lost picture', $page);
            assertStringNotContains('Words only', $page);
            assertStringContains('class="nav-link is-active" href="/gallery/">Gallery</a>', $page);
            assertStringContains('<a id="post-gallery-reply" class="gallery-tile"', $page);
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
            assertStringContains('/gallery/#post-gallery-wide', $this->render($application, '/gallery/?with=gallery-wide'));
            assertSame(3, substr_count($this->markup($this->render($application, '/gallery/?with=gallery-text')), 'class="gallery-tile"'), 'A post without an image shows page 1.');
            $past = $this->markup($this->render($application, '/gallery/?page=2'));
            assertStringContains('No images on this page', $past);
            assertStringContains('First page', $past);
        });
    }

    public function testGalleryIsEmptyThenHiddenWhenTurnedOff(): void
    {
        $this->withImages(function (string $repositoryRoot, string $databasePath): void {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
            $empty = $this->markup($this->render($application, '/gallery'));
            assertStringContains('<h2>No images yet</h2>', $empty);
            assertStringNotContains('gallery_zoom', $empty, 'No tiles, no zoom buttons or script.');
            assertStringNotContains('data-gallery-zoom', $empty);

            putenv(FeatureFlagRegistry::IMAGE_GALLERY_ENABLED . '=false');
            $off = $this->render($application, '/gallery/');
            assertStringContains('Not Found', $off);
            assertStringNotContains('href="/gallery/"', $this->markup($this->render($application, '/recent/')));

            putenv(FeatureFlagRegistry::IMAGE_GALLERY_ENABLED . '=true');
            putenv(FeatureFlagRegistry::IMAGE_CONTENT_ENABLED . '=false');
            assertStringContains('Not Found', $this->render($application, '/gallery/'), 'The gallery requires image attachments.');
        });
    }

    public function testHomeThumbnailsOpenThePaginatedZenGallery(): void
    {
        $this->withImages(function (string $repositoryRoot, string $databasePath): void {
            $wide = $this->storeImage($repositoryRoot, 900, 600, 'A calm pond');
            $image = ImageReference::line($wide);
            $this->writePost($repositoryRoot, 'zen-meme-cover', "Subject: Pond\n\n" . $image, '2026-07-02T00:00:00Z', 'zen meme');
            for ($i = 1; $i <= 49; $i++) {
                $id = sprintf('zen-meme-%02d', $i);
                $this->writePost($repositoryRoot, $id, "Subject: Text meme $i\n\nWords.", '2026-07-01T00:00:00Z', 'zen meme');
            }
            $this->writePost($repositoryRoot, 'zen-meme-untitled', "\nA text meme without a subject.", '2026-07-01T00:00:00Z', 'zen meme');
            $this->writePost($repositoryRoot, 'guest-zen-meme', "Subject: Guest tags\n\n" . $image, '2026-07-02T00:01:00Z', 'zen meme');
            $this->writePost($repositoryRoot, 'unrelated-image', "Subject: Unrelated image\n\n" . $image, '2026-07-02T00:02:00Z');
            $this->commit($repositoryRoot);

            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
            $this->render($application, '/');
            // Model an approved member's signed tags without changing the production approval rules.
            $pdo = new PDO('sqlite:' . $databasePath);
            $pdo->exec("INSERT INTO profiles (identity_id,profile_slug,username,username_token,fallback_label,signer_fingerprint,bootstrap_post_id,bootstrap_thread_id,public_key,is_approved,was_approved) VALUES ('id-zen','zen','zen','zen','zen','fp','b','b','k',1,1)");
            $pdo->exec("UPDATE posts SET author_identity_id = 'id-zen', attribution_counted = 1 WHERE post_id LIKE 'zen-meme-%'");
            $pdo = null;

            $home = $this->markup($this->render($application, '/'));
            assertSame(1, preg_match('/href="([^"]+)" aria-label="All Zen memes"/', $home, $link));
            assertSame('/gallery/?view=zen', $link[1]);
            $gallery = $this->markup($this->render($application, $link[1]));
            assertSame(1, preg_match('#<a\b[^>]*href="/threads/zen-meme-cover"[^>]*>\s*(<img\b[^>]*alt="A calm pond"[^>]*>)#', $gallery, $pond));
            assertStringContains('srcset=', $pond[1]);
            assertStringContains('width="900" height="600" loading="lazy"', $pond[1]);
            assertStringContains('href="/threads/zen-meme-49"', $gallery);
            assertStringContains('Text meme 49', $gallery);
            assertStringContains('A text meme without a subject.', $gallery);
            assertStringNotContains('href="/threads/guest-zen-meme"', $gallery);
            assertStringNotContains('href="/threads/unrelated-image"', $gallery);
            assertStringNotContains('href="/threads/zen-meme-01"', $gallery);
            $with = $this->markup($this->render($application, $link[1] . '&with=unrelated-image'));
            assertStringContains('href="/threads/zen-meme-cover"', $with);
            assertStringNotContains('href="/threads/unrelated-image"', $with);

            assertSame(1, preg_match('/rel="next" href="([^"]+)"/', $gallery, $next));
            $second = $this->markup($this->render($application, html_entity_decode($next[1])));
            assertStringContains('href="/threads/zen-meme-01"', $second);
            assertStringContains('Text meme 1', $second);
            assertStringNotContains('href="/threads/zen-meme-cover"', $second);
            assertStringNotContains('rel="next"', $second);
            assertSame(1, preg_match('/rel="prev" href="([^"]+)"/', $second, $previous));
            assertSame($link[1], $previous[1]);

            $ordinary = $this->markup($this->render($application, '/gallery/'));
            assertStringContains('href="/threads/unrelated-image"', $ordinary);
            assertStringContains('href="/threads/guest-zen-meme"', $ordinary);
            assertStringNotContains('href="/threads/zen-meme-49"', $ordinary);

            putenv(FeatureFlagRegistry::IMAGE_GALLERY_ENABLED . '=false');
            $disabled = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
            $home = $this->markup($this->render($disabled, '/'));
            assertStringContains('href="/tags/zen+meme" aria-label="All Zen memes"', $home);
            assertStringContains('href="/tags/zen+meme"', $this->render($disabled, $link[1]));
            assertSame(302, http_response_code());
        });
    }

    public function testPreviewRouteResizesCachesAndFollowsTheOriginal(): void
    {
        $this->withImages(function (string $repositoryRoot, string $databasePath): void {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
            $store = new ImageStore($repositoryRoot);
            $wide = $this->storeImage($repositoryRoot, 960, 480);
            $small = $this->storeImage($repositoryRoot, 40, 30);

            $bytes = $this->render($application, ThumbnailStore::url($wide, 'tile'));
            $size = getimagesizefromstring($bytes);
            assertSame([480, 240], [$size[0], $size[1]]);
            $cache = $store->root . '-thumbs/' . ThumbnailStore::filename($wide, 'tile');
            assertSame($bytes, (string) file_get_contents($cache));
            assertSame(2, count($store->inventory()), 'Previews stay out of the image store, so its inventory still validates.');
            if (ThumbnailStore::format() === 'webp') {
                assertSame(IMAGETYPE_WEBP, $size[2], 'Previews are WebP, far smaller than truecolor PNG.');
                $legacy = getimagesizefromstring($this->render($application, '/images/thumbs/' . $wide['sha256'] . '-tile.png'));
                assertSame([480, 240, IMAGETYPE_WEBP], [$legacy[0], $legacy[1], $legacy[2]], 'A pre-WebP preview URL still resolves.');
            }

            assertSame('/images/' . ImageReference::filename($small), ThumbnailStore::url($small, 'tile'), 'A small image is linked as is.');

            unlink($cache);
            assertSame($bytes, $this->render($application, ThumbnailStore::url($wide, 'tile')), 'A deleted cache is rebuilt.');

            unlink($store->root . '/' . ImageReference::filename($wide));
            assertStringContains('Image unavailable', $this->render($application, ThumbnailStore::url($wide, 'tile')), 'No preview once the original is gone.');
            assertStringContains('Image unavailable', $this->render($application, '/images/thumbs/' . str_repeat('c', 64) . '-tile.png'));
            assertStringContains('Image unavailable', $this->render($application, '/images/thumbs/../x-tile.png'));
        });
    }

    /** @param callable(string, string): void $test */
    private function withImages(callable $test): void
    {
        $old = [];
        foreach (self::FLAGS as $flag) {
            $old[$flag] = getenv($flag);
            putenv($flag . '=true');
        }
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-gallery-' . bin2hex(random_bytes(6)) . '/repo';
        try {
            mkdir($repositoryRoot, 0777, true);
            $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);
            foreach (['git init', 'git config user.name "Forum Rewrite"', 'git config user.email "forum-rewrite@example.invalid"'] as $command) {
                $this->runCommand($repositoryRoot, $command);
            }
            $this->commit($repositoryRoot);
            $test($repositoryRoot, dirname($repositoryRoot) . '/read-model.sqlite3');
        } finally {
            foreach ($old as $flag => $value) {
                putenv($value === false ? $flag : $flag . '=' . $value);
            }
            exec('rm -rf ' . escapeshellarg(dirname($repositoryRoot)));
        }
    }

    private function storeImage(string $repositoryRoot, int $width, int $height, string $description = ''): array
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, ($width * 7) % 256, ($height * 3) % 256, 90));
        $source = dirname($repositoryRoot) . '/source-' . $width . 'x' . $height . '.png';
        imagepng($image, $source);
        $reference = ImageStore::inspect($source, $description);
        (new ImageStore($repositoryRoot))->put($source, $reference);

        return $reference;
    }

    private function writePost(string $repositoryRoot, string $postId, string $rest, string $createdAt, string $tags = 'general'): void
    {
        file_put_contents($repositoryRoot . '/records/posts/' . $postId . '.txt', "Post-ID: {$postId}\nCreated-At: {$createdAt}\nBoard-Tags: {$tags}\n{$rest}\n");
    }

    private function commit(string $repositoryRoot): void
    {
        $this->runCommand($repositoryRoot, 'git add .');
        $this->runCommand($repositoryRoot, 'git commit -m "Update test repository"');
    }

    /** The page without its inline critical CSS, which names every class. */
    private function markup(string $html): string
    {
        return (string) preg_replace('/<style data-role="critical-css">.*?<\/style>/s', '', $html);
    }

    private function render(Application $application, string $path): string
    {
        ob_start();
        $application->handle('GET', $path);

        return (string) ob_get_clean();
    }

    private function runCommand(string $cwd, string $command): void
    {
        exec('cd ' . escapeshellarg($cwd) . ' && ' . $command . ' 2>&1', $output, $status);
        if ($status !== 0) {
            throw new RuntimeException('Command failed: ' . $command . "\n" . implode("\n", $output));
        }
    }

    private function copyDirectory(string $source, string $destination): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $targetPath = $destination . '/' . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0777, true);
                }
                continue;
            }
            copy($item->getPathname(), $targetPath);
        }
    }
}
