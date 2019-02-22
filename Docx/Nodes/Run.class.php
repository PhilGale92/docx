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
     * @var null|ProcessedRun
     * @desc Stores the relevant data to bubble to render stage
     */
    protected $_processedRun = null;

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
        $nodeType = null;
        if (isset($runElementNode->tagName)) $nodeType = $runElementNode->tagName;
        $safeArr = [
            'w:r', 'w:hyperlink', 'w:drawing', 'w:t'
        ];
        if ( ! in_array($nodeType, $safeArr)) return;


        $this->_bIsValid = true ;

        $this->_runElementNode = $runElementNode;
        $this->_parentNode = $parentNode;
        $this->_docx = $docx;
        $processedRun = new ProcessedRun();
        if ($nodeType == 'w:drawing'){
            $processedRun->setImageContent( $this->_loadDrawingData()) ;
        } else if ($nodeType == 'w:t'){
            $processedRun->setTextContent(  $runElementNode->nodeValue  ) ;
        } else if ($nodeType == 'w:hyperlink'){
            $processedRun = $this->_setHyperlinkForRun($processedRun );
        }
        /*
         * Assign the processed entry into this, so we can bubble up the rendering behaviour
         */
        $this->_setProcessedRun($processedRun ) ;
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

    /**
     * @param $processedRun ProcessedRun
     */
    protected function _setProcessedRun($processedRun){
        $this->_processedRun = $processedRun;
    }


    /**
     * @param $processedRun ProcessedRun
     * @desc This method handles the setting hyperlink specific data and applying into the processed line
     * @return ProcessedRun
     */
    private function _setHyperlinkForRun($processedRun){
        $hyperQuery = $this->_docx->getXPath()->query("w:hyperlink", $this->_runElementNode);
        if ($hyperQuery->length > 0 ) {
            $hyperNode = $hyperQuery->item(0);
            $hyperlink = '';

            $bAutomaticLink = true ;

            foreach ($hyperNode->attributes as $attribute){
                if ($attribute->nodeName == 'r:id') {
                    $attachedLink = $this->_docx->getAttachedLinks($attribute->nodeValue);
                    if (is_object($attachedLink)) {
                        $hyperlink = $attachedLink->getLink();
                        $bAutomaticLink = false;
                    }
                }
            }
            if ($bAutomaticLink) {
                foreach ($hyperNode->childNodes as $cn) {
                    if ($cn->nodeName == 'w:r')
                        $hyperlink = $cn->nodeValue;
                }
                if (substr($hyperlink, 0, 4) != 'http') {
                    if (strpos($hyperlink, '@') !== false) {
                        $hyperlink = 'mailto:' . $hyperlink;
                    } else
                        $hyperlink = 'http://' . $hyperlink;
                }
            }
            $processedRun->setHyperLinkAutoBehaviour($bAutomaticLink);
            $processedRun->setHyperLinkHref($hyperlink);
        }
        return $processedRun;
    }
}