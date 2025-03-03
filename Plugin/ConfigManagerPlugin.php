<?php
declare(strict_types=1);

namespace HenriqueKieckbusch\AutoCSP\Plugin;

use Magento\Csp\Api\Data\ModeConfiguredInterface;
use Magento\Csp\Model\Mode\ConfigManager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;

class ConfigManagerPlugin
{
    private const XML_PATH_CAPTURE = 'henrique_kieckbusch/autocsp/capture';
    private const XML_PATH_MODULE_ENABLED = 'henrique_kieckbusch/autocsp/enabled';

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @var null
     */
    private static $reflectionCache = null;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        UrlInterface $urlBuilder
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Let's update the reportOnly and reportUri properties
     *
     * @param ConfigManager $subject
     * @param ModeConfiguredInterface $result
     * @return ModeConfiguredInterface
     * @throws \ReflectionException
     */
    public function afterGetConfigured(
        ConfigManager $subject,
        ModeConfiguredInterface $result
    ) : ModeConfiguredInterface {
        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_MODULE_ENABLED)
        ) {
            return $result;
        }

        if (self::$reflectionCache === null) {
            self::$reflectionCache = new \ReflectionClass($result);
        }

        $reportOnlyProperty = self::$reflectionCache->getProperty('reportOnly');
        $reportOnlyProperty->setAccessible(true);
        $reportOnlyProperty->setValue($result, $this->scopeConfig->isSetFlag(self::XML_PATH_CAPTURE));

        if ($this->scopeConfig->isSetFlag(self::XML_PATH_CAPTURE)) {
            $reportUriProperty = self::$reflectionCache->getProperty('reportUri');
            $reportUriProperty->setAccessible(true);
            $reportUriProperty->setValue($result, $this->urlBuilder->getUrl('autocsp/report/index'));
        }

        return $result;
    }
}
