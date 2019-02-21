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
class Run extends RunDrawingLib {
    /**
     * @var \Docx\FileAttachment|\Docx\FileAttachment[]|null|string
     * @desc Stores the relevant data to bubble to render stage
     */
    protected $_content = null;

    /**
     * @var bool
     */
    protected $_bIsValid = false;

    /**
     * @var self[]
     */
    protected $_subRunStack = [] ;

    /**
     * Run constructor.
     * @param $docx \Docx\Docx
     * @param $runElementNode \DOMElement
     * @param $parentNode Node
     */
    public function __construct($docx, $runElementNode, $parentNode)
    {

        $nodeType = $runElementNode->tagName ;
        $safeArr = [
            'w:r', 'w:hyperlink', 'w:drawing'
        ];
        if ( ! in_array($nodeType, $safeArr)) return;
        $this->_bIsValid = true ;

        $this->_runElementNode = $runElementNode;
        $this->_parentNode = $parentNode;
        $this->_docx = $docx;


        if ($nodeType == 'w:drawing'){
            $this->_content = $this->_loadDrawingData( ) ;
        }


        foreach ($this->_runElementNode->childNodes as $childNode){
            $subRun = new self($docx, $childNode, $parentNode);
            if ($subRun->isValid()){
                $this->_subRunStack[] = $subRun;
            }
        }

    }


    /**
     * @return bool
     */
    public function isValid(){
        return $this->_bIsValid;
    }


}