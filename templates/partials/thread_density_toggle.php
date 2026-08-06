<div class="thread-density-menu" data-role="thread-density-menu">
  <button
    type="button"
    class="thread-density-menu__trigger"
    data-role="thread-density-toggle"
    data-action="thread-density-toggle"
    aria-label="Choose thread view"
    title="Choose thread view"
    aria-haspopup="menu"
    aria-expanded="false"
    aria-controls="thread-density-menu-popover"
  ><span class="thread-density-menu__trigger-label thread-density-menu__trigger-label--comfortable">Comfortable</span><span class="thread-density-menu__trigger-label thread-density-menu__trigger-label--compact">Compact</span> &#9662;</button>
  <div class="thread-density-menu__popover" id="thread-density-menu-popover" role="menu" aria-label="Thread view" hidden>
    <button
      type="button"
      class="thread-density-menu__option"
      role="menuitemradio"
      aria-checked="false"
      data-thread-density-option="comfortable"
    >Comfortable</button>
    <button
      type="button"
      class="thread-density-menu__option"
      role="menuitemradio"
      aria-checked="false"
      data-thread-density-option="compact"
    >Compact</button>
  </div>
</div>
