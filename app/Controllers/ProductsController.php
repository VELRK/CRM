<?php

namespace App\Controllers;

class ProductsController extends PortalController
{
    public function index()
    {
        $denied = $this->denyUnless('products.view');

        if ($denied !== null) {
            return $denied;
        }

        $locked = $this->planLocked('money', 'Catalog', 'catalog');

        if ($locked !== null) {
            return $locked;
        }

        return $this->page('catalog/index', [
            'active'   => 'catalog',
            'products' => $this->desk->products($this->tenantId),
            'currency' => $this->desk->currency($this->tenantId),
            'open'     => session()->getFlashdata('open_modal') === 'product',
        ]);
    }

    public function create()
    {
        return $this->store(null, 'products.create');
    }

    public function update(int $id)
    {
        return $this->store($id, 'products.update');
    }

    public function delete(int $id)
    {
        $denied = $this->denyUnless('products.delete');

        if ($denied !== null) {
            return $denied;
        }

        $this->desk->deleteProduct($this->tenantId, $id);

        return redirect()->to('/app/catalog')->with('message', 'Product removed.');
    }

    private function store(?int $id, string $permission)
    {
        $denied = $this->denyUnless($permission);

        if ($denied !== null) {
            return $denied;
        }

        $locked = $this->planLocked('money', 'Catalog', 'catalog');

        if ($locked !== null) {
            return $locked;
        }

        try {
            $this->desk->saveProduct($this->tenantId, $id, [
                'name'     => (string) $this->request->getPost('name'),
                'price'    => (string) $this->request->getPost('price'),
                'tax_rate' => (string) $this->request->getPost('tax_rate'),
            ]);
        } catch (\Throwable $e) {
            return redirect()->to('/app/catalog')->withInput()->with('error', $e->getMessage())->with('open_modal', 'product');
        }

        return redirect()->to('/app/catalog')->with('message', $id === null ? 'Product added.' : 'Product saved.');
    }
}
