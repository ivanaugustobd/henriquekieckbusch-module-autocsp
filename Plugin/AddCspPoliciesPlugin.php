<?php
declare(strict_types=1);

namespace HenriqueKieckbusch\AutoCSP\Plugin;

use Magento\Csp\Helper\CspNonceProvider;
use HenriqueKieckbusch\AutoCSP\Model\ResourceModel\AutocspData\CollectionFactory;
use Magento\Csp\Model\Collector\CspWhitelistXmlCollector;
use Magento\Csp\Model\Policy\FetchPolicyFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class AddCspPoliciesPlugin
{
    private const XML_PATH_MODULE_ENABLED = 'henrique_kieckbusch/autocsp/enabled';
    private const XML_PATH_INLINE = 'henrique_kieckbusch/autocsp/inline_scripts';

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $dataCollectionFactory;

    /**
     * @var CspNonceProvider
     */
    private CspNonceProvider $nonceProvider;

    /**
     * @var FetchPolicyFactory
     */
    private FetchPolicyFactory $fetchPolicyFactory;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param CollectionFactory $dataCollectionFactory
     * @param CspNonceProvider $nonceProvider
     * @param FetchPolicyFactory $fetchPolicyFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        CollectionFactory $dataCollectionFactory,
        CspNonceProvider $nonceProvider,
        FetchPolicyFactory $fetchPolicyFactory,
        LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->dataCollectionFactory = $dataCollectionFactory;
        $this->nonceProvider = $nonceProvider;
        $this->fetchPolicyFactory = $fetchPolicyFactory;
        $this->logger = $logger;
    }

    /**
     * Add CSP policies to the collector
     *
     * @param CspWhitelistXmlCollector $subject
     * @param array $result
     * @return array
     * @throws LocalizedException
     */
    public function afterCollect(
        CspWhitelistXmlCollector $subject,
        array $result
    ) : array {
        $enabled = $this->scopeConfig->isSetFlag(self::XML_PATH_MODULE_ENABLED);
        $inline = $this->scopeConfig->isSetFlag(self::XML_PATH_INLINE);

        if (!$enabled) {
            return $result;
        }

        $policiesGrouped = [];

        $collection = $this->dataCollectionFactory->create();
        $collection->addFieldToFilter('active', 1);

        foreach ($collection as $item) {
            $policyId = $this->mapPolicyType($item->getPolicy());
            $type = $item->getData('type') ?: 'host';
            $dataContent = $item->getData('data_content');

            if (!isset($policiesGrouped[$policyId])) {
                $policiesGrouped[$policyId] = [
                    'id' => $policyId,
                    'hostSources' => [],
                    'schemeSources' => [],
                    'nonceValues' => [],
                    'hashValues' => [],
                ];
            }

            switch ($type) {
                case 'inline':
                    if (strpos($dataContent, "'nonce-") === 0) {
                        $policiesGrouped[$policyId]['nonceValues'][] = $dataContent;
                    }
                    break;
                case 'scheme':
                    $policiesGrouped[$policyId]['schemeSources'][] = $dataContent;
                    break;
                case 'hash':
                    $policiesGrouped[$policyId]['hashValues'][] = $dataContent;
                case 'url':
                case 'host':
                default:
                    $policiesGrouped[$policyId]['hostSources'][] = $dataContent;
                    break;
            }
        }

        if ($inline) {
            $nonce = $this->nonceProvider->generateNonce();

            if (!isset($policiesGrouped['script-src'])) {
                $policiesGrouped['script-src'] = [
                    'id' => 'script-src',
                    'hostSources' => [],
                    'schemeSources' => [],
                    'nonceValues' => [$nonce]
                ];
            } else {
                $policiesGrouped['script-src']['nonceValues'][] = $nonce;
            }
        }

        $existingPoliciesByType = [];

        foreach ($result as $index => $policy) {
            $policyId = $this->mapPolicyType($policy->getId());
            if (!isset($existingPoliciesByType[$policyId])) {
                $existingPoliciesByType[$policyId] = [];
            }
            $existingPoliciesByType[$policyId][] = [
                'index' => $index,
                'policy' => $policy
            ];
        }

        foreach ($policiesGrouped as $policyId => $policyData) {
            $existingPolicies = $existingPoliciesByType[$policyId] ?? [];

            $allHostSources = $policyData['hostSources'];
            $allSchemeSources = $policyData['schemeSources'];
            $allNonceValues = $policyData['nonceValues'];
            $allHashValues = $policyData['hashValues'];
            $selfAllowed = false;
            $inlineAllowed = false;
            $evalAllowed = false;
            $dynamicAllowed = false;
            $eventHandlersAllowed = false;

            foreach ($existingPolicies as $existingPolicyData) {
                $existingPolicy = $existingPolicyData['policy'];
                $allHostSources = array_merge(// phpcs:ignore
                    $allHostSources,
                    $existingPolicy->getHostSources() ?: []
                );
                $allSchemeSources = array_merge(// phpcs:ignore
                    $allSchemeSources,
                    $existingPolicy->getSchemeSources() ?: []
                );
                $allNonceValues = array_merge(// phpcs:ignore
                    $allNonceValues,
                    $existingPolicy->getNonceValues() ?: []
                );
                $allHashValues = array_merge( // phpcs:ignore
                    $allHashValues,
                    $existingPolicy->getHashes() ?: []
                );
                $selfAllowed = $selfAllowed || $existingPolicy->isSelfAllowed();
                $inlineAllowed = $inlineAllowed || $existingPolicy->isInlineAllowed();
                $evalAllowed = $evalAllowed || $existingPolicy->isEvalAllowed();
                $dynamicAllowed = $dynamicAllowed || $existingPolicy->isDynamicAllowed();
                $eventHandlersAllowed = $eventHandlersAllowed || $existingPolicy->areEventHandlersAllowed();
                $hashValues = $allHashValues || $existingPolicy->getHashes();
            }

            $newPolicy = $this->fetchPolicyFactory->create([
                'id' => $policyId,
                'noneAllowed' => false,
                'hostSources' => array_unique($allHostSources),
                'schemeSources' => array_unique($allSchemeSources),
                'selfAllowed' => $selfAllowed,
                'inlineAllowed' => $inlineAllowed,
                'evalAllowed' => $evalAllowed,
                'nonceValues' => array_unique($allNonceValues),
                'hashValues' => $hashValues,
                'dynamicAllowed' => $dynamicAllowed,
                'eventHandlersAllowed' => $eventHandlersAllowed
            ]);

            foreach ($existingPolicies as $existingPolicyData) {
                unset($result[$existingPolicyData['index']]);
            }

            $result[$policyId] = $newPolicy;
        }

        $this->logger->debug('CSP Policies after merging: ' . count($result));

        return $result;
    }

    /**
     * Used to map the policy ID to the correct CSP policy type
     *
     * @param string $policyId
     * @return string
     */
    private function mapPolicyType(string $policyId) : string
    {
        $mapping = [
            'script-src-elem' => 'script-src',
            'style-src-elem' => 'style-src'
        ];
        return $mapping[$policyId] ?? $policyId;
    }
}
