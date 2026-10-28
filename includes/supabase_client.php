<?php
/**
 * Minimal Supabase REST (PostgREST) client.
 * No SDK required — just cURL against /rest/v1/<table>.
 */
class Supabase
{
    private static function request(string $method, string $path, array $data = null, array $query = [])
    {
        $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($path, '/');
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $headers = [
            'apikey: ' . SUPABASE_KEY,
            'Authorization: Bearer ' . SUPABASE_KEY,
            'Content-Type: application/json',
            'Prefer: return=representation',
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['ok' => false, 'error' => $err, 'data' => null, 'status' => 0];
        }

        $decoded = json_decode($response, true);
        $ok = $httpCode >= 200 && $httpCode < 300;

        return [
            'ok' => $ok,
            'status' => $httpCode,
            'data' => $decoded,
            'error' => $ok ? null : $decoded,
        ];
    }

    /** SELECT rows. $filters example: ['id' => 'eq.123', 'order' => 'created_at.desc'] */
    public static function select(string $table, array $filters = [], string $select = '*')
    {
        $query = array_merge(['select' => $select], $filters);
        $res = self::request('GET', $table, null, $query);
        return $res['ok'] ? $res['data'] : [];
    }

    public static function selectOne(string $table, array $filters = [], string $select = '*')
    {
        $rows = self::select($table, $filters, $select);
        return $rows[0] ?? null;
    }

    /** INSERT a row (or array of rows). Returns the inserted row(s) or false. */
    public static function insert(string $table, array $data)
    {
        $res = self::request('POST', $table, $data);
        return $res['ok'] ? $res['data'] : false;
    }

    /** UPDATE rows matching filters. */
    public static function update(string $table, array $filters, array $data)
    {
        $res = self::request('PATCH', $table, $data, $filters);
        return $res['ok'] ? $res['data'] : false;
    }

    /** DELETE rows matching filters. */
    public static function delete(string $table, array $filters)
    {
        $res = self::request('DELETE', $table, null, $filters);
        return $res['ok'];
    }

    /** Raw call, e.g. for RPC: Supabase::rpc('my_function', [...]) */
    public static function rpc(string $fn, array $params = [])
    {
        $res = self::request('POST', 'rpc/' . $fn, $params);
        return $res['ok'] ? $res['data'] : false;
    }

    public static function lastError()
    {
        return self::$lastError ?? null;
    }
}
