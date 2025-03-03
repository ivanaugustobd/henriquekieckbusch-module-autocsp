<?php
declare(strict_types=1);

namespace HenriqueKieckbusch\AutoCSP\Model\ResourceModel\AutocspData;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use HenriqueKieckbusch\AutoCSP\Model\AutocspData as Model;
use HenriqueKieckbusch\AutoCSP\Model\ResourceModel\AutocspData as ResourceModel;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * AbstractCollection constructor
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
