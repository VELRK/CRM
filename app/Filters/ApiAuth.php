<?php

namespace App\Filters;

use App\Libraries\ApiContext;
use App\Libraries\WorkspaceService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ApiAuth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $header = $request->getHeaderLine('Authorization');

        if (! preg_match('/Bearer\s+(\S+)/i', $header, $match)) {
            return $this->denied('Send Authorization: Bearer followed by the token from POST /api/v1/auth/login.');
        }

        $db = db_connect();

        if (! $db->tableExists('api_tokens')) {
            return $this->denied('API tokens are not installed yet.');
        }

        $row = $db->table('api_tokens')
            ->where('token_hash', hash('sha256', $match[1]))
            ->where('revoked_at', null)
            ->get()
            ->getRowArray();

        if ($row === null) {
            return $this->denied('That token is not valid.');
        }

        $member = (new WorkspaceService($db))->membership((int) $row['user_id'], (int) $row['tenant_id']);

        if ($member === null) {
            return $this->denied('This token is not attached to a workspace.');
        }

        $db->table('api_tokens')->where('id', (int) $row['id'])->update(['last_used_at' => date('Y-m-d H:i:s')]);
        ApiContext::set([
            'token_id'    => (int) $row['id'],
            'user_id'     => (int) $row['user_id'],
            'tenant_id'   => (int) $row['tenant_id'],
            'role_id'     => (int) $member['role_id'],
            'is_owner'    => (int) $member['is_owner'] === 1,
            'permissions' => (new WorkspaceService($db))->permissions((int) $member['role_id']),
            'role_name'   => (string) $member['role_name'],
            'user_name'   => (string) $member['user_name'],
            'email'       => (string) $member['email'],
            'plan_code'   => (string) $member['plan_code'],
            'workspace'   => (string) $member['workspace_name'],
        ]);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response
            ->setHeader('Access-Control-Allow-Origin', '*')
            ->setHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type')
            ->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    }

    private function denied(string $message): ResponseInterface
    {
        return service('response')
            ->setStatusCode(401)
            ->setHeader('Access-Control-Allow-Origin', '*')
            ->setJSON(['ok' => false, 'error' => $message]);
    }
}
