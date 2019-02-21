<?php
/**
 * Created by PhpStorm.
 * User: Phil Gale
 * Date: 21/02/2019
 * Time: 11:21
 */

namespace Docx\Nodes;
/**
 * Class Run
 * @package Docx\Nodes
 * @desc Run is used to manage the contents that is being injected into the actual Nodes/Node object so that we can pull
 * in the different levels of drawings and other forms of content etc
 * content that goes into the node
 */
class Run  {
    /**
     * @var bool
     */
    protected $_bIsValid = false;
    /**
     * @var Node
     */
    protected $parentNode = null;
    /**
     * @var null | \DOMElement
     */
    protected $runElementNode = null;

    /**
     * Run constructor.
     * @param $runElementNode \DOMElement
     * @param $parentNode Node
     */
    public function __construct($runElementNode, $parentNode)
    {

        $nodeType = $runElementNode->tagName ;
        $safeArr = [
            'w:r', 'w:hyperlink', 'w:drawing'
        ];
        if ( ! in_array($nodeType, $safeArr)) return;
        $this->_bIsValid = true ;


        $this->runElementNode = $runElementNode;
        $this->parentNode = $parentNode;
        
        switch ($nodeType){
            case 'w:r':

                break;
        }

    }


    /**
     * @return bool
     */
    public function isValid(){
        return $this->_bIsValid;
    }


}