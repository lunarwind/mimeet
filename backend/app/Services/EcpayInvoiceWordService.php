<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 綠界電子發票字軌管理服務
 *
 * 字軌（TrackID）是發票號碼的英文前綴字母組合，
 * 必須先在綠界後台設定才能開立發票。
 * RtnCode 5070350 = 字軌未設定或已用完。
 */
class EcpayInvoiceWordService
{
    public function __construct(private ECPayService $ecpay) {}

    /**
     * 新增字軌並取得 TrackID
     *
     * @param int    $invoiceTerm  1-6（1=1-2月, 2=3-4月, ...）
     * @param string $invoiceYear  民國年（如 "115"）
     * @param string $header       兩個英文字母（如 "ZZ"）
     * @param int    $start        起始號碼（尾數須為 00 或 50）
     * @param int    $end          結束號碼（尾數須為 49 或 99）
     * @return array{ok:bool, track_id?:string, msg?:string, raw?:array}
     */
    public function add(int $invoiceTerm, string $invoiceYear, string $header, int $start, int $end, string $invType = '07'): array
    {
        // ── 基本驗證 ────────────────────────────────────────────────
        if ($invoiceTerm < 1 || $invoiceTerm > 6) {
            return ['ok' => false, 'msg' => 'InvoiceTerm 必須為 1-6（1=1-2月, 2=3-4月, 3=5-6月, 4=7-8月, 5=9-10月, 6=11-12月）'];
        }
        $header = strtoupper(trim($header));
        if (!preg_match('/^[A-Z]{2}$/', $header)) {
            return ['ok' => false, 'msg' => '字軌必須為兩個英文大寫字母'];
        }
        if ($start % 50 !== 0) {
            return ['ok' => false, 'msg' => '起號尾數必須為 00 或 50（綠界規定每組 50 個號碼）'];
        }
        if (($end + 1) % 50 !== 0) {
            return ['ok' => false, 'msg' => '迄號尾數必須為 49 或 99'];
        }
        if ($end <= $start) {
            return ['ok' => false, 'msg' => '迄號必須大於起號'];
        }

        // ── 期別防呆：不可小於當期 ───────────────────────────────
        $rocYear     = now()->year - 1911;
        $currentTerm = (int) ceil(now()->month / 2);
        if ((int) $invoiceYear < $rocYear ||
            ((int) $invoiceYear === $rocYear && $invoiceTerm < $currentTerm)) {
            return ['ok' => false, 'msg' => "期別不可小於當期（當期：{$rocYear} 年第 {$currentTerm} 期）"];
        }

        $payload = [
            'MerchantID'      => $this->merchantId(),
            'InvoiceTerm'     => $invoiceTerm,
            'InvoiceYear'     => $invoiceYear,
            'InvType'         => $invType,
            'InvoiceCategory' => '1',          // B2C 固定
            'InvoiceHeader'   => $header,
            'InvoiceStart'    => str_pad((string) $start, 8, '0', STR_PAD_LEFT),
            'InvoiceEnd'      => str_pad((string) $end, 8, '0', STR_PAD_LEFT),
        ];

        $result = $this->callApi('AddInvoiceWordSetting', $payload);

        if (!$result['ok']) {
            return $result;
        }

        // 綠界回應的字軌識別用 TrackID
        $trackId = $result['data']['TrackID'] ?? null;
        return [
            'ok'       => true,
            'track_id' => $trackId,
            'raw'      => $result['data'],
        ];
    }

    /**
     * 設定字軌啟用 / 停用狀態
     */
    public function setStatus(string $trackId, bool $enabled): array
    {
        $payload = [
            'MerchantID'    => $this->merchantId(),
            'TrackID'       => $trackId,
            'InvoiceStatus' => $enabled ? '1' : '0',   // 1=啟用 0=停用
        ];

        return $this->callApi('SetInvoiceWordStatus', $payload);
    }

