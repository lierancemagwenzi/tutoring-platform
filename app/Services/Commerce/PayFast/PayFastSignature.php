<?php

namespace App\Services\Commerce\PayFast;

/**
 * PayFast's MD5 signature algorithm. PayFast uses two subtly different
 * rules depending on direction, confirmed empirically against their real
 * sandbox: when WE build the outbound redirect payload, empty/null fields
 * must be omitted from the signed string entirely. When verifying an
 * INBOUND ITN, PayFast's own signature was computed over every field it
 * sent, including ones with an empty value (e.g. unused custom_str2-5) —
 * omitting those here produces a different string and a false "mismatch".
 */
class PayFastSignature
{
    /**
     * For signing our own outbound redirect payload — omit empty/null fields.
     *
     * @param  array<string, mixed>  $fields  In insertion order, `signature` already excluded.
     */
    public function generate(array $fields, ?string $passphrase): string
    {
        $pairs = [];

        foreach ($fields as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $pairs[] = $key.'='.urlencode(trim((string) $value));
        }

        return $this->hash($pairs, $passphrase);
    }

    /**
     * For recomputing the signature of an inbound ITN — every field PayFast
     * sent is included verbatim, even ones with an empty/null value.
     *
     * @param  array<string, mixed>  $fields  In insertion order, `signature` already excluded.
     */
    public function generateForVerification(array $fields, ?string $passphrase): string
    {
        $pairs = [];

        foreach ($fields as $key => $value) {
            $pairs[] = $key.'='.urlencode(trim((string) $value));
        }

        return $this->hash($pairs, $passphrase);
    }

    /**
     * @param  list<string>  $pairs
     */
    private function hash(array $pairs, ?string $passphrase): string
    {
        $paramString = implode('&', $pairs);

        if ($passphrase) {
            $paramString .= '&passphrase='.urlencode(trim($passphrase));
        }

        return md5($paramString);
    }
}
