<?php
/**
 * Created by PhpStorm.
 * User: Phil Gale
 * Date: 21/02/2019
 * Time: 11:21
 */

namespace PhilGale92Docx\Nodes;
use PhilGale92Docx\Docx;

/**
 * Class Run
 * @package PhilGale92Docx\Nodes
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
     * @param $docx \PhilGale92Docx\Docx
     * @param $runElementNode  \DOMElement
     */
    public function __construct($docx, $runElementNode)
    {
        $nodeType = null;
        if (isset($runElementNode->tagName)) $nodeType = $runElementNode->tagName;
        $safeArr = [
            'w:r', 'w:hyperlink', 'w:drawing', 'w:t'
        ];
        if ( ! in_array($nodeType, $safeArr)) return;


        $this->_bIsValid = true ;

        $this->_runElementNode = $runElementNode;
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
         * Query this run for style information
         */
        $runStyleQuery = $this->_docx->getXPath()->query("w:rPr", $runElementNode);
        $tabQuery = $this->_docx->getXPath()->query("w:tab", $runElementNode);
        if ($runStyleQuery->length > 0 ) {
            foreach ($runStyleQuery->item(0)->childNodes as $docxStyleElement){
                /**
                 * @var $docxStyleElement \DOMElement
                 */
                $valAttrib = (string ) $docxStyleElement->getAttribute('w:val');
                switch ($docxStyleElement->nodeName){
                    case 'w:vertAlign':
                        if ($valAttrib === 'superscript'){
                            $processedRun->setAttributeSupScript(true);
                        } else if ($valAttrib === 'subscript'){
                            $processedRun->setAttributeSubScript(true);
                        }
                        break;
                    case 'w:i':
                        if ($valAttrib !== '0' && $valAttrib !== 'none' && $valAttrib !== ''){
                            $processedRun->setAttributeItalic(true);
                        }
                        break;
                    case 'w:b':
                        if ($valAttrib !== '0' && $valAttrib !== 'none' && $valAttrib !== ''){
                            $processedRun->setAttributeBold(true);
                        }
                        break;
                    case 'w:u':
                        if ($valAttrib !== '0' && $valAttrib !== 'none' && $valAttrib !== ''){
                            $processedRun->setAttributeUnderline(true);
                        }
                        break;
                }
            }
        }

        if ($tabQuery->length > 0 ){
            $processedRun->setAttributeTabbed(true ) ;
        }

        /*
         * Assign the processed entry into this, so we can bubble up the rendering behaviour
         */
        $this->_setProcessedRun($processedRun ) ;
        foreach ($this->_runElementNode->childNodes as $childNode){
            $subRun = new self($docx, $childNode);
            if ($subRun->isValid()){
                $this->_subRunStack[] = $subRun;
            }
        }

    }

    /**
     * @return Run[]
     */
    public function getSubRunStack(){
        return $this->_subRunStack;
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
        $hyperlink = '';
        $bAutomaticLink = true ;
        foreach ($this->_runElementNode->attributes as $attribute){
            if ($attribute->nodeName == 'r:id') {
                $attachedLink = $this->_docx->getAttachedLinks($attribute->nodeValue);
                if (is_object($attachedLink)) {
                    $hyperlink = $attachedLink->getLink();
                    $bAutomaticLink = false;
                }
            }
        }
        if ($bAutomaticLink) {
            foreach ($this->_runElementNode->childNodes as $cn) {
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

        return $processedRun;
    }

    /**
     * @return string
     */
    public function getRawText(){
        $ret = $this->_processedRun->getRawText() ;
        foreach ($this->_subRunStack as $subRun){
            $ret .= $subRun->getRawText();
        }
        return $ret;
    }

    /**
     * @param string $renderMode
     * @return string
     */
    public function getProcessedText($renderMode = Docx::RENDER_MODE_HTML){
        $retArr = $this->_processedRun->getProcessedText( $renderMode) ;

        $ret = $retArr['prepend'] . $retArr['content'];

        foreach ($this->_subRunStack as $subRun){
            $ret .= $subRun->getProcessedText($renderMode);
        }
        $ret .= $retArr['append'];
        return $ret;
    }

    /**
     * @desc Clears docx handler from this object and its stack, after the parse has concluded
     */
    public function unsetHelpers(){
        unset($this->_docx);
        foreach ($this->_subRunStack as $run){
            $run->unsetHelpers();
        }
    }
}