<?php
/**
 * Created by PhpStorm.
 * User: philg
 * Date: 29/04/2018
 * Time: 18:49
 */
namespace Docx;
/**
 * Class Node
 * @package Docx
 */
class Node {
    /**
     * @var null | \DOMElement
     */
    private $_domElement = null;
    /**
     * @var null | Docx
     */
   private $_docx = null;
    /**
     * Node constructor.
     * @param $docx Docx
     * @param $domElement \DOMElement
     * @param bool $isDirect ( Are we inside a table or similar? we may need to process differently if so)
     * @param string | null $parentNodeId ( Tracks our parent div)
     */
   public function __construct($docx, $domElement,  $isDirect = false, $parentNodeId = null){
       $this->_docx = $docx;
       $this->_domElement = $domElement;
       $this->_parseNode($isDirect);
       $this->id = $this->_docx->generateNodeId();
       $this->isDirect = $isDirect;
       $this->parentId = $parentNodeId;
       $this->type = $domElement->nodeName;
   }

    /**
     * @desc Integrates this Node object, into the Docx
     * @param $docx Docx
     */
   public function attachToDocx($docx){
       unset ( $this->_docx);
       $docx->attachNode($this ) ;
   }

    /**
     * @param bool $isDirect
     */
   private function _parseNode($isDirect = false ) {

   }
}