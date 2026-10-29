<?php
    $posted = old('lines');
    $source = is_array($posted) ? array_values($posted) : array_values($items ?? []);
    $lineRows = [];
    foreach ($source as $row) {
        if (! is_array($row)) {
            continue;
        }
        $price = $row['price'] ?? '';
        if ($price === '' && isset($row['price_minor'])) {
            $minor = (int) $row['price_minor'];
            $price = $minor % 100 === 0 ? (string) ($minor / 100) : number_format($minor / 100, 2, '.', '');
        }
        $lineRows[] = [
            'description' => $row['description'] ?? '',
            'qty'         => $row['qty'] ?? '1',
            'price'       => $price,
            'tax'         => $row['tax'] ?? ($row['tax_rate'] ?? '18'),
            'product_id'  => (string) ($row['product_id'] ?? ''),
        ];
    }
    while (count($lineRows) < 6) {
        $lineRows[] = ['description' => '', 'qty' => '1', 'price' => '', 'tax' => '18', 'product_id' => ''];
    }
?>
<div class="lines">
    <?php foreach ($lineRows as $i => $row) : ?>
        <div class="line">
            <select name="lines[<?= $i ?>][product_id]" data-line-product="<?= $i ?>">
                <option value="">Catalog</option>
                <?php foreach ($products as $product) :
                    $productPrice = (int) $product['price_minor'] % 100 === 0
                        ? (string) ((int) $product['price_minor'] / 100)
                        : number_format(((int) $product['price_minor']) / 100, 2, '.', '');
                ?>
                    <option value="<?= (int) $product['id'] ?>" data-name="<?= esc($product['name'], 'attr') ?>" data-price="<?= esc($productPrice, 'attr') ?>" data-tax="<?= esc((string) $product['tax_rate'], 'attr') ?>" <?= (string) $row['product_id'] === (string) $product['id'] ? 'selected' : '' ?>><?= esc($product['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input name="lines[<?= $i ?>][description]" value="<?= esc((string) $row['description']) ?>" placeholder="Description">
            <input name="lines[<?= $i ?>][qty]" value="<?= esc((string) $row['qty']) ?>" inputmode="decimal" aria-label="Quantity">
            <input name="lines[<?= $i ?>][price]" value="<?= esc((string) $row['price']) ?>" inputmode="decimal" placeholder="Price" aria-label="Price">
            <input name="lines[<?= $i ?>][tax]" value="<?= esc((string) $row['tax']) ?>" inputmode="decimal" aria-label="Tax percent">
        </div>
    <?php endforeach; ?>
</div>
