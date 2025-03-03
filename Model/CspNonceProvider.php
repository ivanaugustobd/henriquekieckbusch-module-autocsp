<?php
declare(strict_types=1);

namespace HenriqueKieckbusch\AutoCSP\Model;

class CspNonceProvider
{
    /**
     * @var string|null
     */
    private ?string $nonce = null;

    /**
     * Get the nonce to be used in the CSP header.
     *
     * @return string
     */
    public function getNonce() : ?string
    {
        if ($this->nonce === null) {
            $this->nonce = $this->generateNonce();
        }
        return $this->nonce;
    }

    /**
     * Create a new nonce
     *
     * @return string
     */
    private function generateNonce() : string
    {
        return base64_encode(random_bytes(16));
    }
}