    /**
     * 查詢字軌清單
     */
    public function query(int $invoiceYear, int $invoiceTerm): array
    {
        $payload = [
            'MerchantID'  => $this->merchantId(),
            'InvoiceTerm' => $invoiceTerm,
            'InvoiceYear' => (string) $invoiceYear,
        ];

        return $this->callApi('GetInvoiceWordSetting', $payload);
    }

    // ── 私有 helpers ──────────────────────────────────────────────

    private function merchantId(): string
    {
        $env = $this->ecpay->getEnvironment();
        return SystemSetting::get("ecpay_invoice_{$env}_merchant_id", '2000132');
    }

    private function isSandbox(): bool
    {
        return $this->ecpay->isSandbox();
    }

    private function baseUrl(): string
    {
        return $this->isSandbox()
            ? 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/'
            : 'https://einvoice.ecpay.com.tw/B2CInvoice/';
    }

    private function getHashKeyIv(): array
    {
        $env     = $this->ecpay->getEnvironment();
        $hashKey = SystemSetting::get("ecpay_invoice_{$env}_hash_key", '');
        $hashIv  = SystemSetting::get("ecpay_invoice_{$env}_hash_iv", '');

        // 如果加密了就先解密
        try { $hashKey = Crypt::decryptString($hashKey); } catch (\Throwable) {}
        try { $hashIv  = Crypt::decryptString($hashIv);  } catch (\Throwable) {}

        // fallback: sandbox 用官方測試憑證
        if (empty($hashKey) && $this->isSandbox()) {
            $hashKey = 'ejCk326UnaZWKisg';
        }
        if (empty($hashIv) && $this->isSandbox()) {
            $hashIv = 'q9jcZX8Ib9LM8wYk';
        }

        return [$hashKey, $hashIv];
    }

    private function callApi(string $endpoint, array $dataPayload): array
    {
        $url = $this->baseUrl() . $endpoint;
        [$hashKey, $hashIv] = $this->getHashKeyIv();

        // 借用 ECPayService 的私有 AES 方法（reflection）
        $ref        = new \ReflectionClass($this->ecpay);
        $encMethod  = $ref->getMethod('aesEncrypt');
        $encMethod->setAccessible(true);
        $decMethod  = $ref->getMethod('aesDecrypt');
        $decMethod->setAccessible(true);

        $jsonPayload = json_encode($dataPayload, JSON_UNESCAPED_UNICODE);
        $encrypted   = $encMethod->invoke($this->ecpay, $jsonPayload, $hashKey, $hashIv);

        $body = [
            'MerchantID' => $this->merchantId(),
            'RqHeader'   => ['Timestamp' => time()],
            'Data'       => $encrypted,
        ];

        Log::info("[InvoiceWord] {$endpoint} request", ['payload_keys' => array_keys($dataPayload)]);

        try {
            $response = Http::timeout(30)->asJson()->post($url, $body);
            $rawBody  = $response->body();
            Log::info("[InvoiceWord] {$endpoint} response", [
                'status' => $response->status(),
                'body'   => substr($rawBody, 0, 1500),
            ]);

            $decoded = $response->json();
            if (!$decoded || !isset($decoded['Data'])) {
                return ['ok' => false, 'msg' => 'invalid response: ' . substr($rawBody, 0, 300)];
            }

            if ((int) ($decoded['TransCode'] ?? 0) !== 1) {
                return ['ok' => false, 'msg' => 'TransCode≠1: ' . ($decoded['TransMsg'] ?? $rawBody)];
            }

            $decrypted = $decMethod->invoke($this->ecpay, $decoded['Data'], $hashKey, $hashIv);
            $data      = json_decode($decrypted, true);
            $rtnCode   = (int) ($data['RtnCode'] ?? 0);
            $rtnMsg    = $data['RtnMsg'] ?? '';

            if ($rtnCode !== 1) {
                return ['ok' => false, 'msg' => "RtnCode={$rtnCode}: {$rtnMsg}", 'data' => $data];
            }

            return ['ok' => true, 'data' => $data];
        } catch (\Throwable $e) {
            Log::error("[InvoiceWord] {$endpoint} exception", ['error' => $e->getMessage()]);
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }
}
