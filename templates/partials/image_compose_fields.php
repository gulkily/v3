<fieldset class="image-compose-fields" hidden>
  <legend class="image-compose-legend">Image</legend>
  <div class="image-upload-dropzone" title="Attach an image, drop it here, or paste from your clipboard">
<?php if (!($imageChooseInMenu ?? false)): ?>
    <button type="button" class="image-upload-choose">Attach image</button>
<?php endif; ?>
    <input type="file" name="image" disabled accept="image/jpeg,image/png,image/webp,image/gif,image/bmp" aria-label="Attach image" tabindex="-1" class="image-upload-input">
  </div>
  <div class="image-upload-preview" hidden>
    <img alt="Prepared image preview">
    <button type="button" class="image-upload-remove" aria-label="Remove image" title="Remove image" hidden><span aria-hidden="true">×</span></button>
  </div>
  <p class="meta image-upload-status" role="status" aria-live="polite"></p>
</fieldset>
<p class="image-upload-recovery" hidden><span role="status">Your attachment is retained, but uploading is unavailable. Reconnect or </span><button type="button">Remove attachment to post text</button></p>
