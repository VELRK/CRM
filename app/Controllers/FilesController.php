<?php

namespace App\Controllers;

class FilesController extends PortalController
{
    public function create()
    {
        $denied = $this->denyUnless('files.attach');

        if ($denied !== null) {
            return $denied;
        }

        $file = $this->request->getFile('upload');

        try {
            if ($file === null) {
                throw new \RuntimeException('Choose a file to attach.');
            }

            $id = $this->desk->saveFile(
                $this->tenantId,
                $this->userId,
                (string) session()->get('plan_code'),
                (string) $this->request->getPost('subject_type'),
                (int) $this->request->getPost('subject_id'),
                $file,
            );
            $this->desk->audit($this->tenantId, $this->userId, (string) session()->get('plan_code'), 'file.attach', (string) $this->request->getPost('subject_type'), (int) $this->request->getPost('subject_id'), 'File attached');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('message', 'File attached.');
    }

    public function download(int $id)
    {
        $denied = $this->denyUnless('files.view');

        if ($denied !== null) {
            return $denied;
        }

        $file = $this->desk->file($this->tenantId, $id);

        if ($file === null) {
            return redirect()->back()->with('error', 'That file is not in this workspace.');
        }

        $name = str_replace(['"', "\r", "\n"], '', (string) $file['original_name']);

        return $this->response
            ->setHeader('Content-Type', (string) $file['mime'])
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"')
            ->setBody((string) file_get_contents((string) $file['path']));
    }

    public function delete(int $id)
    {
        $denied = $this->denyUnless('files.delete');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->desk->deleteFile($this->tenantId, $id);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('message', 'File removed.');
    }
}
