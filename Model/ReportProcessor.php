<?php
declare(strict_types=1);

namespace HenriqueKieckbusch\AutoCSP\Model;

use HenriqueKieckbusch\AutoCSP\Model\ResourceModel\AutocspData\CollectionFactory as DataCollectionFactory;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Url;
use Psr\Log\LoggerInterface;

class ReportProcessor
{
    /**
     * @var DataCollectionFactory
     */
    private DataCollectionFactory $dataCollectionFactory;

    /**
     * @var AutocspDataFactory
     */
    private AutocspDataFactory $autocspDataFactory;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var TransactionFactory
     */
    private TransactionFactory $transactionFactory;

    /**
     * @var Url
     */
    private Url $urlModel;

    /**
     * @param DataCollectionFactory $dataCollectionFactory
     * @param AutocspDataFactory $autocspDataFactory
     * @param LoggerInterface $logger
     * @param TransactionFactory $transactionFactory
     * @param Url $urlModel
     */
    public function __construct(
        DataCollectionFactory $dataCollectionFactory,
        AutocspDataFactory $autocspDataFactory,
        LoggerInterface $logger,
        TransactionFactory $transactionFactory,
        Url $urlModel
    ) {
        $this->dataCollectionFactory = $dataCollectionFactory;
        $this->autocspDataFactory = $autocspDataFactory;
        $this->logger = $logger;
        $this->transactionFactory = $transactionFactory;
        $this->urlModel = $urlModel;
    }

    /**
     * Receive a report and process it
     *
     * @param array $reportData
     * @return void
     */
    public function process(array $reportData)
    {
        if (isset($reportData['csp-report'])) {
            $this->processCspReport($reportData['csp-report']);
        } elseif (is_array($reportData) && isset($reportData[0]) && isset($reportData[0]['body'])) {
            foreach ($reportData as $report) {
                if (isset($report['body'])) {
                    $this->processChromeReport($report['body']);
                }
            }
        } else {
            $this->processGenericReport($reportData);
        }
    }

    /**
     * When the report is a CSP report
     *
     * @param array $cspReport
     * @return void
     */
    public function processCspReport(array $cspReport)
    {
        $policy = $cspReport['violated-directive'] ?? '';
        $blockedUri = $cspReport['blocked-uri'] ?? '';

        $this->saveViolation($policy, $blockedUri);
    }

    /**
     * When the report is a Chrome report
     *
     * @param array $body
     * @return void
     */
    public function processChromeReport(array $body)
    {
        $policy = $body['effectiveDirective'] ?? '';
        $blockedUri = $body['blockedURL'] ?? '';

        $this->saveViolation($policy, $blockedUri);
    }

    /**
     * When it is a generic report
     *
     * @param array $data
     * @return void
     */
    public function processGenericReport(array $data)
    {
        $policy = null;
        $blockedUri = null;

        foreach (['violated-directive', 'effectiveDirective', 'directive'] as $field) {
            if (!empty($data[$field])) {
                $policy = $data[$field];
                break;
            }
        }

        foreach (['blocked-uri', 'blockedURL', 'blocked_uri'] as $field) {
            if (!empty($data[$field])) {
                $blockedUri = $data[$field];
                break;
            }
        }

        if ($policy && $blockedUri) {
            $this->saveViolation($policy, $blockedUri);
        } else {
            $this->logger->info('CSP Report: Could not extract policy and URI from report', ['data' => $data]);
        }
    }

    /**
     * Save the violation to the database
     *
     * @param string $policy
     * @param string $blockedUri
     * @return void
     */
    private function saveViolation(string $policy, string $blockedUri)
    {
        if (!$policy || !$blockedUri || $blockedUri === 'inline') {
            return;
        }

        $dataContent = $this->generateCspPattern($blockedUri);
        $collection = $this->dataCollectionFactory->create()
            ->addFieldToFilter('policy', $policy)
            ->addFieldToFilter('data_content', $dataContent);

        if ($collection->getSize() === 0) {
            $transaction = $this->transactionFactory->create();
            try {
                $autocspItem = $this->autocspDataFactory->create();
                $autocspItem->setData([
                    'policy' => $policy,
                    'type' => 'url',
                    'data_content' => $dataContent
                ]);
                $transaction->addObject($autocspItem)->save();
                $this->logger->info('CSP: Added new policy', ['policy' => $policy, 'content' => $dataContent]);
            } catch (\Exception $e) {
                $transaction->rollback();
                $this->logger->error('CSP: Failed to save policy', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Create a pattern from the URL
     *
     * @param string $url
     * @return mixed
     */
    private function generateCspPattern(string $url)
    {
        try {
            $parsedUrl = $this->urlModel->parseUrl($url);
            $host = $parsedUrl->getHost();

            if (empty($host)) {
                return $url;
            }

            return $host;
        } catch (\Exception $e) {
            $this->logger->warning('Failed to parse URL: ' . $url, ['exception' => $e]);
            return $url;
        }
    }
}
