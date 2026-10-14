<fieldset class="image-compose-fields" hidden>
  <legend>Image <span class="meta">(optional)</span></legend>
  <div class="image-upload-dropzone">
    <button type="button" class="image-upload-choose">Choose an image</button>
    <span class="meta">or drop it here · paste from your clipboard</span>
    <input type="file" name="image" disabled accept="image/jpeg,image/png,image/webp,image/gif,image/bmp" aria-label="Choose an image" tabindex="-1" class="image-upload-input">
  </div>
  <div class="image-upload-preview" hidden>
    <img alt="Prepared image preview">
  </div>
  <p class="meta image-upload-status" role="status" aria-live="polite"></p>
  <button type="button" class="image-upload-remove" hidden>Remove image</button>
  <label>Image description (optional)<input type="text" name="image_description" maxlength="500" placeholder="Describe what the image shows"></label>
  <p class="meta image-upload-help">Images are resized on your device. Only the prepared still image is uploaded. Keep this page open to retry; reselect after reloading.</p>
</fieldset>
<p class="image-upload-recovery" hidden><span role="status">Your attachment is retained, but uploading is unavailable. Reconnect or </span><button type="button">Remove attachment to post text</button></p>
