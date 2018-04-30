<?php
/**
 * Created by PhpStorm.
 * User: philg
 * Date: 29/04/2018
 * Time: 16:48
 */
namespace Docx;
class Run {
    /**
     * @var null | \DOMElement
     */
    protected $_domElement = null ;
    /**
     * @var null | Node
     */
    protected $_parentNode = null ;

    /**
     * Run constructor.
     * @param $currentDom \DOMElement
     * @param $parentNode Node
     */
    public function __construct( $currentDom, $parentNode) {
        $this->_domElement = $currentDom;
        $this->_parentNode = $parentNode;
    }
}
/*
 * HYPERLINK SPECIFIC LOADER
                           $this->_run[] = new Run($childNode, $this );
                           $hyperQuery = $this->_docx->getXPath()->query("w:hyperlink", $this->_domElement);
                           if ($hyperQuery->length > 0){
                               $modHyperlink = $hyperlink = '';
                               $hyperNode = $hyperQuery->item(0);
                               $hyperAttributes = $hyperNode->attributes;
                               $bUsingExternalLink = false;
                               foreach ($hyperAttributes as $attribute) {
                                   if ($attribute->nodeName == 'r:id') {
                                       if (isset($this->docx->linkMappings[$attribute->nodeValue])){
                                           $modHyperlink = $this->_docx->getAttachedLinks()[$attribute->nodeValue];
                                           $bUsingExternalLink = true;
                                       }
                                   }
                               }
                               foreach ($hyperNode->childNodes as $cn) {
                                   if ($cn->nodeName == 'w:r')
                                       $hyperlink = $cn->nodeValue;
                               }
                               # If we have the raw hyperlink, parse it
                               if ($hyperlink != ''){
                                   if (!$bUsingExternalLink) {
                                       if (substr($hyperlink, 0, 4) != 'http') {
                                           if (strpos($hyperlink, '@') !== false) {
                                               $modHyperlink = 'mailto:' . $hyperlink;
                                           } else
                                               $modHyperlink = 'http://' . $hyperlink;
                                       } else $modHyperlink = $hyperlink;
                                   }
                                   $this->_run[] = array(
                                       'text' => '<a href="' . $modHyperlink . '">' . $hyperlink . '</a>',
                                       'underline' => false,
                                       'subscript' => false,
                                       'superscript' => false,
                                       'tab' => false,
                                       'italic' => false,
                                       'bold' => false
                                   );
                               }
                           }
 */