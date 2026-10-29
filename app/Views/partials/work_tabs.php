<nav class="subnav">
    <a class="<?= uri_string() === 'app/work' ? 'active' : '' ?>" href="<?= site_url('app/work') ?>"><?= icon('square-check') ?> Tasks</a>
    <a class="<?= str_starts_with(uri_string(), 'app/work/calendar') ? 'active' : '' ?>" href="<?= site_url('app/work/calendar') ?>"><?= icon('calendar') ?> Calendar</a>
</nav>
