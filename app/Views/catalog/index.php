<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
    $symbol = ($currency ?? 'INR') === 'USD' ? '$' : '₹';
    $failedId = (int) old('record_id');
    $action = $failedId > 0 ? site_url('app/catalog/' . $failedId) : site_url('app/catalog');
    $plain = static function (int $minor): string {
        return $minor % 100 === 0 ? (string) ($minor / 100) : number_format($minor / 100, 2, '.', '');
    };
?>
<header class="page-head">
    <div>
        <p class="eyebrow">Money</p>
        <h1>Catalog</h1>
    </div>
    <?php if (in_array('products.create', $can, true)) : ?>
        <button class="primary" type="button" data-modal="product-modal" data-action="<?= site_url('app/catalog') ?>" data-title="New product" data-record_id="" data-name="" data-price="" data-tax_rate="18"><?= icon('plus') ?> New product</button>
    <?php endif; ?>
</header>
<?php if ($products === []) : ?>
    <section class="panel"><div class="empty"><?= icon('inbox') ?><p>No products yet.</p></div></section>
<?php else : ?>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Product</th><th class="num">Price</th><th class="num">Tax</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($products as $product) : ?>
                <tr>
                    <td><strong><?= esc($product['name']) ?></strong></td>
                    <td class="num"><?= esc(\App\Libraries\DeskService::money((int) $product['price_minor'], (string) $product['currency'])) ?></td>
                    <td class="num"><?= esc((string) $product['tax_rate']) ?>%</td>
                    <td class="actions-cell">
                        <?php if (in_array('products.update', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="product-modal" data-action="<?= site_url('app/catalog/' . $product['id']) ?>" data-title="Edit product" data-record_id="<?= (int) $product['id'] ?>" data-name="<?= esc($product['name'], 'attr') ?>" data-price="<?= esc($plain((int) $product['price_minor']), 'attr') ?>" data-tax_rate="<?= esc((string) $product['tax_rate'], 'attr') ?>"><?= icon('square-pen') ?> Edit</button>
                        <?php endif; ?>
                        <?php if (in_array('products.delete', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="confirm-delete" data-action="<?= site_url('app/catalog/' . $product['id'] . '/delete') ?>" data-title="Remove this product?"><?= icon('trash-2') ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<dialog id="product-modal" class="modal" <?= ! empty($open) ? 'data-open="1"' : '' ?>>
    <form method="post" action="<?= $action ?>" data-modal-form>
        <?= csrf_field() ?>
        <input type="hidden" name="record_id" data-field="record_id" value="<?= $failedId ?: '' ?>">
        <header class="modal-head">
            <h2 data-modal-title>Product</h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body stack">
            <label>Name <input name="name" data-field="name" value="<?= esc((string) old('name')) ?>" required></label>
            <div class="split">
                <label>Price (<?= esc($symbol) ?>) <input name="price" data-field="price" value="<?= esc((string) old('price')) ?>" inputmode="decimal"></label>
                <label>Tax % <input name="tax_rate" data-field="tax_rate" value="<?= esc((string) (old('tax_rate') ?: '18')) ?>" inputmode="decimal"></label>
            </div>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Cancel</button>
            <button class="primary" type="submit"><?= icon('package') ?> Save product</button>
        </footer>
    </form>
</dialog>
<?= $this->endSection() ?>
