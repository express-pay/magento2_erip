<?php

namespace Expresspay\Erip\Model;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field as BaseField;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\UrlInterface;
use Zend\Uri\UriFactory;

class Comment extends BaseField
{
    protected function _renderValue(AbstractElement $element)
    {
        $stores = $this->_storeManager->getStores();
        $valueReturn = '';
        $urlArray = [];

        foreach ($stores as $store) {
            $baseUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_WEB, true);
            if ($baseUrl) {
                $uri        = UriFactory::factory($baseUrl);
                $urlArray[] = $this->escapeHtml($uri->getScheme() . '://' . $uri->getHost(). '/erip/url/notification');
            }
        }

        $urlArray = array_unique($urlArray);
        foreach ($urlArray as $uniqueUrl) {
            $valueReturn .= "<div>".$uniqueUrl."</div>";
        }

        return '<td class="value">' . $valueReturn . '</td>';
    }

    protected function _renderInheritCheckbox(AbstractElement $element)
    {
        return '<td class="use-default"></td>';
    }
}
