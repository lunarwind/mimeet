<?php

namespace App\Exceptions;

/**
 * Thrown by PhoneService when phone unique or blacklist check fails.
 *
 * 契約（v5 D17）：此 exception 永遠被 calling controller (AuthController /
 * PhoneChangeController) catch，並轉成 422 response 對齊既有 register unique
 * error shape（anti-enumeration）。**絕不應 propagate 到 Laravel exception
 * handler** — 若有 propagate 表示 controller 漏 catch，是 bug。
 *
 * Example caller:
 *   try {
 *       $result = $phoneService->setVerifiedPhone($user, $phone, 'verify', $request);
 *   } catch (PhoneConflictException $e) {
 *       return response()->json([
 *           'success' => false,
 *           'errors' => ['phone' => [$e->getMessage()]],
 *           ...
 *       ], 422);
 *   }
 */
class PhoneConflictException extends \Exception
{
}
