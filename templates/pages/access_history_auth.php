<section class="stack">
  <article class="card">
    <h1>Access history</h1>
<?= $partial('partials/access_history_auth.php', ['canSignIn' => $canSignIn, 'authReturnTo' => $authReturnTo]) ?>
    <p><a href="/tools/user-access/">User Access</a></p>
  </article>
</section>
