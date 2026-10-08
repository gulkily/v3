<section class="stack">
  <article class="card about-sections">
    <section class="about-section" data-about-section="intro">
      <h1><?= $e($aboutContent['title']) ?></h1>
      <p><?= $e($aboutContent['introduction']) ?></p>
    </section>
    <section class="about-section" data-about-section="community">
      <h2><?= $e($aboutContent['communityHeading']) ?></h2>
<?php foreach ($aboutContent['communityParagraphs'] as $paragraph): ?>
      <p><?= $e($paragraph) ?></p>
<?php endforeach; ?>
    </section>
<?php if ($aboutContent['showHackableSection']): ?>
    <section class="about-section" data-about-section="hackable">
      <h2>Hackable by design</h2>
      <p>chouse is built to be changed. Its open code and legible data make it easy to build tools, experiments, and new ways of participating on top of the forum.</p>
      <p>Bring an idea, make a fork, or add a small feature: the site is a shared foundation, not a finished product.</p>
    </section>
<?php endif; ?>
<?php if ($aboutContent['socialLinks'] !== []): ?>
    <section class="about-section" data-about-section="social-links">
      <h2>Find us</h2>
      <ul class="about-social-links">
<?php foreach ($aboutContent['socialLinks'] as $socialLink): ?>
        <li><a href="<?= $e($socialLink['url']) ?>"><?= $e($socialLink['label']) ?></a></li>
<?php endforeach; ?>
      </ul>
    </section>
<?php endif; ?>
<?php
// graph/participation/portable paragraphs below are rendered without $e()
// because they carry trusted inline <a> markup (links to /users/,
// /activity/, /tools/backup/, /api/, /llms.txt). This is safe only
// because ProfilePresentationContent::EDITORIAL is a private PHP const
// with no runtime/user write path - never feed user input through these
// fields.
?>
    <section class="about-section" data-about-section="graph">
      <h2><?= $e($aboutContent['graphHeading']) ?></h2>
<?php foreach ($aboutContent['graphParagraphs'] as $paragraph): ?>
      <p><?= $paragraph ?></p>
<?php endforeach; ?>
    </section>
    <section class="about-section" data-about-section="participation">
      <h2><?= $e($aboutContent['participationHeading']) ?></h2>
<?php foreach ($aboutContent['participationParagraphs'] as $paragraph): ?>
      <p><?= $paragraph ?></p>
<?php endforeach; ?>
    </section>
    <section class="about-section" data-about-section="portable">
      <h2><?= $e($aboutContent['portableHeading']) ?></h2>
<?php foreach ($aboutContent['portableParagraphs'] as $paragraph): ?>
      <p><?= $paragraph ?></p>
<?php endforeach; ?>
    </section>
  </article>
</section>
