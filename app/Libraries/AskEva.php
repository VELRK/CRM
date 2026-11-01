<?php

namespace App\Libraries;

/**
 * Sends a WhatsApp template through AskEva. The Meta Graph API is not called.
 */
class AskEva
{
    /**
     * @param list<string> $parameters
     * @return array{ok: bool, error: string}
     */
    public function sendTemplate(string $token, string $phone, string $template, string $language, array $parameters): array
    {
        $token = trim($token);
        $template = trim($template);
        $to = $this->phone($phone);

        if ($token === '') {
            return ['ok' => false, 'error' => 'Save the AskEva token before sending.'];
        }

        if ($template === '') {
            return ['ok' => false, 'error' => 'Choose an AskEva template name.'];
        }

        if ($to === '') {
            return ['ok' => false, 'error' => 'That phone number cannot be used for WhatsApp.'];
        }

        $body = [];

        foreach ($parameters as $text) {
            $text = trim((string) $text);
            $body[] = ['type' => 'text', 'text' => $text === '' ? '-' : $text];
        }

        $payload = [
            'to'       => $to,
            'type'     => 'template',
            'template' => [
                'language' => [
                    'policy' => 'deterministic',
                    'code'   => trim($language) !== '' ? trim($language) : 'en',
                ],
                'name'       => $template,
                'components' => [[
                    'type'       => 'body',
                    'parameters' => $body,
                ]],
            ],
        ];
        $response = $this->post('https://backend.askeva.io/v1/message/send-message?token=' . rawurlencode($token), $payload);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            return ['ok' => false, 'error' => $this->reason($response['json'], 'AskEva did not accept the message.')];
        }

        $json = $response['json'];

        if (is_array($json) && (($json['success'] ?? null) === false || ($json['status'] ?? '') === 'error' || ($json['status'] ?? '') === 'failed')) {
            return ['ok' => false, 'error' => $this->reason($json, 'AskEva did not accept the message.')];
        }

        return ['ok' => true, 'error' => ''];
    }

    public function phone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            $digits = '91' . $digits;
        }

        if (strlen($digits) < 11 || strlen($digits) > 15) {
            return '';
        }

        return $digits;
    }

    /**
     * @param array<string, string> $known
     * @return list<string>
     */
    public function values(string $body, array $known, array $fallback): array
    {
        preg_match_all('/\{\{\s*([^}]+)\s*\}\}/', $body, $matches);
        $keys = $matches[1] ?? [];

        if ($keys === []) {
            $keys = $fallback;
        }

        $values = [];

        foreach ($keys as $key) {
            $name = strtolower(trim((string) $key));
            $values[] = $known[$name] ?? $known[(string) $key] ?? '-';
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, json: ?array<string, mixed>}
     */
    private function post(string $url, array $payload): array
    {
        if (! function_exists('curl_init')) {
            return ['status' => 0, 'json' => ['error' => 'The server cannot open an outbound connection.']];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode((string) $raw, true);

        return ['status' => $status, 'json' => is_array($json) ? $json : null];
    }

    /**
     * @param ?array<string, mixed> $json
     */
    private function reason(?array $json, string $fallback): string
    {
        if ($json === null) {
            return $fallback;
        }

        foreach (['error', 'message', 'details', 'reason'] as $key) {
            $value = trim((string) ($json[$key] ?? ''));

            if ($value !== '' && ! str_contains(strtolower($value), 'token=')) {
                return mb_substr($value, 0, 180);
            }
        }

        return $fallback;
    }
}
