<?php declare(strict_types=1);

namespace HenriqueKieckbusch\AutoCSP\Plugin;

use Magento\Csp\Api\Data\ModeConfiguredInterface;
use Magento\Csp\Model\Mode\ConfigManager;
use Magento\Csp\Model\Mode\Data\ModeConfigured;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;

class ConfigManagerPlugin
{
    private const XML_PATH_CAPTURE = 'henrique_kieckbusch/autocsp/capture';
    private const XML_PATH_MODULE_ENABLED = 'henrique_kieckbusch/autocsp/enabled';

    /**
     * Overriden result if this module is enabled and capture mode is on
     *
     * @var ModeConfiguredInterface
     */
    private readonly ModeConfiguredInterface $modeConfigured;

    // phpcs:ignore
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlInterface $urlBuilder,
    ) {
    }

    /**
     * Update the reportOnly and reportUri properties
     *
     * @param ConfigManager $subject
     * @param ModeConfiguredInterface $result
     * @return ModeConfiguredInterface
     */
    public function afterGetConfigured(
        ConfigManager $subject,
        ModeConfiguredInterface $result
    ) : ModeConfiguredInterface {
        if (isset($this->modeConfigured)) {
            return $this->modeConfigured;
        }

        $isAutoCspEnabled = $this->scopeConfig->isSetFlag(self::XML_PATH_MODULE_ENABLED);
        $isCaptureModeEnabled = $this->scopeConfig->isSetFlag(self::XML_PATH_CAPTURE);

        $this->modeConfigured = !$isAutoCspEnabled || !$isCaptureModeEnabled ? $result : new ModeConfigured(
            $isCaptureModeEnabled,
            $this->urlBuilder->getUrl('autocsp/report/index'),
        );

        return $this->modeConfigured;
    }
}
