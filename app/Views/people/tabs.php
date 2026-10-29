<nav class="subnav">
    <a class="<?= uri_string() === 'app/people' || str_starts_with(uri_string(), 'app/people/') ? 'active' : '' ?>" href="<?= site_url('app/people') ?>"><?= icon('users') ?> Contacts</a>
    <?php if (in_array('companies.view', $can, true)) : ?>
        <a class="<?= str_starts_with(uri_string(), 'app/companies') ? 'active' : '' ?>" href="<?= site_url('app/companies') ?>"><?= icon('landmark') ?> Companies</a>
    <?php endif; ?>
</nav>
