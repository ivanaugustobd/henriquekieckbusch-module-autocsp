<?php
declare(strict_types=1);

namespace HenriqueKieckbusch\AutoCSP\Plugin;

use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Result\Layout;
use Magento\Framework\View\Result\Page;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Csp\Helper\CspNonceProvider;

class InlineScriptNoncePlugin
{
    private const XML_PATH_MODULE_ENABLED = 'henrique_kieckbusch/autocsp/enabled';
    private const XML_PATH_INLINE = 'henrique_kieckbusch/autocsp/inline_scripts';
    private const SCRIPT_PATTERN = '/<script(?![^>]*\bnonce=)([^>]*)>/i';

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var CspNonceProvider
     */
    private CspNonceProvider $nonceProvider;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param CspNonceProvider $nonceProvider
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        CspNonceProvider $nonceProvider
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->nonceProvider = $nonceProvider;
    }

    /**
     * Let's inject the nonce attribute into the script tags when needed
     *
     * @param Page $subject
     * @param Layout $result
     * @param ResponseInterface $httpResponse
     * @return mixed
     * @throws LocalizedException
     */
    public function afterRenderResult(Page $subject, Layout $result, ResponseInterface $httpResponse)
    {
        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_MODULE_ENABLED) ||
            !$this->scopeConfig->isSetFlag(self::XML_PATH_INLINE)
        ) {
            return $result;
        }

        $content = $httpResponse->getContent();
        $nonce = $this->nonceProvider->generateNonce();
        $httpResponse->setContent(
            preg_replace(
                self::SCRIPT_PATTERN,
                '<script nonce="' . $nonce . '"$1>',
                $content
            )
        );
        return $result;
    }
}
