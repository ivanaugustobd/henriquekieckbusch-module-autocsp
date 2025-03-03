<?php
declare(strict_types=1);

namespace HenriqueKieckbusch\AutoCSP\Controller\Report;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use HenriqueKieckbusch\AutoCSP\Model\ReportProcessor;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class Index implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const XML_PATH_MODULE_ENABLED = 'henrique_kieckbusch/autocsp/enabled';
    private const XML_PATH_CAPTURE = 'henrique_kieckbusch/autocsp/capture';

    /**
     * @var JsonFactory
     */
    private JsonFactory $jsonFactory;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var ReportProcessor
     */
    private ReportProcessor $reportProcessor;

    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var Json
     */
    private Json $serializer;

    /**
     * @param JsonFactory $jsonFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param ReportProcessor $reportProcessor
     * @param RequestInterface $request
     * @param LoggerInterface $logger
     * @param Json $serializer
     */
    public function __construct(
        JsonFactory $jsonFactory,
        ScopeConfigInterface $scopeConfig,
        ReportProcessor $reportProcessor,
        RequestInterface $request,
        LoggerInterface $logger,
        Json $serializer
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->scopeConfig = $scopeConfig;
        $this->reportProcessor = $reportProcessor;
        $this->request = $request;
        $this->logger = $logger;
        $this->serializer = $serializer;
    }

    /**
     * The browser doesn't send it, it is all good
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * The browser doesn't send it, it is all good
     *
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * When a report is received, it is processed and stored in the database.
     *
     * @return ResponseInterface|\Magento\Framework\Controller\Result\Json|ResultInterface
     */
    public function execute()
    {
        $enabled = $this->scopeConfig->isSetFlag(self::XML_PATH_MODULE_ENABLED);
        $capture = $this->scopeConfig->isSetFlag(self::XML_PATH_CAPTURE);

        $result = $this->jsonFactory->create();

        if (!$enabled || !$capture) {
            return $result->setData(['error' => __('Not accepting reports.')])
                ->setHttpResponseCode(403);
        }

        try {
            $input = $this->request->getContent();
            $data = $this->serializer->unserialize($input, true);

            if (is_array($data)) {
                if (isset($data['csp-report'])) {
                    $this->reportProcessor->processCspReport($data['csp-report']);
                } elseif (isset($data[0]) && isset($data[0]['body'])) {
                    foreach ($data as $report) {
                        if (isset($report['body'])) {
                            $this->reportProcessor->processChromeReport($report['body']);
                        }
                    }
                } else {
                    $this->reportProcessor->processGenericReport($data);
                }
            }
            return $result->setData(['status' => 'ok']);

        } catch (\Exception $e) {
            $this->logger->error('CSP Report processing error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $result->setData(['status' => 'error', 'message' => 'Internal processing error']);
        }
    }
}
