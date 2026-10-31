# Image attachments

Purpose: Publish one locally stored still image bound to a signed post.

Scenario: An approved signing identity chooses an image, or imports a tweet photo. The browser prepares and previews it, signs its Image-v1 reference with the text, and submits the image through the existing prepared-post flow. Removal/replacement cancels pending tweet import; expected imports count as attachment intent so failed capture cannot silently become a text-only post. Image publication eligibility and server validation remain unchanged. Outbox cannot save images. Source links permit manual reattachment after a failed import/reload; binary drafts are not newly persisted.

Sources: [preparation](../../public/assets/image_prepare.js), [shared uploader](../../public/assets/image_upload.js), [tweet import](../../public/assets/tweet_image_import.js), [signed composition](../../public/assets/browser_signing.js), [publication](../../src/ForumRewrite/Write/LocalWriteService.php), [record contract](../specs/canonical_post_record_v1.md#optional-image-reference).
