<?php

declare(strict_types=1);

namespace HenriqueKieckbusch\AutoCSP\Plugin;

use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Result\Layout;
use Magento\Framework\View\Result\Page;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Csp\Helper\CspNonceProvider;

use function PHPSTORM_META\type;

class InlineScriptNoncePlugin
{
    private const XML_PATH_MODULE_ENABLED = 'henrique_kieckbusch/autocsp/enabled';
    private const XML_PATH_INLINE = 'henrique_kieckbusch/autocsp/inline_scripts';

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
        if (
            !$this->scopeConfig->isSetFlag(self::XML_PATH_MODULE_ENABLED) ||
            !$this->scopeConfig->isSetFlag(self::XML_PATH_INLINE)
        ) {
            return $result;
        }

        $content = $httpResponse->getContent();
        $nonceValue = $this->nonceProvider->generateNonce();
        $updatedContent = $content;

        $dom = new \DOMDocument();
        @$dom->loadHTML($content, LIBXML_SCHEMA_CREATE);

        $scripts = $dom->getElementsByTagName('script');
        foreach ($scripts as $script) {
            $nonce = $script->getAttribute('nonce');
            $type = $script->getAttribute('type');
            if (
                !$nonce && 
                $type !== 'text/x-magento-init' && 
                $type !== 'text/x-custom-template' && 
                $type !== 'text/x-magento-template'
                ) {
                $script->setAttribute('nonce', $nonceValue);
                $updatedContent = $dom->saveHTML();
            }
        }

        $httpResponse->setContent($updatedContent);
        return $result;
    }
}
