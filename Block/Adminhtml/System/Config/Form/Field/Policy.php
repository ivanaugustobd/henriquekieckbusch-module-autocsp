<?php
declare(strict_types=1);

namespace HenriqueKieckbusch\AutoCSP\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Html\Select;

class Policy extends AbstractFieldArray
{
    /**
     * @var Select
     */
    private $typeRenderer;

    /**
     * @var Select
     */
    private $activeRenderer;

    /**
     * Prepare rendering the new field by adding all the needed columns
     */
    protected function _prepareToRender()
    {
        $this->addColumn('policy', ['label' => __('Policy'), 'class' => 'required-entry']);
        $this->addColumn('type', [
            'label' => __('Type'),
            'renderer' => $this->getTypeRenderer()
        ]);
        $this->addColumn('content', ['label' => __('Content'), 'class' => 'required-entry']);
        $this->addColumn('active', [
            'label' => __('Active'),
            'renderer' => $this->getActiveRenderer()
        ]);
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Policy');
    }

    /**
     * Prepare existing row data object
     *
     * @param DataObject $row
     * @throws LocalizedException
     */
    protected function _prepareArrayRow(DataObject $row): void
    {
        $options = [];

        $type = $row->getData('type');
        if ($type) {
            $options['option_' . $this->getTypeRenderer()->calcOptionHash($type)] = 'selected="selected"';
        }

        $active = $row->getData('active');
        if ($active) {
            $options['option_' . $this->getActiveRenderer()->calcOptionHash($active)] = 'selected="selected"';
        }

        if ($row->getData('delete')) {
            $options['option_delete'] = 'selected="selected"';
        }

        $row->setData('option_extra_attrs', $options);
    }

    /**
     * Get the type renderer
     *
     * @return Select
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getTypeRenderer() : Select
    {
        if (!$this->typeRenderer) {
            $this->typeRenderer = $this->getLayout()->createBlock(
                Select::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
            $this->typeRenderer->setOptions([
                ['value' => 'url', 'label' => __('URL')],
                ['value' => 'host', 'label' => __('Host')]
            ]);
            $this->typeRenderer->setName($this->_getCellInputElementName('type'));
        }
        return $this->typeRenderer;
    }

    /**
     * Get the Active renderer
     *
     * @return Select
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getActiveRenderer() : Select
    {
        if (!$this->activeRenderer) {
            $this->activeRenderer = $this->getLayout()->createBlock(
                Select::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
            $this->activeRenderer->setOptions([
                ['value' => 'active', 'label' => __('Active')],
                ['value' => 'blocked', 'label' => __('Blocked')],
            ]);
            $this->activeRenderer->setName($this->_getCellInputElementName('active'));
        }
        return $this->activeRenderer;
    }

    /**
     * Inject the CSS
     *
     * @return string
     * @throws \Exception
     */
    protected function _toHtml()
    {
        $html = parent::_toHtml();
        $html .= <<<HTML
<style>
    #row_henrique_kieckbusch_policies_policy_list > td {
        display: none;
    }

    #row_henrique_kieckbusch_policies_policy_list > .value {
        display: table-cell;
        width: 100%;
    }
</style>
HTML;
        return $html;
    }
}
