<?php
declare(strict_types=1);

namespace HenriqueKieckbusch\AutoCSP\Model\System\Config\Backend;

use HenriqueKieckbusch\AutoCSP\Model\AutocspData;
use HenriqueKieckbusch\AutoCSP\Model\ResourceModel\AutocspData\CollectionFactory;
use HenriqueKieckbusch\AutoCSP\Model\ResourceModel\AutocspData as AutocspDataResource;
use HenriqueKieckbusch\AutoCSP\Model\AutocspDataFactory;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class Policies extends Value
{
    /**
     * @var CollectionFactory
     */
    protected CollectionFactory $collectionFactory;

    /**
     * @var AutocspDataFactory
     */
    protected AutocspDataFactory $autocspData;

    /**
     * @var AutocspDataResource
     */
    private AutocspDataResource $autocspDataResource;

    /**
     * @param CollectionFactory $collectionFactory
     * @param AutocspDataFactory $autocspData
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param AutocspDataResource $autocspDataResource
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        AutocspDataFactory $autocspData,
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        AutocspDataResource $autocspDataResource,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->autocspData = $autocspData;
        $this->autocspDataResource = $autocspDataResource;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Get all policies
     *
     * @return DataObject[]
     */
    public function getPolicies() : array
    {
        return $this->collectionFactory->create()
            ->addFieldToSelect(['policy', 'type', 'data_content', 'active'])
            ->getItems();
    }

    /**
     * Save policies
     *
     * @return Policies
     * @throws AlreadyExistsException
     */
    public function beforeSave()
    {
        $values = $this->getValue();
        if (is_array($values)) {
            foreach ($values as $key => $data) {
                if (isset($data['delete']) && $data['delete'] == '1') {
                    unset($values[$key]);
                }
            }
        }

        $collection = $this->collectionFactory->create();
        $collection->walk('delete');

        foreach ($values as $data) {
            if (!is_array($data) ||
                !array_key_exists('policy', $data)
            ) {
                continue;
            }
            $policy = $this->autocspData->create();
            $policy->setData([
                'policy' => $data['policy'],
                'type' => $data['type'],
                'data_content' => $data['content'],
                'active' => ($data['active'] === 'active') ? 1 : 0,
            ]);
            $this->autocspDataResource->save($policy);
        }

        $this->setValue('');
        return parent::beforeSave();
    }

    /**
     * Load data and set to value
     *
     * @return $this
     */
    protected function _afterLoad()
    {
        parent::_afterLoad();
        $testPolicies = [];

        $collection = $this->collectionFactory->create()->load();
        /** @var AutocspData  $item */
        foreach ($collection as $item) {
            $testPolicies[] = [
                'policy' => $item->getPolicy(),
                'type' => $item->getType(),
                'content' => $item->getDataContent(),
                'active' => $item->getActive() ? 'active' : 'blocked',
                'id' => $item->getIdName()
            ];
        }

        $preparedData = [];
        foreach ($testPolicies as $policy) {
            $preparedData['_' . hash('sha256', (string)rand())] = [
                'policy' => $policy['policy'],
                'type' => $policy['type'],
                'content' => $policy['content'],
                'active' => $policy['active'],
                'id' => $policy['id']
            ];
        }

        $this->setValue(
            $preparedData
        );
        return $this;
    }
}
