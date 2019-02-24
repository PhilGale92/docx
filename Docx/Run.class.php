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